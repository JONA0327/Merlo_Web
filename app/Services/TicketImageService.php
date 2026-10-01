<?php

namespace App\Services;

use App\Models\SeatReservation;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

/**
 * Renders a whole apartado/purchase group (one or many seats for the
 * same customer) as ONE combined JPG, but each seat gets its own
 * full, self-contained ticket card — own header/route line, own big
 * QR — stacked with a visible gap between them so it reads as several
 * individual tickets glued into one image rather than a cramped list.
 * The QR is sized for a boarding scan (not just a thumbnail), since
 * this image is what the customer actually shows at the door.
 */
class TicketImageService
{
    private const WIDTH = 640;
    private const MARGIN = 24;
    private const CARD_GAP = 22;
    private const CARD_HEADER_HEIGHT = 84;
    private const QR_SIZE = 340;

    private const COLOR_RED = [0x8C, 0x1D, 0x2B];
    private const COLOR_YELLOW = [0xF5, 0xB3, 0x01];
    private const COLOR_DARK = [0x2B, 0x11, 0x13];
    private const COLOR_WHITE = [0xFF, 0xFF, 0xFF];
    private const COLOR_PAGE_BG = [0xEF, 0xE7, 0xDB];
    private const COLOR_CARD_BG = [0xFF, 0xFB, 0xF6];
    private const COLOR_BORDER = [0xE0, 0xD5, 0xC4];
    private const COLOR_MUTED = [0x8A, 0x78, 0x78];

    /**
     * @param  Collection<int, SeatReservation>  $reservations  every seat in the group, same customer
     * @return string absolute filesystem path to the generated JPG (caller is responsible for deleting it)
     */
    public function buildCombinedImage(Collection $reservations): string
    {
        $reservations = $reservations->values();
        abort_if($reservations->isEmpty(), 422, 'No hay boletos que incluir en la imagen.');

        $font = $this->fontPath();
        $cardHeight = $this->cardHeight();
        $totalHeight = self::MARGIN + ($cardHeight * $reservations->count())
            + (self::CARD_GAP * max(0, $reservations->count() - 1)) + self::MARGIN;

        $canvas = imagecreatetruecolor(self::WIDTH, $totalHeight);
        imageantialias($canvas, true);

        $red = $this->allocate($canvas, self::COLOR_RED);
        $yellow = $this->allocate($canvas, self::COLOR_YELLOW);
        $dark = $this->allocate($canvas, self::COLOR_DARK);
        $white = $this->allocate($canvas, self::COLOR_WHITE);
        $cardBg = $this->allocate($canvas, self::COLOR_CARD_BG);
        $border = $this->allocate($canvas, self::COLOR_BORDER);
        $muted = $this->allocate($canvas, self::COLOR_MUTED);
        $pageBg = $this->allocate($canvas, self::COLOR_PAGE_BG);

        imagefill($canvas, 0, 0, $pageBg);

        $y = self::MARGIN;
        foreach ($reservations as $reservation) {
            $this->drawTicketCard($canvas, $font, $reservation, $y, $cardHeight, [
                'red' => $red, 'yellow' => $yellow, 'dark' => $dark, 'white' => $white,
                'cardBg' => $cardBg, 'border' => $border, 'muted' => $muted,
            ]);
            $y += $cardHeight + self::CARD_GAP;
        }

        $path = $this->tempPath('tickets', 'jpg');
        imagejpeg($canvas, $path, 93);
        imagedestroy($canvas);

        return $path;
    }

    private function cardHeight(): int
    {
        // header + top padding + seat/trip-type row + QR + code row + bottom padding
        return self::CARD_HEADER_HEIGHT + 24 + 36 + self::QR_SIZE + 60 + 24;
    }

    private function drawTicketCard($canvas, string $font, SeatReservation $reservation, int $top, int $height, array $c): void
    {
        $trip = $reservation->landingRoute;
        $left = self::MARGIN;
        $right = self::WIDTH - self::MARGIN;
        $width = $right - $left;

        // Card shadow (a subtle offset darker rect) so each card reads
        // as its own separate image when stacked.
        imagefilledrectangle($canvas, $left + 3, $top + 3, $right + 3, $top + $height + 3, $c['border']);
        imagefilledrectangle($canvas, $left, $top, $right, $top + $height, $c['cardBg']);
        imagerectangle($canvas, $left, $top, $right, $top + $height, $c['border']);

        // Header band: brand + route + date, specific to THIS seat's leg.
        imagefilledrectangle($canvas, $left, $top, $right, $top + self::CARD_HEADER_HEIGHT, $c['red']);
        $this->centeredText($canvas, $font, 12, $c['white'], $left, $right, $top + 22, 'MERLO TRANSPORTES');
        $this->centeredText($canvas, $font, 19, $c['white'], $left, $right, $top + 48, mb_strtoupper($trip->from.'  ->  '.$trip->to));

        $returnDate = $trip->return_date?->format('d/m/Y') ?? '—';
        $tripDate = $reservation->isReturnLeg() ? $returnDate : ($trip->day?->format('d/m/Y') ?? '—');
        $dateLine = $tripDate.'  ·  '.($trip->departure_time_formatted ?? '—');
        $this->centeredText($canvas, $font, 12, $c['white'], $left, $right, $top + 70, $dateLine);

        // Seat badge + trip type + customer name.
        $rowY = $top + self::CARD_HEADER_HEIGHT + 32;
        $seatLabel = $reservation->seat?->label ?? '—';
        $badgeWidth = 26 + $this->textWidth($font, 15, $seatLabel);
        $badgeX = $left + 20;
        imagefilledrectangle($canvas, $badgeX, $rowY - 20, $badgeX + $badgeWidth, $rowY + 6, $c['yellow']);
        $this->text($canvas, $font, 15, $c['dark'], $badgeX + 13, $rowY, $seatLabel);

        $infoX = $badgeX + $badgeWidth + 16;
        $this->text($canvas, $font, 13, $c['muted'], $infoX, $rowY, $reservation->trip_type_label);

        $nameText = $reservation->customer_display_name;
        $nameWidth = $this->textWidth($font, 13, $nameText);
        $this->text($canvas, $font, 13, $c['muted'], $right - 20 - $nameWidth, $rowY, $nameText);

        // Big QR, centered — this is what actually gets scanned at boarding.
        $qrTop = $rowY + 24;
        $qrPath = $this->generateQrFile($reservation);
        $qrImg = @imagecreatefrompng($qrPath);
        if ($qrImg) {
            $qrX = $left + intdiv($width - self::QR_SIZE, 2);
            imagecopyresampled(
                $canvas, $qrImg,
                $qrX, $qrTop,
                0, 0,
                self::QR_SIZE, self::QR_SIZE,
                imagesx($qrImg), imagesy($qrImg)
            );
            imagedestroy($qrImg);
        }
        @unlink($qrPath);

        // Ticket code + hint, centered below the QR.
        $codeY = $qrTop + self::QR_SIZE + 30;
        $this->centeredText($canvas, $font, 14, $c['dark'], $left, $right, $codeY, $reservation->ticket_code);
        $this->centeredText($canvas, $font, 11, $c['muted'], $left, $right, $codeY + 22, 'Muestra este código al abordar');
    }

    private function generateQrFile(SeatReservation $reservation): string
    {
        $checkinUrl = URL::route('admin.checkin.scan', ['code' => $reservation->ticket_code], true);

        $result = (new Builder(
            writer: new PngWriter(),
            data: $checkinUrl,
            size: self::QR_SIZE,
            margin: 10,
        ))->build();

        $path = $this->tempPath('qr', 'png');
        $result->saveToFile($path);

        return $path;
    }

    private function text($canvas, string $font, int $size, int $color, int $x, int $y, string $text): void
    {
        imagettftext($canvas, $size, 0, $x, $y, $color, $font, $text);
    }

    /** Horizontally centers $text within [$left, $right], baseline at $y. */
    private function centeredText($canvas, string $font, int $size, int $color, int $left, int $right, int $y, string $text): void
    {
        $width = $this->textWidth($font, $size, $text);
        $x = $left + intdiv(($right - $left) - $width, 2);
        $this->text($canvas, $font, $size, $color, $x, $y, $text);
    }

    private function textWidth(string $font, int $size, string $text): int
    {
        $box = imagettfbbox($size, 0, $font, $text);

        return $box[2] - $box[0];
    }

    private function allocate($canvas, array $rgb): int
    {
        return imagecolorallocate($canvas, $rgb[0], $rgb[1], $rgb[2]);
    }

    private function fontPath(): string
    {
        return base_path('vendor/endroid/qr-code/assets/open_sans.ttf');
    }

    private function tempPath(string $prefix, string $extension): string
    {
        $dir = storage_path('app/private/ticket-images');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return $dir.DIRECTORY_SEPARATOR.$prefix.'-'.uniqid('', true).'.'.$extension;
    }
}

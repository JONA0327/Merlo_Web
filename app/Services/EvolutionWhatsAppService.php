<?php

namespace App\Services;

use App\Models\SeatReservation;
use App\Models\Setting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use RuntimeException;

/**
 * Talks to a self-hosted Evolution API instance
 * (https://github.com/EvolutionAPI/evolution-api) — a REST wrapper
 * around WhatsApp Web, as opposed to Meta's official Business Cloud
 * API. Connection details (URL/key/instance name) live in the
 * `settings` row, editable from /admin/whatsapp, where the admin also
 * scans the instance's QR code to link a real phone — no .env editing
 * or deploy needed to connect or reconnect.
 */
class EvolutionWhatsAppService
{
    private Setting $settings;

    public function __construct()
    {
        $this->settings = Setting::current();
    }

    public function isConfigured(): bool
    {
        return $this->settings->evolutionConfigured();
    }

    /**
     * Create the configured instance on the Evolution API server if it
     * doesn't exist yet. Safe to call repeatedly — Evolution's own
     * /instance/create just errors on an existing name, which we treat
     * as "already there" rather than a failure.
     */
    public function ensureInstanceExists(): void
    {
        $this->assertConfigured();

        $response = $this->client()->post('/instance/create', [
            'instanceName' => $this->settings->evolution_instance,
            'qrcode' => true,
            'integration' => 'WHATSAPP-BAILEYS',
        ]);

        if ($response->failed() && ! $this->looksLikeAlreadyExists($response)) {
            Log::warning('Evolution API instance creation failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException('No se pudo crear la instancia en Evolution API (código '.$response->status().').');
        }
    }

    /**
     * Fetch a fresh QR code to scan. Returns a data URI ready for an
     * <img src="..."> (or null if the API's response shape didn't carry
     * one — e.g. the instance is already connected, nothing to scan).
     */
    public function fetchQrCode(): ?string
    {
        $this->assertConfigured();
        $this->ensureInstanceExists();

        $response = $this->client()->get('/instance/connect/'.$this->settings->evolution_instance);

        if ($response->failed()) {
            Log::warning('Evolution API QR fetch failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException('Evolution API no devolvió un código QR (código '.$response->status().').');
        }

        $data = $response->json();

        // Evolution's exact response shape has drifted across versions —
        // check every key it's been documented to use rather than
        // betting on one.
        $base64 = $data['base64']
            ?? $data['qrcode']['base64']
            ?? $data['qr']
            ?? null;

        if (! $base64) {
            return null;
        }

        return str_starts_with($base64, 'data:') ? $base64 : 'data:image/png;base64,'.$base64;
    }

    /**
     * Live connection state: 'open' (connected), 'connecting', 'close'
     * (not connected), or 'unknown' if the API's response didn't match
     * any recognized shape.
     */
    public function connectionState(): string
    {
        $this->assertConfigured();

        $response = $this->client()->get('/instance/connectionState/'.$this->settings->evolution_instance);

        if ($response->failed()) {
            return 'close';
        }

        $data = $response->json();
        $state = $data['instance']['state'] ?? $data['state'] ?? null;

        return in_array($state, ['open', 'connecting', 'close'], true) ? $state : 'unknown';
    }

    public function disconnect(): void
    {
        $this->assertConfigured();

        $response = $this->client()->delete('/instance/logout/'.$this->settings->evolution_instance);

        if ($response->failed()) {
            Log::warning('Evolution API disconnect failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException('No se pudo desconectar la instancia (código '.$response->status().').');
        }
    }

    /**
     * @throws RuntimeException when not configured, the customer has no
     *                           phone on file, or the API call itself fails —
     *                           callers decide how to degrade (see
     *                           AdminSeatReservationController::sendTicket()).
     */
    public function sendTicket(SeatReservation $reservation): void
    {
        $this->assertConfigured();

        $number = self::normalizePhone($reservation->customer_phone);
        if (! $number) {
            throw new RuntimeException('El cliente no tiene un número de WhatsApp registrado.');
        }

        $reservation->loadMissing(['landingRoute', 'seat']);
        $trip = $reservation->landingRoute;

        $checkinUrl = URL::route('admin.checkin.scan', ['code' => $reservation->ticket_code], true);
        $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=400x400&margin=10&data='.urlencode($checkinUrl);

        $response = $this->client()->post('/message/sendMedia/'.$this->settings->evolution_instance, [
            'number' => $number,
            'mediatype' => 'image',
            'mimetype' => 'image/png',
            'media' => $qrUrl,
            'fileName' => 'boleto-'.$reservation->ticket_code.'.png',
            'caption' => $this->buildCaption($reservation, $trip),
        ]);

        if ($response->failed()) {
            Log::warning('Evolution API WhatsApp send failed', [
                'reservation_id' => $reservation->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException('Evolution API respondió con error '.$response->status().'.');
        }
    }

    /**
     * Send a locally-generated image file (e.g. TicketImageService's
     * combined multi-seat JPG) — base64-encoded, since unlike the single
     * QR above (fetched from a public CDN URL) this file only exists on
     * this server's disk.
     */
    public function sendImageFile(string $phone, string $imagePath, string $caption, string $fileName): void
    {
        $this->assertConfigured();

        $number = self::normalizePhone($phone);
        if (! $number) {
            throw new RuntimeException('El cliente no tiene un número de WhatsApp registrado.');
        }

        $mimeType = mime_content_type($imagePath) ?: 'image/jpeg';
        $base64 = base64_encode(file_get_contents($imagePath));

        $response = $this->client()->post('/message/sendMedia/'.$this->settings->evolution_instance, [
            'number' => $number,
            'mediatype' => 'image',
            'mimetype' => $mimeType,
            'media' => $base64,
            'fileName' => $fileName,
            'caption' => $caption,
        ]);

        if ($response->failed()) {
            Log::warning('Evolution API WhatsApp combined-image send failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException('Evolution API respondió con error '.$response->status().'.');
        }
    }

    /**
     * Plain-text "your seat is reserved" notice — no QR, no image.
     * Used for apartados with no payment confirmed yet (the QR is only
     * generated when an admin later confirms the payment). Failure mode
     * matches sendTicket() so the caller's existing "couldn't send
     * WhatsApp" UX keeps working.
     *
     * @param  Collection<int, SeatReservation>  $group  every seat in this apartado (a "mancuerna" is 2+ rows, not 1)
     *
     * @throws RuntimeException when not configured, the customer has no
     *                         phone on file, or the API call itself fails.
     */
    public function sendReservationNotice(Collection $group): void
    {
        $this->assertConfigured();

        $first = $group->first();
        $number = self::normalizePhone($first->customer_phone);
        if (! $number) {
            throw new RuntimeException('El cliente no tiene un número de WhatsApp registrado.');
        }

        $group->each->loadMissing(['landingRoute', 'seat']);
        $trip = $first->landingRoute;

        // Use locals so the double quotes don't have to nest nullsafe +
        // null-coalescing inside an interpolation expression — the parser
        // sometimes gets confused by `?-> ... ??` directly inside `{}`.
        $departureDate = $trip->day?->toSpanishLongDate() ?? '—';
        $seatLabels = $group->map(fn (SeatReservation $r) => $r->seat?->label ?? '—')->implode(', ');
        // Every seat in a group ("mancuerna" or otherwise) is charged its
        // own unit_price — the total owed is the sum across all of them,
        // not just the root row's.
        $total = $group->sum(fn (SeatReservation $r) => (float) $r->unit_price);
        $methodLabel = $first->payment_method_label;

        $lines = [
            '*MERLO Transportes* 🚌',
            '',
            "Hola {$first->customer_display_name}, tu apartado fue registrado:",
            '',
            "*{$trip->from} → {$trip->to}*",
            "📅 Salida: *{$departureDate}*",
            ($group->count() > 1 ? '💺 Asientos: ' : '💺 Asiento: ').$seatLabels,
            "💵 A pagar: \$".number_format($total, 2)." MXN",
        ];

        // Boarding-point instructions — the customer should know where
        // to show up even before the real ticket (with QR) exists.
        foreach ($first->boardingLegendLines() as $legendLine) {
            $lines[] = "📍 *{$legendLine}*";
        }

        $lines[] = '';
        $lines[] = "🟡 *RESERVADO — {$methodLabel}*";
        $lines[] = 'Tu boleto se activa cuando se confirme el pago. Te enviaremos el QR oficial en cuanto lo confirmemos.';

        $response = $this->client()->post('/message/sendText/'.$this->settings->evolution_instance, [
            'number' => $number,
            'text' => implode("\n", $lines),
        ]);

        if ($response->failed()) {
            Log::warning('Evolution API WhatsApp reservation notice failed', [
                'reservation_id' => $first->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException('Evolution API respondió con error '.$response->status().'.');
        }
    }

    /**
     * Digits only, with the Mexico country code (52) auto-prefixed on a
     * plain 10-digit local number — the near-universal shape a Merlo
     * admin types into the apartado form. A number already longer than
     * 10 digits is assumed to already carry its own country code and is
     * left untouched.
     */
    public static function normalizePhone(?string $raw): ?string
    {
        if (! $raw) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $raw);
        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 10) {
            $digits = '52'.$digits;
        }

        return $digits;
    }

    private function assertConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Evolution API no está configurada. Completa la URL, la apikey y el nombre de instancia en Administración → WhatsApp.');
        }
    }

    private function client(): PendingRequest
    {
        return Http::withHeaders(['apikey' => $this->settings->evolution_api_key])
            ->baseUrl(rtrim((string) $this->settings->evolution_api_url, '/'))
            ->timeout(20);
    }

    /**
     * Evolution returns a 403/409-ish error (exact code varies by
     * version) when the instance name is already taken — that's a
     * success for our purposes (ensureInstanceExists is idempotent), so
     * we sniff the error body for the telltale wording instead of
     * treating it as a real failure.
     */
    private function looksLikeAlreadyExists($response): bool
    {
        $body = strtolower((string) $response->body());

        return str_contains($body, 'already in use')
            || str_contains($body, 'already exists')
            || str_contains($body, 'ya existe');
    }

    private function buildCaption(SeatReservation $reservation, $trip): string
    {
        $returnDate = $trip->return_date?->toSpanishLongDate() ?? '—';
        $tripDate = $reservation->isReturnLeg() ? $returnDate : ($trip->day?->toSpanishLongDate() ?? '—');
        $departure = $trip->departure_time_formatted ?? '—';
        $seat = $reservation->seat?->label ?? '—';
        $price = $reservation->unit_price ? '$'.number_format((float) $reservation->unit_price, 2) : '—';
        $legLabel = $reservation->isReturnLeg() ? 'regreso' : ($reservation->needsBothLegs() ? 'salida y tu regreso' : 'subida al autobús');

        $lines = [
            '*MERLO Transportes* 🚌',
            '',
            "Hola {$reservation->customer_display_name}, este es tu boleto:",
            '',
            "*{$trip->from} → {$trip->to}*",
            "📅 Salida: *{$tripDate} · {$departure}*",
        ];

        if ($reservation->needsBothLegs()) {
            $lines[] = "🔁 Regreso: *{$returnDate}*";
        }

        $lines[] = "💺 Asiento: {$seat}";
        $lines[] = "💵 Precio: {$price}";

        $legendLines = $reservation->boardingLegendLines();
        if (! empty($legendLines)) {
            $lines[] = '';
            foreach ($legendLines as $legendLine) {
                $lines[] = "📍 *{$legendLine}*";
            }
        }

        $lines[] = '';
        $lines[] = "Muestra el código QR de esta imagen al abordar — el operador lo escanea para registrar tu {$legLabel}.";
        $lines[] = 'Si no escanea, dicta este código:';
        $lines[] = "```{$reservation->ticket_code}```";

        if ($reservation->notes) {
            $lines[] = '';
            $lines[] = "📝 {$reservation->notes}";
        }

        return implode("\n", $lines);
    }
}

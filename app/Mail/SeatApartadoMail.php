<?php

namespace App\Mail;

use App\Models\SeatReservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class SeatApartadoMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public SeatReservation $reservation)
    {
    }

    /**
     * The "this is your ticket" email sent when the admin clicks
     * "Enviar boleto". Carries the design the user approved: site
     * red/yellow palette, logo, full trip info, the seat labels,
     * the QR code (CDN-rendered PNG), and a human-readable ticket
     * code the passenger can also type at the door if the QR
     * doesn't scan.
     *
     * The QR points at the operator-side check-in URL so the scan
     * drops the operator straight into the ticket detail (no
     * typing required). The QR is a CDN-served PNG so we don't
     * have to pull in a PHP QR library.
     */
    public function build(): self
    {
        $reservation = $this->reservation->loadMissing(['landingRoute.busUnit', 'seat']);
        $trip = $reservation->landingRoute;
        $seat = $reservation->seat;
        $checkinUrl = URL::route('admin.checkin.scan', ['code' => $reservation->ticket_code], true);
        $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=240x240&margin=10&data='.urlencode($checkinUrl);

        $tripDate = $trip->day?->format('d/m/Y') ?? '—';
        $returnDate = $trip->return_date?->format('d/m/Y') ?? '—';
        $departure = $trip->departure_time_formatted ?? '—';
        $tripType = $reservation->isRoundTrip() ? 'Viaje redondo' : 'Solo ida';
        $tripTypeBadge = $reservation->isRoundTrip() ? '#F5B301' : '#8C1D2B';
        $seats = $reservation->seats ?? collect([$seat]);
        // Fallback when the reservation has no aggregated seats set yet
        $seatsList = $seats->pluck('label')->filter()->all();
        if (empty($seatsList) && $seat) {
            $seatsList = [$seat->label];
        }

        $html = $this->buildHtml(
            trip: $trip,
            reservation: $reservation,
            tripDate: $tripDate,
            returnDate: $returnDate,
            departure: $departure,
            tripType: $tripType,
            tripTypeBadge: $tripTypeBadge,
            qrUrl: $qrUrl,
            seatsList: $seatsList,
        );

        return $this->subject("Tu boleto Merlo — {$trip->from} → {$trip->to}")
            ->html($html);
    }

    private function buildHtml(
        $trip,
        $reservation,
        string $tripDate,
        string $returnDate,
        string $departure,
        string $tripType,
        string $tripTypeBadge,
        string $qrUrl,
        array $seatsList
    ): string {
        $customer = e($reservation->customer_display_name);
        $email = e($reservation->customer_display_email ?? '—');
        $from = e($trip->from);
        $to = e($trip->to);
        $duration = e($trip->duration);
        $price = $reservation->unit_price ? '$'.number_format((float) $reservation->unit_price, 2) : '—';
        $code = e($reservation->ticket_code);
        $seatsHtml = collect($seatsList)
            ->map(fn ($s) => '<span style="display:inline-block;padding:4px 10px;margin:2px;border:1px solid #8C1D2B;border-radius:6px;background:#fff;color:#8C1D2B;font-weight:700;">'.e($s).'</span>')
            ->implode('');
        $notes = $reservation->notes ? '<div style="margin-top:14px;padding:10px 12px;background:#FFF8E1;border-left:3px solid #F5B301;border-radius:4px;font-size:12px;color:#5C4A00;">'.e($reservation->notes).'</div>' : '';
        $returnRow = $reservation->isRoundTrip() ? '<tr><td style="padding:6px 0;color:#8C1D2B/70;font-weight:600;width:90px;">Regreso</td><td style="padding:6px 0;text-align:right;font-weight:600;">'.$returnDate.'</td></tr>' : '';

        return <<<HTML
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width">
</head>
<body style="margin:0;padding:0;background:#FFFBF6;font-family:Poppins,system-ui,-apple-system,Segoe UI,Roboto,sans-serif;color:#2B1113;">
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#FFFBF6;padding:24px 0;">
  <tr>
    <td align="center">
      <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="560" style="max-width:560px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 20px rgba(43,17,19,0.08);">
        <tr>
          <td style="background:#8C1D2B;padding:20px 28px;color:#ffffff;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
              <tr>
                <td style="vertical-align:middle;">
                  <div style="font-family:Poppins,system-ui,sans-serif;font-size:20px;font-weight:800;letter-spacing:-0.02em;">MERLO</div>
                  <div style="font-size:11px;font-weight:500;letter-spacing:0.16em;text-transform:uppercase;opacity:0.85;margin-top:2px;">Transportes</div>
                </td>
                <td align="right" style="vertical-align:middle;">
                  <span style="display:inline-block;padding:6px 12px;background:$tripTypeBadge;color:#2B1113;border-radius:999px;font-size:11px;font-weight:800;letter-spacing:0.06em;text-transform:uppercase;">$tripType</span>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <tr>
          <td style="background:#F5B301;padding:14px 28px;color:#2B1113;">
            <div style="font-size:22px;font-weight:800;letter-spacing:-0.02em;">$from <span style="opacity:0.6;font-weight:600;">→</span> $to</div>
            <div style="font-size:12px;font-weight:600;margin-top:2px;opacity:0.85;">$duration</div>
          </td>
        </tr>

        <tr>
          <td style="padding:24px 28px 8px 28px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="font-size:13px;">
              <tr>
                <td style="padding:6px 0;color:#8C1D2B/70;font-weight:600;width:90px;">Pasajero</td>
                <td style="padding:6px 0;text-align:right;font-weight:600;">$customer</td>
              </tr>
              <tr>
                <td style="padding:6px 0;color:#8C1D2B/70;font-weight:600;">Correo</td>
                <td style="padding:6px 0;text-align:right;font-size:12px;">$email</td>
              </tr>
              <tr>
                <td style="padding:6px 0;color:#8C1D2B/70;font-weight:600;">Salida</td>
                <td style="padding:6px 0;text-align:right;font-weight:600;">$tripDate &middot; $departure</td>
              </tr>
              $returnRow
              <tr>
                <td style="padding:6px 0;color:#8C1D2B/70;font-weight:600;">Precio</td>
                <td style="padding:6px 0;text-align:right;font-weight:800;color:#8C1D2B;font-size:15px;">$price</td>
              </tr>
            </table>
          </td>
        </tr>

        <tr>
          <td style="padding:14px 28px 8px 28px;">
            <div style="font-size:11px;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;color:#8C1D2B;margin-bottom:8px;">Asientos</div>
            <div>$seatsHtml</div>
            $notes
          </td>
        </tr>

        <tr>
          <td style="padding:24px 28px 28px 28px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#FFFBF6;border:1px dashed #8C1D2B/30;border-radius:12px;">
              <tr>
                <td width="160" style="padding:16px;text-align:center;vertical-align:middle;">
                  <img src="$qrUrl" alt="Código QR del boleto" width="140" height="140" style="display:block;margin:0 auto;border-radius:6px;">
                </td>
                <td style="padding:16px;vertical-align:middle;">
                  <div style="font-size:11px;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;color:#8C1D2B;">Muestra este QR al abordar</div>
                  <div style="font-size:12px;color:#2B1113;margin-top:6px;line-height:1.4;">El operador lo escanea para registrar tu {$this->legLabel($reservation)}.</div>
                  <div style="margin-top:12px;padding:8px 10px;background:#ffffff;border-radius:6px;font-family:ui-monospace,Menlo,Consolas,monospace;font-size:13px;font-weight:700;letter-spacing:0.04em;color:#2B1113;text-align:center;">$code</div>
                  <div style="font-size:10px;color:#2B1113/60;margin-top:6px;text-align:center;">Si el QR no escanea, dicta este código al operador.</div>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <tr>
          <td style="background:#2B1113;padding:14px 28px;color:#FFFBF6;font-size:11px;text-align:center;line-height:1.5;">
            <div style="font-weight:600;">MERLO Transportes &middot; Boleto digital</div>
            <div style="opacity:0.7;margin-top:2px;">Este código QR es único para este viaje. No es transferible a otro viaje.</div>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
</body>
</html>
HTML;
    }

    private function legLabel(SeatReservation $reservation): string
    {
        return $reservation->isRoundTrip() ? 'salida y tu regreso' : 'subida al autobus';
    }
}

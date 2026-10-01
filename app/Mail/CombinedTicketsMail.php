<?php

namespace App\Mail;

use App\Models\SeatReservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * Sent instead of N separate SeatApartadoMail emails when an apartado
 * covers more than one seat for the same customer — ONE email with the
 * combined ticket image (built by TicketImageService) attached, so the
 * customer has one file with every seat's QR instead of several
 * separate emails to dig through.
 */
class CombinedTicketsMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  Collection<int, SeatReservation>  $reservations
     */
    public function __construct(
        public Collection $reservations,
        public string $imagePath,
    ) {
    }

    public function build(): self
    {
        $first = $this->reservations->first();
        $trip = $first->landingRoute;
        $count = $this->reservations->count();

        $seatsHtml = $this->reservations
            ->map(fn (SeatReservation $r) => '<span style="display:inline-block;padding:4px 10px;margin:2px;border:1px solid #8C1D2B;border-radius:6px;background:#fff;color:#8C1D2B;font-weight:700;">'.e($r->seat?->label ?? '—').'</span>')
            ->implode('');

        $from = e($trip->from);
        $to = e($trip->to);
        $customer = e($first->customer_display_name);

        $html = <<<HTML
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
            <div style="font-family:Poppins,system-ui,sans-serif;font-size:20px;font-weight:800;letter-spacing:-0.02em;">MERLO</div>
            <div style="font-size:11px;font-weight:500;letter-spacing:0.16em;text-transform:uppercase;opacity:0.85;margin-top:2px;">Transportes</div>
          </td>
        </tr>
        <tr>
          <td style="background:#F5B301;padding:14px 28px;color:#2B1113;">
            <div style="font-size:22px;font-weight:800;letter-spacing:-0.02em;">$from <span style="opacity:0.6;font-weight:600;">→</span> $to</div>
          </td>
        </tr>
        <tr>
          <td style="padding:24px 28px 8px 28px;">
            <p style="margin:0 0 10px 0;font-size:14px;">Hola $customer, aquí tienes tus $count boletos.</p>
            <div style="font-size:11px;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;color:#8C1D2B;margin-bottom:8px;">Asientos</div>
            <div>$seatsHtml</div>
          </td>
        </tr>
        <tr>
          <td style="padding:14px 28px 28px 28px;">
            <div style="border-radius:12px;border:1px dashed rgba(140,29,43,0.3);padding:16px;background:#FFFBF6;">
              <p style="margin:0;font-size:12px;line-height:1.5;">Todos tus boletos (con sus códigos QR) están en la imagen adjunta de este correo — muéstrala completa al abordar, el operador escanea el QR que corresponda a cada asiento.</p>
            </div>
          </td>
        </tr>
        <tr>
          <td style="background:#2B1113;padding:14px 28px;color:#FFFBF6;font-size:11px;text-align:center;line-height:1.5;">
            <div style="font-weight:600;">MERLO Transportes &middot; Boletos digitales</div>
            <div style="opacity:0.7;margin-top:2px;">Cada código QR es único para su viaje y asiento. No son transferibles.</div>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
</body>
</html>
HTML;

        return $this->subject("Tus boletos Merlo ({$count}) — {$trip->from} → {$trip->to}")
            ->html($html)
            ->attach($this->imagePath, [
                'as' => 'boletos-merlo.jpg',
                'mime' => 'image/jpeg',
            ]);
    }
}

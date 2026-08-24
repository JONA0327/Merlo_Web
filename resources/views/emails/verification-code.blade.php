<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Tu código de verificación</title>
</head>
<body style="margin:0; padding:0; background-color:#FFFBF6; font-family: Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#FFFBF6; padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="480" cellpadding="0" cellspacing="0" style="max-width:480px; width:100%; background-color:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,0.08);">
                    <tr>
                        <td style="background:linear-gradient(135deg,#8C1D2B,#4B0C14); padding:28px 32px; text-align:center;">
                            <div style="font-family: Arial, Helvetica, sans-serif; font-size:22px; font-weight:bold; color:#ffffff; letter-spacing:0.5px;">
                                Merlo <span style="color:#F5C948;">Transportes</span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:36px 32px 8px 32px;">
                            <p style="margin:0 0 8px 0; font-size:16px; color:#2B1113;">Hola{{ $user->name ? ' '.$user->name : '' }},</p>
                            <p style="margin:0 0 24px 0; font-size:15px; line-height:1.6; color:#5b4a4c;">
                                Usa el siguiente código para confirmar tu correo y activar tu cuenta en Merlo Transportes.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 32px 28px 32px;" align="center">
                            <div style="display:inline-block; background-color:#FFFBF6; border:2px dashed #F5B301; border-radius:12px; padding:18px 36px;">
                                <span style="font-size:36px; font-weight:bold; letter-spacing:10px; color:#8C1D2B;">{{ $code }}</span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 32px 32px 32px;">
                            <p style="margin:0; font-size:13px; line-height:1.6; color:#8a7a7c;">
                                Este código vence en <strong>15 minutos</strong> y solo es válido para la cuenta registrada con <strong>{{ $user->email }}</strong>. Si tú no solicitaste este código, puedes ignorar este correo.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#2B1113; padding:20px 32px; text-align:center;">
                            <p style="margin:0; font-size:12px; color:#ffffffb3;">&copy; {{ date('Y') }} Merlo Transportes. Todos los derechos reservados.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

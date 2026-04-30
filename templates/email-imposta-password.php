<?php
/**
 * Template email impostazione password.
 *
 * Variabili disponibili: $nome, $link
 *
 * @package GEvent
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Imposta la tua password</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 20px;">
    <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; margin: 0 auto;">
        <tr>
            <td style="background-color: #ffffff; padding: 40px; border-radius: 8px;">

                <h2 style="color: #1A3A5C; margin-top: 0;">Benvenuto nel Portale CRAL</h2>

                <p style="color: #333333; line-height: 1.6;">
                    Ciao <strong><?php echo esc_html( $nome ); ?></strong>,
                </p>

                <p style="color: #333333; line-height: 1.6;">
                    Il tuo profilo è stato creato. Clicca il pulsante qui sotto per impostare la tua password e accedere al portale.
                </p>

                <p style="text-align: center; margin: 30px 0;">
                    <a href="<?php echo esc_url( $link ); ?>"
                       style="background-color: #1A3A5C; color: #ffffff; padding: 14px 28px;
                              text-decoration: none; border-radius: 4px; font-size: 16px;">
                        Imposta la tua password
                    </a>
                </p>

                <p style="color: #777777; font-size: 13px; line-height: 1.6;">
                    Il link è valido per <strong>24 ore</strong>. Se non hai richiesto questa email, ignorala.
                </p>

                <p style="color: #777777; font-size: 12px; margin-top: 30px; border-top: 1px solid #eeeeee; padding-top: 20px;">
                    Se il pulsante non funziona, copia e incolla questo link nel browser:<br>
                    <a href="<?php echo esc_url( $link ); ?>" style="color: #1A3A5C; word-break: break-all;">
                        <?php echo esc_url( $link ); ?>
                    </a>
                </p>

            </td>
        </tr>
    </table>
</body>
</html>
<?php
/**
 * Template email notifica nuova prenotazione alla segreteria.
 *
 * Variabili disponibili: $dati
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
    <title>Nuova prenotazione</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 20px;">
    <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; margin: 0 auto;">
        <tr>
            <td style="background-color: #ffffff; padding: 40px; border-radius: 8px;">

                <h2 style="color: #1A3A5C; margin-top: 0;">Nuova prenotazione ricevuta</h2>

                <p style="color: #333333; line-height: 1.6;">
                    È stata ricevuta una nuova prenotazione per l'evento
                    <strong><?php echo esc_html( $dati['evento_titolo'] ); ?></strong>.
                </p>

                <h3 style="color: #1A3A5C;">Dati socio</h3>
                <table width="100%" cellpadding="8" cellspacing="0"
                       style="border-collapse: collapse; margin: 20px 0;">
                    <tr style="background-color: #f8f8f8;">
                        <td style="border: 1px solid #dddddd; font-weight: bold;">Matricola</td>
                        <td style="border: 1px solid #dddddd;"><?php echo esc_html( $dati['socio_id_matricola'] ); ?></td>
                    </tr>
                    <tr>
                        <td style="border: 1px solid #dddddd; font-weight: bold;">Nome</td>
                        <td style="border: 1px solid #dddddd;">
                            <?php echo esc_html( $dati['socio_nome'] . ' ' . $dati['socio_cognome'] ); ?>
                        </td>
                    </tr>
                    <tr style="background-color: #f8f8f8;">
                        <td style="border: 1px solid #dddddd; font-weight: bold;">Email</td>
                        <td style="border: 1px solid #dddddd;">
                            <a href="mailto:<?php echo esc_attr( $dati['socio_email'] ); ?>">
                                <?php echo esc_html( $dati['socio_email'] ); ?>
                            </a>
                        </td>
                    </tr>
                </table>

                <h3 style="color: #1A3A5C;">Dati prenotazione</h3>
                <table width="100%" cellpadding="8" cellspacing="0"
                       style="border-collapse: collapse; margin: 20px 0;">
                    <tr style="background-color: #f8f8f8;">
                        <td style="border: 1px solid #dddddd; font-weight: bold;">Evento</td>
                        <td style="border: 1px solid #dddddd;"><?php echo esc_html( $dati['evento_titolo'] ); ?></td>
                    </tr>
                    <tr>
                        <td style="border: 1px solid #dddddd; font-weight: bold;">Data evento</td>
                        <td style="border: 1px solid #dddddd;"><?php echo esc_html( $dati['evento_data'] ); ?></td>
                    </tr>
                    <tr style="background-color: #f8f8f8;">
                        <td style="border: 1px solid #dddddd; font-weight: bold;">Luogo</td>
                        <td style="border: 1px solid #dddddd;"><?php echo esc_html( $dati['evento_luogo'] ); ?></td>
                    </tr>
                    <tr>
                        <td style="border: 1px solid #dddddd; font-weight: bold;">Totale biglietti</td>
                        <td style="border: 1px solid #dddddd;"><?php echo esc_html( $dati['totale_biglietti'] ); ?></td>
                    </tr>
                    <tr style="background-color: #f8f8f8;">
                        <td style="border: 1px solid #dddddd; font-weight: bold;">Importo totale</td>
                        <td style="border: 1px solid #dddddd;">
                            € <?php echo esc_html( number_format( (float) $dati['importo_totale'], 2, ',', '.' ) ); ?>
                        </td>
                    </tr>
                </table>

                <?php if ( ! empty( $dati['partecipanti'] ) ) : ?>
                    <h3 style="color: #1A3A5C;">Partecipanti</h3>
                    <table width="100%" cellpadding="8" cellspacing="0"
                           style="border-collapse: collapse; margin: 20px 0;">
                        <thead>
                            <tr style="background-color: #1A3A5C; color: #ffffff;">
                                <th style="border: 1px solid #dddddd; text-align: left;">Nome</th>
                                <th style="border: 1px solid #dddddd; text-align: left;">Cognome</th>
                                <th style="border: 1px solid #dddddd; text-align: left;">Tipologia</th>
                                <th style="border: 1px solid #dddddd; text-align: left;">Prezzo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $dati['partecipanti'] as $i => $part ) : ?>
                                <tr style="background-color: <?php echo $i % 2 === 0 ? '#f8f8f8' : '#ffffff'; ?>;">
                                    <td style="border: 1px solid #dddddd;">
                                        <?php echo esc_html( $part['partecipante_nome'] ); ?>
                                    </td>
                                    <td style="border: 1px solid #dddddd;">
                                        <?php echo esc_html( $part['partecipante_cognome'] ); ?>
                                    </td>
                                    <td style="border: 1px solid #dddddd;">
                                        <?php echo esc_html( $part['partecipante_tipologia'] ); ?>
                                    </td>
                                    <td style="border: 1px solid #dddddd;">
                                        € <?php echo esc_html( number_format( (float) $part['partecipante_prezzo'], 2, ',', '.' ) ); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <p style="color: #333333; line-height: 1.6;">
                    <a href="<?php echo esc_url( admin_url( 'post.php?post=' . $dati['prenotazione_id'] . '&action=edit' ) ); ?>">
                        Visualizza la prenotazione nel backend
                    </a>
                </p>

            </td>
        </tr>
    </table>
</body>
</html>
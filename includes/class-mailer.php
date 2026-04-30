<?php
/**
 * Invio email di conferma prenotazione e notifica segreteria.
 *
 * @package GEvent
 */

namespace GEvent;

/**
 * Classe per la gestione delle email di prenotazione.
 */
class Mailer {

    /**
     * Invia l'email di conferma al socio.
     *
     * @param int $prenotazione_id Post ID della prenotazione.
     * @param int $socio_id        Post ID del socio.
     * @param int $evento_id       Post ID dell'evento.
     */
    public function send_conferma_socio( $prenotazione_id, $socio_id, $evento_id ) {
        $email = get_post_meta( $socio_id, '_cral_email', true );

        if ( empty( $email ) ) {
            return false;
        }

        $dati    = $this->get_dati_prenotazione( $prenotazione_id, $socio_id, $evento_id );
        $subject = 'Prenotazione confermata — ' . $dati['evento_titolo'];

        ob_start();
        $template = __DIR__ . '/../templates/email-conferma-socio.php';
        if ( file_exists( $template ) ) {
            include $template;
        }
        $body = ob_get_clean();

        $headers = array( 'Content-Type: text/html; charset=UTF-8' );
        wp_mail( $email, $subject, $body, $headers );

        return true;
    }

    /**
     * Invia l'email di notifica alla segreteria.
     *
     * @param int $prenotazione_id Post ID della prenotazione.
     * @param int $socio_id        Post ID del socio.
     * @param int $evento_id       Post ID dell'evento.
     */
    public function send_notifica_segreteria( $prenotazione_id, $socio_id, $evento_id ) {
        $email_segreteria = get_option( 'cral_email_segreteria', get_option( 'admin_email' ) );

        $dati    = $this->get_dati_prenotazione( $prenotazione_id, $socio_id, $evento_id );
        $subject = 'Nuova prenotazione — ' . $dati['evento_titolo'];

        ob_start();
        $template = __DIR__ . '/../templates/email-notifica-segreteria.php';
        if ( file_exists( $template ) ) {
            include $template;
        }
        $body = ob_get_clean();

        $headers = array( 'Content-Type: text/html; charset=UTF-8' );
        wp_mail( $email_segreteria, $subject, $body, $headers );

        return true;
    }

    /**
     * Recupera tutti i dati necessari per i template email.
     *
     * @param int $prenotazione_id Post ID della prenotazione.
     * @param int $socio_id        Post ID del socio.
     * @param int $evento_id       Post ID dell'evento.
     * @return array
     */
    public function get_dati_prenotazione( $prenotazione_id, $socio_id, $evento_id ) {
        $partecipanti = carbon_get_post_meta( $prenotazione_id, 'cral_partecipanti' );
        $evento_data  = get_post_meta( $evento_id, '_cral_evento_data', true );

        return array(
            'prenotazione_id'    => $prenotazione_id,
            'evento_titolo'      => get_the_title( $evento_id ),
            'evento_data'        => $evento_data ? wp_date( 'd/m/Y \a\l\l\e H:i', strtotime( $evento_data ) ) : '—',
            'evento_luogo'       => get_post_meta( $evento_id, '_cral_evento_luogo', true ),
            'socio_nome'         => get_post_meta( $socio_id, '_cral_nome', true ),
            'socio_cognome'      => get_post_meta( $socio_id, '_cral_cognome', true ),
            'socio_email'        => get_post_meta( $socio_id, '_cral_email', true ),
            'socio_id_matricola' => get_post_meta( $socio_id, '_cral_socio_id', true ),
            'totale_biglietti'   => get_post_meta( $prenotazione_id, '_cral_pren_totale_biglietti', true ),
            'importo_totale'     => get_post_meta( $prenotazione_id, '_cral_pren_importo_totale', true ),
            'partecipanti'       => $partecipanti,
        );
    }
}
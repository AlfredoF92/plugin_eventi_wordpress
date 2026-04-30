<?php
/**
 * Gestione token password: generazione, invio email, verifica.
 *
 * @package GEvent
 */

namespace GEvent;

/**
 * Classe per la gestione dei token di impostazione e reset password.
 */
class Password_Manager {

    /**
     * Registra gli hook WordPress.
     */
    public function init() {
        add_action( 'wp_ajax_cral_send_password_email', array( $this, 'handle_send_password_email' ) );
    }

    /**
     * Genera un token univoco, lo salva hashato nel DB e invia la mail al socio.
     *
     * @param int $socio_id Post ID del CPT socio.
     * @return bool True se la mail è stata inviata, false altrimenti.
     */
    public function generate_and_send_token( $socio_id ) {
        global $wpdb;

        // Recupera email del socio.
        $email = get_post_meta( $socio_id, '_cral_email', true );
        $nome  = get_post_meta( $socio_id, '_cral_nome', true );

        if ( empty( $email ) ) {
            return false;
        }

        // Invalida eventuali token precedenti non usati per questo socio.
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->prefix}cral_password_tokens
                SET used_at = %s
                WHERE socio_id = %d
                AND used_at IS NULL",
                current_time( 'mysql' ),
                $socio_id
            )
        );

        // Genera token grezzo e hash.
        $token      = bin2hex( random_bytes( 32 ) );
        $token_hash = hash( 'sha256', $token );
        $expires_at = gmdate( 'Y-m-d H:i:s', strtotime( '+24 hours' ) );

        // Salva il token hashato nel DB.
        $inserted = $wpdb->insert(
            $wpdb->prefix . 'cral_password_tokens',
            array(
                'socio_id'   => $socio_id,
                'token_hash' => $token_hash,
                'expires_at' => $expires_at,
                'used_at'    => null,
            ),
            array( '%d', '%s', '%s', 'NULL' )
        );

        if ( ! $inserted ) {
            return false;
        }

        // Costruisce il link e invia la mail.
        $link = add_query_arg(
            array( 'token' => $token ),
            get_permalink( get_option( 'cral_pagina_imposta_password' ) )
        );

        return $this->send_token_email( $email, $nome, $link );
    }

    /**
     * Invia la mail con il link di impostazione password.
     *
     * @param string $email Email del socio.
     * @param string $nome  Nome del socio.
     * @param string $link  Link con token.
     * @param string $type Tipo di richiesta(reimpostazione password o nuovo utente)
     * @return bool
     */
    private function send_token_email( $email, $nome, $link, $type = 'imposta' ) {
        $subject = 'imposta' === $type
            ? 'Imposta la tua password — Portale CRAL'
            : 'Reimposta la tua password — Portale CRAL';

        ob_start();
        $template = 'imposta' === $type
            ? __DIR__ . '/../templates/email-imposta-password.php'
            : __DIR__ . '/../templates/email-reset-password.php';

        if ( file_exists( $template ) ) {
            include $template;
        }
        $body = ob_get_clean();

        $headers = array( 'Content-Type: text/html; charset=UTF-8' );

        wp_mail( $email, $subject, $body, $headers );

        return true;
    }

    /**
     * Genera un token e invia la mail di reset password.
     *
     * @param int $socio_id Post ID del CPT socio.
     * @return bool
     */
    public function generate_and_send_reset_token( $socio_id ) {
        global $wpdb;

        $email = get_post_meta( $socio_id, '_cral_email', true );
        $nome  = get_post_meta( $socio_id, '_cral_nome', true );

        if ( empty( $email ) ) {
            return false;
        }

        // Invalida eventuali token precedenti non usati.
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->prefix}cral_password_tokens
                SET used_at = %s
                WHERE socio_id = %d
                AND used_at IS NULL",
                current_time( 'mysql' ),
                $socio_id
            )
        );

        // Genera token.
        $token      = bin2hex( random_bytes( 32 ) );
        $token_hash = hash( 'sha256', $token );
        $expires_at = gmdate( 'Y-m-d H:i:s', strtotime( '+24 hours' ) );

        $inserted = $wpdb->insert(
            $wpdb->prefix . 'cral_password_tokens',
            array(
                'socio_id'   => $socio_id,
                'token_hash' => $token_hash,
                'expires_at' => $expires_at,
                'used_at'    => null,
            ),
            array( '%d', '%s', '%s', 'NULL' )
        );

        if ( ! $inserted ) {
            return false;
        }

        $link = add_query_arg(
            array( 'token' => $token ),
            get_permalink( get_option( 'cral_pagina_imposta_password' ) )
        );

        return $this->send_token_email( $email, $nome, $link, 'reset' );
    }

    /**
     * Verifica un token ricevuto via GET.
     * Restituisce il socio_id se il token è valido, false altrimenti.
     *
     * @param string $token Token grezzo ricevuto via GET.
     * @return int|false
     */
    public function verify_token( $token ) {
        global $wpdb;

        if ( empty( $token ) ) {
            return false;
        }

        $token_hash = hash( 'sha256', $token );

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, socio_id, expires_at, used_at
                 FROM {$wpdb->prefix}cral_password_tokens
                 WHERE token_hash = %s
                   AND expires_at > %s
                   AND used_at IS NULL
                 LIMIT 1",
                $token_hash,
                current_time( 'mysql' )
            )
        );

        if ( ! $row ) {
            return false;
        }

        return (int) $row->socio_id;
    }

    /**
     * Invalida un token dopo l'utilizzo.
     *
     * @param string $token Token grezzo.
     * @return bool
     */
    public function invalidate_token( $token ) {
        global $wpdb;

        $token_hash = hash( 'sha256', $token );

        $updated = $wpdb->update(
            $wpdb->prefix . 'cral_password_tokens',
            array( 'used_at' => current_time( 'mysql' ) ),
            array( 'token_hash' => $token_hash ),
            array( '%s' ),
            array( '%s' )
        );

        return (bool) $updated;
    }

    /**
     * Restituisce lo stato della password per un socio.
     *
     * @param int $socio_id Post ID del CPT socio.
     * @return array {
     *     @type string      $status  'not_set' | 'token_pending' | 'set'
     *     @type string|null $expires Data scadenza token (solo se status = token_pending)
     * }
     */
    public function get_password_status( $socio_id ) {
        global $wpdb;

        // Controlla se la password è già impostata.
        $password = get_post_meta( $socio_id, '_cral_password', true );
        if ( ! empty( $password ) ) {
            return array(
                'status'  => 'set',
                'expires' => null,
            );
        }

        // Controlla se c'è un token in attesa.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT expires_at
                 FROM {$wpdb->prefix}cral_password_tokens
                 WHERE socio_id = %d
                   AND expires_at > %s
                   AND used_at IS NULL
                 ORDER BY expires_at DESC
                 LIMIT 1",
                $socio_id,
                current_time( 'mysql' )
            )
        );

        if ( $row ) {
            return array(
                'status'  => 'token_pending',
                'expires' => $row->expires_at,
            );
        }

        return array(
            'status'  => 'not_set',
            'expires' => null,
        );
    }

    /**
     * Gestisce la richiesta AJAX di invio email password dall'admin.
     */
    public function handle_send_password_email() {
        // Verifica nonce e permessi.
        check_ajax_referer( 'cral_send_password_email', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permessi insufficienti.' ) );
        }

        $socio_id = isset( $_POST['socio_id'] )
            ? absint( $_POST['socio_id'] )
            : 0;

        if ( ! $socio_id ) {
            wp_send_json_error( array( 'message' => 'ID socio non valido.' ) );
        }

        $result = $this->generate_and_send_token( $socio_id );

        if ( $result ) {
            wp_send_json_success( array( 'message' => 'Email inviata correttamente.' ) );
        } else {
            wp_send_json_error( array( 'message' => 'Errore durante l\'invio della email.' ) );
        }
    }

    /**
     * Cron giornaliero: elimina i token scaduti da oltre 7 giorni.
     */
    public static function cleanup_expired_tokens() {
        global $wpdb;

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}cral_password_tokens
                 WHERE expires_at < %s",
                gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) )
            )
        );
    }
}
<?php

/**
 * Autenticazione JWT: login, logout, verifica token.
 *
 * @package GEvent
 */

namespace GEvent;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Classe per la gestione dell'autenticazione JWT.
 */
class Auth
{

    /**
     * Registra gli hook WordPress.
     */
    public function init()
    {
        add_action('wp_ajax_nopriv_cral_login', array($this, 'handle_login'));
        add_action('wp_ajax_nopriv_cral_logout', array($this, 'handle_logout'));
        add_action('wp_ajax_cral_logout', array($this, 'handle_logout'));
        add_action('cral_cleanup_blocklist', array($this, 'cleanup_blocklist'));
        // Aggiunge anche la versione per utenti loggati.
        add_action('wp_ajax_cral_login', array($this, 'handle_login'));

        // Cron giornaliero pulizia blocklist.
        if (! wp_next_scheduled('cral_cleanup_blocklist')) {
            wp_schedule_event(time(), 'daily', 'cral_cleanup_blocklist');
        }
    }

    /**
     * Restituisce la JWT secret key.
     *
     * @return string
     */
    private function get_secret()
    {
        // CRAL_JWT_SECRET può essere definita in wp-config.php.
        return defined('CRAL_JWT_SECRET')
            ? constant('CRAL_JWT_SECRET')
            : get_option('cral_jwt_secret', '');
    }

    /**
     * Gestisce il login via AJAX.
     * Endpoint: wp_ajax_nopriv_cral_login
     */
    public function handle_login()
    {
        // Verifica nonce.
        $nonce = isset($_POST['nonce'])
            ? sanitize_text_field(wp_unslash($_POST['nonce']))
            : '';

        if (! wp_verify_nonce($nonce, 'cral_login_nonce')) {
            wp_send_json_error(array('message' => 'Richiesta non valida.'));
        }

        $socio_id = isset($_POST['socio_id'])
            ? sanitize_text_field(wp_unslash($_POST['socio_id']))
            : '';
        $password = isset($_POST['password'])
            ? sanitize_text_field(wp_unslash($_POST['password']))
            : '';

        if (empty($socio_id) || empty($password)) {
            wp_send_json_error(array('message' => 'Inserisci ID socio e password.'));
        }

        // Cerca il socio.
        $posts = get_posts(array(
            'post_type'      => 'socio',
            'meta_query'     => array(
                array(
                    'key'   => '_cral_socio_id',
                    'value' => $socio_id,
                ),
            ),
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ));

        if (empty($posts)) {
            wp_send_json_error(array('message' => 'Credenziali non valide.'));
        }

        $post_id         = (int) $posts[0];
        $hashed_password = get_post_meta($post_id, '_cral_password', true);

        if (empty($hashed_password) || ! wp_check_password($password, $hashed_password)) {
            wp_send_json_error(array('message' => 'Credenziali non valide.'));
        }

        // Genera JWT.
        $secret  = $this->get_secret();
        $issued  = time();
        $expires = $issued + (8 * HOUR_IN_SECONDS);

        $payload = array(
            'socio_id' => $post_id,
            'iat'      => $issued,
            'exp'      => $expires,
        );

        $token = JWT::encode($payload, $secret, 'HS256');

        setcookie(
            'cral_token',
            $token,
            array(
                'expires'  => $expires,
                'path'     => '/',
                'secure'   => is_ssl(),
                'httponly' => true,
                'samesite' => 'Strict',
            )
        );

        wp_send_json_success(array('message' => 'Login effettuato.'));
    }

    /**
     * Gestisce il logout via AJAX.
     * Aggiunge il token alla blocklist e cancella il cookie.
     */
    public function handle_logout()
    {
        $token = isset($_COOKIE['cral_token'])
            ? sanitize_text_field(wp_unslash($_COOKIE['cral_token']))
            : '';

        if (! empty($token)) {
            $this->add_to_blocklist($token);
        }

        // Cancella il cookie impostando expires nel passato.
        setcookie(
            'cral_token',
            '',
            array(
                'expires'  => time() - HOUR_IN_SECONDS,
                'path'     => '/',
                'secure'   => is_ssl(),
                'httponly' => true,
                'samesite' => 'Strict',
            )
        );

        // Reindirizza alla pagina di login.
        $login_page = get_permalink( get_option( 'cral_pagina_login' ) );
        wp_send_json_success(array('redirect' => $login_page));
    }

    /**
     * Aggiunge un token alla blocklist.
     *
     * @param string $token Token JWT grezzo.
     */
    private function add_to_blocklist($token)
    {
        global $wpdb;

        $token_hash = hash('sha256', $token);

        // Decodifica il token per recuperare la scadenza.
        $expires_at = gmdate('Y-m-d H:i:s', time() + (8 * HOUR_IN_SECONDS));

        try {
            $secret  = $this->get_secret();
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));
            if (isset($decoded->exp)) {
                $expires_at = gmdate('Y-m-d H:i:s', $decoded->exp);
            }
        } catch (\Exception $e) {
            // Token non decodificabile — usiamo la scadenza di default.
        }

        $wpdb->insert(
            $wpdb->prefix . 'cral_token_blocklist',
            array(
                'token_hash' => $token_hash,
                'expires_at' => $expires_at,
            ),
            array('%s', '%s')
        );
    }

    /**
     * Verifica il token JWT e restituisce il post ID del socio.
     * Restituisce false se il token non è valido.
     *
     * @return int|false
     */
    public function get_current_socio()
    {
        $token = isset($_COOKIE['cral_token'])
            ? sanitize_text_field(wp_unslash($_COOKIE['cral_token']))
            : '';

        if (empty($token)) {
            return false;
        }

        try {
            $secret  = $this->get_secret();
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));
        } catch (\Exception $e) {
            return false;
        }

        // Controlla che il token non sia in blocklist.
        if ($this->is_token_blocked($token)) {
            return false;
        }

        return isset($decoded->socio_id) ? (int) $decoded->socio_id : false;
    }

    /**
     * Verifica se un token è presente nella blocklist.
     *
     * @param string $token Token JWT grezzo.
     * @return bool
     */
    private function is_token_blocked($token)
    {
        global $wpdb;

        $token_hash = hash('sha256', $token);

        $result = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}cral_token_blocklist
                 WHERE token_hash = %s
                 AND expires_at > %s
                 LIMIT 1",
                $token_hash,
                current_time('mysql')
            )
        );

        return ! empty($result);
    }

    /**
     * Cron giornaliero: elimina i token scaduti dalla blocklist.
     */
    public static function cleanup_blocklist()
    {
        global $wpdb;

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}cral_token_blocklist
                 WHERE expires_at < %s",
                current_time('mysql')
            )
        );
    }
}

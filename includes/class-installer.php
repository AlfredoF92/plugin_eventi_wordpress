<?php
/**
 * Installazione del plugin: creazione tabelle e opzioni di default.
 *
 * @package GEvent
 */

namespace GEvent;

/**
 * Classe per la gestione dell'attivazione del plugin.
 */
class Installer {

    /**
     * Esegue tutte le operazioni di installazione.
     * Viene chiamata all'attivazione del plugin.
     */
    public static function run() {
        self::create_tables();
        self::generate_jwt_secret();
        update_option( 'cral_db_version', '1.0.1' );
    }

    /**
     * Crea le tabelle custom del plugin.
     */
    private static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Tabella token password (Fase 1.4 / 2.7).
        $table_tokens = $wpdb->prefix . 'cral_password_tokens';
        $sql_tokens = "CREATE TABLE {$table_tokens} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            socio_id BIGINT UNSIGNED NOT NULL,
            token_hash VARCHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL DEFAULT NULL,
            PRIMARY KEY (id),
            KEY socio_id (socio_id),
            KEY token_hash (token_hash)
        ) {$charset_collate};";

        // Tabella blocklist JWT (Fase 2.6).
        $table_blocklist = $wpdb->prefix . 'cral_token_blocklist';
        $sql_blocklist = "CREATE TABLE {$table_blocklist} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            token_hash VARCHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY token_hash (token_hash)
        ) {$charset_collate};";

        // Tabella coda email (Fase 1.5).
        $table_queue = $wpdb->prefix . 'cral_email_queue';
        $sql_queue   = "CREATE TABLE {$table_queue} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            socio_id BIGINT UNSIGNED NOT NULL,
            status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
            attempts TINYINT DEFAULT 0,
            created_at DATETIME NOT NULL,
            sent_at DATETIME NULL DEFAULT NULL,
            PRIMARY KEY (id),
            KEY socio_id (socio_id),
            KEY status (status)
        ) {$charset_collate};";

        // Tabella log operazioni.
        $table_logs = $wpdb->prefix . 'cral_operation_logs';
        $sql_logs   = "CREATE TABLE {$table_logs} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            operation VARCHAR(80) NOT NULL,
            message VARCHAR(255) NOT NULL,
            context LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY operation (operation),
            KEY created_at (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql_tokens );
        dbDelta( $sql_blocklist );
        dbDelta( $sql_queue );
        dbDelta( $sql_logs );
    }
    
    private static function generate_jwt_secret() {
        if ( ! get_option( 'cral_jwt_secret' ) ) {
            update_option( 'cral_jwt_secret', bin2hex( random_bytes( 32 ) ) );
        }
    }
    
}
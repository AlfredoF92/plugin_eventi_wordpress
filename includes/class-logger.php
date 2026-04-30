<?php
/**
 * Logging operazioni plugin.
 *
 * @package GEvent
 */

namespace GEvent;

/**
 * Classe per la gestione dei log operativi.
 */
class Logger {

    /**
     * Scrive una riga di log.
     *
     * @param string $operation Operazione eseguita.
     * @param string $message   Messaggio descrittivo.
     * @param array  $context   Dati aggiuntivi.
     */
    public static function log( $operation, $message, $context = array() ) {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'cral_operation_logs',
            array(
                'operation'  => sanitize_text_field( $operation ),
                'message'    => sanitize_text_field( $message ),
                'context'    => wp_json_encode( $context ),
                'created_at' => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%s', '%s' )
        );
    }

    /**
     * Restituisce gli ultimi log.
     *
     * @param int $limit Numero massimo righe.
     * @return array
     */
    public static function get_logs( $limit = 200 ) {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, operation, message, context, created_at
                 FROM {$wpdb->prefix}cral_operation_logs
                 ORDER BY id DESC
                 LIMIT %d",
                $limit
            )
        );
    }

    /**
     * Pulisce tutta la tabella log.
     */
    public static function clear_logs() {
        global $wpdb;
        $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}cral_operation_logs" );
    }
}

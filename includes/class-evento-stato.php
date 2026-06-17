<?php
/**
 * Stato evento: programmazione WordPress e etichette badge.
 *
 * @package GEvent
 */

namespace GEvent;

/**
 * Helper per stato "programmato" (post WordPress in coda di pubblicazione).
 */
class Evento_Stato {

    /**
     * Formatta una data con nome del giorno.
     *
     * @param int $timestamp Timestamp Unix.
     * @return string
     */
    public static function format_data_esplicativa( $timestamp ) {
        return wp_date( 'l d/m/Y \\a\\l\\l\\e H:i', $timestamp );
    }

    /**
     * Timestamp per date iscrizioni (gestisce anche valori legacy solo Y-m-d).
     *
     * @param string $raw  Valore meta.
     * @param string $kind 'apertura' o 'scadenza'.
     * @return int
     */
    public static function parse_iscrizione_ts( $raw, $kind = 'scadenza' ) {
        $raw = trim( (string) $raw );
        if ( '' === $raw ) {
            return 0;
        }

        if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw ) ) {
            $raw .= ( 'apertura' === $kind ) ? ' 00:00:00' : ' 23:59:00';
        } elseif ( ! preg_match( '/\d{2}:\d{2}/', $raw ) ) {
            $raw .= ( 'apertura' === $kind ) ? ' 00:00:00' : ' 23:59:00';
        }

        $ts = strtotime( $raw );
        return $ts ? $ts : 0;
    }

    /**
     * Formatta data/ora iscrizioni per UI.
     *
     * @param string $raw  Valore meta.
     * @param string $kind 'apertura' o 'scadenza'.
     * @return string
     */
    public static function format_iscrizione_datetime( $raw, $kind = 'scadenza' ) {
        $ts = self::parse_iscrizione_ts( $raw, $kind );
        return $ts ? wp_date( 'd/m/Y H:i', $ts ) : '';
    }

    /**
     * Registra hook di sincronizzazione stato meta ↔ post_status.
     */
    public static function init() {
        add_action( 'transition_post_status', array( __CLASS__, 'sync_meta_on_transition' ), 10, 3 );
    }

    /**
     * @param int $post_id ID evento.
     * @return bool
     */
    public static function is_programmato( $post_id ) {
        $post = get_post( $post_id );
        return $post && 'evento' === $post->post_type && 'future' === $post->post_status;
    }

    /**
     * Testo badge per evento programmato (data/ora pubblicazione WP).
     *
     * @param int $post_id ID evento.
     * @return string
     */
    public static function get_programmato_label( $post_id ) {
        if ( ! self::is_programmato( $post_id ) ) {
            return '';
        }

        $dt = get_post_datetime( $post_id, 'date', 'local' );
        if ( ! $dt ) {
            return __( 'Programmato', 'g-event' );
        }

        return sprintf(
            /* translators: %s: data completa con giorno e ora */
            __( 'Programmato per %s', 'g-event' ),
            self::format_data_esplicativa( $dt->getTimestamp() )
        );
    }

    /**
     * Allinea _cral_evento_stato al post_status WordPress.
     *
     * @param string   $new_status Nuovo stato WP.
     * @param string   $old_status Vecchio stato WP.
     * @param \WP_Post $post       Post.
     */
    public static function sync_meta_on_transition( $new_status, $old_status, $post ) {
        if ( ! $post || 'evento' !== $post->post_type ) {
            return;
        }

        $stato = (string) get_post_meta( $post->ID, '_cral_evento_stato', true );
        if ( in_array( $stato, array( 'concluso', 'annullato' ), true ) ) {
            return;
        }

        if ( 'future' === $new_status ) {
            update_post_meta( $post->ID, '_cral_evento_stato', 'programmato' );
            return;
        }

        if ( 'draft' === $new_status ) {
            update_post_meta( $post->ID, '_cral_evento_stato', 'bozza' );
            return;
        }

        if ( 'publish' === $new_status && in_array( $stato, array( 'bozza', 'programmato', '' ), true ) ) {
            update_post_meta( $post->ID, '_cral_evento_stato', 'pubblicato' );
        }
    }
}

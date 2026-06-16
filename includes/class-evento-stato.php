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
            /* translators: 1: data (gg/mm/aaaa), 2: ora (HH:MM) */
            __( 'Programmato per il giorno %1$s alle ore %2$s', 'g-event' ),
            wp_date( 'd/m/Y', $dt->getTimestamp() ),
            wp_date( 'H:i', $dt->getTimestamp() )
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

<?php
/**
 * Variabili dinamiche evento per Elementor (via shortcode).
 *
 * @package GEvent
 */

namespace GEvent;

/**
 * Shortcode helper per dati evento in loop Elementor.
 */
class Elementor_Dynamic {

    /**
     * Registra gli shortcode dinamici.
     */
    public function init() {
        add_shortcode( 'cral_evento_id', array( $this, 'evento_id' ) );
        add_shortcode( 'cral_evento_titolo', array( $this, 'evento_titolo' ) );
        add_shortcode( 'cral_evento_data', array( $this, 'evento_data' ) );
        add_shortcode( 'cral_evento_luogo', array( $this, 'evento_luogo' ) );
        add_shortcode( 'cral_evento_stato', array( $this, 'evento_stato' ) );
        add_shortcode( 'cral_evento_descrizione', array( $this, 'evento_descrizione' ) );
        add_shortcode( 'cral_evento_prezzo_biglietto', array( $this, 'evento_prezzo_biglietto' ) );
        add_shortcode( 'cral_evento_posti_totali', array( $this, 'evento_posti_totali' ) );
        add_shortcode( 'cral_evento_posti_residui', array( $this, 'evento_posti_residui' ) );
        add_shortcode( 'cral_evento_iscritti_count', array( $this, 'evento_iscritti_count' ) );
        add_shortcode( 'cral_evento_percentuale_riempimento', array( $this, 'evento_percentuale_riempimento' ) );
        add_shortcode( 'cral_evento_acc_socio_attivo', array( $this, 'evento_acc_socio_attivo' ) );
        add_shortcode( 'cral_evento_acc_socio_prezzo', array( $this, 'evento_acc_socio_prezzo' ) );
        add_shortcode( 'cral_evento_acc_socio_max', array( $this, 'evento_acc_socio_max' ) );
        add_shortcode( 'cral_evento_acc_esterno_attivo', array( $this, 'evento_acc_esterno_attivo' ) );
        add_shortcode( 'cral_evento_acc_esterno_prezzo', array( $this, 'evento_acc_esterno_prezzo' ) );
        add_shortcode( 'cral_evento_acc_esterno_max', array( $this, 'evento_acc_esterno_max' ) );
        add_shortcode( 'cral_evento_acc_junior_attivo', array( $this, 'evento_acc_junior_attivo' ) );
        add_shortcode( 'cral_evento_acc_junior_prezzo', array( $this, 'evento_acc_junior_prezzo' ) );
        add_shortcode( 'cral_evento_acc_junior_max', array( $this, 'evento_acc_junior_max' ) );
        add_shortcode( 'cral_evento_permalink', array( $this, 'evento_permalink' ) );
        add_shortcode( 'cral_evento_immagine', array( $this, 'evento_immagine' ) );
        add_shortcode( 'cral_evento_estratto', array( $this, 'evento_estratto' ) );
        add_shortcode( 'cral_evento_data_iscrizione', array( $this, 'evento_data_iscrizione' ) );
        add_shortcode( 'cral_evento_posti_riepilogo', array( $this, 'evento_posti_riepilogo' ) );
        add_shortcode( 'cral_evento_categoria', array( $this, 'evento_categoria' ) );
        add_shortcode( 'cral_evento_categoria_slug', array( $this, 'evento_categoria_slug' ) );
        add_shortcode( 'cral_evento_categoria_link', array( $this, 'evento_categoria_link' ) );
    }

    private function get_event_id( $atts ) {
        $atts = shortcode_atts(
            array(
                'id' => 0,
            ),
            $atts,
            'cral_evento'
        );

        $post_id = absint( $atts['id'] );
        if ( $post_id > 0 ) {
            return $post_id;
        }

        $current_id = get_the_ID();
        if ( $current_id && 'evento' === get_post_type( $current_id ) ) {
            return (int) $current_id;
        }

        return 0;
    }

    private function get_meta( $event_id, $key, $default = '' ) {
        if ( $event_id <= 0 ) {
            return $default;
        }
        $value = get_post_meta( $event_id, $key, true );
        return '' === $value ? $default : $value;
    }

    private function format_euro( $value ) {
        return '€ ' . number_format( (float) $value, 2, ',', '.' );
    }

    private function yes_no( $value ) {
        return 'yes' === (string) $value ? 'Si' : 'No';
    }

    public function evento_id( $atts ) {
        return (string) $this->get_event_id( $atts );
    }

    public function evento_titolo( $atts ) {
        $event_id = $this->get_event_id( $atts );
        return $event_id > 0 ? get_the_title( $event_id ) : '';
    }

    public function evento_data( $atts ) {
        $atts = shortcode_atts(
            array(
                'id'     => 0,
                'format' => 'd/m/Y H:i',
            ),
            $atts,
            'cral_evento_data'
        );

        $event_id = $this->get_event_id( $atts );
        $raw      = (string) $this->get_meta( $event_id, '_cral_evento_data', '' );
        if ( '' === $raw ) {
            return '';
        }

        $timestamp = strtotime( $raw );
        if ( ! $timestamp ) {
            return $raw;
        }

        return wp_date( sanitize_text_field( $atts['format'] ), $timestamp );
    }

    public function evento_luogo( $atts ) {
        $event_id = $this->get_event_id( $atts );
        return (string) $this->get_meta( $event_id, '_cral_evento_luogo', '' );
    }

    public function evento_stato( $atts ) {
        $event_id = $this->get_event_id( $atts );
        return (string) $this->get_meta( $event_id, '_cral_evento_stato', '' );
    }

    public function evento_descrizione( $atts ) {
        $atts = shortcode_atts(
            array(
                'id'    => 0,
                'plain' => 'no',
            ),
            $atts,
            'cral_evento_descrizione'
        );

        $event_id     = $this->get_event_id( $atts );
        $descrizione  = (string) $this->get_meta( $event_id, '_cral_evento_descrizione', '' );
        $plain_output = 'yes' === strtolower( (string) $atts['plain'] );

        return $plain_output ? wp_strip_all_tags( $descrizione ) : wp_kses_post( $descrizione );
    }

    public function evento_prezzo_biglietto( $atts ) {
        $atts = shortcode_atts(
            array(
                'id'     => 0,
                'format' => 'eur',
            ),
            $atts,
            'cral_evento_prezzo_biglietto'
        );
        $event_id = $this->get_event_id( $atts );
        $value    = (float) $this->get_meta( $event_id, '_cral_evento_prezzo_base', 0 );
        return 'raw' === strtolower( (string) $atts['format'] ) ? (string) $value : $this->format_euro( $value );
    }

    public function evento_posti_totali( $atts ) {
        $event_id = $this->get_event_id( $atts );
        return (string) (int) $this->get_meta( $event_id, '_cral_evento_posti_totali', 0 );
    }

    public function evento_posti_residui( $atts ) {
        $event_id = $this->get_event_id( $atts );
        return (string) (int) $this->get_meta( $event_id, '_cral_evento_posti_residui', 0 );
    }

    public function evento_iscritti_count( $atts ) {
        $event_id = $this->get_event_id( $atts );
        if ( $event_id <= 0 ) {
            return '0';
        }
        $totali   = (int) $this->get_meta( $event_id, '_cral_evento_posti_totali', 0 );
        $residui  = (int) $this->get_meta( $event_id, '_cral_evento_posti_residui', 0 );
        $iscritti = max( 0, $totali - $residui );
        return (string) $iscritti;
    }

    public function evento_percentuale_riempimento( $atts ) {
        $atts = shortcode_atts(
            array(
                'id'     => 0,
                'suffix' => '%',
            ),
            $atts,
            'cral_evento_percentuale_riempimento'
        );
        $event_id = $this->get_event_id( $atts );
        $totali   = (int) $this->get_meta( $event_id, '_cral_evento_posti_totali', 0 );
        $residui  = (int) $this->get_meta( $event_id, '_cral_evento_posti_residui', 0 );
        if ( $totali <= 0 ) {
            return '0' . (string) $atts['suffix'];
        }
        $iscritti = max( 0, $totali - $residui );
        $perc     = ( $iscritti / $totali ) * 100;
        return number_format_i18n( $perc, 0 ) . (string) $atts['suffix'];
    }

    public function evento_acc_socio_attivo( $atts ) {
        $event_id = $this->get_event_id( $atts );
        return $this->yes_no( $this->get_meta( $event_id, '_cral_evento_enable_acc_socio', '' ) );
    }

    public function evento_acc_socio_prezzo( $atts ) {
        return $this->acc_prezzo( $atts, '_cral_evento_prezzo_acc_socio' );
    }

    public function evento_acc_socio_max( $atts ) {
        return $this->acc_max( $atts, '_cral_evento_max_acc_socio' );
    }

    public function evento_acc_esterno_attivo( $atts ) {
        $event_id = $this->get_event_id( $atts );
        return $this->yes_no( $this->get_meta( $event_id, '_cral_evento_enable_acc_esterno', '' ) );
    }

    public function evento_acc_esterno_prezzo( $atts ) {
        return $this->acc_prezzo( $atts, '_cral_evento_prezzo_acc_esterno' );
    }

    public function evento_acc_esterno_max( $atts ) {
        return $this->acc_max( $atts, '_cral_evento_max_acc_esterno' );
    }

    public function evento_acc_junior_attivo( $atts ) {
        $event_id = $this->get_event_id( $atts );
        return $this->yes_no( $this->get_meta( $event_id, '_cral_evento_enable_acc_junior', '' ) );
    }

    public function evento_acc_junior_prezzo( $atts ) {
        return $this->acc_prezzo( $atts, '_cral_evento_prezzo_acc_junior' );
    }

    public function evento_acc_junior_max( $atts ) {
        return $this->acc_max( $atts, '_cral_evento_max_acc_junior' );
    }

    public function evento_permalink( $atts ) {
        $event_id = $this->get_event_id( $atts );
        return $event_id > 0 ? get_permalink( $event_id ) : '';
    }

    public function evento_immagine( $atts ) {
        $atts = shortcode_atts(
            array(
                'id'     => 0,
                'size'   => 'full',
                'format' => 'url',
            ),
            $atts,
            'cral_evento_immagine'
        );

        $event_id = $this->get_event_id( $atts );
        if ( $event_id <= 0 ) {
            return '';
        }

        $thumb_id = get_post_thumbnail_id( $event_id );
        if ( ! $thumb_id ) {
            return '';
        }

        if ( 'id' === strtolower( (string) $atts['format'] ) ) {
            return (string) $thumb_id;
        }

        $image_url = wp_get_attachment_image_url( $thumb_id, sanitize_key( $atts['size'] ) ?: 'full' );
        return $image_url ? (string) $image_url : '';
    }

    public function evento_estratto( $atts ) {
        $event_id = $this->get_event_id( $atts );
        if ( $event_id <= 0 ) {
            return '';
        }
        $post = get_post( $event_id );
        return $post ? wp_strip_all_tags( $post->post_excerpt ) : '';
    }

    public function evento_data_iscrizione( $atts ) {
        $atts = shortcode_atts(
            array(
                'id'     => 0,
                'format' => 'd/m/Y',
            ),
            $atts,
            'cral_evento_data_iscrizione'
        );
        $event_id = $this->get_event_id( $atts );
        $raw      = (string) $this->get_meta( $event_id, '_cral_evento_data_iscrizione', '' );
        if ( '' === $raw ) {
            return '';
        }
        $timestamp = strtotime( $raw );
        return $timestamp ? wp_date( sanitize_text_field( $atts['format'] ), $timestamp ) : $raw;
    }

    public function evento_posti_riepilogo( $atts ) {
        $event_id = $this->get_event_id( $atts );
        if ( $event_id <= 0 ) {
            return '';
        }
        $residui = (int) $this->get_meta( $event_id, '_cral_evento_posti_residui', 0 );
        $totali  = (int) $this->get_meta( $event_id, '_cral_evento_posti_totali', 0 );
        return $residui . ' / ' . $totali;
    }

    public function evento_categoria( $atts ) {
        $event_id = $this->get_event_id( $atts );
        if ( $event_id <= 0 ) {
            return '';
        }
        $terms = get_the_terms( $event_id, 'categoria_evento' );
        if ( ! $terms || is_wp_error( $terms ) ) {
            return '';
        }
        return esc_html( $terms[0]->name );
    }

    public function evento_categoria_slug( $atts ) {
        $event_id = $this->get_event_id( $atts );
        if ( $event_id <= 0 ) {
            return '';
        }
        $terms = get_the_terms( $event_id, 'categoria_evento' );
        if ( ! $terms || is_wp_error( $terms ) ) {
            return '';
        }
        return esc_attr( $terms[0]->slug );
    }

    public function evento_categoria_link( $atts ) {
        $event_id = $this->get_event_id( $atts );
        if ( $event_id <= 0 ) {
            return '';
        }
        $terms = get_the_terms( $event_id, 'categoria_evento' );
        if ( ! $terms || is_wp_error( $terms ) ) {
            return '';
        }
        $link = get_term_link( $terms[0] );
        return is_wp_error( $link ) ? '' : esc_url( $link );
    }

    private function acc_prezzo( $atts, $meta_key ) {
        $atts = shortcode_atts(
            array(
                'id'     => 0,
                'format' => 'eur',
            ),
            $atts,
            'cral_acc_prezzo'
        );
        $event_id = $this->get_event_id( $atts );
        $value    = (float) $this->get_meta( $event_id, $meta_key, 0 );
        return 'raw' === strtolower( (string) $atts['format'] ) ? (string) $value : $this->format_euro( $value );
    }

    private function acc_max( $atts, $meta_key ) {
        $event_id = $this->get_event_id( $atts );
        return (string) (int) $this->get_meta( $event_id, $meta_key, 0 );
    }
}

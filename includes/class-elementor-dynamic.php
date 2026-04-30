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

        // Hook query Elementor: loop eventi prenotati dal socio loggato.
        add_action( 'elementor/query/cral_eventi_prenotati', array( $this, 'query_eventi_prenotati' ) );

        // Hook query Elementor: loop tutti gli eventi futuri (pubblici).
        add_action( 'elementor/query/cral_eventi_futuri', array( $this, 'query_eventi_futuri' ) );

        // Hook query Elementor: loop eventi passati prenotati dal socio loggato.
        add_action( 'elementor/query/cral_eventi_passati', array( $this, 'query_eventi_passati' ) );
    }

    /**
     * Modifica la WP_Query del loop Elementor per mostrare solo
     * gli eventi futuri prenotati dal socio attualmente loggato.
     *
     * Da usare nel campo "ID Query" del Loop Grid con valore: cral_eventi_prenotati
     * Usa SQL diretto per evitare WP_Query annidate che esauriscono la memoria.
     *
     * @param \WP_Query $query Query Elementor da modificare.
     */
    public function query_eventi_prenotati( $query ) {
        global $wpdb;

        $auth     = new \GEvent\Auth();
        $socio_id = $auth->get_current_socio();

        if ( ! $socio_id ) {
            $query->set( 'post__in', array( 0 ) );
            return;
        }

        // Cache statica per non rieseguire la query nella stessa richiesta.
        static $cache = array();
        if ( isset( $cache[ $socio_id ] ) ) {
            $evento_ids = $cache[ $socio_id ];
        } else {
            $oggi = gmdate( 'Y-m-d H:i:s' );

            // SQL diretto: recupera gli ID evento dalle prenotazioni attive
            // del socio con data evento futura — tutto in una sola query.
            // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
            $evento_ids = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT DISTINCT ev_id.meta_value
                     FROM {$wpdb->posts} p
                     INNER JOIN {$wpdb->postmeta} socio_m  ON socio_m.post_id  = p.ID AND socio_m.meta_key  = '_cral_pren_socio_id'
                     INNER JOIN {$wpdb->postmeta} stato_m  ON stato_m.post_id  = p.ID AND stato_m.meta_key  = '_cral_pren_stato'
                     INNER JOIN {$wpdb->postmeta} ev_id    ON ev_id.post_id    = p.ID AND ev_id.meta_key    = '_cral_pren_evento_id'
                     INNER JOIN {$wpdb->postmeta} ev_data  ON ev_data.post_id  = CAST(ev_id.meta_value AS UNSIGNED) AND ev_data.meta_key = '_cral_evento_data'
                     WHERE p.post_type   = 'prenotazione'
                       AND p.post_status = 'publish'
                       AND socio_m.meta_value = %d
                       AND stato_m.meta_value IN ('confermata','in_attesa')
                       AND ev_data.meta_value >= %s",
                    $socio_id,
                    $oggi
                )
            );
            // phpcs:enable

            $evento_ids = array_map( 'intval', (array) $evento_ids );
            $evento_ids = array_filter( $evento_ids );

            $cache[ $socio_id ] = $evento_ids;
        }

        if ( empty( $evento_ids ) ) {
            $query->set( 'post__in', array( 0 ) );
            return;
        }

        $query->set( 'post_type', 'evento' );
        $query->set( 'post__in', $evento_ids );
        $query->set( 'meta_query', array(
            'data_evento_clause' => array(
                'key'     => '_cral_evento_data',
                'value'   => gmdate( 'Y-m-d H:i:s' ),
                'compare' => '>=',
                'type'    => 'DATETIME',
            ),
        ) );
        // Ordina per la clause nominata — garantisce ASC anche con post__in.
        $query->set( 'orderby', array( 'data_evento_clause' => 'ASC' ) );
    }

    /**
     * Modifica la WP_Query del loop Elementor per mostrare
     * tutti gli eventi futuri aperti (non annullati, non conclusi).
     *
     * Da usare nel campo "ID Query" del Loop Grid con valore: cral_eventi_futuri
     *
     * @param \WP_Query $query Query Elementor da modificare.
     */
    public function query_eventi_futuri( $query ) {
        $oggi = gmdate( 'Y-m-d H:i:s' );

        $query->set( 'post_type', 'evento' );
        $query->set( 'orderby', 'meta_value' );
        $query->set( 'meta_key', '_cral_evento_data' );
        $query->set( 'order', 'ASC' );
        $query->set( 'meta_query', array(
            'relation' => 'AND',
            array(
                'key'     => '_cral_evento_data',
                'value'   => $oggi,
                'compare' => '>=',
                'type'    => 'DATETIME',
            ),
            array(
                'key'     => '_cral_evento_stato',
                'value'   => array( 'annullato', 'concluso' ),
                'compare' => 'NOT IN',
            ),
        ) );
    }

    /**
     * Modifica la WP_Query del loop Elementor per mostrare solo
     * gli eventi passati prenotati dal socio attualmente loggato.
     *
     * Da usare nel campo "ID Query" del Loop Grid con valore: cral_eventi_passati
     * Usa SQL diretto per evitare WP_Query annidate che esauriscono la memoria.
     *
     * @param \WP_Query $query Query Elementor da modificare.
     */
    public function query_eventi_passati( $query ) {
        global $wpdb;

        $auth     = new \GEvent\Auth();
        $socio_id = $auth->get_current_socio();

        if ( ! $socio_id ) {
            $query->set( 'post__in', array( 0 ) );
            return;
        }

        // Cache statica per non rieseguire nella stessa richiesta.
        static $cache = array();
        if ( isset( $cache[ $socio_id ] ) ) {
            $evento_ids = $cache[ $socio_id ];
        } else {
            $oggi = gmdate( 'Y-m-d H:i:s' );

            $evento_ids = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT DISTINCT ev_id.meta_value
                     FROM {$wpdb->posts} p
                     INNER JOIN {$wpdb->postmeta} socio_m ON socio_m.post_id = p.ID AND socio_m.meta_key = '_cral_pren_socio_id'
                     INNER JOIN {$wpdb->postmeta} stato_m ON stato_m.post_id = p.ID AND stato_m.meta_key = '_cral_pren_stato'
                     INNER JOIN {$wpdb->postmeta} ev_id   ON ev_id.post_id   = p.ID AND ev_id.meta_key   = '_cral_pren_evento_id'
                     INNER JOIN {$wpdb->postmeta} ev_data ON ev_data.post_id = CAST(ev_id.meta_value AS UNSIGNED) AND ev_data.meta_key = '_cral_evento_data'
                     WHERE p.post_type   = 'prenotazione'
                       AND p.post_status = 'publish'
                       AND socio_m.meta_value = %d
                       AND stato_m.meta_value IN ('confermata','in_attesa')
                       AND ev_data.meta_value < %s",
                    $socio_id,
                    $oggi
                )
            );

            $evento_ids = array_map( 'intval', (array) $evento_ids );
            $evento_ids = array_filter( $evento_ids );

            $cache[ $socio_id ] = $evento_ids;
        }

        if ( empty( $evento_ids ) ) {
            $query->set( 'post__in', array( 0 ) );
            return;
        }

        $query->set( 'post_type', 'evento' );
        $query->set( 'post__in', $evento_ids );
        $query->set( 'meta_query', array(
            'data_evento_clause' => array(
                'key'     => '_cral_evento_data',
                'value'   => gmdate( 'Y-m-d H:i:s' ),
                'compare' => '<',
                'type'    => 'DATETIME',
            ),
        ) );
        // Dal più recente al più vecchio.
        $query->set( 'orderby', array( 'data_evento_clause' => 'DESC' ) );
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

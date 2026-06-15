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
        add_shortcode( 'cral_evento_data_estesa', array( $this, 'evento_data_estesa' ) );
        add_shortcode( 'cral_evento_badge', array( $this, 'evento_badge' ) );
        add_shortcode( 'cral_filtro_eventi', array( $this, 'filtro_eventi' ) );
        add_shortcode( 'cral_eventi_ajax', array( $this, 'shortcode_eventi_ajax' ) );

        // Endpoint AJAX per [cral_eventi_ajax].
        add_action( 'wp_ajax_cral_get_eventi',        array( $this, 'ajax_get_eventi' ) );
        add_action( 'wp_ajax_nopriv_cral_get_eventi', array( $this, 'ajax_get_eventi' ) );

        // Hook query Elementor: loop eventi filtrati dal form frontend.
        add_action( 'elementor/query/cral_eventi_filtrati', array( $this, 'query_eventi_filtrati' ) );

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
        $post         = $event_id > 0 ? get_post( $event_id ) : null;
        $descrizione  = $post ? (string) $post->post_content : '';
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

    /**
     * Shortcode [cral_evento_data_estesa] — data evento in formato esteso italiano.
     * Esempio: "Gio 12 maggio 2026"
     */
    public function evento_data_estesa( $atts ) {
        $event_id = $this->get_event_id( $atts );
        $raw      = (string) $this->get_meta( $event_id, '_cral_evento_data', '' );
        if ( '' === $raw ) {
            return '';
        }

        $ts = strtotime( $raw );
        if ( ! $ts ) {
            return '';
        }

        $giorni = array( 1 => 'Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab', 'Dom' );
        $mesi   = array(
            1  => 'gennaio', 'febbraio', 'marzo',    'aprile',
            'maggio',        'giugno',   'luglio',   'agosto',
            'settembre',     'ottobre',  'novembre', 'dicembre',
        );

        $dow   = (int) gmdate( 'N', $ts ); // 1 = lunedì, 7 = domenica
        $giorno_n = (int) gmdate( 'j', $ts );
        $mese_n   = (int) gmdate( 'n', $ts );
        $anno     = gmdate( 'Y', $ts );

        return $giorni[ $dow ] . ' ' . $giorno_n . ' ' . $mesi[ $mese_n ] . ' ' . $anno;
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

    /**
     * Shortcode [cral_evento_badge] — badge stato evento dinamico.
     * Funziona nel Loop Grid di Elementor (usa il post corrente).
     */
    public function evento_badge( $atts ) {
        $event_id = $this->get_event_id( $atts );
        if ( $event_id <= 0 ) {
            return '';
        }

        $stato           = (string) get_post_meta( $event_id, '_cral_evento_stato', true );
        $data_raw        = (string) get_post_meta( $event_id, '_cral_evento_data', true );
        $data_iscr_raw   = (string) get_post_meta( $event_id, '_cral_evento_data_iscrizione', true );
        $data_ap_raw     = (string) get_post_meta( $event_id, '_cral_evento_data_apertura_iscrizioni', true );
        $posti_residui   = (int)    get_post_meta( $event_id, '_cral_evento_posti_residui', true );
        $posti_totali    = (int)    get_post_meta( $event_id, '_cral_evento_posti_totali', true );

        $now         = time();
        $ts_evento   = $data_raw      ? strtotime( $data_raw )      : 0;
        $ts_scadenza = $data_iscr_raw ? strtotime( $data_iscr_raw ) : 0;
        $ts_apertura = $data_ap_raw   ? strtotime( $data_ap_raw )   : 0;
        $fmt         = static function( $ts ) { return $ts ? wp_date( 'd/m/Y', $ts ) : ''; };

        $is_annullato   = ( 'annullato' === $stato );
        $is_concluso    = ( 'concluso' === $stato ) || ( $ts_evento > 0 && $ts_evento < $now );
        $is_soldout     = ( ! $is_annullato && ! $is_concluso && $posti_residui <= 0 );
        $is_chiuse      = ( ! $is_annullato && ! $is_concluso && ! $is_soldout && $ts_scadenza > 0 && $ts_scadenza < $now );
        $is_non_ancora  = ( ! $is_annullato && ! $is_concluso && ! $is_soldout && ! $is_chiuse && $ts_apertura > 0 && $ts_apertura > $now );

        if ( $is_annullato ) {
            $label = 'Evento annullato'; $sub = '';
            $mod   = 'annullato';
        } elseif ( $is_concluso ) {
            $n_part = $posti_totali - $posti_residui;
            $label  = 'Evento concluso'; $sub = $n_part > 0 ? 'Partecipanti: ' . $n_part : '';
            $mod    = 'concluso';
        } elseif ( $is_soldout ) {
            $label = 'Sold out'; $sub = 'Posti disponibili: 0';
            $mod   = 'soldout';
        } elseif ( $is_chiuse ) {
            $label = 'Iscrizioni chiuse'; $sub = $ts_scadenza ? 'Scadute il ' . $fmt( $ts_scadenza ) : '';
            $mod   = 'chiuse';
        } elseif ( $is_non_ancora ) {
            $label = 'Evento pubblicato'; $sub = $ts_apertura ? 'Le iscrizioni aprono il ' . $fmt( $ts_apertura ) : '';
            $mod   = 'presto';
        } else {
            $label = 'Iscrizioni aperte'; $sub = $ts_scadenza ? 'fino al ' . $fmt( $ts_scadenza ) : '';
            $mod   = 'aperto';
        }

        ob_start();
        ?>
        <div class="cral-scheda__badge cral-scheda__badge--<?php echo esc_attr( $mod ); ?>">
            <span class="cral-scheda__badge-title"><?php echo esc_html( $label ); ?></span>
            <?php if ( $sub ) : ?>
            <span class="cral-scheda__badge-sub"><?php echo esc_html( $sub ); ?></span>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Shortcode [cral_filtro_eventi] — filtro AJAX per Loop Grid Elementor.
     *
     * Nel Loop Grid imposta:
     *   Query → ID Query = cral_eventi_filtrati
     *
     * Il JS auto-rileva il Loop Grid sulla pagina tramite data-id Elementor,
     * senza bisogno di impostare alcun CSS ID manualmente.
     */
    public function filtro_eventi( $atts ) {
        $atts = shortcode_atts( array(), $atts, 'cral_filtro_eventi' );

        $categorie = get_terms( array(
            'taxonomy'   => 'categoria_evento',
            'hide_empty' => true,
        ) );

        $stati = array(
            'aperto'   => 'Iscrizioni aperte',
            'presto'   => 'Prossimamente',
            'chiuse'   => 'Iscrizioni chiuse',
            'soldout'  => 'Sold out',
            'concluso' => 'Evento concluso',
        );

        ob_start();
        ?>
        <form class="cral-fe-filtri" id="cral-fe-filtri-form" novalidate>
            <div class="cral-fe-filtri__row">

                <div class="cral-fe-filtri__group cral-fe-filtri__group--cerca">
                    <span class="cral-fe-filtri__label" aria-hidden="true">&#128269;</span>
                    <input type="text" name="cfe_cerca" placeholder="Cerca evento…"
                        class="cral-fe-filtri__input" autocomplete="off">
                </div>

                <div class="cral-fe-filtri__group">
                    <select name="cfe_stato" class="cral-fe-filtri__select">
                        <option value="">Tutti gli stati</option>
                        <?php foreach ( $stati as $val => $lbl ) : ?>
                        <option value="<?php echo esc_attr( $val ); ?>"><?php echo esc_html( $lbl ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ( ! empty( $categorie ) && ! is_wp_error( $categorie ) ) : ?>
                <div class="cral-fe-filtri__group">
                    <select name="cfe_cat" class="cral-fe-filtri__select">
                        <option value="">Tutte le categorie</option>
                        <?php foreach ( $categorie as $cat ) : ?>
                        <option value="<?php echo esc_attr( $cat->slug ); ?>"><?php echo esc_html( $cat->name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="cral-fe-filtri__group cral-fe-filtri__group--date">
                    <span class="cral-fe-filtri__label" aria-hidden="true">&#128197;</span>
                    <input type="date" name="cfe_data_da"
                        class="cral-fe-filtri__input cral-fe-filtri__input--date" title="Data da">
                </div>

                <div class="cral-fe-filtri__group cral-fe-filtri__group--date">
                    <span class="cral-fe-filtri__label" aria-hidden="true">&#8594;</span>
                    <input type="date" name="cfe_data_a"
                        class="cral-fe-filtri__input cral-fe-filtri__input--date" title="Data a">
                </div>

                <div class="cral-fe-filtri__group cral-fe-filtri__group--actions">
                    <button type="button"
                        class="cral-fe-filtri__btn cral-fe-filtri__btn--reset cral-fe-filtri__btn--reset-hidden"
                        id="cral-fe-reset"
                        aria-label="Rimuovi filtri">&#10005; Azzera filtri</button>
                </div>

            </div>
        </form>

        <script>
        (function () {
            'use strict';

            var DEBOUNCE = 500;
            var timer;
            var form     = document.getElementById('cral-fe-filtri-form');
            var loopEl   = null; // riferimento al widget Loop Grid trovato
            var loopId   = null; // data-id Elementor del Loop Grid

            /* ── Trova il Loop Grid di Elementor sulla pagina ────────────── */
            function findLoopGrid() {
                /* 1) Prova a trovare il loop con data-settings contenente
                      l'ID query cral_eventi_filtrati (il più preciso). */
                var all = document.querySelectorAll('.elementor-widget-loop-grid');
                for (var i = 0; i < all.length; i++) {
                    var s = all[i].getAttribute('data-settings');
                    if (s && s.indexOf('cral_eventi_filtrati') !== -1) {
                        return all[i];
                    }
                }
                /* 2) Fallback: primo Loop Grid trovato sulla pagina. */
                return all.length ? all[0] : null;
            }

            /* ── Costruisce la URL con i parametri del form ──────────────── */
            function buildUrl() {
                var params = new URLSearchParams();
                var data   = new FormData(form);
                data.forEach(function (val, key) {
                    if (val && val.trim && val.trim()) {
                        params.set(key, val.trim());
                    }
                });
                var qs = params.toString();
                return window.location.pathname + (qs ? '?' + qs : '');
            }

            /* ── Aggiorna visibilità pulsante reset ──────────────────────── */
            function syncResetBtn(url) {
                var btn = document.getElementById('cral-fe-reset');
                if (!btn) return;
                btn.classList.toggle(
                    'cral-fe-filtri__btn--reset-hidden',
                    url === window.location.pathname || url.indexOf('?') === -1
                );
            }

            /* ── Fetch + swap del contenuto del Loop Grid ────────────────── */
            function doFilter() {
                if (!loopEl) return;

                var url = buildUrl();
                loopEl.classList.add('cral-loop--loading');
                showLoader();

                fetch(url)
                    .then(function (r) {
                        if (!r.ok) throw new Error('HTTP ' + r.status);
                        return r.text();
                    })
                    .then(function (html) {
                        var parser  = new DOMParser();
                        var doc     = parser.parseFromString(html, 'text/html');

                        /* Cerca lo stesso widget per data-id (stabile e univoco). */
                        var newLoop = loopId
                            ? doc.querySelector('[data-id="' + loopId + '"]')
                            : doc.querySelector('.elementor-widget-loop-grid');

                        var EMPTY_MSG =
                            '<div class="cral-loop-noresults">' +
                            '<span class="cral-loop-noresults__icon">&#128269;</span>' +
                            '<p class="cral-loop-noresults__text">Non abbiamo trovato eventi per questo filtro.</p>' +
                            '<p class="cral-loop-noresults__hint">Prova a modificare o rimuovere alcuni criteri di ricerca.</p>' +
                            '</div>';

                        /* Controlla se il loop contiene effettivamente elementi post
                           (Elementor usa .e-loop-item per ogni card del loop). */
                        var hasItems = newLoop && newLoop.querySelector(
                            '.e-loop-item, .elementor-post, .elementor-article'
                        );

                        /* ── SOSTITUZIONE COMPLETA DEL WIDGET ─────────────────
                           Sostituiamo l'intero .elementor-widget-loop-grid con
                           il nodo fresco dalla pagina fetchata. Questo garantisce
                           che classi, data-attributes e inline-styles siano
                           IDENTICI a quelli renderizzati dal server, eliminando
                           qualsiasi discrepanza di layout (colonne, gap, width). */
                        var parentEl = loopEl.parentNode;

                        if (hasItems && newLoop && parentEl) {
                            var freshEl = newLoop.cloneNode(true);
                            parentEl.replaceChild(freshEl, loopEl);
                            loopEl = freshEl;
                            loopId = loopEl.getAttribute('data-id') || null;
                        } else {
                            /* Nessun risultato */
                            var wc = loopEl.querySelector('.elementor-widget-container');
                            if (wc) { wc.innerHTML = EMPTY_MSG; }
                            else    { loopEl.innerHTML = EMPTY_MSG; }
                        }

                        loaderEl = createLoader(loopEl);
                        loopEl.classList.remove('cral-loop--loading');

                        history.pushState({}, '', url);
                        syncResetBtn(url);
                    })
                    .catch(function () {
                        loopEl.classList.remove('cral-loop--loading');
                    });
            }

            /* ── Crea l'overlay loader e lo aggancia al Loop Grid ────────── */
            function createLoader(parent) {
                var overlay = document.createElement('div');
                overlay.className = 'cral-loop-loader';
                overlay.innerHTML =
                    '<div class="cral-loop-dots">' +
                    '<span></span><span></span><span></span>' +
                    '</div>';
                parent.style.position = 'relative';
                parent.appendChild(overlay);
                return overlay;
            }

            var loaderEl = null;

            function showLoader()  { if (loaderEl) loaderEl.classList.add('is-active');    }
            function hideLoader()  { if (loaderEl) loaderEl.classList.remove('is-active'); }

            /* ── Init ────────────────────────────────────────────────────── */
            document.addEventListener('DOMContentLoaded', function () {
                if (!form) return;

                loopEl = findLoopGrid();
                if (loopEl) {
                    loopId   = loopEl.getAttribute('data-id') || null;
                    loaderEl = createLoader(loopEl);
                }

                /* Select e date: aggiornamento rapido */
                form.querySelectorAll('select, input[type="date"]').forEach(function (el) {
                    el.addEventListener('change', function () {
                        clearTimeout(timer);
                        timer = setTimeout(doFilter, 250);
                    });
                });

                /* Campo testo: debounce più lungo */
                form.querySelectorAll('input[type="text"]').forEach(function (el) {
                    el.addEventListener('input', function () {
                        clearTimeout(timer);
                        timer = setTimeout(doFilter, DEBOUNCE);
                    });
                });

                /* Submit esplicito */
                form.addEventListener('submit', function (e) {
                    e.preventDefault();
                    clearTimeout(timer);
                    doFilter();
                });

                /* Reset */
                var resetBtn = document.getElementById('cral-fe-reset');
                if (resetBtn) {
                    resetBtn.addEventListener('click', function () {
                        form.reset();
                        clearTimeout(timer);
                        doFilter();
                    });
                }
            });
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Hook Elementor query ID: cral_eventi_filtrati
     * Filtra il Loop Grid in base ai parametri GET del form frontend.
     *
     * @param \WP_Query $query Query Elementor da modificare.
     */
    public function query_eventi_filtrati( $query ) {
        $now_dt    = gmdate( 'Y-m-d H:i:s' );
        $today     = gmdate( 'Y-m-d' );
        $meta_q    = array( 'relation' => 'AND' );

        $f_cerca   = isset( $_GET['cfe_cerca'] )   ? sanitize_text_field( wp_unslash( $_GET['cfe_cerca'] ) )   : ''; // phpcs:ignore
        $f_stato   = isset( $_GET['cfe_stato'] )   ? sanitize_text_field( wp_unslash( $_GET['cfe_stato'] ) )   : ''; // phpcs:ignore
        $f_cat     = isset( $_GET['cfe_cat'] )     ? sanitize_text_field( wp_unslash( $_GET['cfe_cat'] ) )     : ''; // phpcs:ignore
        $f_data_da = isset( $_GET['cfe_data_da'] ) ? sanitize_text_field( wp_unslash( $_GET['cfe_data_da'] ) ) : ''; // phpcs:ignore
        $f_data_a  = isset( $_GET['cfe_data_a'] )  ? sanitize_text_field( wp_unslash( $_GET['cfe_data_a'] ) )  : ''; // phpcs:ignore

        $query->set( 'post_type', 'evento' );
        $query->set( 'post_status', 'publish' );

        // Filtro stato badge.
        switch ( $f_stato ) {
            case 'concluso':
                $meta_q[] = array(
                    'relation' => 'OR',
                    array( 'key' => '_cral_evento_stato', 'value' => 'concluso', 'compare' => '=' ),
                    array( 'key' => '_cral_evento_data',  'value' => $now_dt,    'compare' => '<', 'type' => 'DATETIME' ),
                );
                break;
            case 'soldout':
                $meta_q[] = array( 'key' => '_cral_evento_stato', 'value' => array( 'annullato', 'concluso' ), 'compare' => 'NOT IN' );
                $meta_q[] = array( 'key' => '_cral_evento_data',  'value' => $now_dt, 'compare' => '>=', 'type' => 'DATETIME' );
                $meta_q[] = array( 'key' => '_cral_evento_posti_residui', 'value' => '0', 'compare' => '<=', 'type' => 'NUMERIC' );
                break;
            case 'chiuse':
                $meta_q[] = array( 'key' => '_cral_evento_stato', 'value' => array( 'annullato', 'concluso' ), 'compare' => 'NOT IN' );
                $meta_q[] = array( 'key' => '_cral_evento_data',  'value' => $now_dt, 'compare' => '>=', 'type' => 'DATETIME' );
                $meta_q[] = array( 'key' => '_cral_evento_posti_residui', 'value' => '0', 'compare' => '>', 'type' => 'NUMERIC' );
                $meta_q[] = array( 'key' => '_cral_evento_data_iscrizione', 'value' => $today, 'compare' => '<', 'type' => 'DATE' );
                break;
            case 'presto':
                $meta_q[] = array( 'key' => '_cral_evento_stato', 'value' => array( 'annullato', 'concluso' ), 'compare' => 'NOT IN' );
                $meta_q[] = array( 'key' => '_cral_evento_data',  'value' => $now_dt, 'compare' => '>=', 'type' => 'DATETIME' );
                $meta_q[] = array( 'key' => '_cral_evento_posti_residui', 'value' => '0', 'compare' => '>', 'type' => 'NUMERIC' );
                $meta_q[] = array( 'key' => '_cral_evento_data_apertura_iscrizioni', 'value' => $today, 'compare' => '>', 'type' => 'DATE' );
                break;
            case 'aperto':
                $meta_q[] = array( 'key' => '_cral_evento_stato', 'value' => array( 'annullato', 'concluso' ), 'compare' => 'NOT IN' );
                $meta_q[] = array( 'key' => '_cral_evento_data',  'value' => $now_dt, 'compare' => '>=', 'type' => 'DATETIME' );
                $meta_q[] = array( 'key' => '_cral_evento_posti_residui', 'value' => '0', 'compare' => '>', 'type' => 'NUMERIC' );
                $meta_q[] = array(
                    'relation' => 'OR',
                    array( 'key' => '_cral_evento_data_iscrizione', 'compare' => 'NOT EXISTS' ),
                    array( 'key' => '_cral_evento_data_iscrizione', 'value' => '', 'compare' => '=' ),
                    array( 'key' => '_cral_evento_data_iscrizione', 'value' => $today, 'compare' => '>=', 'type' => 'DATE' ),
                );
                $meta_q[] = array(
                    'relation' => 'OR',
                    array( 'key' => '_cral_evento_data_apertura_iscrizioni', 'compare' => 'NOT EXISTS' ),
                    array( 'key' => '_cral_evento_data_apertura_iscrizioni', 'value' => '', 'compare' => '=' ),
                    array( 'key' => '_cral_evento_data_apertura_iscrizioni', 'value' => $today, 'compare' => '<=', 'type' => 'DATE' ),
                );
                break;
            default:
                // Nessun filtro stato: mostra solo eventi futuri, ordinati per data ASC.
                $meta_q[] = array(
                    'key'     => '_cral_evento_data',
                    'value'   => $now_dt,
                    'compare' => '>=',
                    'type'    => 'DATETIME',
                );
                break;
        }

        // Filtro data da / a.
        if ( $f_data_da && $f_data_a ) {
            $meta_q[] = array( 'key' => '_cral_evento_data', 'value' => array( $f_data_da . ' 00:00:00', $f_data_a . ' 23:59:59' ), 'compare' => 'BETWEEN', 'type' => 'DATETIME' );
        } elseif ( $f_data_da ) {
            $meta_q[] = array( 'key' => '_cral_evento_data', 'value' => $f_data_da . ' 00:00:00', 'compare' => '>=', 'type' => 'DATETIME' );
        } elseif ( $f_data_a ) {
            $meta_q[] = array( 'key' => '_cral_evento_data', 'value' => $f_data_a . ' 23:59:59', 'compare' => '<=', 'type' => 'DATETIME' );
        }

        if ( count( $meta_q ) > 1 ) {
            $query->set( 'meta_query', $meta_q );
        }

        // Ordinamento per data evento ASC.
        $query->set( 'meta_key', '_cral_evento_data' );
        $query->set( 'orderby', 'meta_value' );
        $query->set( 'order', 'ASC' );

        // Filtro categoria.
        if ( $f_cat ) {
            $query->set( 'tax_query', array(
                array( 'taxonomy' => 'categoria_evento', 'field' => 'slug', 'terms' => $f_cat ),
            ) );
        }

        // Ricerca testuale.
        if ( $f_cerca ) {
            $query->set( 's', $f_cerca );
        }
    }

    /* ──────────────────────────────────────────────────────────────────────
     * [cral_eventi_ajax] — griglia eventi con filtro AJAX autonomo.
     * Non dipende dal Loop Grid di Elementor.
     * ────────────────────────────────────────────────────────────────────── */

    /**
     * Shortcode [cral_eventi_ajax].
     *
     * Attributi:
     *   per_page  — numero di eventi da mostrare (default 12)
     *   columns   — numero di colonne della griglia (default 3)
     *
     * @param array $atts Attributi shortcode.
     * @return string HTML completo (filtri + griglia + JS AJAX).
     */
    public function shortcode_eventi_ajax( $atts ) {
        $atts = shortcode_atts(
            array(
                'per_page' => 12,
                'columns'  => 3,
            ),
            $atts,
            'cral_eventi_ajax'
        );

        // ID univoco per gestire più istanze nella stessa pagina.
        $uid = 'cev' . wp_rand( 1000, 9999 );

        $categorie = get_terms( array(
            'taxonomy'   => 'categoria_evento',
            'hide_empty' => true,
        ) );

        $stati = array(
            'aperto'   => 'Iscrizioni aperte',
            'presto'   => 'Prossimamente',
            'chiuse'   => 'Iscrizioni chiuse',
            'soldout'  => 'Sold out',
            'concluso' => 'Evento concluso',
        );

        // Render server-side iniziale (utile per SEO e prima visualizzazione).
        $initial_html = $this->render_eventi_cards( array(
            'cerca'    => '',
            'stato'    => '',
            'cat'      => '',
            'data_da'  => '',
            'data_a'   => '',
            'per_page' => (int) $atts['per_page'],
        ) );

        $ajax_url = esc_url( admin_url( 'admin-ajax.php' ) );
        $nonce    = wp_create_nonce( 'cral_filter_eventi' );
        $cols     = max( 1, min( 4, (int) $atts['columns'] ) );
        $form_id  = esc_attr( $uid . '-form' );
        $grid_id  = esc_attr( $uid . '-grid' );

        ob_start();
        ?>
        <div class="cral-eventi-ajax-wrap">

            <!-- ── FILTRI ─────────────────────────────────────────────────── -->
            <form class="cral-fe-filtri" id="<?php echo $form_id; ?>" novalidate>
                <div class="cral-fe-filtri__row">

                    <div class="cral-fe-filtri__group cral-fe-filtri__group--cerca">
                        <span class="cral-fe-filtri__label" aria-hidden="true">&#128269;</span>
                        <input type="text" name="cfe_cerca" placeholder="Cerca evento…" class="cral-fe-filtri__input" autocomplete="off">
                    </div>

                    <div class="cral-fe-filtri__group">
                        <select name="cfe_stato" class="cral-fe-filtri__select">
                            <option value="">Tutti gli stati</option>
                            <?php foreach ( $stati as $val => $lbl ) : ?>
                            <option value="<?php echo esc_attr( $val ); ?>"><?php echo esc_html( $lbl ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php if ( ! empty( $categorie ) && ! is_wp_error( $categorie ) ) : ?>
                    <div class="cral-fe-filtri__group">
                        <select name="cfe_cat" class="cral-fe-filtri__select">
                            <option value="">Tutte le categorie</option>
                            <?php foreach ( $categorie as $cat ) : ?>
                            <option value="<?php echo esc_attr( $cat->slug ); ?>"><?php echo esc_html( $cat->name ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <div class="cral-fe-filtri__group cral-fe-filtri__group--date">
                        <span class="cral-fe-filtri__label" aria-hidden="true">&#128197;</span>
                        <input type="date" name="cfe_data_da" class="cral-fe-filtri__input cral-fe-filtri__input--date" title="Data da">
                    </div>

                    <div class="cral-fe-filtri__group cral-fe-filtri__group--date">
                        <span class="cral-fe-filtri__label" aria-hidden="true">&#8594;</span>
                        <input type="date" name="cfe_data_a" class="cral-fe-filtri__input cral-fe-filtri__input--date" title="Data a">
                    </div>

                    <div class="cral-fe-filtri__group cral-fe-filtri__group--actions">
                        <button type="submit" class="cral-fe-filtri__btn cral-fe-filtri__btn--submit">Filtra</button>
                        <button type="button"
                            class="cral-fe-filtri__btn cral-fe-filtri__btn--reset cral-fe-filtri__btn--reset-hidden"
                            data-cral-reset="1"
                            aria-label="Rimuovi filtri"
                        >&#10005;</button>
                    </div>

                </div>
            </form>

            <!-- ── GRIGLIA ────────────────────────────────────────────────── -->
            <div class="cral-ev-grid cral-ev-grid--cols-<?php echo $cols; ?>" id="<?php echo $grid_id; ?>">
                <?php echo $initial_html; ?>
            </div>

        </div>

        <script>
        (function () {
            var AJAX_URL = <?php echo wp_json_encode( $ajax_url ); ?>;
            var NONCE    = <?php echo wp_json_encode( $nonce ); ?>;
            var formEl   = document.getElementById(<?php echo wp_json_encode( $uid . '-form' ); ?>);
            var gridEl   = document.getElementById(<?php echo wp_json_encode( $uid . '-grid' ); ?>);
            var DEBOUNCE = 500;
            var timer;

            function hasActiveFilters() {
                if (!formEl) return false;
                var els = formEl.querySelectorAll('input, select');
                for (var i = 0; i < els.length; i++) {
                    if (els[i].value && els[i].value.trim()) return true;
                }
                return false;
            }

            function toggleResetBtn() {
                var btn = formEl && formEl.querySelector('[data-cral-reset]');
                if (!btn) return;
                btn.classList.toggle('cral-fe-filtri__btn--reset-hidden', !hasActiveFilters());
            }

            function doFilter() {
                if (!formEl || !gridEl) return;

                var data = new FormData(formEl);
                data.append('action', 'cral_get_eventi');
                data.append('_nonce', NONCE);

                gridEl.classList.add('cral-ev-grid--loading');

                fetch(AJAX_URL, { method: 'POST', body: data })
                    .then(function (r) { return r.json(); })
                    .then(function (json) {
                        gridEl.classList.remove('cral-ev-grid--loading');
                        if (json && json.success) {
                            gridEl.innerHTML = json.data.html;
                        }
                        toggleResetBtn();
                    })
                    .catch(function () {
                        gridEl.classList.remove('cral-ev-grid--loading');
                    });
            }

            if (formEl) {
                /* Select e date: aggiornamento rapido al cambio */
                formEl.querySelectorAll('select, input[type="date"]').forEach(function (el) {
                    el.addEventListener('change', function () {
                        clearTimeout(timer);
                        timer = setTimeout(doFilter, 200);
                    });
                });

                /* Campo testo: debounce più lungo */
                formEl.querySelectorAll('input[type="text"]').forEach(function (el) {
                    el.addEventListener('input', function () {
                        clearTimeout(timer);
                        timer = setTimeout(doFilter, DEBOUNCE);
                    });
                });

                /* Submit esplicito */
                formEl.addEventListener('submit', function (e) {
                    e.preventDefault();
                    clearTimeout(timer);
                    doFilter();
                });

                /* Reset */
                var resetBtn = formEl.querySelector('[data-cral-reset]');
                if (resetBtn) {
                    resetBtn.addEventListener('click', function () {
                        formEl.reset();
                        resetBtn.classList.add('cral-fe-filtri__btn--reset-hidden');
                        clearTimeout(timer);
                        doFilter();
                    });
                }
            }
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Handler AJAX wp_ajax_cral_get_eventi / wp_ajax_nopriv_cral_get_eventi.
     * Restituisce JSON con l'HTML delle card filtrate.
     */
    public function ajax_get_eventi() {
        // Verifica nonce.
        if ( ! isset( $_POST['_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_nonce'] ) ), 'cral_filter_eventi' ) ) {
            wp_send_json_error( array( 'message' => 'Nonce non valido.' ), 403 );
        }

        $params = array(
            'cerca'    => isset( $_POST['cfe_cerca'] )   ? sanitize_text_field( wp_unslash( $_POST['cfe_cerca'] ) )   : '',
            'stato'    => isset( $_POST['cfe_stato'] )   ? sanitize_text_field( wp_unslash( $_POST['cfe_stato'] ) )   : '',
            'cat'      => isset( $_POST['cfe_cat'] )     ? sanitize_text_field( wp_unslash( $_POST['cfe_cat'] ) )     : '',
            'data_da'  => isset( $_POST['cfe_data_da'] ) ? sanitize_text_field( wp_unslash( $_POST['cfe_data_da'] ) ) : '',
            'data_a'   => isset( $_POST['cfe_data_a'] )  ? sanitize_text_field( wp_unslash( $_POST['cfe_data_a'] ) )  : '',
            'per_page' => 12,
        );

        $html = $this->render_eventi_cards( $params );
        wp_send_json_success( array( 'html' => $html ) );
    }

    /**
     * Esegue la WP_Query e restituisce l'HTML delle card eventi.
     *
     * @param array $params Parametri filtro.
     * @return string HTML delle card (o messaggio "nessun risultato").
     */
    private function render_eventi_cards( array $params ) {
        $args  = $this->build_eventi_query_args( $params );
        $query = new \WP_Query( $args );

        if ( ! $query->have_posts() ) {
            return '<p class="cral-loop-empty">Nessun evento trovato per i filtri selezionati.</p>';
        }

        $html = '';
        while ( $query->have_posts() ) {
            $query->the_post();
            $html .= $this->render_evento_card( get_the_ID() );
        }
        wp_reset_postdata();

        return $html;
    }

    /**
     * Costruisce gli argomenti WP_Query a partire dai parametri filtro.
     *
     * @param array $params Parametri filtro (cerca, stato, cat, data_da, data_a, per_page).
     * @return array Argomenti per WP_Query.
     */
    private function build_eventi_query_args( array $params ) {
        $now_dt  = gmdate( 'Y-m-d H:i:s' );
        $today   = gmdate( 'Y-m-d' );
        $meta_q  = array( 'relation' => 'AND' );

        $f_stato   = (string) ( $params['stato']   ?? '' );
        $f_data_da = (string) ( $params['data_da'] ?? '' );
        $f_data_a  = (string) ( $params['data_a']  ?? '' );
        $f_cerca   = (string) ( $params['cerca']   ?? '' );
        $f_cat     = (string) ( $params['cat']     ?? '' );
        $per_page  = (int) ( $params['per_page'] ?? 12 );

        switch ( $f_stato ) {
            case 'concluso':
                $meta_q[] = array(
                    'relation' => 'OR',
                    array( 'key' => '_cral_evento_stato', 'value' => 'concluso', 'compare' => '=' ),
                    array( 'key' => '_cral_evento_data',  'value' => $now_dt,    'compare' => '<', 'type' => 'DATETIME' ),
                );
                break;
            case 'soldout':
                $meta_q[] = array( 'key' => '_cral_evento_stato', 'value' => array( 'annullato', 'concluso' ), 'compare' => 'NOT IN' );
                $meta_q[] = array( 'key' => '_cral_evento_data',  'value' => $now_dt, 'compare' => '>=', 'type' => 'DATETIME' );
                $meta_q[] = array( 'key' => '_cral_evento_posti_residui', 'value' => '0', 'compare' => '<=', 'type' => 'NUMERIC' );
                break;
            case 'chiuse':
                $meta_q[] = array( 'key' => '_cral_evento_stato', 'value' => array( 'annullato', 'concluso' ), 'compare' => 'NOT IN' );
                $meta_q[] = array( 'key' => '_cral_evento_data',  'value' => $now_dt, 'compare' => '>=', 'type' => 'DATETIME' );
                $meta_q[] = array( 'key' => '_cral_evento_posti_residui', 'value' => '0', 'compare' => '>', 'type' => 'NUMERIC' );
                $meta_q[] = array( 'key' => '_cral_evento_data_iscrizione', 'value' => $today, 'compare' => '<', 'type' => 'DATE' );
                break;
            case 'presto':
                $meta_q[] = array( 'key' => '_cral_evento_stato', 'value' => array( 'annullato', 'concluso' ), 'compare' => 'NOT IN' );
                $meta_q[] = array( 'key' => '_cral_evento_data',  'value' => $now_dt, 'compare' => '>=', 'type' => 'DATETIME' );
                $meta_q[] = array( 'key' => '_cral_evento_posti_residui', 'value' => '0', 'compare' => '>', 'type' => 'NUMERIC' );
                $meta_q[] = array( 'key' => '_cral_evento_data_apertura_iscrizioni', 'value' => $today, 'compare' => '>', 'type' => 'DATE' );
                break;
            case 'aperto':
                $meta_q[] = array( 'key' => '_cral_evento_stato', 'value' => array( 'annullato', 'concluso' ), 'compare' => 'NOT IN' );
                $meta_q[] = array( 'key' => '_cral_evento_data',  'value' => $now_dt, 'compare' => '>=', 'type' => 'DATETIME' );
                $meta_q[] = array( 'key' => '_cral_evento_posti_residui', 'value' => '0', 'compare' => '>', 'type' => 'NUMERIC' );
                $meta_q[] = array(
                    'relation' => 'OR',
                    array( 'key' => '_cral_evento_data_iscrizione', 'compare' => 'NOT EXISTS' ),
                    array( 'key' => '_cral_evento_data_iscrizione', 'value' => '', 'compare' => '=' ),
                    array( 'key' => '_cral_evento_data_iscrizione', 'value' => $today, 'compare' => '>=', 'type' => 'DATE' ),
                );
                $meta_q[] = array(
                    'relation' => 'OR',
                    array( 'key' => '_cral_evento_data_apertura_iscrizioni', 'compare' => 'NOT EXISTS' ),
                    array( 'key' => '_cral_evento_data_apertura_iscrizioni', 'value' => '', 'compare' => '=' ),
                    array( 'key' => '_cral_evento_data_apertura_iscrizioni', 'value' => $today, 'compare' => '<=', 'type' => 'DATE' ),
                );
                break;
            default:
                // Nessun filtro stato: mostra solo eventi futuri, ordinati per data ASC.
                $meta_q[] = array(
                    'key'     => '_cral_evento_data',
                    'value'   => $now_dt,
                    'compare' => '>=',
                    'type'    => 'DATETIME',
                );
                break;
        }

        // Filtro data da / a.
        if ( $f_data_da && $f_data_a ) {
            $meta_q[] = array( 'key' => '_cral_evento_data', 'value' => array( $f_data_da . ' 00:00:00', $f_data_a . ' 23:59:59' ), 'compare' => 'BETWEEN', 'type' => 'DATETIME' );
        } elseif ( $f_data_da ) {
            $meta_q[] = array( 'key' => '_cral_evento_data', 'value' => $f_data_da . ' 00:00:00', 'compare' => '>=', 'type' => 'DATETIME' );
        } elseif ( $f_data_a ) {
            $meta_q[] = array( 'key' => '_cral_evento_data', 'value' => $f_data_a . ' 23:59:59', 'compare' => '<=', 'type' => 'DATETIME' );
        }

        $args = array(
            'post_type'      => 'evento',
            'post_status'    => 'publish',
            'posts_per_page' => $per_page,
            'meta_key'       => '_cral_evento_data',
            'orderby'        => 'meta_value',
            'order'          => 'ASC',
        );

        if ( count( $meta_q ) > 1 ) {
            $args['meta_query'] = $meta_q;
        }

        if ( $f_cat ) {
            $args['tax_query'] = array(
                array( 'taxonomy' => 'categoria_evento', 'field' => 'slug', 'terms' => $f_cat ),
            );
        }

        if ( $f_cerca ) {
            $args['s'] = $f_cerca;
        }

        return $args;
    }

    /**
     * Renderizza una singola card evento.
     *
     * @param int $post_id ID del post evento.
     * @return string HTML della card.
     */
    private function render_evento_card( $post_id ) {
        $title    = get_the_title( $post_id );
        $url      = get_permalink( $post_id );
        $excerpt  = wp_trim_words( get_the_excerpt( $post_id ), 18, '…' );
        $data_raw = (string) get_post_meta( $post_id, '_cral_evento_data', true );
        $luogo    = (string) get_post_meta( $post_id, '_cral_evento_luogo', true );
        $date_str = $data_raw ? wp_date( 'd/m/Y', strtotime( $data_raw ) ) : '';

        $thumb = get_the_post_thumbnail( $post_id, 'medium' );
        if ( ! $thumb ) {
            $thumb = '<div class="cral-ev-card__placeholder"><span>&#127917;</span></div>';
        }

        $badge = $this->evento_badge( array( 'id' => $post_id ) );

        ob_start();
        ?>
        <article class="cral-ev-card">
            <a href="<?php echo esc_url( $url ); ?>" class="cral-ev-card__link">
                <div class="cral-ev-card__media">
                    <?php echo $thumb; ?>
                    <?php if ( $badge ) : ?>
                    <div class="cral-ev-card__badge-wrap"><?php echo $badge; ?></div>
                    <?php endif; ?>
                </div>
                <div class="cral-ev-card__body">
                    <h3 class="cral-ev-card__title"><?php echo esc_html( $title ); ?></h3>
                    <?php if ( $date_str || $luogo ) : ?>
                    <div class="cral-ev-card__meta">
                        <?php if ( $date_str ) : ?>
                        <span class="cral-ev-card__date">&#128197; <?php echo esc_html( $date_str ); ?></span>
                        <?php endif; ?>
                        <?php if ( $luogo ) : ?>
                        <span class="cral-ev-card__location">&#128205; <?php echo esc_html( $luogo ); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <?php if ( $excerpt ) : ?>
                    <p class="cral-ev-card__excerpt"><?php echo esc_html( $excerpt ); ?></p>
                    <?php endif; ?>
                    <span class="cral-ev-card__cta">Scopri di più &#8594;</span>
                </div>
            </a>
        </article>
        <?php
        return ob_get_clean();
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

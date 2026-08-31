<?php
/**
 * Shortcode calendario eventi mensile con lista e popup.
 *
 * @package GEvent
 */

namespace GEvent;

/**
 * Calendario eventi frontend [cral_calendario_eventi].
 */
class Calendario_Eventi {

    /**
     * @var bool
     */
    private static $assets_enqueued = false;

    /**
     * @var Elementor_Dynamic|null
     */
    private $dynamic = null;

    /**
     * Registra shortcode e AJAX.
     */
    public function init() {
        add_shortcode( 'cral_calendario_eventi', array( $this, 'render' ) );
        add_action( 'wp_ajax_cral_calendario_mese', array( $this, 'ajax_mese' ) );
        add_action( 'wp_ajax_nopriv_cral_calendario_mese', array( $this, 'ajax_mese' ) );
    }

    /**
     * @return Elementor_Dynamic
     */
    protected function dynamic() {
        if ( null === $this->dynamic ) {
            $this->dynamic = new Elementor_Dynamic();
        }
        return $this->dynamic;
    }

    /**
     * Nome e stato accesso per la sezione di benvenuto.
     *
     * @return array{name: string, logged_in: bool}
     */
    protected function resolve_welcome_identity() {
        if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
            $wp_user = wp_get_current_user();
            $nome    = trim( (string) $wp_user->first_name );
            if ( '' === $nome ) {
                $nome = trim( (string) $wp_user->display_name );
            }
            if ( '' === $nome ) {
                $nome = (string) $wp_user->user_login;
            }

            return array(
                'name'      => $nome,
                'logged_in' => true,
            );
        }

        $auth     = new Auth();
        $socio_id = $auth->get_current_socio();
        if ( $socio_id ) {
            $nome = trim( (string) get_post_meta( $socio_id, '_cral_nome', true ) );
            if ( '' === $nome ) {
                $nome = __( 'Socio', 'g-event' );
            }

            return array(
                'name'      => $nome,
                'logged_in' => true,
            );
        }

        return array(
            'name'      => '',
            'logged_in' => false,
        );
    }

    /**
     * Carica CSS/JS del calendario (anche in render shortcode, post wp_head).
     */
    private function enqueue_assets() {
        if ( self::$assets_enqueued ) {
            return;
        }
        self::$assets_enqueued = true;

        $base = plugin_dir_url( dirname( __FILE__ ) );
        $ver  = '1.7.4';

        wp_enqueue_style(
            'g-event-calendario-font',
            'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap',
            array(),
            null
        );
        wp_enqueue_style( 'g-event-frontend', $base . 'assets/css/frontend.css', array(), '1.1.10' );
        wp_enqueue_style( 'g-event-scheda', $base . 'assets/css/scheda-evento.css', array(), '2.2.7' );
        wp_enqueue_style(
            'g-event-calendario',
            $base . 'assets/css/calendario-eventi.css',
            array( 'g-event-calendario-font', 'g-event-frontend', 'g-event-scheda' ),
            $ver
        );

        wp_enqueue_script(
            'g-event-calendario',
            $base . 'assets/js/calendario-eventi.js',
            array(),
            $ver,
            true
        );

        wp_localize_script(
            'g-event-calendario',
            'cralCalendario',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'cral_calendario_mese' ),
                'i18n'    => array(
                    'prev'               => 'Mese precedente',
                    'next'               => 'Mese successivo',
                    'loading'            => 'Caricamento…',
                    'noEvents'           => 'Nessun evento in questo mese.',
                    'goEvent'            => 'Scopri di più',
                    'close'              => 'Chiudi',
                    'prossimo'           => 'Prossimo evento',
                    'listaMese'          => 'Eventi del mese di',
                    'oggi'               => 'Oggi',
                    'eventiGiorno'       => 'Eventi',
                    'prossimiEventi'     => 'Prossimi eventi',
                    'eventiPassati'      => 'Eventi passati',
                    'nessunPassato'      => 'Nessun evento passato.',
                    'nessunEventoGiorno' => 'Nessun evento in questo giorno.',
                    'nessunEventoMese'   => 'Nessun evento questo mese',
                    'nessunProssimo'     => 'Nessun prossimo evento in programma.',
                    'selezionaGiorno'    => 'Seleziona un giorno con eventi',
                    'tornaOggi'          => 'Torna ad oggi',
                ),
            )
        );
    }

    /**
     * Shortcode [cral_calendario_eventi].
     *
     * @param array $atts Attributi.
     * @return string
     */
    public function render( $atts ) {
        $this->enqueue_assets();

        $atts = shortcode_atts(
            array(
                'mese' => '',
            ),
            $atts,
            'cral_calendario_eventi'
        );

        if ( $atts['mese'] && preg_match( '/^\d{4}-\d{2}$/', $atts['mese'] ) ) {
            list( $year, $month ) = array_map( 'intval', explode( '-', $atts['mese'] ) );
        } else {
            $year  = (int) wp_date( 'Y' );
            $month = (int) wp_date( 'n' );
        }

        $year  = max( 1970, min( 2100, $year ) );
        $month = max( 1, min( 12, $month ) );

        $events = $this->get_events_for_month( $year, $month );
        $by_day = $this->group_events_by_day( $events );
        $focus  = $this->resolve_focus_day( $year, $month, $by_day );

        // Se il mese corrente non ha eventi futuri, apri il mese del prossimo evento.
        if ( $focus <= 0 && empty( $atts['mese'] ) ) {
            $next_batch = $this->get_upcoming_events( 1 );
            if ( ! empty( $next_batch[0]['data_raw'] ) ) {
                $next_ts = strtotime( $next_batch[0]['data_raw'] );
                if ( $next_ts ) {
                    $year   = (int) wp_date( 'Y', $next_ts );
                    $month  = (int) wp_date( 'n', $next_ts );
                    $events = $this->get_events_for_month( $year, $month );
                    $by_day = $this->group_events_by_day( $events );
                    $focus  = $this->resolve_focus_day( $year, $month, $by_day );
                }
            }
        }

        $upcoming = $this->get_upcoming_events( 12 );
        $past     = $this->get_past_events( 48, 0 );
        $uid      = 'cral-cal-' . wp_rand( 1000, 9999 );
        $welcome  = $this->resolve_welcome_identity();

        ob_start();
        ?>
        <div class="cral-cal" id="<?php echo esc_attr( $uid ); ?>"
             data-year="<?php echo esc_attr( (string) $year ); ?>"
             data-month="<?php echo esc_attr( (string) $month ); ?>"
             data-focus-day="<?php echo esc_attr( (string) $focus ); ?>">

            <section class="cral-cal__welcome" aria-label="<?php esc_attr_e( 'Benvenuto', 'g-event' ); ?>">
                <p class="cral-cal__welcome-hello">
                    <?php if ( ! empty( $welcome['name'] ) ) : ?>
                        <?php
                        printf(
                            /* translators: %s: user first name */
                            esc_html__( 'Ciao, %s', 'g-event' ),
                            esc_html( $welcome['name'] )
                        );
                        ?>
                    <?php else : ?>
                        <?php esc_html_e( 'Ciao', 'g-event' ); ?>
                    <?php endif; ?>
                </p>
                <p class="cral-cal__welcome-sub">
                    <?php
                    echo esc_html(
                        ! empty( $welcome['logged_in'] )
                            ? __( 'Piacere di rivederti', 'g-event' )
                            : __( 'Piacere di conoscerti', 'g-event' )
                    );
                    ?>
                </p>
                <p class="cral-cal__welcome-desc">
                    <?php esc_html_e( 'Scopri i prossimi eventi in programma per il CRAL BCC di Milano', 'g-event' ); ?>
                </p>
            </section>

            <section class="cral-cal__upcoming" aria-label="<?php esc_attr_e( 'Prossimi eventi', 'g-event' ); ?>">
                <div class="cral-cal__upcoming-head">
                    <h3 class="cral-cal__upcoming-title"><?php esc_html_e( 'Prossimi eventi', 'g-event' ); ?></h3>
                    <div class="cral-cal__carousel-nav" aria-label="<?php esc_attr_e( 'Navigazione carosello', 'g-event' ); ?>">
                        <button type="button" class="cral-cal__carousel-btn" data-cal-up-prev aria-label="<?php esc_attr_e( 'Precedenti', 'g-event' ); ?>">
                            <span aria-hidden="true">&#8249;</span>
                        </button>
                        <button type="button" class="cral-cal__carousel-btn" data-cal-up-next aria-label="<?php esc_attr_e( 'Successivi', 'g-event' ); ?>">
                            <span aria-hidden="true">&#8250;</span>
                        </button>
                    </div>
                </div>
                <div class="cral-cal__carousel" data-cal-upcoming-carousel>
                    <div class="cral-cal__product-grid cral-cal__product-grid--carousel" data-cal-upcoming>
                        <?php echo $this->render_product_grid( $upcoming ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                </div>
            </section>

            <section class="cral-cal__calendar-block" aria-label="<?php esc_attr_e( 'Calendario eventi', 'g-event' ); ?>">
                <h3 class="cral-cal__section-title"><?php esc_html_e( 'Calendario eventi', 'g-event' ); ?></h3>
                <div class="cral-cal__layout">
                    <section class="cral-cal__calendar-panel">
                        <?php echo $this->render_calendar_nav( $year, $month ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <div class="cral-cal__grid-wrap" data-cal-grid>
                            <?php echo $this->render_calendar_grid( $year, $month, $by_day, $focus ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </div>
                    </section>

                    <section class="cral-cal__list-panel cral-cal__day-panel" aria-label="<?php esc_attr_e( 'Eventi del giorno', 'g-event' ); ?>">
                        <h3 class="cral-cal__list-title" data-cal-day-title><?php echo $this->render_day_panel_title( $year, $month, $focus ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></h3>
                        <div class="cral-cal__list" data-cal-day-list>
                            <?php echo $this->render_day_events_list( $focus > 0 ? ( $by_day[ $focus ] ?? array() ) : array() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </div>
                    </section>
                </div>
            </section>

            <section class="cral-cal__past" aria-label="<?php esc_attr_e( 'Eventi passati', 'g-event' ); ?>">
                <div class="cral-cal__upcoming-head cral-cal__past-head">
                    <h3 class="cral-cal__section-title cral-cal__past-title"><?php esc_html_e( 'Eventi passati', 'g-event' ); ?></h3>
                    <div class="cral-cal__carousel-nav" aria-label="<?php esc_attr_e( 'Navigazione eventi passati', 'g-event' ); ?>">
                        <button type="button" class="cral-cal__carousel-btn" data-cal-past-prev aria-label="<?php esc_attr_e( 'Precedenti', 'g-event' ); ?>">
                            <span aria-hidden="true">&#8249;</span>
                        </button>
                        <button type="button" class="cral-cal__carousel-btn" data-cal-past-next aria-label="<?php esc_attr_e( 'Successivi', 'g-event' ); ?>">
                            <span aria-hidden="true">&#8250;</span>
                        </button>
                    </div>
                </div>
                <div class="cral-cal__carousel" data-cal-past-carousel>
                    <div class="cral-cal__past-grid cral-cal__past-grid--carousel" data-cal-past>
                        <?php echo $this->render_past_product_grid( $past['events'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                </div>
            </section>

            <?php echo $this->render_modal(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

            <script type="application/json" class="cral-cal__events-json" data-cal-events-json><?php
                echo wp_json_encode(
                    array(
                        'byDay'    => $by_day,
                        'flat'     => array_values( $events ),
                        'focusDay' => $focus,
                    ),
                    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
                );
            ?></script>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Handler AJAX cambio mese.
     */
    public function ajax_mese() {
        check_ajax_referer( 'cral_calendario_mese', 'nonce' );

        $year  = isset( $_POST['year'] ) ? absint( $_POST['year'] ) : 0;
        $month = isset( $_POST['month'] ) ? absint( $_POST['month'] ) : 0;

        if ( $year < 1970 || $year > 2100 || $month < 1 || $month > 12 ) {
            wp_send_json_error( array( 'message' => 'Mese non valido.' ), 400 );
        }

        $events   = $this->get_events_for_month( $year, $month );
        $by_day   = $this->group_events_by_day( $events );
        $focus    = $this->resolve_focus_day( $year, $month, $by_day );
        $upcoming = $this->get_upcoming_events( 12 );

        wp_send_json_success(
            array(
                'year'             => $year,
                'month'            => $month,
                'monthLabel'       => $this->format_month_label( $year, $month ),
                'navHtml'          => $this->render_calendar_nav( $year, $month ),
                'calendarHtml'     => $this->render_calendar_grid( $year, $month, $by_day, $focus ),
                'dayTitleHtml'     => $this->render_day_panel_title( $year, $month, $focus ),
                'dayListHtml'      => $this->render_day_events_list( $focus > 0 ? ( $by_day[ $focus ] ?? array() ) : array() ),
                'upcomingHtml'     => $this->render_product_grid( $upcoming ),
                'focusDay'         => $focus,
                'eventsByDay'      => $by_day,
                'eventsFlat'       => array_values( $events ),
            )
        );
    }

    /**
     * @param int $year  Anno.
     * @param int $month Mese 1-12.
     * @return array<int, array<string, mixed>>
     */
    protected function get_events_for_month( $year, $month ) {
        $start = sprintf( '%04d-%02d-01 00:00:00', $year, $month );
        $last  = (int) wp_date( 't', strtotime( $start ) );
        $end   = sprintf( '%04d-%02d-%02d 23:59:59', $year, $month, $last );

        $query = new \WP_Query(
            array(
                'post_type'      => 'evento',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'meta_key'       => '_cral_evento_data',
                'orderby'        => 'meta_value',
                'order'          => 'ASC',
                'meta_query'     => array(
                    'relation' => 'AND',
                    array(
                        'key'     => '_cral_evento_data',
                        'value'   => array( $start, $end ),
                        'compare' => 'BETWEEN',
                        'type'    => 'DATETIME',
                    ),
                    array(
                        'key'     => '_cral_evento_stato',
                        'value'   => array( 'bozza', 'annullato', 'programmato' ),
                        'compare' => 'NOT IN',
                    ),
                ),
            )
        );

        $events = array();
        if ( $query->have_posts() ) {
            foreach ( $query->posts as $post ) {
                $events[ $post->ID ] = $this->format_event( $post->ID );
            }
        }
        wp_reset_postdata();

        return $events;
    }

    /**
     * @param int $post_id ID evento.
     * @return array<string, mixed>
     */
    protected function format_event( $post_id ) {
        $post_id  = absint( $post_id );
        $data_raw = (string) get_post_meta( $post_id, '_cral_evento_data', true );
        $ts       = $data_raw ? strtotime( $data_raw ) : 0;
        $day      = $ts ? (int) wp_date( 'j', $ts ) : 0;

        $thumb_id = get_post_thumbnail_id( $post_id );
        $thumb    = '';
        $thumb_md = '';
        if ( $thumb_id ) {
            $thumb    = (string) wp_get_attachment_image_url( $thumb_id, 'thumbnail' );
            $thumb_md = (string) wp_get_attachment_image_url( $thumb_id, 'medium_large' );
            if ( ! $thumb_md ) {
                $thumb_md = (string) wp_get_attachment_image_url( $thumb_id, 'medium' );
            }
            if ( ! $thumb_md ) {
                $thumb_md = $thumb;
            }
        }

        $terms     = get_the_terms( $post_id, 'categoria_evento' );
        $cat       = '';
        $cat_color = '#a7c957';
        $cat_text  = '#111827';
        if ( $terms && ! is_wp_error( $terms ) ) {
            $cat       = $terms[0]->name;
            $cat_color = CPT_Evento::get_categoria_colore( $terms[0] );
            $cat_text  = CPT_Evento::contrast_text_color( $cat_color );
        }
        $socio = $this->get_socio_stato_evento( $post_id );

        $posti_totali  = (int) get_post_meta( $post_id, '_cral_evento_posti_totali', true );
        $posti_residui = (int) get_post_meta( $post_id, '_cral_evento_posti_residui', true );
        $partecipanti  = max( 0, $posti_totali - $posti_residui );
        $stato_info    = $this->get_evento_stato_info( $post_id );

        return array(
            'id'                => $post_id,
            'title'             => get_the_title( $post_id ),
            'url'               => get_permalink( $post_id ),
            'excerpt'           => wp_trim_words( get_the_excerpt( $post_id ), 18, '…' ),
            'data_raw'          => $data_raw,
            'data'              => $ts ? wp_date( 'd/m/Y', $ts ) : '',
            'data_estesa'       => $this->dynamic()->evento_data_estesa( array( 'id' => $post_id ) ),
            'data_card'         => $ts ? $this->format_product_date( $ts ) : '',
            'ora'               => $ts ? wp_date( 'H:i', $ts ) : '',
            'luogo'             => (string) get_post_meta( $post_id, '_cral_evento_luogo', true ),
            'categoria'         => $cat,
            'categoria_colore'  => $cat_color,
            'categoria_testo'   => $cat_text,
            'day'               => $day,
            'thumb'             => $thumb,
            'thumb_md'          => $thumb_md,
            'thumb_html'        => $this->render_thumb_html( $post_id, 'cral-cal-thumb' ),
            'badge_html'        => $this->dynamic()->evento_badge( array( 'id' => $post_id ) ),
            'stato_label'       => $stato_info['label'],
            'stato_mod'         => $stato_info['mod'],
            'posti_totali'      => $posti_totali,
            'posti_residui'     => $posti_residui,
            'partecipanti'      => $partecipanti,
            'socio_stato'       => $socio['code'],
            'socio_stato_label' => $socio['label'],
        );
    }

    /**
     * Data scheda prodotto: "SETTEMBRE 5 · ore 20:00".
     *
     * @param int $ts Timestamp.
     * @return string
     */
    protected function format_product_date( $ts ) {
        $mesi = array(
            1  => 'GENNAIO',
            2  => 'FEBBRAIO',
            3  => 'MARZO',
            4  => 'APRILE',
            5  => 'MAGGIO',
            6  => 'GIUGNO',
            7  => 'LUGLIO',
            8  => 'AGOSTO',
            9  => 'SETTEMBRE',
            10 => 'OTTOBRE',
            11 => 'NOVEMBRE',
            12 => 'DICEMBRE',
        );

        $m = (int) wp_date( 'n', $ts );
        $d = (int) wp_date( 'j', $ts );
        $h = wp_date( 'H:i', $ts );

        return sprintf( '%s %d · ore %s', $mesi[ $m ] ?? '', $d, $h );
    }

    /**
     * Stato evento sintetico (label + modificatore CSS).
     *
     * @param int $event_id ID evento.
     * @return array{label: string, mod: string}
     */
    protected function get_evento_stato_info( $event_id ) {
        $stato         = (string) get_post_meta( $event_id, '_cral_evento_stato', true );
        $data_raw      = (string) get_post_meta( $event_id, '_cral_evento_data', true );
        $data_iscr_raw = (string) get_post_meta( $event_id, '_cral_evento_data_iscrizione', true );
        $data_ap_raw   = (string) get_post_meta( $event_id, '_cral_evento_data_apertura_iscrizioni', true );
        $posti_residui = (int) get_post_meta( $event_id, '_cral_evento_posti_residui', true );

        $now         = time();
        $ts_evento   = $data_raw ? strtotime( $data_raw ) : 0;
        $ts_scadenza = Evento_Stato::parse_iscrizione_ts( $data_iscr_raw, 'scadenza' );
        $ts_apertura = Evento_Stato::parse_iscrizione_ts( $data_ap_raw, 'apertura' );

        $is_annullato   = ( 'annullato' === $stato );
        $is_programmato = Evento_Stato::is_programmato( $event_id );
        $is_concluso    = ( ! $is_programmato && 'concluso' === $stato ) || ( ! $is_programmato && $ts_evento > 0 && $ts_evento < $now );
        $is_soldout     = ( ! $is_annullato && ! $is_programmato && ! $is_concluso && $posti_residui <= 0 );
        $is_chiuse      = ( ! $is_annullato && ! $is_programmato && ! $is_concluso && ! $is_soldout && $ts_scadenza > 0 && $ts_scadenza < $now );
        $is_non_ancora  = ( ! $is_annullato && ! $is_programmato && ! $is_concluso && ! $is_soldout && ! $is_chiuse && $ts_apertura > 0 && $ts_apertura > $now );

        if ( $is_annullato ) {
            return array( 'label' => 'Annullato', 'mod' => 'annullato' );
        }
        if ( $is_programmato ) {
            return array( 'label' => 'Programmato', 'mod' => 'programmato' );
        }
        if ( $is_concluso ) {
            return array( 'label' => 'Concluso', 'mod' => 'concluso' );
        }
        if ( $is_soldout ) {
            return array( 'label' => 'Sold out', 'mod' => 'soldout' );
        }
        if ( $is_chiuse ) {
            return array( 'label' => 'Iscrizioni chiuse', 'mod' => 'chiuse' );
        }
        if ( $is_non_ancora ) {
            return array( 'label' => 'Prossimamente', 'mod' => 'presto' );
        }

        return array( 'label' => 'Iscrizioni aperte', 'mod' => 'aperto' );
    }

    /**
     * Stato iscrizione del socio loggato per un evento.
     *
     * @param int $event_id ID evento.
     * @return array{code: string, label: string, pren_id?: int}
     */
    protected function get_socio_stato_evento( $event_id ) {
        $auth     = new Auth();
        $socio_id = $auth->get_current_socio();

        if ( ! $socio_id ) {
            return array(
                'code'  => 'non_loggato',
                'label' => __( 'Accedi per prenotarti', 'g-event' ),
            );
        }

        $active = get_posts(
            array(
                'post_type'      => 'prenotazione',
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'meta_query'     => array(
                    'relation' => 'AND',
                    array(
                        'key'   => '_cral_pren_socio_id',
                        'value' => (string) $socio_id,
                    ),
                    array(
                        'key'   => '_cral_pren_evento_id',
                        'value' => (string) $event_id,
                    ),
                    array(
                        'key'     => '_cral_pren_stato',
                        'value'   => array( 'confermata', 'in_attesa' ),
                        'compare' => 'IN',
                    ),
                ),
                'fields'         => 'ids',
            )
        );

        if ( ! empty( $active ) ) {
            $pren_id = (int) $active[0];
            $stato   = (string) get_post_meta( $pren_id, '_cral_pren_stato', true );
            $labels  = array(
                'in_attesa'  => __( 'Iscrizione in attesa di conferma', 'g-event' ),
                'confermata' => __( 'Sei iscritto — Confermata', 'g-event' ),
            );

            return array(
                'code'    => $stato,
                'label'   => $labels[ $stato ] ?? $stato,
                'pren_id' => $pren_id,
            );
        }

        return array(
            'code'  => 'non_prenotato',
            'label' => __( 'Non sei iscritto', 'g-event' ),
        );
    }

    /**
     * @param int    $post_id ID evento.
     * @param string $class   Classe CSS img.
     * @return string
     */
    protected function render_thumb_html( $post_id, $class = '' ) {
        $thumb = get_the_post_thumbnail(
            $post_id,
            'thumbnail',
            array(
                'class'   => $class,
                'loading' => 'lazy',
                'alt'     => get_the_title( $post_id ),
            )
        );

        if ( $thumb ) {
            return $thumb;
        }

        return '<span class="cral-cal-thumb cral-cal-thumb--placeholder" aria-hidden="true">&#127917;</span>';
    }

    /**
     * @param array<int, array<string, mixed>> $events Eventi indicizzati per ID.
     * @return array<int, array<int, array<string, mixed>>>
     */
    protected function group_events_by_day( $events ) {
        $by_day = array();
        foreach ( $events as $event ) {
            $day = (int) $event['day'];
            if ( $day <= 0 ) {
                continue;
            }
            if ( ! isset( $by_day[ $day ] ) ) {
                $by_day[ $day ] = array();
            }
            $by_day[ $day ][] = $event;
        }
        ksort( $by_day, SORT_NUMERIC );
        return $by_day;
    }

    /**
     * @param int $year  Anno.
     * @param int $month Mese 1-12.
     * @return string
     */
    protected function format_month_name( $year, $month ) {
        $ts = strtotime( sprintf( '%04d-%02d-01', $year, $month ) );
        if ( ! $ts ) {
            return '';
        }
        $mesi = array(
            1  => 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno',
            'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre',
        );
        return $mesi[ (int) wp_date( 'n', $ts ) ];
    }

    /**
     * @param int $year  Anno corrente.
     * @param int $month Mese corrente 1-12.
     * @param int $delta -1 precedente, +1 successivo.
     * @return array{year: int, month: int, name: string}
     */
    protected function adjacent_month( $year, $month, $delta ) {
        $month += $delta;
        $year  = (int) $year;

        if ( $month < 1 ) {
            $month = 12;
            $year--;
        } elseif ( $month > 12 ) {
            $month = 1;
            $year++;
        }

        return array(
            'year'  => $year,
            'month' => $month,
            'name'  => $this->format_month_name( $year, $month ),
        );
    }

    /**
     * @param int $year  Anno.
     * @param int $month Mese.
     * @return string
     */
    protected function format_month_label( $year, $month ) {
        return $this->format_month_name( $year, $month ) . ' ' . wp_date( 'Y', strtotime( sprintf( '%04d-%02d-01', $year, $month ) ) );
    }

    /**
     * Giorno da mostrare nel pannello: selezionato / oggi / prossimo con eventi.
     *
     * @param int                                      $year   Anno.
     * @param int                                      $month  Mese.
     * @param array<int, array<int, array<string,mixed>>> $by_day Eventi per giorno.
     * @return int
     */
    protected function resolve_focus_day( $year, $month, $by_day ) {
        if ( empty( $by_day ) ) {
            return 0;
        }

        $days = array_map( 'intval', array_keys( $by_day ) );
        sort( $days, SORT_NUMERIC );

        $today_y = (int) wp_date( 'Y' );
        $today_m = (int) wp_date( 'n' );
        $today_d = (int) wp_date( 'j' );

        if ( $year === $today_y && $month === $today_m ) {
            if ( ! empty( $by_day[ $today_d ] ) ) {
                return $today_d;
            }
            foreach ( $days as $day ) {
                if ( $day >= $today_d ) {
                    return $day;
                }
            }
            return 0;
        }

        if ( $year > $today_y || ( $year === $today_y && $month > $today_m ) ) {
            return (int) $days[0];
        }

        // Mese passato: primo giorno con eventi (solo consultazione).
        return (int) $days[0];
    }

    /**
     * Prossimi eventi da oggi in poi (max N).
     *
     * @param int $limit Numero massimo.
     * @return array<int, array<string, mixed>>
     */
    protected function get_upcoming_events( $limit = 4 ) {
        $limit = max( 1, min( 16, (int) $limit ) );
        $now   = wp_date( 'Y-m-d H:i:s' );

        $query = new \WP_Query(
            array(
                'post_type'      => 'evento',
                'post_status'    => 'publish',
                'posts_per_page' => $limit,
                'meta_key'       => '_cral_evento_data',
                'orderby'        => 'meta_value',
                'order'          => 'ASC',
                'meta_query'     => array(
                    'relation' => 'AND',
                    array(
                        'key'     => '_cral_evento_data',
                        'value'   => $now,
                        'compare' => '>=',
                        'type'    => 'DATETIME',
                    ),
                    array(
                        'key'     => '_cral_evento_stato',
                        'value'   => array( 'bozza', 'annullato', 'programmato' ),
                        'compare' => 'NOT IN',
                    ),
                ),
            )
        );

        $events = array();
        if ( $query->have_posts() ) {
            foreach ( $query->posts as $post ) {
                $events[] = $this->format_event( $post->ID );
            }
        }
        wp_reset_postdata();

        return $events;
    }

    /**
     * Eventi passati (da ieri all'indietro) per il carosello.
     *
     * @param int $limit  Numero massimo.
     * @param int $offset Offset.
     * @return array{events: array<int, array<string, mixed>>, has_more: bool, total: int}
     */
    protected function get_past_events( $limit = 48, $offset = 0 ) {
        $limit  = max( 1, min( 60, (int) $limit ) );
        $offset = max( 0, (int) $offset );
        // Prima di oggi 00:00 = da ieri all'indietro.
        $before = wp_date( 'Y-m-d' ) . ' 00:00:00';

        $query = new \WP_Query(
            array(
                'post_type'      => 'evento',
                'post_status'    => 'publish',
                'posts_per_page' => $limit,
                'offset'         => $offset,
                'meta_key'       => '_cral_evento_data',
                'orderby'        => 'meta_value',
                'order'          => 'DESC',
                'meta_query'     => array(
                    'relation' => 'AND',
                    array(
                        'key'     => '_cral_evento_data',
                        'value'   => $before,
                        'compare' => '<',
                        'type'    => 'DATETIME',
                    ),
                    array(
                        'key'     => '_cral_evento_stato',
                        'value'   => array( 'bozza', 'annullato', 'programmato' ),
                        'compare' => 'NOT IN',
                    ),
                ),
            )
        );

        $events = array();
        if ( $query->have_posts() ) {
            foreach ( $query->posts as $post ) {
                $events[] = $this->format_event( $post->ID );
            }
        }

        $total    = (int) $query->found_posts;
        $has_more = ( $offset + count( $events ) ) < $total;
        wp_reset_postdata();

        return array(
            'events'   => $events,
            'has_more' => $has_more,
            'total'    => $total,
        );
    }

    /**
     * Titolo pannello giorno.
     *
     * @param int $year  Anno.
     * @param int $month Mese.
     * @param int $day   Giorno.
     * @return string
     */
    protected function render_day_panel_title( $year, $month, $day ) {
        if ( $day <= 0 ) {
            return esc_html__( 'Nessun evento questo mese', 'g-event' );
        }

        $ts = strtotime( sprintf( '%04d-%02d-%02d', $year, $month, $day ) );
        if ( ! $ts ) {
            return esc_html__( 'Eventi', 'g-event' );
        }

        $giorni = array(
            1 => 'Lunedì',
            2 => 'Martedì',
            3 => 'Mercoledì',
            4 => 'Giovedì',
            5 => 'Venerdì',
            6 => 'Sabato',
            7 => 'Domenica',
        );
        $mesi = array(
            1  => 'gennaio',
            2  => 'febbraio',
            3  => 'marzo',
            4  => 'aprile',
            5  => 'maggio',
            6  => 'giugno',
            7  => 'luglio',
            8  => 'agosto',
            9  => 'settembre',
            10 => 'ottobre',
            11 => 'novembre',
            12 => 'dicembre',
        );

        $dow  = (int) wp_date( 'N', $ts );
        $d    = (int) wp_date( 'j', $ts );
        $m    = (int) wp_date( 'n', $ts );
        $label = sprintf( '%s %d %s', $giorni[ $dow ] ?? '', $d, $mesi[ $m ] ?? '' );

        $today_y = (int) wp_date( 'Y' );
        $today_m = (int) wp_date( 'n' );
        $today_d = (int) wp_date( 'j' );
        $prefix  = ( $year === $today_y && $month === $today_m && $day === $today_d )
            ? __( 'Oggi', 'g-event' )
            : __( 'Eventi', 'g-event' );

        return sprintf(
            '%s <span class="cral-cal__list-title-month">%s</span>',
            esc_html( $prefix ),
            esc_html( $label )
        );
    }

    /**
     * Lista eventi del giorno (pannello laterale) — link alla scheda.
     *
     * @param array<int, array<string, mixed>> $events Eventi del giorno.
     * @return string
     */
    protected function render_day_events_list( $events ) {
        if ( empty( $events ) ) {
            return '<p class="cral-cal__empty">' . esc_html__( 'Nessun evento in questo giorno.', 'g-event' ) . '</p>';
        }

        ob_start();
        foreach ( $events as $event ) {
            $img     = $event['thumb_md'] ?: $event['thumb'];
            $cat_bg  = $event['categoria_colore'] ?? '#a7c957';
            $cat_fg  = $event['categoria_testo'] ?? '#111827';
            $mod     = $event['stato_mod'] ?? 'aperto';
            $accent  = '--cral-cat:' . esc_attr( $cat_bg ) . ';';
            ?>
            <article class="cral-cal-list__item cral-cal-list__item--day" data-event-id="<?php echo esc_attr( (string) $event['id'] ); ?>" style="<?php echo esc_attr( $accent ); ?>">
                <a class="cral-cal-list__btn" href="<?php echo esc_url( $event['url'] ); ?>">
                    <span class="cral-cal-list__thumb-wrap">
                        <?php if ( $img ) : ?>
                        <img src="<?php echo esc_url( $img ); ?>" alt="" class="cral-cal-thumb" loading="lazy" />
                        <?php else : ?>
                        <span class="cral-cal-thumb cral-cal-thumb--placeholder" aria-hidden="true">&#127917;</span>
                        <?php endif; ?>
                    </span>
                    <span class="cral-cal-list__body">
                        <?php if ( ! empty( $event['data_card'] ) || ! empty( $event['ora'] ) ) : ?>
                        <span class="cral-cal-list__when">
                            <?php echo esc_html( $event['data_card'] ?: ( $event['ora'] ?? '' ) ); ?>
                        </span>
                        <?php endif; ?>
                        <span class="cral-cal-list__title"><?php echo esc_html( $event['title'] ); ?></span>
                        <?php if ( $event['luogo'] ) : ?>
                        <span class="cral-cal-list__meta">
                            <span class="cral-cal-list__meta-luogo"><?php echo esc_html( $event['luogo'] ); ?></span>
                        </span>
                        <?php endif; ?>
                        <span class="cral-cal-list__meta-row">
                            <?php if ( ! empty( $event['categoria'] ) ) : ?>
                            <span class="cral-cal-list__cat" style="background:<?php echo esc_attr( $cat_bg ); ?>;color:<?php echo esc_attr( $cat_fg ); ?>">
                                <?php echo esc_html( $event['categoria'] ); ?>
                            </span>
                            <?php endif; ?>
                            <span class="cral-cal-list__stato cral-cal-product__stato cral-cal-product__stato--<?php echo esc_attr( $mod ); ?>">
                                <?php echo esc_html( $event['stato_label'] ?? '' ); ?>
                            </span>
                            <span class="cral-cal-list__parti">
                                <?php
                                printf(
                                    esc_html__( '%1$d / %2$d posti', 'g-event' ),
                                    (int) ( $event['partecipanti'] ?? 0 ),
                                    (int) ( $event['posti_totali'] ?? 0 )
                                );
                                ?>
                            </span>
                        </span>
                    </span>
                </a>
            </article>
            <?php
        }
        return ob_get_clean();
    }

    /**
     * Griglia / carosello prossimi eventi.
     *
     * @param array<int, array<string, mixed>> $events Eventi.
     * @return string
     */
    protected function render_product_grid( $events ) {
        if ( empty( $events ) ) {
            return '<p class="cral-cal__empty cral-cal__empty--grid">' . esc_html__( 'Nessun prossimo evento in programma.', 'g-event' ) . '</p>';
        }

        $events = array_slice( array_values( $events ), 0, 12 );

        ob_start();
        foreach ( $events as $event ) {
            $img   = $event['thumb_md'] ?: $event['thumb'];
            $mod       = $event['stato_mod'] ?? 'aperto';
            $img_style = $img ? 'background-image:url(\'' . esc_url( $img ) . '\');' : '';
            $cat_bg    = $event['categoria_colore'] ?? '#a7c957';
            $cat_fg    = $event['categoria_testo'] ?? '#111827';
            $card_accent = '--cral-cat:' . esc_attr( $cat_bg ) . ';';
            ?>
            <a class="cral-cal-product"
               href="<?php echo esc_url( $event['url'] ); ?>"
               data-event-id="<?php echo esc_attr( (string) $event['id'] ); ?>"
               style="<?php echo esc_attr( $card_accent ); ?>">
                <span class="cral-cal-product__media<?php echo $img ? '' : ' cral-cal-product__media--ph'; ?>"<?php echo $img_style ? ' style="' . esc_attr( $img_style ) . '"' : ''; ?>>
                    <span class="cral-cal-product__shine" aria-hidden="true"></span>
                    <?php if ( ! empty( $event['categoria'] ) ) : ?>
                    <span class="cral-cal-product__cat" style="background:<?php echo esc_attr( $cat_bg ); ?>;color:<?php echo esc_attr( $cat_fg ); ?>">
                        <?php echo esc_html( $event['categoria'] ); ?>
                    </span>
                    <?php endif; ?>
                </span>
                <span class="cral-cal-product__body">
                    <?php if ( ! empty( $event['data_card'] ) ) : ?>
                    <span class="cral-cal-product__when">
                        <time datetime="<?php echo esc_attr( $event['data_raw'] ); ?>"><?php echo esc_html( $event['data_card'] ); ?></time>
                    </span>
                    <?php endif; ?>
                    <span class="cral-cal-product__title"><?php echo esc_html( $event['title'] ); ?></span>
                    <?php if ( $event['luogo'] ) : ?>
                    <span class="cral-cal-product__place"><?php echo esc_html( $event['luogo'] ); ?></span>
                    <?php endif; ?>
                    <span class="cral-cal-product__meta-row">
                        <span class="cral-cal-product__stato cral-cal-product__stato--<?php echo esc_attr( $mod ); ?>">
                            <?php echo esc_html( $event['stato_label'] ?? '' ); ?>
                        </span>
                        <span class="cral-cal-product__parti">
                            <?php
                            printf(
                                /* translators: 1: iscritti, 2: posti totali */
                                esc_html__( '%1$d / %2$d posti', 'g-event' ),
                                (int) ( $event['partecipanti'] ?? 0 ),
                                (int) ( $event['posti_totali'] ?? 0 )
                            );
                            ?>
                        </span>
                    </span>
                    <span class="cral-cal-product__cta">
                        <?php esc_html_e( 'Scopri di più', 'g-event' ); ?>
                        <span class="cral-cal-product__cta-arrow" aria-hidden="true">→</span>
                    </span>
                </span>
            </a>
            <?php
        }
        return ob_get_clean();
    }

    /**
     * Griglia eventi passati (schede verticali).
     *
     * @param array<int, array<string, mixed>> $events     Eventi.
     * @param bool                             $with_empty Mostra empty se vuoto.
     * @return string
     */
    protected function render_past_product_grid( $events, $with_empty = true ) {
        if ( empty( $events ) ) {
            return $with_empty
                ? '<p class="cral-cal__empty cral-cal__empty--grid">' . esc_html__( 'Nessun evento passato.', 'g-event' ) . '</p>'
                : '';
        }

        ob_start();
        foreach ( $events as $event ) {
            $img         = $event['thumb_md'] ?: $event['thumb'];
            $img_style   = $img ? 'background-image:url(\'' . esc_url( $img ) . '\');' : '';
            $cat_bg      = $event['categoria_colore'] ?? '#a7c957';
            $cat_fg      = $event['categoria_testo'] ?? '#111827';
            $card_accent = '--cral-cat:' . esc_attr( $cat_bg ) . ';';
            ?>
            <a class="cral-cal-product cral-cal-product--past"
               href="<?php echo esc_url( $event['url'] ); ?>"
               data-event-id="<?php echo esc_attr( (string) $event['id'] ); ?>"
               style="<?php echo esc_attr( $card_accent ); ?>">
                <span class="cral-cal-product__media<?php echo $img ? '' : ' cral-cal-product__media--ph'; ?>"<?php echo $img_style ? ' style="' . esc_attr( $img_style ) . '"' : ''; ?>>
                    <span class="cral-cal-product__shine" aria-hidden="true"></span>
                    <?php if ( ! empty( $event['categoria'] ) ) : ?>
                    <span class="cral-cal-product__cat" style="background:<?php echo esc_attr( $cat_bg ); ?>;color:<?php echo esc_attr( $cat_fg ); ?>">
                        <?php echo esc_html( $event['categoria'] ); ?>
                    </span>
                    <?php endif; ?>
                    <?php if ( ! empty( $event['data_card'] ) ) : ?>
                    <span class="cral-cal-product__date-badge">
                        <time datetime="<?php echo esc_attr( $event['data_raw'] ); ?>"><?php echo esc_html( $event['data_card'] ); ?></time>
                    </span>
                    <?php endif; ?>
                </span>
                <span class="cral-cal-product__body">
                    <span class="cral-cal-product__title"><?php echo esc_html( $event['title'] ); ?></span>
                    <?php if ( $event['luogo'] ) : ?>
                    <span class="cral-cal-product__place"><?php echo esc_html( $event['luogo'] ); ?></span>
                    <?php endif; ?>
                    <span class="cral-cal-product__meta-row">
                        <span class="cral-cal-product__stato cral-cal-product__stato--concluso">
                            <?php esc_html_e( 'Concluso', 'g-event' ); ?>
                        </span>
                        <span class="cral-cal-product__parti">
                            <?php
                            printf(
                                esc_html__( '%1$d / %2$d posti', 'g-event' ),
                                (int) ( $event['partecipanti'] ?? 0 ),
                                (int) ( $event['posti_totali'] ?? 0 )
                            );
                            ?>
                        </span>
                    </span>
                    <span class="cral-cal-product__cta">
                        <?php esc_html_e( 'Vedi evento', 'g-event' ); ?>
                        <span class="cral-cal-product__cta-arrow" aria-hidden="true">→</span>
                    </span>
                </span>
            </a>
            <?php
        }
        return ob_get_clean();
    }

    /**
     * Titolo pannello lista eventi con nome mese.
     *
     * @param int $year  Anno.
     * @param int $month Mese.
     * @return string
     */
    protected function render_list_title( $year, $month ) {
        $name = $this->format_month_name( $year, $month );

        return sprintf(
            '%s <span class="cral-cal__list-title-month">%s</span>',
            esc_html__( 'Eventi del mese di', 'g-event' ),
            esc_html( $name )
        );
    }

    /**
     * @param int $year  Anno.
     * @param int $month Mese.
     * @return string
     */
    protected function render_calendar_nav( $year, $month ) {
        $prev     = $this->adjacent_month( $year, $month, -1 );
        $next     = $this->adjacent_month( $year, $month, 1 );
        $today_y  = (int) wp_date( 'Y' );
        $today_m  = (int) wp_date( 'n' );
        $is_past  = ( $year < $today_y ) || ( $year === $today_y && $month < $today_m );

        ob_start();
        ?>
        <div class="cral-cal__nav-wrap" data-cal-nav-wrap>
            <div class="cral-cal__nav">
                <button type="button" class="cral-cal__nav-btn cral-cal__nav-btn--prev" data-cal-prev
                        aria-label="<?php echo esc_attr( sprintf( __( 'Vai a %s', 'g-event' ), $prev['name'] ) ); ?>">
                    <span class="cral-cal__nav-btn-arrow" aria-hidden="true">&#8249;</span>
                    <span class="cral-cal__nav-btn-label"><?php echo esc_html( $prev['name'] ); ?></span>
                </button>
                <h2 class="cral-cal__month-label" data-cal-month-label><?php echo esc_html( $this->format_month_label( $year, $month ) ); ?></h2>
                <button type="button" class="cral-cal__nav-btn cral-cal__nav-btn--next" data-cal-next
                        aria-label="<?php echo esc_attr( sprintf( __( 'Vai a %s', 'g-event' ), $next['name'] ) ); ?>">
                    <span class="cral-cal__nav-btn-label"><?php echo esc_html( $next['name'] ); ?></span>
                    <span class="cral-cal__nav-btn-arrow" aria-hidden="true">&#8250;</span>
                </button>
            </div>
            <?php if ( $is_past ) : ?>
            <div class="cral-cal__nav-today">
                <button type="button" class="cral-cal__today-btn" data-cal-today>
                    <?php esc_html_e( 'Torna ad oggi', 'g-event' ); ?>
                </button>
            </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * @param int                                      $year   Anno.
     * @param int                                      $month  Mese.
     * @param array<int, array<int, array<string,mixed>>> $by_day Eventi per giorno.
     * @param int                                      $selected_day Giorno selezionato.
     * @return string
     */
    protected function render_calendar_grid( $year, $month, $by_day, $selected_day = 0 ) {
        $first_ts    = strtotime( sprintf( '%04d-%02d-01', $year, $month ) );
        $days_in_mon = (int) wp_date( 't', $first_ts );
        $start_dow   = (int) wp_date( 'N', $first_ts ); // 1 = lun … 7 = dom
        $offset      = $start_dow - 1;

        $today_y = (int) wp_date( 'Y' );
        $today_m = (int) wp_date( 'n' );
        $today_d = (int) wp_date( 'j' );

        $weekdays = array( 'Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab', 'Dom' );

        ob_start();
        ?>
        <div class="cral-cal__weekdays" aria-hidden="true">
            <?php foreach ( $weekdays as $wd ) : ?>
            <span class="cral-cal__weekday"><?php echo esc_html( $wd ); ?></span>
            <?php endforeach; ?>
        </div>
        <div class="cral-cal__grid" role="grid" aria-label="<?php echo esc_attr( $this->format_month_label( $year, $month ) ); ?>">
            <?php
            for ( $i = 0; $i < $offset; $i++ ) {
                echo '<span class="cral-cal__cell cral-cal__cell--empty" role="gridcell"></span>';
            }

            for ( $day = 1; $day <= $days_in_mon; $day++ ) {
                $is_today    = ( $year === $today_y && $month === $today_m && $day === $today_d );
                $has_events  = ! empty( $by_day[ $day ] );
                $is_selected = ( $selected_day > 0 && $day === $selected_day );

                $classes = array( 'cral-cal__cell', 'cral-cal__cell--day' );
                if ( $is_today ) {
                    $classes[] = 'is-today';
                }
                if ( $has_events ) {
                    $classes[] = 'has-events';
                }
                if ( $is_selected ) {
                    $classes[] = 'is-selected';
                    $classes[] = 'is-active-day';
                }

                $events = $has_events ? $by_day[ $day ] : array();
                ?>
                <div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
                     role="gridcell"
                     data-cal-day="<?php echo esc_attr( (string) $day ); ?>"
                     tabindex="<?php echo $has_events ? '0' : '-1'; ?>"
                     aria-label="<?php echo esc_attr( sprintf( '%d %s', $day, $this->format_month_label( $year, $month ) ) ); ?>">
                    <span class="cral-cal__day-num"><?php echo esc_html( (string) $day ); ?></span>
                    <?php if ( $has_events ) : ?>
                    <span class="cral-cal__dots" aria-hidden="true">
                        <?php
                        $max_dots = min( 3, count( $events ) );
                        for ( $d = 0; $d < $max_dots; $d++ ) {
                            echo '<span class="cral-cal__dot"></span>';
                        }
                        ?>
                    </span>
                    <div class="cral-cal__event-cards">
                        <?php foreach ( $events as $event ) : ?>
                        <div class="cral-cal__event-card"
                             role="button"
                             tabindex="0"
                             data-event-id="<?php echo esc_attr( (string) $event['id'] ); ?>"
                             aria-label="<?php echo esc_attr( $event['title'] ); ?>">
                            <span class="cral-cal__event-card-thumb">
                                <?php echo $event['thumb_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </span>
                            <span class="cral-cal__event-card-title"><?php echo esc_html( $event['title'] ); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php
            }
            ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * @param array<int, array<string, mixed>> $events Eventi.
     * @param int                              $highlight_day Giorno evidenziato.
     * @return string
     */
    protected function render_events_list( $events ) {
        if ( empty( $events ) ) {
            return '<p class="cral-cal__empty">' . esc_html__( 'Nessun evento in questo mese.', 'g-event' ) . '</p>';
        }

        ob_start();
        foreach ( $events as $event ) {
            ?>
            <article class="cral-cal-list__item"
                     data-cal-list-day="<?php echo esc_attr( (string) $event['day'] ); ?>"
                     data-event-id="<?php echo esc_attr( (string) $event['id'] ); ?>">
                <button type="button" class="cral-cal-list__btn" data-cal-open-day="<?php echo esc_attr( (string) $event['day'] ); ?>">
                    <span class="cral-cal-list__thumb-wrap">
                        <?php echo $event['thumb_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </span>
                    <span class="cral-cal-list__body">
                        <span class="cral-cal-list__title"><?php echo esc_html( $event['title'] ); ?></span>
                        <?php if ( $event['data_estesa'] || $event['luogo'] ) : ?>
                        <span class="cral-cal-list__meta">
                            <?php if ( $event['data_estesa'] ) : ?>
                            <span class="cral-cal-list__meta-date"><?php echo esc_html( $event['data_estesa'] ); ?></span>
                            <?php endif; ?>
                            <?php if ( $event['data_estesa'] && $event['luogo'] ) : ?>
                            <span class="cral-cal-list__meta-sep" aria-hidden="true">·</span>
                            <?php endif; ?>
                            <?php if ( $event['luogo'] ) : ?>
                            <span class="cral-cal-list__meta-luogo"><?php echo esc_html( $event['luogo'] ); ?></span>
                            <?php endif; ?>
                        </span>
                        <?php endif; ?>
                        <?php if ( $event['badge_html'] ) : ?>
                        <span class="cral-cal-list__badge"><?php echo $event['badge_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                        <?php endif; ?>
                    </span>
                </button>
            </article>
            <?php
        }
        return ob_get_clean();
    }

    /**
     * @return string
     */
    protected function render_modal() {
        ob_start();
        ?>
        <div class="cral-cal-modal" data-cal-modal hidden>
            <div class="cral-cal-modal__overlay" data-cal-modal-close tabindex="-1"></div>
            <div class="cral-cal-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="cral-cal-modal-day-title">
                <button type="button" class="cral-cal-modal__close" data-cal-modal-close aria-label="<?php esc_attr_e( 'Chiudi', 'g-event' ); ?>">
                    <?php esc_html_e( 'Chiudi', 'g-event' ); ?> <span aria-hidden="true">&times;</span>
                </button>
                <div class="cral-cal-modal__header">
                    <h3 class="cral-cal-modal__title" id="cral-cal-modal-day-title" data-cal-modal-day-title></h3>
                </div>
                <div class="cral-cal-modal__day-list" data-cal-modal-day-list></div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

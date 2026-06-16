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
     * Carica CSS/JS del calendario (anche in render shortcode, post wp_head).
     */
    private function enqueue_assets() {
        if ( self::$assets_enqueued ) {
            return;
        }
        self::$assets_enqueued = true;

        $base = plugin_dir_url( dirname( __FILE__ ) );
        $ver  = '1.2.1';

        wp_enqueue_style( 'g-event-frontend', $base . 'assets/css/frontend.css', array(), '1.0.7' );
        wp_enqueue_style( 'g-event-scheda', $base . 'assets/css/scheda-evento.css', array(), '1.0.0' );
        wp_enqueue_style(
            'g-event-calendario',
            $base . 'assets/css/calendario-eventi.css',
            array( 'g-event-frontend', 'g-event-scheda' ),
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
                    'prev'       => 'Mese precedente',
                    'next'       => 'Mese successivo',
                    'loading'    => 'Caricamento…',
                    'noEvents'   => 'Nessun evento in questo mese.',
                    'goEvent'    => 'Vai all\'evento',
                    'close'      => 'Chiudi',
                    'prossimo'   => 'Prossimo evento',
                    'listaMese'  => 'Eventi del mese di',
                    'oggi'       => 'Oggi',
                    'eventiGiorno' => 'Eventi del giorno',
                    'nessunEventoGiorno' => 'Nessun evento in questo giorno.',
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
        $uid    = 'cral-cal-' . wp_rand( 1000, 9999 );

        ob_start();
        ?>
        <div class="cral-cal" id="<?php echo esc_attr( $uid ); ?>"
             data-year="<?php echo esc_attr( (string) $year ); ?>"
             data-month="<?php echo esc_attr( (string) $month ); ?>">

            <div class="cral-cal__layout">
                <section class="cral-cal__calendar-panel" aria-label="<?php esc_attr_e( 'Calendario eventi', 'g-event' ); ?>">
                    <?php echo $this->render_calendar_nav( $year, $month ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <div class="cral-cal__grid-wrap" data-cal-grid>
                        <?php echo $this->render_calendar_grid( $year, $month, $by_day ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                </section>

                <section class="cral-cal__list-panel" aria-label="<?php esc_attr_e( 'Elenco eventi del mese', 'g-event' ); ?>">
                    <h3 class="cral-cal__list-title" data-cal-list-title><?php echo $this->render_list_title( $year, $month ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></h3>
                    <div class="cral-cal__list" data-cal-list>
                        <?php echo $this->render_events_list( $events ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                </section>
            </div>

            <?php echo $this->render_modal(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

            <script type="application/json" class="cral-cal__events-json" data-cal-events-json><?php
                echo wp_json_encode(
                    array(
                        'byDay' => $by_day,
                        'flat'  => array_values( $events ),
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

        $events = $this->get_events_for_month( $year, $month );
        $by_day = $this->group_events_by_day( $events );

        wp_send_json_success(
            array(
                'year'          => $year,
                'month'         => $month,
                'monthLabel'    => $this->format_month_label( $year, $month ),
                'navHtml'       => $this->render_calendar_nav( $year, $month ),
                'calendarHtml'  => $this->render_calendar_grid( $year, $month, $by_day ),
                'listHtml'      => $this->render_events_list( $events ),
                'listTitleHtml' => $this->render_list_title( $year, $month ),
                'eventsByDay'   => $by_day,
                'eventsFlat'    => array_values( $events ),
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
        if ( $thumb_id ) {
            $thumb = (string) wp_get_attachment_image_url( $thumb_id, 'thumbnail' );
        }

        $terms = get_the_terms( $post_id, 'categoria_evento' );
        $cat   = ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->name : '';
        $socio = $this->get_socio_stato_evento( $post_id );

        return array(
            'id'                => $post_id,
            'title'             => get_the_title( $post_id ),
            'url'               => get_permalink( $post_id ),
            'excerpt'           => wp_trim_words( get_the_excerpt( $post_id ), 20, '…' ),
            'data_raw'          => $data_raw,
            'data'              => $ts ? wp_date( 'd/m/Y', $ts ) : '',
            'data_estesa'       => $this->dynamic()->evento_data_estesa( array( 'id' => $post_id ) ),
            'ora'               => $ts ? wp_date( 'H:i', $ts ) : '',
            'luogo'             => (string) get_post_meta( $post_id, '_cral_evento_luogo', true ),
            'categoria'         => $cat,
            'day'               => $day,
            'thumb'             => $thumb,
            'thumb_html'        => $this->render_thumb_html( $post_id, 'cral-cal-thumb' ),
            'badge_html'        => $this->dynamic()->evento_badge( array( 'id' => $post_id ) ),
            'socio_stato'       => $socio['code'],
            'socio_stato_label' => $socio['label'],
        );
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
        $prev = $this->adjacent_month( $year, $month, -1 );
        $next = $this->adjacent_month( $year, $month, 1 );

        ob_start();
        ?>
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

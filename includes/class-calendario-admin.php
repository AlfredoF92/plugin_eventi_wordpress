<?php
/**
 * Calendario eventi in admin Plugin CRAL BCC.
 *
 * @package GEvent
 */

namespace GEvent;

/**
 * Pagina admin Calendario con griglia mensile e lista eventi.
 */
class Calendario_Admin extends Calendario_Eventi {

    /**
     * @var bool
     */
    private static $assets_enqueued = false;

    /**
     * Registra menu, asset e AJAX admin.
     */
    public function init_admin() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'wp_ajax_cral_calendario_admin_mese', array( $this, 'ajax_admin_mese' ) );
    }

    /**
     * Voce menu Calendario.
     */
    public function register_menu() {
        add_submenu_page(
            'g-event',
            __( 'Calendario', 'g-event' ),
            __( 'Calendario', 'g-event' ),
            'manage_options',
            'g-event-calendario',
            array( $this, 'render_admin_page' )
        );
    }

    /**
     * Asset CSS/JS caricati tramite Admin::enqueue_scripts.
     * Questo metodo è mantenuto per compatibilità ma non fa nulla.
     */
    public function enqueue_admin_assets( $hook ) {
        // enqueue gestito in Admin::enqueue_scripts
    }

    /**
     * Pagina admin calendario.
     */
    public function render_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Accesso negato.', 'g-event' ) );
        }

        $year  = isset( $_GET['anno'] ) ? absint( $_GET['anno'] ) : (int) wp_date( 'Y' ); // phpcs:ignore WordPress.Security.NonceVerification
        $month = isset( $_GET['mese'] ) ? absint( $_GET['mese'] ) : (int) wp_date( 'n' ); // phpcs:ignore WordPress.Security.NonceVerification

        $year  = max( 1970, min( 2100, $year ) );
        $month = max( 1, min( 12, $month ) );

        $events = $this->get_events_for_month_admin( $year, $month );
        $by_day = $this->group_events_by_day( $events );
        $uid    = 'cral-cal-admin-' . wp_rand( 1000, 9999 );
        $base   = plugin_dir_url( dirname( __FILE__ ) );
        $ver    = '1.3.1';
        $nonce  = wp_create_nonce( 'cral_calendario_admin_mese' );

        $add_new_url = admin_url( 'post-new.php?post_type=evento' );
        ?>
        <link rel="stylesheet" href="<?php echo esc_url( $base . 'assets/css/scheda-evento.css?ver=' . $ver ); ?>">
        <link rel="stylesheet" href="<?php echo esc_url( $base . 'assets/css/calendario-eventi.css?ver=' . $ver ); ?>">

        <div class="wrap cral-cal-admin-wrap">

            <div class="cral-cal-admin-toolbar">
                <h2 class="cral-cal-admin-toolbar__title"><?php esc_html_e( 'Calendario eventi', 'g-event' ); ?></h2>
                <a href="<?php echo esc_url( $add_new_url ); ?>" class="cral-btn-add-evento">
                    <span class="cral-add-icon" aria-hidden="true">+</span>
                    <?php esc_html_e( 'Aggiungi nuovo evento', 'g-event' ); ?>
                </a>
            </div>

            <div class="cral-cal cral-cal--admin" id="<?php echo esc_attr( $uid ); ?>"
                 data-year="<?php echo esc_attr( (string) $year ); ?>"
                 data-month="<?php echo esc_attr( (string) $month ); ?>">

                <div class="cral-cal__layout">
                    <section class="cral-cal__calendar-panel" aria-label="<?php esc_attr_e( 'Calendario eventi', 'g-event' ); ?>">
                        <?php echo $this->render_admin_calendar_nav( $year, $month ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <div class="cral-cal__grid-wrap" data-cal-grid>
                            <?php echo $this->render_calendar_grid( $year, $month, $by_day ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </div>
                    </section>

                    <section class="cral-cal__list-panel" aria-label="<?php esc_attr_e( 'Elenco eventi del mese', 'g-event' ); ?>">
                        <h3 class="cral-cal__list-title" data-cal-list-title><?php echo $this->render_list_title( $year, $month ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></h3>
                        <div class="cral-cal__list" data-cal-list>
                            <?php echo $this->render_admin_events_list( $events ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </div>
                    </section>
                </div>

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
        </div>

        <script>
        window.cralCalendarioAdmin = {
            ajaxUrl: <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>,
            nonce: <?php echo wp_json_encode( $nonce ); ?>
        };
        </script>
        <script src="<?php echo esc_url( $base . 'assets/js/calendario-admin.js?ver=' . $ver ); ?>"></script>
        <?php
    }

    /**
     * AJAX cambio mese admin.
     */
    public function ajax_admin_mese() {
        check_ajax_referer( 'cral_calendario_admin_mese', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permessi insufficienti.' ), 403 );
        }

        $year  = isset( $_POST['year'] ) ? absint( $_POST['year'] ) : 0;
        $month = isset( $_POST['month'] ) ? absint( $_POST['month'] ) : 0;

        if ( $year < 1970 || $year > 2100 || $month < 1 || $month > 12 ) {
            wp_send_json_error( array( 'message' => 'Mese non valido.' ), 400 );
        }

        $events = $this->get_events_for_month_admin( $year, $month );
        $by_day = $this->group_events_by_day( $events );

        wp_send_json_success(
            array(
                'year'          => $year,
                'month'         => $month,
                'monthLabel'    => $this->format_month_label( $year, $month ),
                'navHtml'       => $this->render_admin_calendar_nav( $year, $month ),
                'calendarHtml'  => $this->render_calendar_grid( $year, $month, $by_day ),
                'listHtml'      => $this->render_admin_events_list( $events ),
                'listTitleHtml' => $this->render_list_title( $year, $month ),
                'eventsByDay'   => $by_day,
                'eventsFlat'    => array_values( $events ),
            )
        );
    }

    /**
     * Eventi del mese per admin (include bozze e programmati).
     *
     * @param int $year  Anno.
     * @param int $month Mese 1-12.
     * @return array<int, array<string, mixed>>
     */
    protected function get_events_for_month_admin( $year, $month ) {
        $start = sprintf( '%04d-%02d-01 00:00:00', $year, $month );
        $last  = (int) wp_date( 't', strtotime( $start ) );
        $end   = sprintf( '%04d-%02d-%02d 23:59:59', $year, $month, $last );

        $query = new \WP_Query(
            array(
                'post_type'      => 'evento',
                'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private' ),
                'posts_per_page' => -1,
                'meta_key'       => '_cral_evento_data',
                'orderby'        => 'meta_value',
                'order'          => 'ASC',
                'meta_query'     => array(
                    array(
                        'key'     => '_cral_evento_data',
                        'value'   => array( $start, $end ),
                        'compare' => 'BETWEEN',
                        'type'    => 'DATETIME',
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
     * Navigazione admin con select mese/anno.
     *
     * @param int $year  Anno.
     * @param int $month Mese.
     * @return string
     */
    protected function render_admin_calendar_nav( $year, $month ) {
        $prev = $this->adjacent_month( $year, $month, -1 );
        $next = $this->adjacent_month( $year, $month, 1 );

        $mesi = array(
            1  => 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno',
            'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre',
        );

        $year_min = (int) wp_date( 'Y' ) - 5;
        $year_max = (int) wp_date( 'Y' ) + 5;

        ob_start();
        ?>
        <div class="cral-cal__nav cral-cal__nav--admin">
            <button type="button" class="cral-cal__nav-btn cral-cal__nav-btn--prev" data-cal-prev
                    aria-label="<?php echo esc_attr( sprintf( __( 'Vai a %s', 'g-event' ), $prev['name'] ) ); ?>">
                <span class="cral-cal__nav-btn-arrow" aria-hidden="true">&#8249;</span>
                <span class="cral-cal__nav-btn-label"><?php echo esc_html( $prev['name'] ); ?></span>
            </button>

            <div class="cral-cal__nav-selects">
                <label class="cral-cal__nav-select-wrap">
                    <span class="screen-reader-text"><?php esc_html_e( 'Mese', 'g-event' ); ?></span>
                    <select class="cral-cal__nav-select" data-cal-month-select>
                        <?php foreach ( $mesi as $num => $nome ) : ?>
                        <option value="<?php echo esc_attr( (string) $num ); ?>" <?php selected( $month, $num ); ?>>
                            <?php echo esc_html( $nome ); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="cral-cal__nav-select-wrap">
                    <span class="screen-reader-text"><?php esc_html_e( 'Anno', 'g-event' ); ?></span>
                    <select class="cral-cal__nav-select" data-cal-year-select>
                        <?php for ( $y = $year_max; $y >= $year_min; $y-- ) : ?>
                        <option value="<?php echo esc_attr( (string) $y ); ?>" <?php selected( $year, $y ); ?>>
                            <?php echo esc_html( (string) $y ); ?>
                        </option>
                        <?php endfor; ?>
                    </select>
                </label>
            </div>

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
     * Lista eventi admin con azioni.
     *
     * @param array<int, array<string, mixed>> $events Eventi.
     * @return string
     */
    protected function render_admin_events_list( $events ) {
        if ( empty( $events ) ) {
            return '<p class="cral-cal__empty">' . esc_html__( 'Nessun evento in questo mese.', 'g-event' ) . '</p>';
        }

        ob_start();
        foreach ( $events as $event ) {
            $edit_url = get_edit_post_link( $event['id'], 'raw' );
            $iscr_url = add_query_arg(
                array(
                    'page'      => 'g-event-prenotazioni-evento',
                    'evento_id' => $event['id'],
                ),
                admin_url( 'admin.php' )
            );
            ?>
            <article class="cral-cal-list__item cral-cal-list__item--admin"
                     data-cal-list-day="<?php echo esc_attr( (string) $event['day'] ); ?>"
                     data-event-id="<?php echo esc_attr( (string) $event['id'] ); ?>">
                <div class="cral-cal-list__btn cral-cal-list__btn--static">
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
                </div>
                <div class="cral-cal-list__actions">
                    <?php if ( $edit_url ) : ?>
                    <a href="<?php echo esc_url( $edit_url ); ?>" class="button button-small cral-cal-list__action">
                        <?php esc_html_e( 'Modifica Evento', 'g-event' ); ?>
                    </a>
                    <?php endif; ?>
                    <a href="<?php echo esc_url( $iscr_url ); ?>" class="button button-small button-primary cral-cal-list__action">
                        <?php esc_html_e( 'Vedi iscritti', 'g-event' ); ?>
                    </a>
                </div>
            </article>
            <?php
        }
        return ob_get_clean();
    }
}

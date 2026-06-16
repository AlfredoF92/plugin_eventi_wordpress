<?php
/**
 * Dashboard admin: menu, pagine, impostazioni.
 *
 * @package GEvent
 */

namespace GEvent;

/**
 * Classe per la gestione della dashboard admin.
 */
class Admin {

    /**
     * Registra gli hook WordPress.
     */
    public function init() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_post_cral_save_impostazioni', array( $this, 'handle_save_impostazioni' ) );
        add_action( 'admin_post_cral_generate_demo_data', array( $this, 'handle_generate_demo_data' ) );
        add_action( 'admin_post_cral_clear_logs', array( $this, 'handle_clear_logs' ) );
        add_action( 'wp_ajax_cral_manage_prenotazione_admin', array( $this, 'handle_manage_prenotazione_admin' ) );
        add_action( 'wp_ajax_cral_add_prenotazione_admin', array( $this, 'handle_add_prenotazione_admin' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'in_admin_header', array( $this, 'render_plugin_header' ) );
        add_action( 'save_post_socio', array( $this, 'log_created_socio' ), 10, 3 );
        add_action( 'save_post_evento', array( $this, 'log_created_evento' ), 10, 3 );
        add_action( 'save_post_prenotazione', array( $this, 'log_created_prenotazione' ), 10, 3 );
    }

    /**
     * Registra il menu admin del plugin.
     */
    public function register_menu() {
        add_menu_page(
            'Plugin CRAL BCC',
            'Plugin CRAL BCC',
            'manage_options',
            'g-event',
            array( $this, 'render_impostazioni' ),
            'dashicons-calendar-alt',
            30
        );

        add_submenu_page(
            'g-event',
            'Impostazioni',
            'Impostazioni',
            'manage_options',
            'g-event-impostazioni',
            array( $this, 'render_impostazioni' )
        );

        // Sottopagina nascosta per le prenotazioni di un evento.
        add_submenu_page(
            null,
            'Prenotazioni evento',
            'Prenotazioni evento',
            'manage_options',
            'g-event-prenotazioni-evento',
            array( $this, 'render_prenotazioni_evento' )
        );
    }

    /**
     * Carica gli script e gli stili per le pagine admin del plugin.
     *
     * @param string $hook Hook della pagina corrente.
     */
    public function enqueue_scripts( $hook ) {
        $pages = array(
            'toplevel_page_g-event',
            'g-event_page_g-event-impostazioni',
            'admin_page_g-event-prenotazioni-evento',
            'admin_page_g-event-scheda-socio',
        );
        $screen          = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        $is_cpt_page     = $screen && in_array( $screen->post_type, array( 'evento', 'socio' ), true );

        if ( ! in_array( $hook, $pages, true ) && ! $is_cpt_page ) {
            return;
        }

        wp_enqueue_style(
            'g-event-admin',
            plugins_url( 'assets/css/admin.css', dirname( __FILE__ ) ),
            array(),
            '1.0.2'
        );
    }

    /**
     * Renderizza l'header brandizzato CRAL/BCC in cima a tutte le pagine del plugin.
     */
    public function render_plugin_header() {
        $screen = get_current_screen();
        if ( ! $screen ) {
            return;
        }

        $plugin_pages = array(
            'toplevel_page_g-event',
            'g-event_page_g-event-impostazioni',
            'admin_page_g-event-prenotazioni-evento',
            'admin_page_g-event-scheda-socio',
        );

        $is_plugin_page = in_array( $screen->id, $plugin_pages, true );
        $is_cpt_page    = in_array( $screen->post_type, array( 'evento', 'socio' ), true );

        if ( ! $is_plugin_page && ! $is_cpt_page ) {
            return;
        }

        // Titolo contestuale per ogni pagina.
        switch ( $screen->id ) {
            case 'toplevel_page_g-event':
                $page_title = 'Dashboard';
                break;
            case 'g-event_page_g-event-impostazioni':
                $page_title = 'Impostazioni';
                break;
            case 'admin_page_g-event-prenotazioni-evento':
                $ev_id      = isset( $_GET['evento_id'] ) ? absint( $_GET['evento_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
                $page_title = $ev_id ? 'Prenotazioni — ' . get_the_title( $ev_id ) : 'Prenotazioni evento';
                break;
            case 'admin_page_g-event-scheda-socio':
                $sid        = isset( $_GET['socio_id'] ) ? absint( $_GET['socio_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
                $cognome    = $sid ? (string) get_post_meta( $sid, '_cral_cognome', true ) : '';
                $nome       = $sid ? (string) get_post_meta( $sid, '_cral_nome', true ) : '';
                $page_title = $sid ? 'Scheda — ' . trim( $cognome . ' ' . $nome ) : 'Scheda socio';
                break;
            case 'edit-evento':
                $page_title = 'Eventi';
                break;
            case 'evento':
                $page_title = 'Modifica evento';
                break;
            case 'add-evento':
                $page_title = 'Nuovo evento';
                break;
            case 'edit-socio':
                $page_title = 'Soci CRAL Iscritti';
                break;
            case 'socio':
                $page_title = 'Scheda socio';
                break;
            default:
                $page_title = 'Plugin CRAL BCC';
        }

        $logo_url = plugins_url( 'assets/img/logo-bcc.png', dirname( __FILE__ ) );
        ?>
        <div class="cral-admin-page-header">
            <div class="cral-admin-page-header__inner">
                <div class="cral-admin-page-header__title"><?php echo esc_html( $page_title ); ?></div>
                <img src="<?php echo esc_url( $logo_url ); ?>" alt="BCC Logo" class="cral-admin-page-header__logo">
            </div>
        </div>
        <?php
    }

    /**
     * Renderizza la pagina dashboard con lista eventi.
     */
    public function render_dashboard() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Accesso negato.' );
        }

        $eventi = get_posts( array(
            'post_type'      => 'evento',
            'posts_per_page' => -1,
            'orderby'        => 'meta_value',
            'meta_key'       => '_cral_evento_data',
            'order'          => 'DESC',
            'post_status'    => array( 'publish', 'draft' ),
        ) );

        $stati_label = array(
            'bozza'       => '<span style="color:#888;">Bozza</span>',
            'programmato' => '<span style="color:#1e40af;">Programmato</span>',
            'pubblicato'  => '<span style="color:#46b450;">Pubblicato</span>',
            'concluso'    => '<span style="color:#555;">Concluso</span>',
            'annullato'   => '<span style="color:#dc3232;">Annullato</span>',
        );
        ?>
        <div class="wrap">
            <h1>G-Event — Dashboard</h1>

            <?php if ( empty( $eventi ) ) : ?>
                <p>Nessun evento trovato. <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=evento' ) ); ?>">Crea il primo evento</a>.</p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Titolo evento</th>
                            <th>Data</th>
                            <th>Stato</th>
                            <th>Posti residui</th>
                            <th>Prenotazioni</th>
                            <th>Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $eventi as $evento ) : ?>
                            <?php
                            $data          = get_post_meta( $evento->ID, '_cral_evento_data', true );
                            $stato         = get_post_meta( $evento->ID, '_cral_evento_stato', true );
                            $posti_residui = get_post_meta( $evento->ID, '_cral_evento_posti_residui', true );
                            $posti_totali  = get_post_meta( $evento->ID, '_cral_evento_posti_totali', true );

                            // Conta le prenotazioni per questo evento.
                            $num_prenotazioni = count( get_posts( array(
                                'post_type'      => 'prenotazione',
                                'posts_per_page' => -1,
                                'fields'         => 'ids',
                                'meta_query'     => array(
                                    array(
                                        'key'   => '_cral_pren_evento_id',
                                        'value' => $evento->ID,
                                    ),
                                ),
            ) ) );

                            $url_prenotazioni = add_query_arg(
                                array(
                                    'page'      => 'g-event-prenotazioni-evento',
                                    'evento_id' => $evento->ID,
                                ),
                                admin_url( 'admin.php' )
                            );

                            $url_csv = add_query_arg(
                                array(
                                    'action'    => 'cral_export_csv',
                                    'evento_id' => $evento->ID,
                                    'nonce'     => wp_create_nonce( 'cral_export_csv' ),
                                ),
                                admin_url( 'admin-post.php' )
                            );
                            ?>
                            <tr>
                                <td>
                                    <strong>
                                        <a href="<?php echo esc_url( get_edit_post_link( $evento->ID ) ); ?>">
                                            <?php echo esc_html( $evento->post_title ); ?>
                                        </a>
                                    </strong>
                                </td>
                                <td>
                                    <?php echo esc_html( $data ? Evento_Stato::format_data_esplicativa( strtotime( $data ) ) : '—' ); ?>
                                </td>
                                <td>
                                    <?php
                                    if ( Evento_Stato::is_programmato( $evento->ID ) ) {
                                        $dt = get_post_datetime( $evento->ID, 'date', 'local' );
                                        $sub = $dt ? 'Pubblicazione: ' . Evento_Stato::format_data_esplicativa( $dt->getTimestamp() ) : '';
                                        echo '<div class="cral-scheda__badge cral-scheda__badge--programmato cral-list-badge">';
                                        echo '<span class="cral-scheda__badge-title">Programmato</span>';
                                        if ( $sub ) {
                                            echo '<span class="cral-scheda__badge-sub">' . esc_html( $sub ) . '</span>';
                                        }
                                        echo '</div>';
                                    } else {
                                        echo wp_kses( $stati_label[ $stato ] ?? '—', array( 'span' => array( 'style' => array() ) ) );
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php echo esc_html( $posti_residui . ' / ' . $posti_totali ); ?>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url( $url_prenotazioni ); ?>">
                                        <?php echo esc_html( $num_prenotazioni ); ?> prenotazioni
                                    </a>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url( $url_csv ); ?>" class="button button-small">
                                        Esporta CSV
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Renderizza la pagina prenotazioni di un evento.
     */
    public function render_prenotazioni_evento() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Accesso negato.' );
        }

        $evento_id = isset( $_GET['evento_id'] ) ? absint( $_GET['evento_id'] ) : 0;

        if ( ! $evento_id ) {
            wp_die( 'Evento non valido.' );
        }

        $evento = get_post( $evento_id );
        if ( ! $evento ) {
            wp_die( 'Evento non trovato.' );
        }

        $prenotazioni = get_posts( array(
            'post_type'      => 'prenotazione',
            'posts_per_page' => -1,
            'meta_query'     => array(
                array(
                    'key'   => '_cral_pren_evento_id',
                    'value' => $evento_id,
                ),
            ),
            'orderby' => 'date',
            'order'   => 'DESC',
        ) );

        $stati_label = array(
            'in_attesa'  => '<span style="color:#f0ad4e;">In attesa</span>',
            'confermata' => '<span style="color:#46b450;">Confermata</span>',
            'annullata'  => '<span style="color:#dc3232;">Annullata</span>',
        );

        $evento_data         = get_post_meta( $evento_id, '_cral_evento_data', true );
        $evento_luogo        = get_post_meta( $evento_id, '_cral_evento_luogo', true );
        $evento_stato        = get_post_meta( $evento_id, '_cral_evento_stato', true );
        $evento_posti_totali = (int) get_post_meta( $evento_id, '_cral_evento_posti_totali', true );
        $evento_posti_res    = (int) get_post_meta( $evento_id, '_cral_evento_posti_residui', true );
        $evento_prezzo_base  = (float) get_post_meta( $evento_id, '_cral_evento_prezzo_base', true );
        $data_iscr_raw       = get_post_meta( $evento_id, '_cral_evento_data_iscrizione', true );
        $data_apertura_raw   = get_post_meta( $evento_id, '_cral_evento_data_apertura_iscrizioni', true );

        $acc_socio_enabled   = 'yes' === get_post_meta( $evento_id, '_cral_evento_enable_acc_socio', true );
        $acc_esterno_enabled = 'yes' === get_post_meta( $evento_id, '_cral_evento_enable_acc_esterno', true );
        $acc_junior_enabled  = 'yes' === get_post_meta( $evento_id, '_cral_evento_enable_acc_junior', true );

        // ── Badge stato dinamico (stessa logica del frontend) ─────────────────
        $now_ts         = time();
        $ts_evento      = $evento_data    ? strtotime( (string) $evento_data )    : 0;
        $ts_scadenza    = $data_iscr_raw  ? strtotime( (string) $data_iscr_raw )  : 0;
        $ts_apertura    = $data_apertura_raw ? strtotime( (string) $data_apertura_raw ) : 0;
        $fmt_badge_data = static function( $ts ) { return $ts ? wp_date( 'd/m/Y', $ts ) : ''; };

        $badge_is_annullato   = ( 'annullato' === $evento_stato );
        $badge_is_programmato = Evento_Stato::is_programmato( $evento_id );
        $badge_is_concluso    = ( ! $badge_is_programmato && 'concluso' === $evento_stato ) || ( ! $badge_is_programmato && $ts_evento > 0 && $ts_evento < $now_ts );
        $badge_is_soldout     = ( ! $badge_is_annullato && ! $badge_is_programmato && ! $badge_is_concluso && $evento_posti_res <= 0 );
        $badge_is_iscr_chiuse = ( ! $badge_is_annullato && ! $badge_is_programmato && ! $badge_is_concluso && ! $badge_is_soldout && $ts_scadenza > 0 && $ts_scadenza < $now_ts );
        $badge_is_non_ancora  = ( ! $badge_is_annullato && ! $badge_is_programmato && ! $badge_is_concluso && ! $badge_is_soldout && ! $badge_is_iscr_chiuse && $ts_apertura > 0 && $ts_apertura > $now_ts );

        if ( $badge_is_annullato ) {
            $badge_label = 'Evento annullato';  $badge_sub = '';              $badge_color = '#92400e'; $badge_bg = '#fef9c3';
        } elseif ( $badge_is_programmato ) {
            $badge_label = Evento_Stato::get_programmato_label( $evento_id ); $badge_sub = ''; $badge_color = '#1e40af'; $badge_bg = '#eff6ff';
        } elseif ( $badge_is_concluso ) {
            $n_part      = $evento_posti_totali - $evento_posti_res;
            $badge_label = 'Evento concluso';   $badge_sub = $n_part > 0 ? 'Partecipanti: ' . $n_part : ''; $badge_color = '#991b1b'; $badge_bg = '#fee2e2';
        } elseif ( $badge_is_soldout ) {
            $badge_label = 'Sold out';          $badge_sub = 'Posti disponibili: 0';                   $badge_color = '#991b1b'; $badge_bg = '#fee2e2';
        } elseif ( $badge_is_iscr_chiuse ) {
            $badge_label = 'Iscrizioni chiuse'; $badge_sub = $ts_scadenza ? 'Scadute il ' . $fmt_badge_data( $ts_scadenza ) : ''; $badge_color = '#9a3412'; $badge_bg = '#ffedd5';
        } elseif ( $badge_is_non_ancora ) {
            $badge_label = 'Evento pubblicato'; $badge_sub = $ts_apertura ? 'Le iscrizioni aprono il ' . $fmt_badge_data( $ts_apertura ) : ''; $badge_color = '#1e40af'; $badge_bg = '#eff6ff';
        } else {
            $badge_label = 'Iscrizioni aperte'; $badge_sub = $ts_scadenza ? 'fino al ' . $fmt_badge_data( $ts_scadenza ) : ''; $badge_color = '#166534'; $badge_bg = '#dcfce7';
        }

        $url_csv = add_query_arg(
            array(
                'action'    => 'cral_export_evento_csv',
                'evento_id' => $evento_id,
                'nonce'     => wp_create_nonce( 'cral_export_evento_csv' ),
            ),
            admin_url( 'admin-post.php' )
        );
        $manage_nonce = wp_create_nonce( 'cral_manage_prenotazione_admin' );
        $add_nonce    = wp_create_nonce( 'cral_add_prenotazione_admin' );
        $soci_list    = get_posts(
            array(
                'post_type'      => 'socio',
                'posts_per_page' => -1,
                'orderby'        => 'title',
                'order'          => 'ASC',
                'post_status'    => 'publish',
            )
        );
        $acc_config = array(
            'Accompagnatore Socio' => array(
                'enabled' => $acc_socio_enabled,
                'price'   => (float) get_post_meta( $evento_id, '_cral_evento_prezzo_acc_socio', true ),
            ),
            'Accompagnatore Esterno' => array(
                'enabled' => $acc_esterno_enabled,
                'price'   => (float) get_post_meta( $evento_id, '_cral_evento_prezzo_acc_esterno', true ),
            ),
            'Accompagnatore Junior' => array(
                'enabled' => $acc_junior_enabled,
                'price'   => (float) get_post_meta( $evento_id, '_cral_evento_prezzo_acc_junior', true ),
            ),
        );

        // Copertina evento.
        $thumbnail_html = '';
        if ( has_post_thumbnail( $evento_id ) ) {
            $thumbnail_html = get_the_post_thumbnail( $evento_id, array( 320, 200 ), array(
                'style' => 'width:100%;height:100%;object-fit:cover;display:block;',
            ) );
        }

        // Riassunto evento.
        $riassunto = $evento->post_excerpt
            ? $evento->post_excerpt
            : (string) get_post_meta( $evento_id, '_cral_evento_descrizione', true );
        // Fallback: prime 200 parole del contenuto.
        if ( ! $riassunto && $evento->post_content ) {
            $riassunto = wp_trim_words( wp_strip_all_tags( $evento->post_content ), 40, '…' );
        }
        ?>
        <div class="wrap">

            <!-- ══ SEZIONE 1: Copertina · Titolo · Riassunto ══════════════════ -->
            <div class="cral-ev-hero">
                <?php if ( $thumbnail_html ) : ?>
                <div class="cral-ev-hero__cover"><?php echo $thumbnail_html; // phpcs:ignore ?></div>
                <?php endif; ?>
                <div class="cral-ev-hero__body">
                    <!-- Badge stato -->
                    <div class="cral-evento-summary__dyn-badge" style="background:<?php echo esc_attr( $badge_bg ); ?>;color:<?php echo esc_attr( $badge_color ); ?>;">
                        <span class="cral-evento-summary__dyn-badge-title"><?php echo esc_html( $badge_label ); ?></span>
                        <?php if ( $badge_sub ) : ?>
                        <span class="cral-evento-summary__dyn-badge-sub"><?php echo esc_html( $badge_sub ); ?></span>
                        <?php endif; ?>
                    </div>
                    <h2 class="cral-ev-hero__title"><?php echo esc_html( $evento->post_title ); ?></h2>
                    <?php if ( $riassunto ) : ?>
                    <p class="cral-ev-hero__excerpt"><?php echo esc_html( wp_strip_all_tags( $riassunto ) ); ?></p>
                    <?php endif; ?>
                    <a href="<?php echo esc_url( get_edit_post_link( $evento_id ) ); ?>" class="cral-ev-hero__edit-link">&#9998; Modifica evento</a>
                </div>
            </div>

            <!-- ══ SEZIONE 2: Statistiche ════════════════════════════════════ -->
            <div class="cral-evento-summary" style="margin-top:0;">
                <div class="cral-evento-summary__title-wrap" style="display:none;"></div>

                <!-- ── Info principali ── -->
                <div class="cral-ev-info-grid">
                    <div class="cral-ev-info-item">
                        <span class="cral-ev-info-label">&#128197; Data evento</span>
                        <span class="cral-ev-info-value"><?php echo esc_html( $evento_data ? wp_date( 'd/m/Y H:i', strtotime( $evento_data ) ) : '—' ); ?></span>
                    </div>
                    <div class="cral-ev-info-item">
                        <span class="cral-ev-info-label">&#128205; Luogo</span>
                        <span class="cral-ev-info-value"><?php echo esc_html( $evento_luogo ?: '—' ); ?></span>
                    </div>
                    <div class="cral-ev-info-item">
                        <span class="cral-ev-info-label">&#127915; Prezzo biglietto</span>
                        <span class="cral-ev-info-value">€ <?php echo esc_html( number_format( $evento_prezzo_base, 2, ',', '.' ) ); ?></span>
                    </div>
                    <div class="cral-ev-info-item">
                        <span class="cral-ev-info-label">&#128065; Posti residui</span>
                        <span class="cral-ev-info-value">
                            <strong><?php echo esc_html( $evento_posti_res ); ?></strong>
                            <span style="color:#888;font-size:.9em;"> / <?php echo esc_html( $evento_posti_totali ); ?></span>
                        </span>
                    </div>
                    <div class="cral-ev-info-item">
                        <span class="cral-ev-info-label">&#128203; Prenotazioni</span>
                        <span class="cral-ev-info-value"><strong><?php echo esc_html( count( $prenotazioni ) ); ?></strong></span>
                    </div>
                </div>

                <!-- ── Accompagnatori ── -->
                <div class="cral-ev-acc-grid">
                    <?php
                    $acc_types = array(
                        'Socio'   => array( 'enabled' => $acc_socio_enabled,   'price' => (float) get_post_meta( $evento_id, '_cral_evento_prezzo_acc_socio', true ),   'max' => (int) get_post_meta( $evento_id, '_cral_evento_max_acc_socio', true ) ),
                        'Esterno' => array( 'enabled' => $acc_esterno_enabled, 'price' => (float) get_post_meta( $evento_id, '_cral_evento_prezzo_acc_esterno', true ), 'max' => (int) get_post_meta( $evento_id, '_cral_evento_max_acc_esterno', true ) ),
                        'Junior'  => array( 'enabled' => $acc_junior_enabled,  'price' => (float) get_post_meta( $evento_id, '_cral_evento_prezzo_acc_junior', true ),  'max' => (int) get_post_meta( $evento_id, '_cral_evento_max_acc_junior', true ) ),
                    );
                    foreach ( $acc_types as $label => $cfg ) :
                    ?>
                    <div class="cral-ev-acc-item <?php echo $cfg['enabled'] ? 'cral-ev-acc-item--on' : 'cral-ev-acc-item--off'; ?>">
                        <span class="cral-ev-acc-label">Acc. <?php echo esc_html( $label ); ?></span>
                        <?php if ( $cfg['enabled'] ) : ?>
                            <span class="cral-ev-acc-price">€ <?php echo esc_html( number_format( $cfg['price'], 2, ',', '.' ) ); ?></span>
                            <span class="cral-ev-acc-max">max <?php echo esc_html( $cfg['max'] ); ?></span>
                        <?php else : ?>
                            <span class="cral-ev-acc-off">Non attivo</span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>

            </div>

            <p>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=g-event' ) ); ?>">
                    &larr; Torna alla dashboard
                </a>
                &nbsp;|&nbsp;
                <a href="<?php echo esc_url( $url_csv ); ?>" class="button button-primary">
                    ESPORTA CSV
                </a>
                &nbsp;
                <button type="button" class="button button-secondary" id="cral-open-add-pren-modal">
                    Aggiungi Prenotazione
                </button>
            </p>

            <?php if ( empty( $prenotazioni ) ) : ?>
                <p>Nessuna prenotazione trovata per questo evento.</p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Socio</th>
                            <th>Data prenotazione</th>
                            <th>Biglietti</th>
                            <th>Biglietto Evento</th>
                            <th>Accompagnatori</th>
                            <th>Note</th>
                            <th>Stato</th>
                            <th>Totale pagato socio</th>
                            <th>Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $prenotazioni as $pren ) : ?>
                            <?php
                            $socio_id  = get_post_meta( $pren->ID, '_cral_pren_socio_id', true );
                            $nome      = get_post_meta( $socio_id, '_cral_nome', true );
                            $cognome   = get_post_meta( $socio_id, '_cral_cognome', true );
                            $data      = get_post_meta( $pren->ID, '_cral_pren_data', true );
                            $stato     = get_post_meta( $pren->ID, '_cral_pren_stato', true );
                            $biglietti = (int) get_post_meta( $pren->ID, '_cral_pren_totale_biglietti', true );
                            $importo   = get_post_meta( $pren->ID, '_cral_pren_importo_totale', true );
                            $note      = (string) get_post_meta( $pren->ID, '_cral_pren_note', true );
                            $partecipanti = carbon_get_post_meta( $pren->ID, 'cral_partecipanti' );
                            $evento_prezzo = (float) get_post_meta( $evento_id, '_cral_evento_prezzo_base', true );

                            $partecipanti_html = '—';
                            $partecipanti_sum  = 0.0;
                            $accompagnatori_count = 0;
                            if ( ! empty( $partecipanti ) && is_array( $partecipanti ) ) {
                                $items = array();
                                foreach ( $partecipanti as $part ) {
                                    $p_nome    = sanitize_text_field( $part['partecipante_nome'] ?? '' );
                                    $p_cognome = sanitize_text_field( $part['partecipante_cognome'] ?? '' );
                                    $p_tipo    = sanitize_text_field( $part['partecipante_tipologia'] ?? '' );
                                    $p_prezzo  = (float) ( $part['partecipante_prezzo'] ?? 0 );
                                    $partecipanti_sum += $p_prezzo;

                                    // Mostra in tabella solo gli accompagnatori.
                                    if ( 'Socio' === $p_tipo ) {
                                        continue;
                                    }

                                    $label = trim( $p_nome . ' ' . $p_cognome );
                                    if ( '' !== $label ) {
                                        $label .= ' (' . $p_tipo . ') — € ' . number_format( $p_prezzo, 2, ',', '.' );
                                        $items[] = $label;
                                        $accompagnatori_count++;
                                    }
                                }

                                $partecipanti_html = '<ul style="margin:0; padding-left: 18px;">';
                                if ( empty( $items ) ) {
                                    $partecipanti_html .= '<li>Nessun accompagnatore</li>';
                                } else {
                                    foreach ( $items as $txt ) {
                                        $partecipanti_html .= '<li>' . esc_html( $txt ) . '</li>';
                                    }
                                }
                                $partecipanti_html .= '</ul>';
                            }

                            // Totale pagato della singola prenotazione (biglietto + accompagnatori).
                            // Se ci sono partecipanti, sommiamo i loro prezzi; altrimenti fallback su importo totale.
                            $totale_pagato = $partecipanti_sum > 0 ? $partecipanti_sum : (float) $importo;
                            ?>
                            <tr>
                                <td><?php echo esc_html( $pren->ID ); ?></td>
                                <td>
                                    <a href="<?php echo esc_url( get_edit_post_link( $socio_id ) ); ?>">
                                        <?php echo esc_html( $cognome . ' ' . $nome ); ?>
                                    </a>
                                </td>
                                <td><?php echo esc_html( $data ? wp_date( 'd/m/Y H:i', strtotime( $data ) ) : '—' ); ?></td>
                                <td><?php echo esc_html( '1 + ' . $accompagnatori_count ); ?></td>
                                <td><?php echo esc_html( '€ ' . number_format( $evento_prezzo, 2, ',', '.' ) ); ?></td>
                                <td><?php echo wp_kses( $partecipanti_html, array( 'ul' => array( 'style' => array() ), 'li' => array() ) ); ?></td>
                                <td><?php echo esc_html( $note ?: '—' ); ?></td>
                                <td><?php echo wp_kses( $stati_label[ $stato ] ?? '—', array( 'span' => array( 'style' => array() ) ) ); ?></td>
                                <td><?php echo esc_html( '€ ' . number_format( $totale_pagato, 2, ',', '.' ) ); ?></td>
                                <td>
                                    <button
                                        type="button"
                                        class="button button-small cral-open-pren-modal"
                                        data-pren-id="<?php echo esc_attr( $pren->ID ); ?>"
                                        data-pren-title="<?php echo esc_attr( $cognome . ' ' . $nome ); ?>"
                                        data-pren-accompagnatori="<?php echo esc_attr( wp_json_encode( $items ?? array() ) ); ?>"
                                        data-pren-date="<?php echo esc_attr( $data ? wp_date( 'd/m/Y H:i', strtotime( $data ) ) : '—' ); ?>"
                                        data-pren-state="<?php echo esc_attr( $stato ); ?>"
                                        data-pren-totale="<?php echo esc_attr( number_format( $totale_pagato, 2, ',', '.' ) ); ?>"
                                        data-pren-note="<?php echo esc_attr( $note ); ?>"
                                    >
                                        Visualizza Prenotazione
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <div id="cral-pren-modal" class="cral-pren-modal" style="display:none;">
                <div class="cral-pren-modal__backdrop"></div>
                <div class="cral-pren-modal__dialog">
                    <h3 id="cral-pren-modal-title">Gestione prenotazione</h3>
                    <div id="cral-pren-modal-summary" class="cral-pren-modal__section"></div>
                    <p class="description">Scegli un'azione e salva.</p>

                    <div class="cral-pren-modal__section">
                        <label style="display:flex; gap:8px; align-items:center;">
                            <input type="radio" name="cral_modal_action" value="annulla">
                            Annulla prenotazione (ripristina i posti evento)
                        </label>
                        <label style="display:flex; gap:8px; align-items:center; margin-top:8px;">
                            <input type="radio" name="cral_modal_action" value="remove_acc">
                            Elimina un accompagnatore
                        </label>
                    </div>

                    <div class="cral-pren-modal__section" id="cral-modal-acc-wrap" style="display:none;">
                        <label for="cral-modal-acc-select"><strong>Accompagnatore da eliminare</strong></label>
                        <select id="cral-modal-acc-select" style="width:100%; max-width:100%;">
                            <option value="">Seleziona accompagnatore</option>
                        </select>
                    </div>

                    <div class="cral-pren-modal__section">
                        <label for="cral-modal-note"><strong>Note prenotazione</strong></label>
                        <textarea id="cral-modal-note" rows="3" style="width:100%; max-width:100%;"></textarea>
                    </div>

                    <div id="cral-pren-modal-msg" class="notice inline" style="display:none; margin:10px 0 0;"></div>

                    <div style="margin-top:14px; display:flex; gap:8px;">
                        <button type="button" class="button button-primary" id="cral-modal-save">Salva modifiche</button>
                        <button type="button" class="button" id="cral-modal-cancel">Chiudi</button>
                    </div>
                </div>
            </div>

            <div id="cral-add-pren-modal" class="cral-pren-modal" style="display:none;">
                <div class="cral-pren-modal__backdrop"></div>
                <div class="cral-pren-modal__dialog">
                    <h3>Nuova prenotazione</h3>
                    <div class="cral-pren-modal__section">
                        <label for="cral-add-socio"><strong>Socio</strong></label>
                        <select id="cral-add-socio" style="width:100%; max-width:100%;">
                            <option value="">Seleziona socio</option>
                            <?php foreach ( $soci_list as $socio ) : ?>
                                <?php
                                $sid      = get_post_meta( $socio->ID, '_cral_socio_id', true );
                                $snome    = get_post_meta( $socio->ID, '_cral_nome', true );
                                $scognome = get_post_meta( $socio->ID, '_cral_cognome', true );
                                ?>
                                <option value="<?php echo esc_attr( $socio->ID ); ?>">
                                    <?php echo esc_html( trim( $scognome . ' ' . $snome ) . ( $sid ? ' (' . $sid . ')' : '' ) ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="cral-pren-modal__section">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <strong>Accompagnatori</strong>
                            <button type="button" class="button" id="cral-add-acc-row">Aggiungi accompagnatore</button>
                        </div>
                        <div id="cral-add-acc-list" style="margin-top:8px;"></div>
                    </div>

                    <div class="cral-pren-modal__section">
                        <label for="cral-add-pren-note"><strong>Note prenotazione</strong></label>
                        <textarea id="cral-add-pren-note" rows="3" style="width:100%; max-width:100%;"></textarea>
                    </div>

                    <div id="cral-add-pren-total" class="cral-pren-modal__section">
                        Totale pagato: € 0,00
                    </div>

                    <div id="cral-add-pren-msg" class="notice inline" style="display:none; margin:10px 0 0;"></div>

                    <div style="margin-top:14px; display:flex; gap:8px;">
                        <button type="button" class="button button-primary" id="cral-add-pren-save">Salva e invia</button>
                        <button type="button" class="button" id="cral-add-pren-cancel">Chiudi</button>
                    </div>
                </div>
            </div>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('cral-pren-modal');
            if (!modal) return;
            const modalTitle = document.getElementById('cral-pren-modal-title');
            const modalSummary = document.getElementById('cral-pren-modal-summary');
            const accWrap = document.getElementById('cral-modal-acc-wrap');
            const accSelect = document.getElementById('cral-modal-acc-select');
            const noteField = document.getElementById('cral-modal-note');
            const saveBtn = document.getElementById('cral-modal-save');
            const cancelBtn = document.getElementById('cral-modal-cancel');
            const msg = document.getElementById('cral-pren-modal-msg');
            const radios = () => Array.from(document.querySelectorAll('input[name="cral_modal_action"]'));
            let currentPrenId = 0;

            function selectedAction() {
                const found = radios().find(r => r.checked);
                return found ? found.value : '';
            }

            function setMsg(text, ok) {
                msg.style.display = 'block';
                msg.className = ok ? 'notice inline notice-success' : 'notice inline notice-error';
                msg.innerHTML = '<p>' + text + '</p>';
            }

            function closeModal() {
                modal.style.display = 'none';
                msg.style.display = 'none';
                msg.innerHTML = '';
                radios().forEach(r => { r.checked = false; });
                accWrap.style.display = 'none';
                accSelect.innerHTML = '<option value="">Seleziona accompagnatore</option>';
                noteField.value = '';
                modalSummary.innerHTML = '';
                currentPrenId = 0;
            }

            document.querySelectorAll('.cral-open-pren-modal').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    currentPrenId = Number(btn.dataset.prenId || 0);
                    modalTitle.textContent = 'Gestione prenotazione — ' + (btn.dataset.prenTitle || '');
                    modalSummary.innerHTML =
                        '<strong>Data:</strong> ' + (btn.dataset.prenDate || '—') + '<br>' +
                        '<strong>Stato:</strong> ' + (btn.dataset.prenState || '—') + '<br>' +
                        '<strong>Totale pagato:</strong> € ' + (btn.dataset.prenTotale || '0,00');
                    accSelect.innerHTML = '<option value="">Seleziona accompagnatore</option>';
                    noteField.value = btn.dataset.prenNote || '';
                    try {
                        const accompagnatori = JSON.parse(btn.dataset.prenAccompagnatori || '[]');
                        accompagnatori.forEach(function(label, idx) {
                            const op = document.createElement('option');
                            op.value = String(idx);
                            op.textContent = label;
                            accSelect.appendChild(op);
                        });
                    } catch (e) {}
                    modal.style.display = 'block';
                });
            });

            radios().forEach(function(r) {
                r.addEventListener('change', function() {
                    accWrap.style.display = selectedAction() === 'remove_acc' ? 'block' : 'none';
                });
            });

            saveBtn.addEventListener('click', function() {
                if (!currentPrenId) return;
                const action = selectedAction();
                if (action === 'remove_acc' && !accSelect.value) {
                    setMsg('Seleziona un accompagnatore da eliminare.', false);
                    return;
                }

                saveBtn.disabled = true;
                saveBtn.textContent = 'Salvataggio...';
                fetch(ajaxurl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'cral_manage_prenotazione_admin',
                        nonce: '<?php echo esc_js( $manage_nonce ); ?>',
                        prenotazione_id: String(currentPrenId),
                        manage_action: action || 'update',
                        note: noteField.value || '',
                        acc_index: action === 'remove_acc' ? accSelect.value : ''
                    })
                })
                .then(r => r.json())
                .then(function(data) {
                    if (data.success) {
                        setMsg(data.data.message || 'Modifiche salvate.', true);
                        setTimeout(function(){ window.location.reload(); }, 700);
                    } else {
                        setMsg((data.data && data.data.message) ? data.data.message : 'Errore durante il salvataggio.', false);
                    }
                })
                .catch(function() {
                    setMsg('Errore di connessione. Riprova.', false);
                })
                .finally(function() {
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Salva modifiche';
                });
            });

            cancelBtn.addEventListener('click', closeModal);
            modal.querySelector('.cral-pren-modal__backdrop').addEventListener('click', closeModal);

            // Nuovo modale: aggiungi prenotazione.
            const addModal = document.getElementById('cral-add-pren-modal');
            const openAddBtn = document.getElementById('cral-open-add-pren-modal');
            const addCloseBtn = document.getElementById('cral-add-pren-cancel');
            const addSaveBtn = document.getElementById('cral-add-pren-save');
            const addSocio = document.getElementById('cral-add-socio');
            const addAccList = document.getElementById('cral-add-acc-list');
            const addAccRowBtn = document.getElementById('cral-add-acc-row');
            const addTotal = document.getElementById('cral-add-pren-total');
            const addMsg = document.getElementById('cral-add-pren-msg');
            const addNote = document.getElementById('cral-add-pren-note');
            const accConfig = <?php echo wp_json_encode( $acc_config ); ?>;
            const enabledTypes = Object.keys(accConfig).filter(k => !!accConfig[k].enabled);
            const basePrice = <?php echo (float) $evento_prezzo_base; ?>;
            let addAccIndex = 0;

            function setAddMsg(text, ok) {
                addMsg.style.display = 'block';
                addMsg.className = ok ? 'notice inline notice-success' : 'notice inline notice-error';
                addMsg.innerHTML = '<p>' + text + '</p>';
            }

            function calcAddTotal() {
                let total = Number(basePrice || 0);
                addAccList.querySelectorAll('.cral-add-acc-row').forEach(function(row) {
                    const tipo = row.querySelector('select')?.value || '';
                    if (accConfig[tipo]) {
                        total += Number(accConfig[tipo].price || 0);
                    }
                });
                addTotal.textContent = 'Totale pagato: € ' + total.toFixed(2).replace('.', ',');
            }

            function addAccRow() {
                if (!enabledTypes.length) {
                    setAddMsg('Nessuna tipologia accompagnatore attiva per questo evento.', false);
                    return;
                }
                const row = document.createElement('div');
                row.className = 'cral-add-acc-row';
                row.style.cssText = 'display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:8px;margin-bottom:8px;';
                const options = enabledTypes.map(function(t) {
                    return '<option value="' + t + '">' + t + ' (€ ' + Number(accConfig[t].price || 0).toFixed(2).replace('.', ',') + ')</option>';
                }).join('');
                row.innerHTML =
                    '<input type="text" placeholder="Nome" class="cral-add-acc-nome">' +
                    '<input type="text" placeholder="Cognome" class="cral-add-acc-cognome">' +
                    '<select class="cral-add-acc-tipo">' + options + '</select>' +
                    '<button type="button" class="button">Rimuovi</button>';
                addAccList.appendChild(row);
                addAccIndex++;
                row.querySelector('.button').addEventListener('click', function() {
                    row.remove();
                    calcAddTotal();
                });
                row.querySelector('.cral-add-acc-tipo').addEventListener('change', calcAddTotal);
                calcAddTotal();
            }

            function openAddModal() {
                addModal.style.display = 'block';
                addMsg.style.display = 'none';
                addMsg.innerHTML = '';
                addSocio.value = '';
                addAccList.innerHTML = '';
                addNote.value = '';
                addAccIndex = 0;
                calcAddTotal();
            }

            function closeAddModal() {
                addModal.style.display = 'none';
            }

            if (openAddBtn) openAddBtn.addEventListener('click', openAddModal);
            if (addCloseBtn) addCloseBtn.addEventListener('click', closeAddModal);
            if (addAccRowBtn) addAccRowBtn.addEventListener('click', addAccRow);
            if (addModal) addModal.querySelector('.cral-pren-modal__backdrop').addEventListener('click', closeAddModal);

            if (addSaveBtn) {
                addSaveBtn.addEventListener('click', function() {
                    if (!addSocio.value) {
                        setAddMsg('Seleziona un socio.', false);
                        return;
                    }

                    const accompagnatori = [];
                    let invalid = false;
                    addAccList.querySelectorAll('.cral-add-acc-row').forEach(function(row) {
                        const nome = (row.querySelector('.cral-add-acc-nome')?.value || '').trim();
                        const cognome = (row.querySelector('.cral-add-acc-cognome')?.value || '').trim();
                        const tipologia = row.querySelector('.cral-add-acc-tipo')?.value || '';
                        if (!nome || !cognome || !tipologia) {
                            invalid = true;
                            return;
                        }
                        accompagnatori.push({ nome, cognome, tipologia });
                    });
                    if (invalid) {
                        setAddMsg('Compila nome, cognome e tipologia per ogni accompagnatore.', false);
                        return;
                    }

                    addSaveBtn.disabled = true;
                    addSaveBtn.textContent = 'Salvataggio...';
                    fetch(ajaxurl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'cral_add_prenotazione_admin',
                            nonce: '<?php echo esc_js( $add_nonce ); ?>',
                            evento_id: '<?php echo esc_js( (string) $evento_id ); ?>',
                            socio_id: String(addSocio.value),
                            note: addNote.value || '',
                            accompagnatori_json: JSON.stringify(accompagnatori)
                        })
                    })
                    .then(r => r.json())
                    .then(function(data) {
                        if (data.success) {
                            setAddMsg(data.data.message || 'Prenotazione creata.', true);
                            setTimeout(function(){ window.location.reload(); }, 700);
                        } else {
                            setAddMsg((data.data && data.data.message) ? data.data.message : 'Errore durante il salvataggio.', false);
                        }
                    })
                    .catch(function() {
                        setAddMsg('Errore di connessione. Riprova.', false);
                    })
                    .finally(function() {
                        addSaveBtn.disabled = false;
                        addSaveBtn.textContent = 'Salva e invia';
                    });
                });
            }
        });
        </script>
        <?php
    }

    /**
     * Gestione modifiche prenotazione da modale admin.
     */
    public function handle_manage_prenotazione_admin() {
        check_ajax_referer( 'cral_manage_prenotazione_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permessi insufficienti.' ) );
        }

        $pren_id = isset( $_POST['prenotazione_id'] ) ? absint( $_POST['prenotazione_id'] ) : 0;
        $action  = isset( $_POST['manage_action'] ) ? sanitize_text_field( wp_unslash( $_POST['manage_action'] ) ) : '';
        $note    = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';
        $acc_idx = isset( $_POST['acc_index'] ) ? absint( $_POST['acc_index'] ) : -1;
        if ( ! $pren_id || ! in_array( $action, array( 'annulla', 'remove_acc', 'update' ), true ) ) {
            wp_send_json_error( array( 'message' => 'Dati non validi.' ) );
        }

        $evento_id = (int) get_post_meta( $pren_id, '_cral_pren_evento_id', true );
        if ( ! $evento_id ) {
            wp_send_json_error( array( 'message' => 'Evento non valido.' ) );
        }

        $partecipanti = carbon_get_post_meta( $pren_id, 'cral_partecipanti' );
        $partecipanti = is_array( $partecipanti ) ? $partecipanti : array();
        $total_before = count( $partecipanti ) > 0 ? count( $partecipanti ) : (int) get_post_meta( $pren_id, '_cral_pren_totale_biglietti', true );
        $stato_corrente = (string) get_post_meta( $pren_id, '_cral_pren_stato', true );

        // Salva sempre eventuali note.
        update_post_meta( $pren_id, '_cral_pren_note', $note );

        if ( 'update' === $action ) {
            wp_send_json_success( array( 'message' => 'Prenotazione aggiornata correttamente.' ) );
        }

        if ( 'annulla' === $action ) {
            if ( 'annullata' === $stato_corrente ) {
                wp_send_json_error( array( 'message' => 'La prenotazione e gia annullata.' ) );
            }
            update_post_meta( $pren_id, '_cral_pren_stato', 'annullata' );
            $this->adjust_event_seats( $evento_id, $total_before );
            wp_send_json_success( array( 'message' => 'Prenotazione annullata e posti aggiornati correttamente.' ) );
        }

        // remove_acc.
        $accompagnatori_map = array();
        foreach ( $partecipanti as $idx => $part ) {
            $tipo = sanitize_text_field( $part['partecipante_tipologia'] ?? '' );
            if ( 'Socio' === $tipo ) {
                continue;
            }
            $accompagnatori_map[] = $idx;
        }
        if ( ! isset( $accompagnatori_map[ $acc_idx ] ) ) {
            wp_send_json_error( array( 'message' => 'Accompagnatore non valido.' ) );
        }

        $remove_real_idx = $accompagnatori_map[ $acc_idx ];
        unset( $partecipanti[ $remove_real_idx ] );
        $partecipanti = array_values( $partecipanti );
        if ( empty( $partecipanti ) ) {
            wp_send_json_error( array( 'message' => 'Non puoi eliminare il socio dalla prenotazione.' ) );
        }

        $totale_importo = 0.0;
        foreach ( $partecipanti as $p ) {
            $totale_importo += (float) ( $p['partecipante_prezzo'] ?? 0 );
        }
        $totale_biglietti = count( $partecipanti );

        carbon_set_post_meta( $pren_id, 'cral_partecipanti', $partecipanti );
        update_post_meta( $pren_id, '_cral_pren_totale_biglietti', $totale_biglietti );
        update_post_meta( $pren_id, '_cral_pren_importo_totale', $totale_importo );
        $this->adjust_event_seats( $evento_id, 1 );

        wp_send_json_success( array( 'message' => 'Accompagnatore eliminato. Prezzo e posti aggiornati.' ) );
    }

    /**
     * Crea prenotazione da modale admin e invia notifiche.
     */
    public function handle_add_prenotazione_admin() {
        check_ajax_referer( 'cral_add_prenotazione_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permessi insufficienti.' ) );
        }

        $evento_id = isset( $_POST['evento_id'] ) ? absint( $_POST['evento_id'] ) : 0;
        $socio_id  = isset( $_POST['socio_id'] ) ? absint( $_POST['socio_id'] ) : 0;
        $note      = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';
        $acc_json  = isset( $_POST['accompagnatori_json'] ) ? wp_unslash( $_POST['accompagnatori_json'] ) : '[]';
        $accompagnatori = json_decode( $acc_json, true );
        $accompagnatori = is_array( $accompagnatori ) ? $accompagnatori : array();

        if ( ! $evento_id || 'evento' !== get_post_type( $evento_id ) ) {
            wp_send_json_error( array( 'message' => 'Evento non valido.' ) );
        }
        if ( ! $socio_id || 'socio' !== get_post_type( $socio_id ) ) {
            wp_send_json_error( array( 'message' => 'Socio non valido.' ) );
        }

        $prezzo_base = (float) get_post_meta( $evento_id, '_cral_evento_prezzo_base', true );
        $types = array(
            'Accompagnatore Socio' => array(
                'enabled' => 'yes' === get_post_meta( $evento_id, '_cral_evento_enable_acc_socio', true ),
                'price'   => (float) get_post_meta( $evento_id, '_cral_evento_prezzo_acc_socio', true ),
                'max'     => (int) get_post_meta( $evento_id, '_cral_evento_max_acc_socio', true ),
            ),
            'Accompagnatore Esterno' => array(
                'enabled' => 'yes' === get_post_meta( $evento_id, '_cral_evento_enable_acc_esterno', true ),
                'price'   => (float) get_post_meta( $evento_id, '_cral_evento_prezzo_acc_esterno', true ),
                'max'     => (int) get_post_meta( $evento_id, '_cral_evento_max_acc_esterno', true ),
            ),
            'Accompagnatore Junior' => array(
                'enabled' => 'yes' === get_post_meta( $evento_id, '_cral_evento_enable_acc_junior', true ),
                'price'   => (float) get_post_meta( $evento_id, '_cral_evento_prezzo_acc_junior', true ),
                'max'     => (int) get_post_meta( $evento_id, '_cral_evento_max_acc_junior', true ),
            ),
        );

        $count_by_type = array(
            'Accompagnatore Socio' => 0,
            'Accompagnatore Esterno' => 0,
            'Accompagnatore Junior' => 0,
        );
        $partecipanti = array();

        $socio_nome    = (string) get_post_meta( $socio_id, '_cral_nome', true );
        $socio_cognome = (string) get_post_meta( $socio_id, '_cral_cognome', true );
        $partecipanti[] = array(
            'partecipante_nome'      => $socio_nome,
            'partecipante_cognome'   => $socio_cognome,
            'partecipante_tipologia' => 'Socio',
            'partecipante_prezzo'    => (string) $prezzo_base,
        );
        $importo_totale = $prezzo_base;

        foreach ( $accompagnatori as $acc ) {
            $nome = sanitize_text_field( $acc['nome'] ?? '' );
            $cognome = sanitize_text_field( $acc['cognome'] ?? '' );
            $tipologia = sanitize_text_field( $acc['tipologia'] ?? '' );
            if ( '' === $nome || '' === $cognome || ! isset( $types[ $tipologia ] ) ) {
                wp_send_json_error( array( 'message' => 'Dati accompagnatore non validi.' ) );
            }
            if ( ! $types[ $tipologia ]['enabled'] ) {
                wp_send_json_error( array( 'message' => 'Tipologia accompagnatore non attiva: ' . $tipologia ) );
            }
            $count_by_type[ $tipologia ]++;
            if ( $count_by_type[ $tipologia ] > $types[ $tipologia ]['max'] ) {
                wp_send_json_error( array( 'message' => 'Superato massimo per ' . $tipologia ) );
            }
            $price = (float) $types[ $tipologia ]['price'];
            $importo_totale += $price;
            $partecipanti[] = array(
                'partecipante_nome'      => $nome,
                'partecipante_cognome'   => $cognome,
                'partecipante_tipologia' => $tipologia,
                'partecipante_prezzo'    => (string) $price,
            );
        }

        $totale_biglietti = count( $partecipanti );
        global $wpdb;
        $updated = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->postmeta}
                 SET meta_value = CAST(meta_value AS UNSIGNED) - %d
                 WHERE post_id = %d
                 AND meta_key = '_cral_evento_posti_residui'
                 AND CAST(meta_value AS UNSIGNED) >= %d",
                $totale_biglietti,
                $evento_id,
                $totale_biglietti
            )
        );
        if ( ! $updated ) {
            wp_send_json_error( array( 'message' => 'Posti disponibili insufficienti.' ) );
        }

        $post_id = wp_insert_post(
            array(
                'post_type'   => 'prenotazione',
                'post_title'  => $socio_cognome . ' ' . $socio_nome . ' — ' . get_the_title( $evento_id ),
                'post_status' => 'publish',
            )
        );
        if ( is_wp_error( $post_id ) ) {
            $this->adjust_event_seats( $evento_id, $totale_biglietti );
            wp_send_json_error( array( 'message' => 'Errore creazione prenotazione.' ) );
        }

        update_post_meta( $post_id, '_cral_pren_socio_id', $socio_id );
        update_post_meta( $post_id, '_cral_pren_evento_id', $evento_id );
        update_post_meta( $post_id, '_cral_pren_data', current_time( 'mysql' ) );
        update_post_meta( $post_id, '_cral_pren_stato', 'confermata' );
        update_post_meta( $post_id, '_cral_pren_totale_biglietti', $totale_biglietti );
        update_post_meta( $post_id, '_cral_pren_importo_totale', $importo_totale );
        update_post_meta( $post_id, '_cral_pren_pagamento', 'yes' );
        update_post_meta( $post_id, '_cral_pren_data_pagamento', wp_date( 'Y-m-d' ) );
        update_post_meta( $post_id, '_cral_pren_note', $note );
        carbon_set_post_meta( $post_id, 'cral_partecipanti', $partecipanti );

        $mailer = new Mailer();
        $mailer->send_conferma_socio( $post_id, $socio_id, $evento_id );
        $mailer->send_notifica_segreteria( $post_id, $socio_id, $evento_id );

        wp_send_json_success( array( 'message' => 'Prenotazione creata, salvata e notifiche inviate.' ) );
    }

    /**
     * Aggiorna i posti residui evento.
     *
     * @param int $evento_id ID evento.
     * @param int $delta     Posti da aggiungere ai residui.
     */
    private function adjust_event_seats( $evento_id, $delta ) {
        global $wpdb;
        $delta = (int) $delta;
        if ( $delta <= 0 ) {
            return;
        }
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->postmeta}
                 SET meta_value = CAST(meta_value AS UNSIGNED) + %d
                 WHERE post_id = %d
                 AND meta_key = '_cral_evento_posti_residui'",
                $delta,
                $evento_id
            )
        );
    }

    /**
     * Renderizza la pagina impostazioni.
     */
    public function render_impostazioni() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Accesso negato.' );
        }

        // Recupera le impostazioni salvate.
        $email_segreteria       = get_option( 'cral_email_segreteria', '' );
        $pagina_login           = get_option( 'cral_pagina_login', 0 );
        $pagina_area_soci       = get_option( 'cral_pagina_area_soci', 0 );
        $pagina_imposta_pwd     = get_option( 'cral_pagina_imposta_password', 0 );
        $pagina_recupera_pwd    = get_option( 'cral_pagina_recupera_password', 0 );

        // Recupera tutte le pagine WordPress.
        $pagine = get_posts( array(
            'post_type'      => 'page',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'post_status'    => 'publish',
        ) );

        $saved = isset( $_GET['saved'] ) && '1' === $_GET['saved'];
        $demo_generated = isset( $_GET['demo_generated'] ) && '1' === $_GET['demo_generated'];
        $logs_cleared   = isset( $_GET['logs_cleared'] ) && '1' === $_GET['logs_cleared'];
        $logs           = Logger::get_logs( 200 );
        ?>
        <div class="wrap">
            <h1>Plugin CRAL BCC — Impostazioni</h1>

            <?php if ( $saved ) : ?>
                <div class="notice notice-success is-dismissible">
                    <p>Impostazioni salvate correttamente.</p>
                </div>
            <?php endif; ?>

            <?php if ( $demo_generated ) : ?>
                <div class="notice notice-success is-dismissible">
                    <p>Dati demo generati correttamente.</p>
                </div>
            <?php endif; ?>

            <?php if ( $logs_cleared ) : ?>
                <div class="notice notice-success is-dismissible">
                    <p>Log svuotato correttamente.</p>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'cral_save_impostazioni', 'cral_impostazioni_nonce' ); ?>
                <input type="hidden" name="action" value="cral_save_impostazioni">

                <h2>Email</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="cral_email_segreteria">Email segreteria</label>
                        </th>
                        <td>
                            <input
                                type="email"
                                id="cral_email_segreteria"
                                name="cral_email_segreteria"
                                value="<?php echo esc_attr( $email_segreteria ); ?>"
                                class="regular-text"
                            >
                            <p class="description">
                                Indirizzo email che riceve le notifiche di nuova prenotazione.
                            </p>
                        </td>
                    </tr>
                </table>

                <h2>Pagine</h2>
                <p class="description" style="margin-bottom: 15px;">
                    Associa le pagine WordPress agli shortcode del plugin.
                    Le pagine devono contenere i rispettivi shortcode.
                </p>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="cral_pagina_login">Pagina di login</label>
                        </th>
                        <td>
                            <select id="cral_pagina_login" name="cral_pagina_login">
                                <option value="0">— Seleziona pagina —</option>
                                <?php foreach ( $pagine as $pagina ) : ?>
                                    <option value="<?php echo esc_attr( $pagina->ID ); ?>"
                                        <?php selected( $pagina_login, $pagina->ID ); ?>>
                                        <?php echo esc_html( $pagina->post_title ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Shortcode richiesto: <code>[cral_login]</code></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="cral_pagina_area_soci">Pagina area soci</label>
                        </th>
                        <td>
                            <select id="cral_pagina_area_soci" name="cral_pagina_area_soci">
                                <option value="0">— Seleziona pagina —</option>
                                <?php foreach ( $pagine as $pagina ) : ?>
                                    <option value="<?php echo esc_attr( $pagina->ID ); ?>"
                                        <?php selected( $pagina_area_soci, $pagina->ID ); ?>>
                                        <?php echo esc_html( $pagina->post_title ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Shortcode richiesto: <code>[cral_area_soci]</code></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="cral_pagina_imposta_password">Pagina imposta password</label>
                        </th>
                        <td>
                            <select id="cral_pagina_imposta_password" name="cral_pagina_imposta_password">
                                <option value="0">— Seleziona pagina —</option>
                                <?php foreach ( $pagine as $pagina ) : ?>
                                    <option value="<?php echo esc_attr( $pagina->ID ); ?>"
                                        <?php selected( $pagina_imposta_pwd, $pagina->ID ); ?>>
                                        <?php echo esc_html( $pagina->post_title ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Shortcode richiesto: <code>[cral_imposta_password]</code></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="cral_pagina_recupera_password">Pagina recupera password</label>
                        </th>
                        <td>
                            <select id="cral_pagina_recupera_password" name="cral_pagina_recupera_password">
                                <option value="0">— Seleziona pagina —</option>
                                <?php foreach ( $pagine as $pagina ) : ?>
                                    <option value="<?php echo esc_attr( $pagina->ID ); ?>"
                                        <?php selected( $pagina_recupera_pwd, $pagina->ID ); ?>>
                                        <?php echo esc_html( $pagina->post_title ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Shortcode richiesto: <code>[cral_reset_password]</code></p>
                        </td>
                    </tr>
                </table>

                <?php submit_button( 'Salva impostazioni' ); ?>
            </form>

            <hr style="margin: 30px 0;">

            <h2>Strumenti test</h2>
            <p class="description">
                Genera automaticamente 5 soci demo, 10 eventi demo e 3-4 prenotazioni per ogni socio.
            </p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:25px;">
                <?php wp_nonce_field( 'cral_generate_demo_data', 'cral_demo_nonce' ); ?>
                <input type="hidden" name="action" value="cral_generate_demo_data">
                <?php submit_button( 'Genera dati demo', 'secondary', 'submit', false ); ?>
            </form>

            <h2>Strumenti Elementor</h2>
            <p class="description">
                Pulisce la cache locale Elementor nel browser corrente.
            </p>
            <p style="margin-bottom: 25px;">
                <button type="button" class="button" id="cral-reset-elementor-cache">
                    Reset cache Elementor browser
                </button>
            </p>

            <h2>Log operazioni</h2>
            <p class="description">
                Tracciamento creazione soci, eventi, prenotazioni e operazioni demo.
            </p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:15px;">
                <?php wp_nonce_field( 'cral_clear_logs', 'cral_clear_logs_nonce' ); ?>
                <input type="hidden" name="action" value="cral_clear_logs">
                <?php submit_button( 'Svuota log', 'delete', 'submit', false ); ?>
            </form>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:140px;">Quando</th>
                        <th style="width:160px;">Operazione</th>
                        <th style="width:320px;">Messaggio</th>
                        <th>Contesto</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $logs ) ) : ?>
                        <tr>
                            <td colspan="4">Nessun log disponibile.</td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ( $logs as $log ) : ?>
                            <tr>
                                <td><?php echo esc_html( wp_date( 'd/m/Y H:i:s', strtotime( $log->created_at ) ) ); ?></td>
                                <td><code><?php echo esc_html( $log->operation ); ?></code></td>
                                <td><?php echo esc_html( $log->message ); ?></td>
                                <td>
                                    <code><?php echo esc_html( (string) $log->context ); ?></code>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const btn = document.getElementById('cral-reset-elementor-cache');
                if (!btn) return;
                btn.addEventListener('click', function() {
                    let removed = 0;
                    try {
                        localStorage.removeItem('e_library');
                        removed++;
                    } catch (e) {}
                    try {
                        Object.keys(localStorage)
                            .filter(k => k.startsWith('e_') || k.startsWith('elementor'))
                            .forEach(k => {
                                localStorage.removeItem(k);
                                removed++;
                            });
                        alert('Cache Elementor ripulita. Chiavi rimosse: ' + removed + '. Ricarica con Ctrl+F5.');
                    } catch (e) {
                        alert('Impossibile ripulire la cache Elementor da questo browser.');
                    }
                });
            });
            </script>
        </div>
        <?php
    }

    /**
     * Gestisce il salvataggio delle impostazioni.
     */
    public function handle_save_impostazioni() {
        if ( ! isset( $_POST['cral_impostazioni_nonce'] ) ||
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cral_impostazioni_nonce'] ) ), 'cral_save_impostazioni' ) ) {
            wp_die( 'Nonce non valido.' );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Accesso negato.' );
        }

        update_option( 'cral_email_segreteria', sanitize_email( $_POST['cral_email_segreteria'] ?? '' ) );
        update_option( 'cral_pagina_login', absint( $_POST['cral_pagina_login'] ?? 0 ) );
        update_option( 'cral_pagina_area_soci', absint( $_POST['cral_pagina_area_soci'] ?? 0 ) );
        update_option( 'cral_pagina_imposta_password', absint( $_POST['cral_pagina_imposta_password'] ?? 0 ) );
        update_option( 'cral_pagina_recupera_password', absint( $_POST['cral_pagina_recupera_password'] ?? 0 ) );

        wp_redirect( admin_url( 'admin.php?page=g-event-impostazioni&saved=1' ) );
        exit;
    }

    /**
     * Genera dataset demo.
     */
    public function handle_generate_demo_data() {
        if ( ! isset( $_POST['cral_demo_nonce'] ) ||
            ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cral_demo_nonce'] ) ), 'cral_generate_demo_data' ) ) {
            wp_die( 'Nonce non valido.' );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Accesso negato.' );
        }

        $generator = new Demo_Generator();
        $result    = $generator->generate();

        Logger::log(
            'demo_generation',
            'Generazione dati demo completata',
            $result
        );

        wp_redirect( admin_url( 'admin.php?page=g-event-impostazioni&demo_generated=1' ) );
        exit;
    }

    /**
     * Pulisce tutti i log.
     */
    public function handle_clear_logs() {
        if ( ! isset( $_POST['cral_clear_logs_nonce'] ) ||
            ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cral_clear_logs_nonce'] ) ), 'cral_clear_logs' ) ) {
            wp_die( 'Nonce non valido.' );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Accesso negato.' );
        }

        Logger::clear_logs();
        wp_redirect( admin_url( 'admin.php?page=g-event-impostazioni&logs_cleared=1' ) );
        exit;
    }

    /**
     * Log creazione socio.
     *
     * @param int     $post_id ID post.
     * @param \WP_Post $post Post.
     * @param bool    $update Update flag.
     */
    public function log_created_socio( $post_id, $post, $update ) {
        if ( $update || wp_is_post_revision( $post_id ) ) {
            return;
        }

        Logger::log(
            'create_socio',
            'Creato socio "' . $post->post_title . '"',
            array( 'post_id' => $post_id )
        );
    }

    /**
     * Log creazione evento.
     *
     * @param int      $post_id ID post.
     * @param \WP_Post $post Post.
     * @param bool     $update Update flag.
     */
    public function log_created_evento( $post_id, $post, $update ) {
        if ( $update || wp_is_post_revision( $post_id ) ) {
            return;
        }

        Logger::log(
            'create_evento',
            'Creato evento "' . $post->post_title . '"',
            array( 'post_id' => $post_id )
        );
    }

    /**
     * Log creazione prenotazione.
     *
     * @param int      $post_id ID post.
     * @param \WP_Post $post Post.
     * @param bool     $update Update flag.
     */
    public function log_created_prenotazione( $post_id, $post, $update ) {
        if ( $update || wp_is_post_revision( $post_id ) ) {
            return;
        }

        Logger::log(
            'create_prenotazione',
            'Creata prenotazione "' . $post->post_title . '"',
            array( 'post_id' => $post_id )
        );
    }
}
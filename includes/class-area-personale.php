<?php
/**
 * Shortcode [area-personale] — profilo socio + eventi prenotati.
 *
 * @package GEvent
 */

namespace GEvent;

/**
 * Area personale socio loggato.
 */
class Area_Personale {

    /**
     * Registra hook.
     */
    public function init() {
        add_shortcode( 'area-personale', array( $this, 'render' ) );
        add_action( 'init', array( $this, 'maybe_seed_mario_demo' ), 40 );
    }

    /**
     * Seed una tantum: Mario ha 3 prenotazioni future e 4 passate.
     */
    public function maybe_seed_mario_demo() {
        if ( get_option( 'g_event_mario_area_demo_v1' ) ) {
            return;
        }

        $socio_id = $this->find_demo_socio_id();
        if ( ! $socio_id ) {
            return;
        }

        // Completa anagrafica demo se mancante.
        if ( ! get_post_meta( $socio_id, '_cral_data_nascita', true ) ) {
            update_post_meta( $socio_id, '_cral_data_nascita', '1988-03-14' );
        }
        if ( ! get_post_meta( $socio_id, '_cral_email', true ) ) {
            update_post_meta( $socio_id, '_cral_email', 'socio.demo@example.com' );
        }

        $now = current_time( 'mysql' );

        $upcoming_ids = $this->get_event_ids_by_time( 'future', 12 );
        $past_ids     = $this->get_event_ids_by_time( 'past', 12 );

        // Se mancano eventi passati, duplica 4 verso settembre (passato).
        if ( count( $past_ids ) < 4 ) {
            $past_ids = array_merge( $past_ids, $this->create_past_september_events( 4 - count( $past_ids ) ) );
            $past_ids = array_values( array_unique( array_filter( array_map( 'absint', $past_ids ) ) ) );
        }

        // Se mancano eventi futuri, duplica da esistenti con date prossime.
        if ( count( $upcoming_ids ) < 3 ) {
            $upcoming_ids = array_merge( $upcoming_ids, $this->create_future_events( 3 - count( $upcoming_ids ) ) );
            $upcoming_ids = array_values( array_unique( array_filter( array_map( 'absint', $upcoming_ids ) ) ) );
        }

        $booked_upcoming = $this->count_socio_bookings( $socio_id, 'future' );
        $booked_past     = $this->count_socio_bookings( $socio_id, 'past' );

        $need_up = max( 0, 3 - $booked_upcoming );
        $need_pa = max( 0, 4 - $booked_past );

        $already = $this->get_socio_booked_event_ids( $socio_id );

        foreach ( $upcoming_ids as $eid ) {
            if ( $need_up <= 0 ) {
                break;
            }
            if ( in_array( $eid, $already, true ) ) {
                continue;
            }
            if ( $this->create_demo_prenotazione( $socio_id, $eid ) ) {
                $already[] = $eid;
                $need_up--;
            }
        }

        foreach ( $past_ids as $eid ) {
            if ( $need_pa <= 0 ) {
                break;
            }
            if ( in_array( $eid, $already, true ) ) {
                continue;
            }
            if ( $this->create_demo_prenotazione( $socio_id, $eid ) ) {
                $already[] = $eid;
                $need_pa--;
            }
        }

        update_option( 'g_event_mario_area_demo_v1', '1', false );
    }

    /**
     * @return int Socio post ID o 0.
     */
    protected function find_demo_socio_id() {
        $found = get_posts(
            array(
                'post_type'      => 'socio',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'meta_query'     => array(
                    array(
                        'key'   => '_cral_socio_id',
                        'value' => 'SOCIODEMO',
                    ),
                ),
            )
        );
        return ! empty( $found ) ? (int) $found[0] : 0;
    }

    /**
     * @param string $when future|past.
     * @param int    $limit Limite.
     * @return int[]
     */
    protected function get_event_ids_by_time( $when, $limit = 12 ) {
        $now = current_time( 'mysql' );
        $compare = ( 'future' === $when ) ? '>=' : '<';
        $order   = ( 'future' === $when ) ? 'ASC' : 'DESC';

        $q = get_posts(
            array(
                'post_type'      => 'evento',
                'post_status'    => 'publish',
                'posts_per_page' => $limit,
                'fields'         => 'ids',
                'orderby'        => 'meta_value',
                'meta_key'       => '_cral_evento_data',
                'order'          => $order,
                'meta_query'     => array(
                    'relation' => 'AND',
                    array(
                        'key'     => '_cral_evento_data',
                        'value'   => $now,
                        'compare' => $compare,
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

        return array_map( 'absint', $q );
    }

    /**
     * Crea N eventi futuri duplicando sorgenti esistenti.
     *
     * @param int $need Quanti crearne.
     * @return int[]
     */
    protected function create_future_events( $need ) {
        $need = max( 0, min( 6, (int) $need ) );
        if ( $need < 1 ) {
            return array();
        }

        $sources = get_posts(
            array(
                'post_type'      => 'evento',
                'post_status'    => 'publish',
                'posts_per_page' => max( 3, $need ),
                'orderby'        => 'date',
                'order'          => 'DESC',
            )
        );

        if ( empty( $sources ) ) {
            return array();
        }

        $base = current_time( 'timestamp' );
        $dates = array(
            wp_date( 'Y-m-d', strtotime( '+10 days', $base ) ) . ' 18:30:00',
            wp_date( 'Y-m-d', strtotime( '+20 days', $base ) ) . ' 17:00:00',
            wp_date( 'Y-m-d', strtotime( '+35 days', $base ) ) . ' 20:00:00',
            wp_date( 'Y-m-d', strtotime( '+50 days', $base ) ) . ' 11:00:00',
        );

        $cpt     = new CPT_Evento();
        $created = array();
        $i       = 0;
        foreach ( $sources as $source ) {
            if ( count( $created ) >= $need ) {
                break;
            }
            $dt  = $dates[ $i % count( $dates ) ];
            $nid = $cpt->duplicate_evento_post( $source->ID, $dt );
            if ( $nid ) {
                // Riapri come pubblicato (duplicate mette concluso).
                update_post_meta( $nid, '_cral_evento_stato', 'pubblicato' );
                $ts = strtotime( $dt );
                if ( $ts ) {
                    update_post_meta( $nid, '_cral_evento_data_apertura_iscrizioni', wp_date( 'Y-m-d', strtotime( '-7 days', $ts ) ) . ' 00:00:00' );
                    update_post_meta( $nid, '_cral_evento_data_iscrizione', wp_date( 'Y-m-d', strtotime( '-1 day', $ts ) ) . ' 23:59:00' );
                }
                $created[] = $nid;
            }
            $i++;
        }

        return $created;
    }

    /**
     * Crea N eventi passati a settembre (anno scorso se settembre non è ancora passato).
     *
     * @param int $need Quanti crearne.
     * @return int[]
     */
    protected function create_past_september_events( $need ) {
        $need = max( 0, min( 8, (int) $need ) );
        if ( $need < 1 ) {
            return array();
        }

        $sources = get_posts(
            array(
                'post_type'      => 'evento',
                'post_status'    => 'publish',
                'posts_per_page' => max( 4, $need ),
                'orderby'        => 'date',
                'order'          => 'DESC',
            )
        );

        if ( empty( $sources ) ) {
            return array();
        }

        $y = (int) wp_date( 'Y' );
        $m = (int) wp_date( 'n' );
        // Verso settembre già trascorso.
        $sept_year = ( $m > 9 ) ? $y : ( $y - 1 );

        $dates = array(
            sprintf( '%04d-09-06 18:30:00', $sept_year ),
            sprintf( '%04d-09-13 17:00:00', $sept_year ),
            sprintf( '%04d-09-20 20:00:00', $sept_year ),
            sprintf( '%04d-09-27 11:00:00', $sept_year ),
            sprintf( '%04d-09-10 19:00:00', $sept_year ),
            sprintf( '%04d-09-17 16:30:00', $sept_year ),
        );

        $cpt = new CPT_Evento();
        $created = array();
        $i = 0;
        foreach ( $sources as $source ) {
            if ( count( $created ) >= $need ) {
                break;
            }
            $dt  = $dates[ $i % count( $dates ) ];
            $nid = $cpt->duplicate_evento_post( $source->ID, $dt );
            if ( $nid ) {
                // Titolo distinguibile.
                wp_update_post(
                    array(
                        'ID'         => $nid,
                        'post_title' => get_the_title( $source->ID ) . ' (passato)',
                    )
                );
                $created[] = $nid;
            }
            $i++;
        }

        return $created;
    }

    /**
     * @param int    $socio_id Socio.
     * @param string $when     future|past.
     * @return int
     */
    protected function count_socio_bookings( $socio_id, $when ) {
        return count( $this->get_socio_bookings( $socio_id, $when ) );
    }

    /**
     * @param int $socio_id Socio.
     * @return int[]
     */
    protected function get_socio_booked_event_ids( $socio_id ) {
        $ids = array();
        foreach ( array( 'future', 'past' ) as $when ) {
            foreach ( $this->get_socio_bookings( $socio_id, $when ) as $row ) {
                $ids[] = (int) $row['evento_id'];
            }
        }
        return array_values( array_unique( $ids ) );
    }

    /**
     * @param int $socio_id Socio.
     * @param int $evento_id Evento.
     * @return bool
     */
    protected function create_demo_prenotazione( $socio_id, $evento_id ) {
        $socio_id  = absint( $socio_id );
        $evento_id = absint( $evento_id );
        if ( ! $socio_id || ! $evento_id ) {
            return false;
        }

        $nome    = (string) get_post_meta( $socio_id, '_cral_nome', true );
        $cognome = (string) get_post_meta( $socio_id, '_cral_cognome', true );
        $prezzo  = (float) get_post_meta( $evento_id, '_cral_evento_prezzo_base', true );

        $post_id = wp_insert_post(
            array(
                'post_type'   => 'prenotazione',
                'post_status' => 'publish',
                'post_title'  => sprintf( 'Prenotazione demo — %s', get_the_title( $evento_id ) ),
            ),
            true
        );

        if ( is_wp_error( $post_id ) || ! $post_id ) {
            return false;
        }

        update_post_meta( $post_id, '_cral_pren_socio_id', $socio_id );
        update_post_meta( $post_id, '_cral_pren_evento_id', $evento_id );
        update_post_meta( $post_id, '_cral_pren_data', current_time( 'mysql' ) );
        update_post_meta( $post_id, '_cral_pren_stato', 'confermata' );
        update_post_meta( $post_id, '_cral_pren_totale_biglietti', 1 );
        update_post_meta( $post_id, '_cral_pren_importo_totale', $prezzo );
        update_post_meta( $post_id, '_cral_pren_pagamento', 'yes' );
        update_post_meta( $post_id, '_cral_pren_note', 'Prenotazione demo area personale' );

        if ( function_exists( 'carbon_set_post_meta' ) ) {
            carbon_set_post_meta(
                $post_id,
                'cral_partecipanti',
                array(
                    array(
                        'partecipante_nome'      => $nome ?: 'Mario',
                        'partecipante_cognome'   => $cognome ?: 'Rossi',
                        'partecipante_tipologia' => 'Socio',
                        'partecipante_prezzo'    => $prezzo,
                    ),
                )
            );
        }

        return true;
    }

    /**
     * Prenotazioni socio filtrate per data evento.
     *
     * @param int    $socio_id Socio.
     * @param string $when     future|past.
     * @return array<int, array<string, mixed>>
     */
    protected function get_socio_bookings( $socio_id, $when ) {
        $prenotazioni = get_posts(
            array(
                'post_type'      => 'prenotazione',
                'posts_per_page' => -1,
                'meta_query'     => array(
                    'relation' => 'AND',
                    array(
                        'key'   => '_cral_pren_socio_id',
                        'value' => $socio_id,
                    ),
                    array(
                        'key'     => '_cral_pren_stato',
                        'value'   => 'annullata',
                        'compare' => '!=',
                    ),
                ),
            )
        );

        $now = current_time( 'timestamp' );
        $out = array();

        foreach ( $prenotazioni as $pren ) {
            $evento_id = (int) get_post_meta( $pren->ID, '_cral_pren_evento_id', true );
            if ( ! $evento_id || 'evento' !== get_post_type( $evento_id ) ) {
                continue;
            }
            $data_raw = (string) get_post_meta( $evento_id, '_cral_evento_data', true );
            $ts       = $data_raw ? strtotime( $data_raw ) : 0;
            if ( ! $ts ) {
                continue;
            }

            $is_future = $ts >= $now;
            if ( ( 'future' === $when && ! $is_future ) || ( 'past' === $when && $is_future ) ) {
                continue;
            }

            $out[] = array(
                'pren_id'   => (int) $pren->ID,
                'evento_id' => $evento_id,
                'ts'        => $ts,
                'stato'     => (string) get_post_meta( $pren->ID, '_cral_pren_stato', true ),
                'biglietti' => (int) get_post_meta( $pren->ID, '_cral_pren_totale_biglietti', true ),
                'importo'   => (float) get_post_meta( $pren->ID, '_cral_pren_importo_totale', true ),
            );
        }

        usort(
            $out,
            static function( $a, $b ) use ( $when ) {
                if ( 'future' === $when ) {
                    return $a['ts'] <=> $b['ts'];
                }
                return $b['ts'] <=> $a['ts'];
            }
        );

        return $out;
    }

    /**
     * Enqueue asset.
     */
    protected function enqueue_assets() {
        static $done = false;
        if ( $done ) {
            return;
        }
        $done = true;

        $base = plugin_dir_url( dirname( __FILE__ ) );

        wp_enqueue_style(
            'g-event-area-font',
            'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap',
            array(),
            null
        );
        wp_enqueue_style(
            'g-event-scheda',
            $base . 'assets/css/scheda-evento.css',
            array( 'g-event-area-font' ),
            '2.2.7'
        );
        wp_enqueue_style(
            'g-event-calendario',
            $base . 'assets/css/calendario-eventi.css',
            array( 'g-event-scheda' ),
            '1.7.5'
        );
        wp_enqueue_style(
            'g-event-area-personale',
            $base . 'assets/css/area-personale.css',
            array( 'g-event-scheda', 'g-event-calendario' ),
            '1.1.1'
        );
        wp_enqueue_script(
            'g-event-area-personale',
            $base . 'assets/js/area-personale.js',
            array(),
            '1.1.1',
            true
        );
    }

    /**
     * Render shortcode.
     *
     * @return string
     */
    public function render() {
        $auth     = new Auth();
        $socio_id = $auth->get_current_socio();

        if ( ! $socio_id && ! Auth_Frontend::is_cral_authenticated() ) {
            wp_safe_redirect( Auth_Frontend::get_home_page_url() );
            exit;
        }

        $this->enqueue_assets();

        $login_url = Auth_Frontend::get_login_page_url();

        if ( ! $socio_id ) {
            ob_start();
            ?>
            <div class="cral-area">
                <div class="cral-area__band cral-area__band--top">
                    <div class="cral-area__inner">
                        <div class="cral-area__login-prompt">
                            <h1 class="cral-area__title"><?php esc_html_e( 'Area personale', 'g-event' ); ?></h1>
                            <p class="cral-area__excerpt"><?php esc_html_e( 'Accedi con il tuo account socio per vedere le prenotazioni e gli eventi a cui hai partecipato.', 'g-event' ); ?></p>
                            <?php if ( $login_url ) : ?>
                            <a class="cral-scheda__btn cral-scheda__btn--secondary" href="<?php echo esc_url( $login_url ); ?>">
                                <?php esc_html_e( 'Accedi al tuo account', 'g-event' ); ?>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }

        $nome         = (string) get_post_meta( $socio_id, '_cral_nome', true );
        $cognome      = (string) get_post_meta( $socio_id, '_cral_cognome', true );
        $email        = (string) get_post_meta( $socio_id, '_cral_email', true );
        $matricola   = (string) get_post_meta( $socio_id, '_cral_socio_id', true );
        $nascita_raw  = (string) get_post_meta( $socio_id, '_cral_data_nascita', true );
        $nascita_fmt  = '';
        if ( $nascita_raw ) {
            $nts = strtotime( $nascita_raw );
            $nascita_fmt = $nts ? wp_date( 'd/m/Y', $nts ) : $nascita_raw;
        }

        $upcoming = $this->get_socio_bookings( $socio_id, 'future' );
        $past     = $this->get_socio_bookings( $socio_id, 'past' );

        $full_name = trim( $nome . ' ' . $cognome );
        if ( ! $full_name ) {
            $full_name = __( 'Socio', 'g-event' );
        }

        $initials = $this->get_initials( $nome, $cognome );
        $avatar_hue = $this->avatar_hue( $full_name . $matricola );

        ob_start();
        ?>
        <div class="cral-area" id="cral-area-<?php echo esc_attr( (string) $socio_id ); ?>">

            <div class="cral-area__band cral-area__band--top">
                <div class="cral-area__inner">
                    <header class="cral-area__header">
                        <div class="cral-area__intro">
                            <div class="cral-area__tags">
                                <span class="cral-scheda__badge cral-scheda__badge--aperto">
                                    <span class="cral-scheda__badge-title"><?php esc_html_e( 'Area personale', 'g-event' ); ?></span>
                                </span>
                                <span class="cral-scheda__badge cral-scheda__badge--programmato">
                                    <span class="cral-scheda__badge-title"><?php echo esc_html( $matricola ?: 'Socio' ); ?></span>
                                </span>
                            </div>

                            <div class="cral-area__identity">
                                <div class="cral-area__avatar" style="--cral-avatar-hue: <?php echo esc_attr( (string) $avatar_hue ); ?>;" aria-hidden="true">
                                    <span class="cral-area__avatar-initials"><?php echo esc_html( $initials ); ?></span>
                                    <span class="cral-area__avatar-icon">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="3.5"/><path d="M5 19.5c1.5-3.2 4-4.8 7-4.8s5.5 1.6 7 4.8"/></svg>
                                    </span>
                                </div>
                                <div class="cral-area__identity-text">
                                    <p class="cral-area__hello"><?php esc_html_e( 'Ciao,', 'g-event' ); ?></p>
                                    <h1 class="cral-area__title"><?php echo esc_html( $full_name ); ?></h1>
                                    <p class="cral-area__excerpt"><?php esc_html_e( 'Qui trovi i tuoi dati e le prenotazioni agli eventi del CRAL.', 'g-event' ); ?></p>
                                </div>
                            </div>

                            <ul class="cral-scheda__facts-inline cral-area__facts" aria-label="<?php esc_attr_e( 'Dati socio', 'g-event' ); ?>">
                                <?php if ( $matricola ) : ?>
                                <li class="cral-scheda__fi cral-scheda__fi--date">
                                    <span class="cral-scheda__fi-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><circle cx="12" cy="12" r="3"/></svg>
                                    </span>
                                    <span class="cral-scheda__fi-text">
                                        <strong><?php echo esc_html( $matricola ); ?></strong>
                                        <span><?php esc_html_e( 'id socio', 'g-event' ); ?></span>
                                    </span>
                                </li>
                                <?php endif; ?>

                                <?php if ( $email ) : ?>
                                <li class="cral-scheda__fi cral-scheda__fi--time">
                                    <span class="cral-scheda__fi-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16v12H4z"/><path d="m4 7 8 6 8-6"/></svg>
                                    </span>
                                    <span class="cral-scheda__fi-text">
                                        <strong><?php echo esc_html( $email ); ?></strong>
                                        <span><?php esc_html_e( 'email', 'g-event' ); ?></span>
                                    </span>
                                </li>
                                <?php endif; ?>

                                <?php if ( $nascita_fmt ) : ?>
                                <li class="cral-scheda__fi cral-scheda__fi--place">
                                    <span class="cral-scheda__fi-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                    </span>
                                    <span class="cral-scheda__fi-text">
                                        <strong><?php echo esc_html( $nascita_fmt ); ?></strong>
                                        <span><?php esc_html_e( 'data di nascita', 'g-event' ); ?></span>
                                    </span>
                                </li>
                                <?php endif; ?>

                                <li class="cral-scheda__fi cral-scheda__fi--deadline">
                                    <span class="cral-scheda__fi-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3"/><path d="M3.5 20v-1.4c0-2.2 2.5-3.6 5.5-3.6s5.5 1.4 5.5 3.6V20"/><circle cx="17" cy="9" r="2.5"/><path d="M14.5 20v-1.1c0-1.6 1.7-2.6 3.5-2.6"/></svg>
                                    </span>
                                    <span class="cral-scheda__fi-text">
                                        <strong><?php echo esc_html( (string) count( $upcoming ) ); ?> · <?php echo esc_html( (string) count( $past ) ); ?></strong>
                                        <span><?php esc_html_e( 'prossimi · passati', 'g-event' ); ?></span>
                                    </span>
                                </li>
                            </ul>
                        </div>

                        <div class="cral-area__actions">
                            <?php echo do_shortcode( '[cral_logout]' ); ?>
                        </div>
                    </header>
                </div>
            </div>

            <div class="cral-area__band cral-area__band--body">
                <div class="cral-area__inner">

                    <section class="cral-area__section" aria-labelledby="cral-area-upcoming">
                        <div class="cral-area__section-head">
                            <h2 id="cral-area-upcoming" class="cral-scheda__section-title"><?php esc_html_e( 'Prossimi eventi prenotati', 'g-event' ); ?></h2>
                            <div class="cral-cal__carousel-nav" aria-label="<?php esc_attr_e( 'Navigazione prossimi eventi', 'g-event' ); ?>">
                                <button type="button" class="cral-cal__carousel-btn" data-area-up-prev aria-label="<?php esc_attr_e( 'Precedenti', 'g-event' ); ?>">
                                    <span aria-hidden="true">&#8249;</span>
                                </button>
                                <button type="button" class="cral-cal__carousel-btn" data-area-up-next aria-label="<?php esc_attr_e( 'Successivi', 'g-event' ); ?>">
                                    <span aria-hidden="true">&#8250;</span>
                                </button>
                            </div>
                        </div>
                        <div class="cral-cal__carousel" data-area-up-carousel>
                            <div class="cral-cal__product-grid cral-cal__product-grid--carousel cral-area__track cral-area__track--upcoming" data-area-upcoming>
                                <?php echo $this->render_slot_track( $upcoming, 4, false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </div>
                        </div>
                    </section>

                    <section class="cral-area__section" aria-labelledby="cral-area-past">
                        <div class="cral-area__section-head">
                            <div class="cral-area__section-titles">
                                <h2 id="cral-area-past" class="cral-scheda__section-title"><?php esc_html_e( 'Eventi passati', 'g-event' ); ?></h2>
                                <p class="cral-area__section-meta">
                                    <?php
                                    printf(
                                        /* translators: %d: number of past events */
                                        esc_html__( 'Numero eventi a cui hai partecipato: %d', 'g-event' ),
                                        count( $past )
                                    );
                                    ?>
                                </p>
                            </div>
                            <div class="cral-cal__carousel-nav" aria-label="<?php esc_attr_e( 'Navigazione eventi passati', 'g-event' ); ?>">
                                <button type="button" class="cral-cal__carousel-btn" data-area-past-prev aria-label="<?php esc_attr_e( 'Precedenti', 'g-event' ); ?>">
                                    <span aria-hidden="true">&#8249;</span>
                                </button>
                                <button type="button" class="cral-cal__carousel-btn" data-area-past-next aria-label="<?php esc_attr_e( 'Successivi', 'g-event' ); ?>">
                                    <span aria-hidden="true">&#8250;</span>
                                </button>
                            </div>
                        </div>
                        <div class="cral-cal__carousel" data-area-past-carousel>
                            <div class="cral-cal__past-grid cral-cal__past-grid--carousel cral-area__track cral-area__track--past" data-area-past>
                                <?php echo $this->render_slot_track( $past, 6, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </div>
                        </div>
                    </section>

                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Track carosello con slot fissi (vuoti se mancano eventi).
     *
     * @param array $rows  Prenotazioni.
     * @param int   $slots Numero minimo slot.
     * @param bool  $past  Passati.
     * @return string
     */
    protected function render_slot_track( $rows, $slots, $past ) {
        $html  = '';
        $count = 0;
        foreach ( $rows as $row ) {
            $html .= $this->render_event_card( $row, $past );
            $count++;
        }
        while ( $count < $slots ) {
            $html .= $this->render_empty_slot( $past );
            $count++;
        }
        return $html;
    }

    /**
     * Placeholder slot vuoto.
     *
     * @param bool $past Passato.
     * @return string
     */
    protected function render_empty_slot( $past = false ) {
        $cls = 'cral-area__slot-empty' . ( $past ? ' cral-area__slot-empty--past' : '' );
        return '<div class="' . esc_attr( $cls ) . '" aria-hidden="true"></div>';
    }

    /**
     * Iniziali avatar.
     *
     * @param string $nome    Nome.
     * @param string $cognome Cognome.
     * @return string
     */
    protected function get_initials( $nome, $cognome ) {
        $a = function_exists( 'mb_substr' ) ? mb_substr( trim( (string) $nome ), 0, 1 ) : substr( trim( (string) $nome ), 0, 1 );
        $b = function_exists( 'mb_substr' ) ? mb_substr( trim( (string) $cognome ), 0, 1 ) : substr( trim( (string) $cognome ), 0, 1 );
        $out = strtoupper( $a . $b );
        return $out !== '' ? $out : 'CR';
    }

    /**
     * Hue avatar da stringa.
     *
     * @param string $seed Seed.
     * @return int
     */
    protected function avatar_hue( $seed ) {
        $h = 0;
        $s = (string) $seed;
        $len = strlen( $s );
        for ( $i = 0; $i < $len; $i++ ) {
            $h = ( $h * 31 + ord( $s[ $i ] ) ) % 360;
        }
        return (int) $h;
    }

    /**
     * Card evento stile calendario.
     *
     * @param array $row  Booking row.
     * @param bool  $past Passato.
     * @return string
     */
    protected function render_event_card( $row, $past = false ) {
        $evento_id = (int) $row['evento_id'];
        $event     = $this->format_card_event( $evento_id, $row, $past );
        if ( empty( $event ) ) {
            return '';
        }

        $img       = $event['thumb_md'] ?: $event['thumb'];
        $img_style = $img ? 'background-image:url(\'' . esc_url( $img ) . '\');' : '';
        $cat_bg    = $event['categoria_colore'];
        $cat_fg    = $event['categoria_testo'];
        $mod       = $past ? 'concluso' : $event['stato_mod'];
        $cls       = 'cral-cal-product' . ( $past ? ' cral-cal-product--past' : '' );

        ob_start();
        ?>
        <a class="<?php echo esc_attr( $cls ); ?>"
           href="<?php echo esc_url( $event['url'] ); ?>"
           data-event-id="<?php echo esc_attr( (string) $evento_id ); ?>"
           style="--cral-cat:<?php echo esc_attr( $cat_bg ); ?>;">
            <span class="cral-cal-product__media<?php echo $img ? '' : ' cral-cal-product__media--ph'; ?>"<?php echo $img_style ? ' style="' . esc_attr( $img_style ) . '"' : ''; ?>>
                <span class="cral-cal-product__shine" aria-hidden="true"></span>
                <span class="cral-cal-product__booked cral-cal-product__booked--<?php echo $past ? 'partecipato' : 'iscritto'; ?>">
                    <?php echo $past ? esc_html__( 'Partecipato', 'g-event' ) : esc_html__( 'Già iscritto', 'g-event' ); ?>
                </span>
                <?php if ( $event['categoria'] ) : ?>
                <span class="cral-cal-product__cat" style="background:<?php echo esc_attr( $cat_bg ); ?>;color:<?php echo esc_attr( $cat_fg ); ?>">
                    <?php echo esc_html( $event['categoria'] ); ?>
                </span>
                <?php endif; ?>
                <?php if ( $past && $event['data_card'] ) : ?>
                <span class="cral-cal-product__date-badge">
                    <time datetime="<?php echo esc_attr( $event['data_raw'] ); ?>"><?php echo esc_html( $event['data_card'] ); ?></time>
                </span>
                <?php endif; ?>
            </span>
            <span class="cral-cal-product__body">
                <?php if ( ! $past && $event['data_card'] ) : ?>
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
                        <?php echo esc_html( $past ? __( 'Partecipato', 'g-event' ) : ( $event['stato_label'] ?: __( 'Prenotato', 'g-event' ) ) ); ?>
                    </span>
                    <span class="cral-cal-product__parti">
                        <?php
                        printf(
                            /* translators: tickets count */
                            esc_html( _n( '%d biglietto', '%d biglietti', max( 1, (int) $row['biglietti'] ), 'g-event' ) ),
                            max( 1, (int) $row['biglietti'] )
                        );
                        ?>
                    </span>
                </span>
                <span class="cral-cal-product__cta">
                    <?php echo $past ? esc_html__( 'Vedi evento', 'g-event' ) : esc_html__( 'Vai alla scheda', 'g-event' ); ?>
                    <span class="cral-cal-product__cta-arrow" aria-hidden="true">→</span>
                </span>
            </span>
        </a>
        <?php
        return ob_get_clean();
    }

    /**
     * Dati card da ID evento.
     *
     * @param int   $evento_id ID.
     * @param array $row       Booking.
     * @param bool  $past      Passato.
     * @return array<string, mixed>
     */
    protected function format_card_event( $evento_id, $row, $past ) {
        $evento_id = absint( $evento_id );
        if ( ! $evento_id ) {
            return array();
        }

        $data_raw = (string) get_post_meta( $evento_id, '_cral_evento_data', true );
        $ts       = $data_raw ? strtotime( $data_raw ) : (int) ( $row['ts'] ?? 0 );

        $thumb_id = get_post_thumbnail_id( $evento_id );
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

        $terms     = get_the_terms( $evento_id, 'categoria_evento' );
        $cat       = '';
        $cat_color = '#a7c957';
        $cat_text  = '#111827';
        if ( $terms && ! is_wp_error( $terms ) ) {
            $cat       = $terms[0]->name;
            $cat_color = CPT_Evento::get_categoria_colore( $terms[0] );
            $cat_text  = CPT_Evento::contrast_text_color( $cat_color );
        }

        $stato_pren = (string) ( $row['stato'] ?? '' );
        $stato_label = 'confermata' === $stato_pren
            ? __( 'Confermata', 'g-event' )
            : ( 'in_attesa' === $stato_pren ? __( 'In attesa', 'g-event' ) : __( 'Prenotato', 'g-event' ) );
        $stato_mod = 'confermata' === $stato_pren ? 'aperto' : ( 'in_attesa' === $stato_pren ? 'presto' : 'aperto' );

        if ( $past ) {
            $stato_label = __( 'Partecipato', 'g-event' );
            $stato_mod   = 'concluso';
        }

        return array(
            'title'            => get_the_title( $evento_id ),
            'url'              => get_permalink( $evento_id ),
            'data_raw'         => $data_raw,
            'data_card'        => $ts ? $this->format_product_date( $ts ) : '',
            'luogo'            => (string) get_post_meta( $evento_id, '_cral_evento_luogo', true ),
            'categoria'        => $cat,
            'categoria_colore' => $cat_color,
            'categoria_testo'  => $cat_text,
            'thumb'            => $thumb,
            'thumb_md'         => $thumb_md,
            'stato_label'      => $stato_label,
            'stato_mod'        => $stato_mod,
        );
    }

    /**
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
}

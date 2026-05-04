<?php

/**
 * Registrazione CPT Socio e campi Carbon Fields.
 *
 * @package GEvent
 */

namespace GEvent;

use Carbon_Fields\Container;
use Carbon_Fields\Field;

/**
 * Classe per la gestione del Custom Post Type Socio.
 */
class CPT_Socio
{

    /**
     * Registra gli hook WordPress.
     */
    public function init()
    {
        add_action('init', array($this, 'register_cpt'));
        add_action('carbon_fields_register_fields', array($this, 'register_fields'));
        add_action('save_post_socio', array($this, 'hash_password'), 10, 2);

        // Fase 1.3 — Lista admin personalizzata.
        add_filter('manage_socio_posts_columns', array($this, 'set_columns'));
        add_action('manage_socio_posts_custom_column', array($this, 'render_column'), 10, 2);
        add_filter('list_table_primary_column', array($this, 'set_primary_column'), 10, 2);
        add_action('admin_menu', array($this, 'register_scheda_menu'));
        add_filter('manage_edit-socio_sortable_columns', array($this, 'set_sortable_columns'));
        add_action('pre_get_posts', array($this, 'handle_sorting'));
        add_action('restrict_manage_posts', array($this, 'render_search_box'));
        add_action('pre_get_posts', array($this, 'handle_search'));

        // Fase 1.4 — Metabox gestione password.
        add_action('add_meta_boxes', array($this, 'register_password_metabox'));
        add_action('admin_footer', array($this, 'password_metabox_script'));
        add_action('manage_posts_extra_tablenav', array($this, 'render_import_button'));
    }

    /**
     * Registra il Custom Post Type socio.
     */
    public function register_cpt()
    {
        $labels = array(
            'name'               => 'Soci Cral Iscritti',
            'singular_name'      => 'Socio Cral Iscritto',
            'add_new'            => 'Aggiungi socio',
            'add_new_item'       => 'Aggiungi nuovo socio',
            'edit_item'          => 'Modifica socio',
            'new_item'           => 'Nuovo socio',
            'view_item'          => 'Visualizza socio',
            'search_items'       => 'Cerca soci',
            'not_found'          => 'Nessun socio trovato',
            'not_found_in_trash' => 'Nessun socio nel cestino',
        );

        $args = array(
            'labels'          => $labels,
            'public'          => false,
            'show_ui'         => true,
            'show_in_menu'    => 'g-event',
            'menu_icon'       => 'dashicons-groups',
            'supports'        => array('title'),
            'capability_type' => 'post',
        );

        register_post_type('socio', $args);
    }

    /**
     * Registra i campi Carbon Fields per il CPT socio.
     */
    public function register_fields()
    {
        Container::make('post_meta', 'Dati Socio')
            ->where('post_type', '=', 'socio')
            ->add_fields(array(

                Field::make('text', 'cral_socio_id', 'ID Socio / Matricola')
                    ->set_required(true)
                    ->set_help_text('Codice identificativo univoco del socio (es. matricola).'),

                Field::make('text', 'cral_nome', 'Nome')
                    ->set_required(true),

                Field::make('text', 'cral_cognome', 'Cognome')
                    ->set_required(true),

                Field::make('text', 'cral_email', 'Email')
                    ->set_required(true)
                    ->set_help_text('Usata per le notifiche di conferma prenotazione.'),

                Field::make('date', 'cral_data_nascita', 'Data di nascita')
                    ->set_help_text('Campo opzionale.'),

                Field::make('text', 'cral_password', 'Password')
                    ->set_attribute('type', 'password')
                    ->set_attribute('autocomplete', 'new-password')
                    ->set_help_text('La password verrà salvata in forma hashata automaticamente. Non modificare questo campo, anche se vuoto, se non si desidera modificare la password.'),

                Field::make('textarea', 'cral_socio_note', 'Note')
                    ->set_help_text('Eventuali note relative al socio.'),

            ));
    }

    /**
     * Hasha la password prima del salvataggio.
     * Viene chiamato solo se il campo password non è vuoto,
     * per evitare di sovrascrivere una password già hashata.
     *
     * @param int      $post_id ID del post.
     * @param \WP_Post $post    Oggetto post.
     */
    public function hash_password($post_id, $post)
    {
        // Recupera il valore grezzo del campo dal database prima che CF lo salvi.
        $raw_password = isset($_POST['_cral_password']) // phpcs:ignore WordPress.Security.NonceVerification
            ? sanitize_text_field(wp_unslash($_POST['_cral_password']))
            : '';

        if (empty($raw_password)) {
            return;
        }

        // Se il valore non è già un hash WP, lo hasha.
        if ( ! ( strpos( $raw_password, '$P$' ) === 0 || strpos( $raw_password, '$wp$' ) === 0 ) ) {
            $hashed = wp_hash_password($raw_password);
            update_post_meta($post_id, '_cral_password', $hashed);
        }
    }

    /**
     * Registra la sottopagina nascosta per la scheda del singolo socio.
     */
    public function register_scheda_menu()
    {
        add_submenu_page(
            null,
            'Scheda Socio',
            'Scheda Socio',
            'manage_options',
            'g-event-scheda-socio',
            array( $this, 'render_scheda_socio' )
        );
    }

    /**
     * Definisce le colonne della lista admin del CPT socio.
     *
     * @param array $columns Colonne predefinite di WordPress.
     * @return array
     */
    public function set_columns($columns)
    {
        // Ricostruiamo l'array nell'ordine desiderato.
        return array(
            'cb'              => $columns['cb'],
            'cral_socio_icon' => '',
            'cral_cognome'    => 'Cognome e Nome',
            'cral_socio_id'   => 'ID Socio',
            'cral_email'      => 'Email',
            'date'            => 'Data inserimento',
            'cral_scheda'     => 'Scheda',
        );
    }

    /**
     * Renderizza il contenuto delle colonne custom.
     *
     * @param string $column  Nome della colonna.
     * @param int    $post_id ID del post.
     */
    public function render_column($column, $post_id)
    {
        switch ($column) {
            case 'cral_socio_icon':
                $nome    = (string) get_post_meta( $post_id, '_cral_nome', true );
                $cognome = (string) get_post_meta( $post_id, '_cral_cognome', true );
                $initials = '';
                if ( $nome )    $initials .= mb_strtoupper( mb_substr( $nome, 0, 1 ) );
                if ( $cognome ) $initials .= mb_strtoupper( mb_substr( $cognome, 0, 1 ) );
                if ( ! $initials ) $initials = '?';
                // Colore deterministico dall'ID.
                $colors  = array( '#1d4ed8','#0369a1','#0f766e','#7c3aed','#b45309','#be185d','#166534','#9f1239' );
                $bg      = $colors[ $post_id % count( $colors ) ];
                echo '<div style="
                    width:40px;height:40px;border-radius:50%;
                    background:' . esc_attr( $bg ) . ';
                    color:#fff;font-size:.85rem;font-weight:700;
                    display:flex;align-items:center;justify-content:center;
                    letter-spacing:.03em;flex-shrink:0;
                ">' . esc_html( $initials ) . '</div>';
                break;
            case 'cral_socio_id':
                echo esc_html(get_post_meta($post_id, '_cral_socio_id', true));
                break;
            case 'cral_cognome':
                $cognome_val = (string) get_post_meta( $post_id, '_cral_cognome', true );
                $nome_val    = (string) get_post_meta( $post_id, '_cral_nome', true );
                echo '<strong>' . esc_html( $cognome_val ) . '</strong>';
                if ( $nome_val ) {
                    echo ' <span style="color:#64748b;">' . esc_html( $nome_val ) . '</span>';
                }
                break;
            case 'cral_email':
                $email = get_post_meta($post_id, '_cral_email', true);
                echo '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>';
                break;
            case 'cral_scheda':
                $url = add_query_arg(
                    array( 'page' => 'g-event-scheda-socio', 'socio_id' => $post_id ),
                    admin_url( 'admin.php' )
                );
                echo '<a href="' . esc_url( $url ) . '" class="button button-small">&#128100; Vedi scheda</a>';
                break;
        }
    }

    /**
     * Rende ordinabili le colonne cognome e ID socio.
     *
     * @param array $columns Colonne ordinabili esistenti.
     * @return array
     */
    public function set_sortable_columns($columns)
    {
        $columns['cral_cognome']  = 'cral_cognome';
        $columns['cral_socio_id'] = 'cral_socio_id';
        return $columns;
    }

    /**
     * Gestisce l'ordinamento per meta field.
     *
     * @param \WP_Query $query La query corrente.
     */
    public function handle_sorting($query)
    {
        if (! is_admin() || ! $query->is_main_query()) {
            return;
        }

        if ('socio' !== $query->get('post_type')) {
            return;
        }

        $orderby = $query->get('orderby');

        if ('cral_cognome' === $orderby) {
            $query->set('meta_key', '_cral_cognome');
            $query->set('orderby', 'meta_value');
        }

        if ('cral_socio_id' === $orderby) {
            $query->set('meta_key', '_cral_socio_id');
            $query->set('orderby', 'meta_value');
        }
    }

    /**
     * Aggiunge il campo di ricerca sopra la lista soci.
     *
     * @param string $post_type Il post type corrente.
     */
    public function render_search_box($post_type)
    {
        if ('socio' !== $post_type) {
            return;
        }

        $search_id      = isset($_GET['cral_search_id'])      // phpcs:ignore WordPress.Security.NonceVerification
            ? sanitize_text_field(wp_unslash($_GET['cral_search_id']))
            : '';
        $search_cognome = isset($_GET['cral_search_cognome']) // phpcs:ignore WordPress.Security.NonceVerification
            ? sanitize_text_field(wp_unslash($_GET['cral_search_cognome']))
            : '';
?>
        <input
            type="text"
            name="cral_search_id"
            placeholder="Cerca per ID socio"
            value="<?php echo esc_attr($search_id); ?>"
            style="margin-right: 6px;" />
        <input
            type="text"
            name="cral_search_cognome"
            placeholder="Cerca per cognome"
            value="<?php echo esc_attr($search_cognome); ?>"
            style="margin-right: 6px;" />
    <?php
    }

    /**
     * Filtra la query in base ai campi di ricerca custom.
     *
     * @param \WP_Query $query La query corrente.
     */
    public function handle_search($query)
    {
        if (! is_admin() || ! $query->is_main_query()) {
            return;
        }

        if ('socio' !== $query->get('post_type')) {
            return;
        }

        $meta_query = array('relation' => 'AND');

        $search_id = isset($_GET['cral_search_id']) // phpcs:ignore WordPress.Security.NonceVerification
            ? sanitize_text_field(wp_unslash($_GET['cral_search_id']))
            : '';

        $search_cognome = isset($_GET['cral_search_cognome']) // phpcs:ignore WordPress.Security.NonceVerification
            ? sanitize_text_field(wp_unslash($_GET['cral_search_cognome']))
            : '';

        if (! empty($search_id)) {
            $meta_query[] = array(
                'key'     => '_cral_socio_id',
                'value'   => $search_id,
                'compare' => 'LIKE',
            );
        }

        if (! empty($search_cognome)) {
            $meta_query[] = array(
                'key'     => '_cral_cognome',
                'value'   => $search_cognome,
                'compare' => 'LIKE',
            );
        }

        if (count($meta_query) > 1) {
            $query->set('meta_query', $meta_query);
        }
    }

    /**
     * Imposta cral_cognome come colonna primaria (porta le azioni di riga).
     *
     * @param string $default  Colonna primaria corrente.
     * @param string $screen   ID dello screen corrente.
     * @return string
     */
    public function set_primary_column( $default, $screen ) {
        if ( 'edit-socio' === $screen ) {
            return 'cral_cognome';
        }
        return $default;
    }

    /**
     * Registra il metabox Gestione Password.
     */
    public function register_password_metabox()
    {
        add_meta_box(
            'cral_gestione_password',
            'Gestione Password',
            array($this, 'render_password_metabox'),
            'socio',
            'side',
            'high'
        );
    }

    /**
     * Renderizza il metabox Gestione Password.
     *
     * @param \WP_Post $post Il post corrente.
     */
    public function render_password_metabox($post)
    {
        $password_manager = new \GEvent\Password_Manager();
        $status           = $password_manager->get_password_status($post->ID);
        $nonce            = wp_create_nonce('cral_send_password_email');

        // Determina testo stato e colore.
        switch ($status['status']) {
            case 'set':
                $status_label = '<span style="color: #46b450;">&#10003; Impostata</span>';
                $button_label = 'Reinvia email impostazione password';
                break;
            case 'token_pending':
                $expires      = wp_date('d/m/Y H:i', strtotime($status['expires']));
                $status_label = '<span style="color: #f0ad4e;">Token in attesa (scade il ' . esc_html($expires) . ')</span>';
                $button_label = 'Reinvia email impostazione password';
                break;
            default:
                $status_label = '<span style="color: #dc3232;">Non impostata</span>';
                $button_label = 'Invia email impostazione password';
                break;
        }
    ?>
        <p>
            <strong>Stato:</strong><br>
            <?php echo wp_kses($status_label, array('span' => array('style' => array()))); ?>
        </p>
        <p>
            <button
                type="button"
                id="cral-send-password-btn"
                class="button button-primary"
                data-socio-id="<?php echo esc_attr($post->ID); ?>"
                data-nonce="<?php echo esc_attr($nonce); ?>"
                style="width: 100%;">
                <?php echo esc_html($button_label); ?>
            </button>
        </p>
        <p id="cral-password-msg" style="display:none; margin-top: 8px;"></p>
    <?php
    }

    /**
     * Script JS per la gestione del pulsante nel metabox password.
     */
    public function password_metabox_script()
    {
        $screen = get_current_screen();
        if (! $screen || 'socio' !== $screen->post_type) {
            return;
        }
    ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Nasconde il campo password dal metabox Dati Socio.
                const allFields = document.querySelectorAll('.cf-field__head .cf-field__label');
                allFields.forEach(function(label) {
                    if (label.textContent.trim() === 'Password') {
                        label.closest('.cf-field').style.display = 'none';
                    }
                });

                // Gestione pulsante invio email password.
                const btn = document.getElementById('cral-send-password-btn');
                if (!btn) return;

                btn.addEventListener('click', function() {
                    const socioId = btn.dataset.socioId;
                    const nonce = btn.dataset.nonce;
                    const msg = document.getElementById('cral-password-msg');

                    btn.disabled = true;
                    btn.textContent = 'Invio in corso...';

                    fetch(ajaxurl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: new URLSearchParams({
                                action: 'cral_send_password_email',
                                socio_id: socioId,
                                nonce: nonce,
                            })
                        })
                        .then(r => r.json())
                        .then(data => {
                            msg.style.display = 'block';
                            if (data.success) {
                                msg.style.color = '#46b450';
                                msg.textContent = data.data.message;
                                btn.textContent = 'Reinvia email impostazione password';
                            } else {
                                msg.style.color = '#dc3232';
                                msg.textContent = data.data.message;
                                btn.textContent = 'Riprova';
                            }
                            btn.disabled = false;
                        })
                        .catch(function() {
                            msg.style.display = 'block';
                            msg.style.color = '#dc3232';
                            msg.textContent = 'Errore di connessione. Riprova.';
                            btn.disabled = false;
                            btn.textContent = 'Riprova';
                        });
                });
            });
        </script>
    <?php
    }
    /**
     * Renderizza la pagina di scheda del singolo socio con statistiche e prenotazioni.
     */
    public function render_scheda_socio()
    {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Accesso negato.' );
        }

        $socio_post_id = isset( $_GET['socio_id'] ) ? (int) $_GET['socio_id'] : 0;
        if ( ! $socio_post_id || 'socio' !== get_post_type( $socio_post_id ) ) {
            echo '<div class="wrap"><p>Socio non valido.</p></div>';
            return;
        }

        global $wpdb;

        $socio_id_str = (string) get_post_meta( $socio_post_id, '_cral_socio_id', true );
        $nome         = (string) get_post_meta( $socio_post_id, '_cral_nome', true );
        $cognome      = (string) get_post_meta( $socio_post_id, '_cral_cognome', true );
        $email        = (string) get_post_meta( $socio_post_id, '_cral_email', true );
        $telefono     = (string) get_post_meta( $socio_post_id, '_cral_telefono', true );

        $back_url = admin_url( 'edit.php?post_type=socio' );
        $edit_url = get_edit_post_link( $socio_post_id );

        // ── Tutte le prenotazioni del socio (qualsiasi stato) ──────────────────
        $prenotazioni = get_posts( array(
            'post_type'      => 'prenotazione',
            'posts_per_page' => -1,
            'meta_query'     => array(
                array( 'key' => '_cral_pren_socio_id', 'value' => $socio_post_id ),
            ),
            'orderby'        => 'date',
            'order'          => 'DESC',
        ) );

        // ── Helper: legge i partecipanti Carbon Fields di una prenotazione ────
        $get_partecipanti = static function( $pren_id ) use ( $wpdb ) {
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT meta_key, meta_value FROM {$wpdb->postmeta}
                 WHERE post_id = %d AND meta_key LIKE %s",
                $pren_id, '_cral_partecipanti|%'
            ) );
            $partecipanti = array();
            foreach ( $rows as $row ) {
                // pattern: _cral_partecipanti|partecipante_FIELD|INDEX|0|value
                if ( preg_match( '/_cral_partecipanti\|partecipante_(\w+)\|(\d+)\|0\|value/', $row->meta_key, $m ) ) {
                    $field = $m[1];
                    $idx   = (int) $m[2];
                    $partecipanti[ $idx ][ $field ] = $row->meta_value;
                }
            }
            ksort( $partecipanti );
            return $partecipanti;
        };

        // ── Statistiche ────────────────────────────────────────────────────────
        $stat_biglietti_totali   = 0;
        $stat_eventi_partecipati = 0;
        $stat_eventi_futuri      = 0;
        $stat_totale_speso       = 0.0;
        $stat_speso_acc          = 0.0;
        $stat_speso_proprio      = 0.0;
        $now_ts                  = time();

        foreach ( $prenotazioni as $pren ) {
            $pren_stato = (string) get_post_meta( $pren->ID, '_cral_pren_stato', true );
            if ( ! in_array( $pren_stato, array( 'confermata', 'in_attesa' ), true ) ) {
                continue;
            }

            $ev_id   = (int) get_post_meta( $pren->ID, '_cral_pren_evento_id', true );
            $ev_data = (string) get_post_meta( $ev_id, '_cral_evento_data', true );
            $ev_ts   = $ev_data ? strtotime( $ev_data ) : 0;

            $biglietti = max( 1, (int) get_post_meta( $pren->ID, '_cral_pren_totale_biglietti', true ) );
            $totale    = (float) get_post_meta( $pren->ID, '_cral_pren_importo_totale', true );

            // Prezzo del solo biglietto del socio (partecipante indice 0).
            $parts         = $get_partecipanti( $pren->ID );
            $prezzo_socio  = isset( $parts[0]['prezzo'] ) ? (float) $parts[0]['prezzo'] : 0.0;

            $stat_biglietti_totali += $biglietti;
            $stat_totale_speso     += $totale;
            $stat_speso_proprio    += $prezzo_socio;

            if ( $ev_ts > 0 && $ev_ts < $now_ts ) {
                $stat_eventi_partecipati++;
            } else {
                $stat_eventi_futuri++;
            }
        }
        $stat_speso_acc = max( 0.0, $stat_totale_speso - $stat_speso_proprio );

        $fmt_euro = static function( $v ) {
            return '€ ' . number_format( (float) $v, 2, ',', '.' );
        };

        $stati_label = array(
            'confermata' => '<span style="color:#46b450;font-weight:600;">Confermata</span>',
            'in_attesa'  => '<span style="color:#f56e28;font-weight:600;">In attesa</span>',
            'annullata'  => '<span style="color:#dc3232;font-weight:600;">Annullata</span>',
        );

        ?>
        <div class="wrap cral-scheda-socio-wrap">

            <!-- Breadcrumb -->
            <p style="margin-bottom:4px;">
                <a href="<?php echo esc_url( $back_url ); ?>">&#8592; Torna alla lista soci</a>
            </p>

            <?php
            // Avatar con iniziali (stesso stile della lista soci).
            $initials_h = '';
            if ( $nome )    $initials_h .= mb_strtoupper( mb_substr( $nome, 0, 1 ) );
            if ( $cognome ) $initials_h .= mb_strtoupper( mb_substr( $cognome, 0, 1 ) );
            if ( ! $initials_h ) $initials_h = '?';
            $colors_h = array( '#1d4ed8','#0369a1','#0f766e','#7c3aed','#b45309','#be185d','#166534','#9f1239' );
            $bg_h     = $colors_h[ $socio_post_id % count( $colors_h ) ];
            ?>
            <h1 class="wp-heading-inline" style="display:flex;align-items:center;gap:14px;">
                <span style="
                    width:52px;height:52px;border-radius:50%;
                    background:<?php echo esc_attr( $bg_h ); ?>;
                    color:#fff;font-size:1.1rem;font-weight:700;
                    display:inline-flex;align-items:center;justify-content:center;
                    flex-shrink:0;letter-spacing:.03em;
                "><?php echo esc_html( $initials_h ); ?></span>
                <span>
                    <?php echo esc_html( $cognome . ' ' . $nome ); ?>
                    <span style="font-size:.6em;color:#666;font-weight:400;">(<?php echo esc_html( $socio_id_str ); ?>)</span>
                </span>
            </h1>
            <a href="<?php echo esc_url( $edit_url ); ?>" class="page-title-action">Modifica socio</a>
            <hr class="wp-header-end">

            <!-- Dati anagrafici rapidi -->
            <div class="cral-ss-meta">
                <?php if ( $email ) : ?>
                <span>&#9993; <a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a></span>
                <?php endif; ?>
                <?php if ( $telefono ) : ?>
                <span>&#128222; <?php echo esc_html( $telefono ); ?></span>
                <?php endif; ?>
            </div>

            <!-- ── STATISTICHE ─────────────────────────────────────────────── -->
            <div class="cral-ss-stats">
                <div class="cral-ss-stat">
                    <div class="cral-ss-stat__value"><?php echo esc_html( $stat_biglietti_totali ); ?></div>
                    <div class="cral-ss-stat__label">Biglietti acquistati</div>
                </div>
                <div class="cral-ss-stat">
                    <div class="cral-ss-stat__value"><?php echo esc_html( $stat_eventi_partecipati ); ?></div>
                    <div class="cral-ss-stat__label">Eventi partecipati</div>
                </div>
                <div class="cral-ss-stat">
                    <div class="cral-ss-stat__value"><?php echo esc_html( $stat_eventi_futuri ); ?></div>
                    <div class="cral-ss-stat__label">Prenotazioni future</div>
                </div>
                <div class="cral-ss-stat cral-ss-stat--money">
                    <div class="cral-ss-stat__value"><?php echo esc_html( $fmt_euro( $stat_totale_speso ) ); ?></div>
                    <div class="cral-ss-stat__label">Totale speso</div>
                </div>
                <div class="cral-ss-stat cral-ss-stat--money">
                    <div class="cral-ss-stat__value"><?php echo esc_html( $fmt_euro( $stat_speso_proprio ) ); ?></div>
                    <div class="cral-ss-stat__label">Speso (biglietto proprio)</div>
                </div>
                <div class="cral-ss-stat cral-ss-stat--money">
                    <div class="cral-ss-stat__value"><?php echo esc_html( $fmt_euro( $stat_speso_acc ) ); ?></div>
                    <div class="cral-ss-stat__label">Speso (accompagnatori)</div>
                </div>
            </div>

            <!-- ── LISTA PRENOTAZIONI ──────────────────────────────────────── -->
            <h2 style="margin-top:32px;">Prenotazioni (<?php echo count( $prenotazioni ); ?>)</h2>

            <?php if ( empty( $prenotazioni ) ) : ?>
                <p>Nessuna prenotazione trovata per questo socio.</p>
            <?php else : ?>

            <table class="wp-list-table widefat fixed striped cral-ss-table">
                <thead>
                    <tr>
                        <th style="width:220px;">Evento</th>
                        <th style="width:90px;">Data evento</th>
                        <th style="width:80px;">Stato</th>
                        <th style="width:60px;">Biglietti</th>
                        <th>Accompagnatori</th>
                        <th style="width:80px;">Prezzo bigl.</th>
                        <th style="width:80px;">Totale</th>
                        <th style="width:80px;">Data pren.</th>
                        <th style="width:180px;">Azioni</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $prenotazioni as $pren ) :
                    $pren_stato     = (string) get_post_meta( $pren->ID, '_cral_pren_stato', true );
                    $pren_data      = (string) get_post_meta( $pren->ID, '_cral_pren_data', true );
                    $pren_biglietti = (int)    get_post_meta( $pren->ID, '_cral_pren_totale_biglietti', true );
                    $pren_totale    = (float)  get_post_meta( $pren->ID, '_cral_pren_importo_totale', true );
                    $ev_id          = (int)    get_post_meta( $pren->ID, '_cral_pren_evento_id', true );
                    $ev_titolo      = $ev_id ? get_the_title( $ev_id ) : '—';
                    $ev_data_raw    = $ev_id ? (string) get_post_meta( $ev_id, '_cral_evento_data', true ) : '';
                    $ev_data_fmt    = $ev_data_raw ? wp_date( 'd/m/Y H:i', strtotime( $ev_data_raw ) ) : '—';
                    $ev_page_url    = $ev_id ? get_permalink( $ev_id ) : '';
                    $ev_pren_url    = $ev_id ? add_query_arg(
                        array( 'page' => 'g-event-prenotazioni-evento', 'evento_id' => $ev_id ),
                        admin_url( 'admin.php' )
                    ) : '';
                    $stato_html     = isset( $stati_label[ $pren_stato ] ) ? $stati_label[ $pren_stato ] : esc_html( $pren_stato );
                    $pren_data_fmt  = $pren_data ? wp_date( 'd/m/Y', strtotime( $pren_data ) ) : wp_date( 'd/m/Y', strtotime( $pren->post_date ) );

                    // Partecipanti via Carbon Fields.
                    $parts_row    = $get_partecipanti( $pren->ID );
                    $prezzo_socio = isset( $parts_row[0]['prezzo'] ) ? (float) $parts_row[0]['prezzo'] : 0.0;

                    // Accompagnatori = partecipanti con indice > 0.
                    $acc_list = '';
                    $acc_items = array();
                    foreach ( $parts_row as $idx => $p ) {
                        if ( 0 === $idx ) continue; // il socio stesso, non è accompagnatore
                        $an = trim( ( isset( $p['nome'] ) ? $p['nome'] : '' ) . ' ' . ( isset( $p['cognome'] ) ? $p['cognome'] : '' ) );
                        $at = isset( $p['tipologia'] ) ? $p['tipologia'] : '';
                        $ap = isset( $p['prezzo'] ) ? ' — ' . $fmt_euro( $p['prezzo'] ) : '';
                        $acc_items[] = esc_html( $an ) . ( $at ? ' <em style="color:#888;font-size:.9em;">(' . esc_html( $at ) . ')</em>' : '' ) . '<span style="color:#555;">' . esc_html( $ap ) . '</span>';
                    }
                    $acc_list = $acc_items ? implode( '<br>', $acc_items ) : '<em style="color:#999;">—</em>';

                    $ev_thumb_html = '';
                    if ( $ev_id && has_post_thumbnail( $ev_id ) ) {
                        $ev_thumb_html = get_the_post_thumbnail( $ev_id, array( 48, 48 ), array(
                            'class' => 'cral-ss-ev-thumb',
                            'style' => 'width:48px;height:48px;object-fit:cover;border-radius:6px;flex-shrink:0;',
                        ) );
                    } elseif ( $ev_id ) {
                        $ev_thumb_html = '<div class="cral-ss-ev-thumb cral-ss-ev-thumb--placeholder" aria-hidden="true">&#128247;</div>';
                    }
                ?>
                    <tr>
                        <td>
                            <div class="cral-ss-evcell">
                                <?php echo $ev_thumb_html ? $ev_thumb_html : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                <strong class="cral-ss-evcell__title"><?php echo esc_html( $ev_titolo ); ?></strong>
                            </div>
                        </td>
                        <td><?php echo esc_html( $ev_data_fmt ); ?></td>
                        <td><?php echo wp_kses_post( $stato_html ); ?></td>
                        <td style="text-align:center;"><?php echo esc_html( max( 1, $pren_biglietti ) ); ?></td>
                        <td><?php echo wp_kses_post( $acc_list ); ?></td>
                        <td><?php echo esc_html( $fmt_euro( $prezzo_socio ) ); ?></td>
                        <td><strong><?php echo esc_html( $fmt_euro( $pren_totale ) ); ?></strong></td>
                        <td><?php echo esc_html( $pren_data_fmt ); ?></td>
                        <td class="cral-ss-actions">
                            <?php if ( $ev_page_url ) : ?>
                            <a href="<?php echo esc_url( $ev_page_url ); ?>" target="_blank" class="button button-small">&#127760; Pagina evento</a>
                            <?php endif; ?>
                            <?php if ( $ev_pren_url ) : ?>
                            <a href="<?php echo esc_url( $ev_pren_url ); ?>" class="button button-small">&#128203; Prenotazioni</a>
                            <?php endif; ?>
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
     * Aggiunge il pulsante Importa da CSV sopra la lista soci.
     *
     * @param string $which Posizione: 'top' o 'bottom'.
     */
    public function render_import_button( $which ) {
    $screen = get_current_screen();
    if ( ! $screen || 'socio' !== $screen->post_type ) {
        return;
    }

    if ( 'top' !== $which ) {
        return;
    }

    $url = admin_url( 'admin.php?page=cral-import-csv' );
    echo '<a href="' . esc_url( $url ) . '" class="button" style="margin-left: 6px;">Importa da CSV</a>';
}
}

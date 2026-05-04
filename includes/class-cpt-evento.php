<?php
/**
 * Registrazione CPT Evento e campi Carbon Fields.
 *
 * @package GEvent
 */

namespace GEvent;

use Carbon_Fields\Container;
use Carbon_Fields\Field;

/**
 * Classe per la gestione del Custom Post Type Evento.
 */
class CPT_Evento {

    /**
     * Registra gli hook WordPress.
     */
    public function init() {
        add_action( 'init', array( $this, 'register_cpt' ) );
        add_action( 'init', array( $this, 'register_taxonomy' ) );
        add_action( 'carbon_fields_register_fields', array( $this, 'register_fields' ) );
        add_action( 'carbon_fields_post_meta_container_saved', array( $this, 'init_posti_residui' ), 10, 2 );
        add_action( 'save_post_evento', array( $this, 'normalize_prices' ), 10, 3 );
        add_action( 'admin_footer', array( $this, 'toggle_companion_fields_script' ) );

        // Lista admin personalizzata.
        add_filter( 'manage_evento_posts_columns', array( $this, 'set_columns' ) );
        add_action( 'manage_evento_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
        add_filter( 'post_row_actions', array( $this, 'add_row_action_vedi_iscritti' ), 10, 2 );

        // Filtri custom per la lista eventi.
        add_action( 'restrict_manage_posts', array( $this, 'render_event_filters' ) );
        add_action( 'pre_get_posts', array( $this, 'apply_event_filters' ) );
        add_action( 'admin_footer', array( $this, 'evento_list_filter_script' ) );
    }

    /**
     * Registra il Custom Post Type evento.
     */
    public function register_cpt() {
        $labels = array(
            'name'               => 'Eventi',
            'singular_name'      => 'Evento',
            'add_new'            => 'Aggiungi evento',
            'add_new_item'       => 'Aggiungi nuovo evento',
            'edit_item'          => 'Modifica evento',
            'new_item'           => 'Nuovo evento',
            'view_item'          => 'Visualizza evento',
            'search_items'       => 'Cerca eventi',
            'not_found'          => 'Nessun evento trovato',
            'not_found_in_trash' => 'Nessun evento nel cestino',
        );

        $args = array(
            'labels'          => $labels,
            'public'          => true,
            'has_archive'     => true,
            'show_ui'         => true,
            'show_in_menu'    => 'g-event',
            'menu_icon'       => 'dashicons-calendar-alt',
            'supports'        => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
            'capability_type' => 'post',
            'rewrite'         => array( 'slug' => 'eventi' ),
        );

        register_post_type( 'evento', $args );
    }

    /**
     * Registra la tassonomia categoria_evento.
     */
    public function register_taxonomy() {
        $labels = array(
            'name'          => 'Categorie evento',
            'singular_name' => 'Categoria evento',
            'search_items'  => 'Cerca categorie',
            'all_items'     => 'Tutte le categorie',
            'edit_item'     => 'Modifica categoria',
            'update_item'   => 'Aggiorna categoria',
            'add_new_item'  => 'Aggiungi nuova categoria',
            'new_item_name' => 'Nome nuova categoria',
            'menu_name'     => 'Categorie',
        );

        $args = array(
            'labels'            => $labels,
            'hierarchical'      => true,
            'public'            => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'rewrite'           => array( 'slug' => 'categoria-evento' ),
        );

        register_taxonomy( 'categoria_evento', array( 'evento' ), $args );
    }

    /**
     * Registra i campi Carbon Fields per il CPT evento.
     */
    public function register_fields() {
        Container::make( 'post_meta', 'Dettagli Evento' )
            ->where( 'post_type', '=', 'evento' )
            ->add_fields( array(

                Field::make( 'date_time', 'cral_evento_data', 'Data e ora evento' )
                    ->set_required( true )
                    ->set_help_text( 'Data e ora di inizio evento.' ),

                Field::make( 'date', 'cral_evento_data_apertura_iscrizioni', 'Data apertura iscrizioni' )
                    ->set_help_text( 'Da quando i soci possono iscriversi. Se vuoto, le iscrizioni sono subito aperte.' ),

                Field::make( 'date', 'cral_evento_data_iscrizione', 'Data scadenza iscrizioni' )
                    ->set_help_text( 'Ultimo giorno utile per iscriversi all\'evento.' ),

                Field::make( 'text', 'cral_evento_luogo', 'Luogo' )
                    ->set_required( true ),

                Field::make( 'select', 'cral_evento_stato', 'Stato' )
                    ->set_required( true )
                    ->set_options( array(
                        'bozza'      => 'Bozza',
                        'pubblicato' => 'Pubblicato',
                        'concluso'   => 'Concluso',
                        'annullato'  => 'Annullato',
                    ) ),

                Field::make( 'rich_text', 'cral_evento_descrizione', 'Descrizione breve' )
                    ->set_help_text( 'Breve descrizione dell\'evento mostrata nel box di prenotazione.' ),

                Field::make( 'text', 'cral_evento_posti_totali', 'Posti totali' )
                    ->set_attribute( 'type', 'number' )
                    ->set_attribute( 'min', '1' )
                    ->set_required( true )
                    ->set_help_text( 'Numero massimo assoluto di biglietti vendibili per questo evento.' ),

                Field::make( 'text', 'cral_evento_posti_residui', 'Posti residui' )
                    ->set_attribute( 'type', 'number' )
                    ->set_attribute( 'min', '0' )
                    ->set_attribute( 'readOnly', 'readOnly' )
                    ->set_help_text( 'Aggiornato automaticamente dal plugin. Non modificare.' ),

            ) );

        // Prezzo base socio.
        Container::make( 'post_meta', 'Prezzo socio' )
            ->where( 'post_type', '=', 'evento' )
            ->add_fields( array(
                Field::make( 'text', 'cral_evento_prezzo_base', 'Costo biglietto evento (€)' )
                    ->set_attribute( 'type', 'number' )
                    ->set_attribute( 'min', '0' )
                    ->set_attribute( 'step', '0.01' )
                    ->set_required( true )
                    ->set_help_text( 'Prezzo del biglietto del socio.' ),
            ) );

        // Accompagnatore Socio.
        Container::make( 'post_meta', 'Accompagnatore Socio' )
            ->where( 'post_type', '=', 'evento' )
            ->add_fields( array(
                Field::make( 'html', 'cral_evento_actions_acc_socio', 'Azioni tipologia' )
                    ->set_html(
                        '<div class="cral-acc-actions">'
                        . '<button type="button" class="button button-secondary" data-cral-enable="_cral_evento_enable_acc_socio">Aggiungi Accompagnatore Tipologia Socio</button>'
                        . '<button type="button" class="button button-link-delete" style="margin-left:8px;" data-cral-disable="_cral_evento_enable_acc_socio">Nascondi Accompagnatore Tipologia Socio</button>'
                        . '</div>'
                    ),
                Field::make( 'checkbox', 'cral_evento_enable_acc_socio', 'Abilita Accompagnatore Socio' )
                    ->set_option_value( 'yes' ),
                Field::make( 'text', 'cral_evento_prezzo_acc_socio', 'Prezzo Accompagnatore Socio (€)' )
                    ->set_attribute( 'type', 'number' )
                    ->set_attribute( 'min', '0' )
                    ->set_attribute( 'step', '0.01' )
                    ->set_required( true )
                    ->set_help_text( 'Non puo essere maggiore del costo biglietto evento.' ),
                Field::make( 'text', 'cral_evento_max_acc_socio', 'Max Accompagnatore Socio' )
                    ->set_attribute( 'type', 'number' )
                    ->set_attribute( 'min', '0' )
                    ->set_required( true ),
            ) );

        // Accompagnatore Esterno.
        Container::make( 'post_meta', 'Accompagnatore Esterno' )
            ->where( 'post_type', '=', 'evento' )
            ->add_fields( array(
                Field::make( 'html', 'cral_evento_actions_acc_esterno', 'Azioni tipologia' )
                    ->set_html(
                        '<div class="cral-acc-actions">'
                        . '<button type="button" class="button button-secondary" data-cral-enable="_cral_evento_enable_acc_esterno">Aggiungi Accompagnatore Tipologia Esterno</button>'
                        . '<button type="button" class="button button-link-delete" style="margin-left:8px;" data-cral-disable="_cral_evento_enable_acc_esterno">Nascondi Accompagnatore Tipologia Esterno</button>'
                        . '</div>'
                    ),
                Field::make( 'checkbox', 'cral_evento_enable_acc_esterno', 'Abilita Accompagnatore Esterno' )
                    ->set_option_value( 'yes' ),
                Field::make( 'text', 'cral_evento_prezzo_acc_esterno', 'Prezzo Accompagnatore Esterno (€)' )
                    ->set_attribute( 'type', 'number' )
                    ->set_attribute( 'min', '0' )
                    ->set_attribute( 'step', '0.01' )
                    ->set_required( true )
                    ->set_help_text( 'Non puo essere maggiore del costo biglietto evento.' ),
                Field::make( 'text', 'cral_evento_max_acc_esterno', 'Max Accompagnatore Esterno' )
                    ->set_attribute( 'type', 'number' )
                    ->set_attribute( 'min', '0' )
                    ->set_required( true ),
            ) );

        // Accompagnatore Junior.
        Container::make( 'post_meta', 'Accompagnatore Junior' )
            ->where( 'post_type', '=', 'evento' )
            ->add_fields( array(
                Field::make( 'html', 'cral_evento_actions_acc_junior', 'Azioni tipologia' )
                    ->set_html(
                        '<div class="cral-acc-actions">'
                        . '<button type="button" class="button button-secondary" data-cral-enable="_cral_evento_enable_acc_junior">Aggiungi Accompagnatore Tipologia Junior</button>'
                        . '<button type="button" class="button button-link-delete" style="margin-left:8px;" data-cral-disable="_cral_evento_enable_acc_junior">Nascondi Accompagnatore Tipologia Junior</button>'
                        . '</div>'
                    ),
                Field::make( 'checkbox', 'cral_evento_enable_acc_junior', 'Abilita Accompagnatore Junior' )
                    ->set_option_value( 'yes' ),
                Field::make( 'text', 'cral_evento_prezzo_acc_junior', 'Prezzo Accompagnatore Junior (€)' )
                    ->set_attribute( 'type', 'number' )
                    ->set_attribute( 'min', '0' )
                    ->set_attribute( 'step', '0.01' )
                    ->set_required( true )
                    ->set_help_text( 'Non puo essere maggiore del costo biglietto evento.' ),
                Field::make( 'text', 'cral_evento_max_acc_junior', 'Max Accompagnatore Junior' )
                    ->set_attribute( 'type', 'number' )
                    ->set_attribute( 'min', '0' )
                    ->set_required( true ),
            ) );
    }

    /**
     * Inizializza i posti residui al primo salvataggio dell'evento.
     *
     * @param int                                          $post_id   ID del post.
     * @param \Carbon_Fields\Container\Post_Meta_Container $container Container CF.
     */
    public function init_posti_residui( $post_id, $container ) {
        if ( get_post_type( $post_id ) !== 'evento' ) {
            return;
        }

        $posti_totali  = get_post_meta( $post_id, '_cral_evento_posti_totali', true );
        $posti_residui = get_post_meta( $post_id, '_cral_evento_posti_residui', true );

        if ( ! empty( $posti_totali ) && empty( $posti_residui ) ) {
            update_post_meta( $post_id, '_cral_evento_posti_residui', $posti_totali );
        }
    }

    /**
     * Normalizza i prezzi accompagnatori: non possono superare il prezzo base.
     *
     * @param int      $post_id ID evento.
     * @param \WP_Post $post    Oggetto post.
     * @param bool     $update  Flag update.
     */
    public function normalize_prices( $post_id, $post, $update ) {
        if ( wp_is_post_revision( $post_id ) || 'evento' !== $post->post_type ) {
            return;
        }

        $base = (float) get_post_meta( $post_id, '_cral_evento_prezzo_base', true );
        if ( $base < 0 ) {
            $base = 0.0;
            update_post_meta( $post_id, '_cral_evento_prezzo_base', $base );
        }

        $keys = array(
            '_cral_evento_prezzo_acc_socio',
            '_cral_evento_prezzo_acc_esterno',
            '_cral_evento_prezzo_acc_junior',
        );

        foreach ( $keys as $key ) {
            $value = (float) get_post_meta( $post_id, $key, true );
            $value = max( 0.0, min( $value, $base ) );
            update_post_meta( $post_id, $key, $value );
        }

        $max_keys = array(
            '_cral_evento_max_acc_socio',
            '_cral_evento_max_acc_esterno',
            '_cral_evento_max_acc_junior',
        );
        foreach ( $max_keys as $key ) {
            $value = (int) get_post_meta( $post_id, $key, true );
            update_post_meta( $post_id, $key, max( 0, $value ) );
        }
    }

    /**
     * Disabilita i campi prezzo/max quando la tipologia accompagnatore non e attiva.
     */
    public function toggle_companion_fields_script() {
        $screen = get_current_screen();
        if ( ! $screen || 'evento' !== $screen->post_type ) {
            return;
        }
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const map = [
                {
                    toggle: '_cral_evento_enable_acc_socio',
                    fields: ['_cral_evento_prezzo_acc_socio', '_cral_evento_max_acc_socio']
                },
                {
                    toggle: '_cral_evento_enable_acc_esterno',
                    fields: ['_cral_evento_prezzo_acc_esterno', '_cral_evento_max_acc_esterno']
                },
                {
                    toggle: '_cral_evento_enable_acc_junior',
                    fields: ['_cral_evento_prezzo_acc_junior', '_cral_evento_max_acc_junior']
                }
            ];

            function applyState(toggleName, fieldNames) {
                const toggleInput = document.querySelector('[name="carbon_fields_compact_input[' + toggleName + ']"]');
                if (!toggleInput) return;
                const isEnabled = !!toggleInput.checked;

                fieldNames.forEach(function(fieldName) {
                    const fieldInput = document.querySelector('[name="carbon_fields_compact_input[' + fieldName + ']"]');
                    if (!fieldInput) return;
                    const fieldWrap = fieldInput.closest('.cf-field');
                    if (fieldWrap) {
                        fieldWrap.style.display = isEnabled ? '' : 'none';
                    }
                    fieldInput.disabled = !isEnabled;
                });

                const btnAdd = document.querySelector('[data-cral-enable="' + toggleName + '"]');
                const btnRemove = document.querySelector('[data-cral-disable="' + toggleName + '"]');
                if (btnAdd) btnAdd.style.display = isEnabled ? 'none' : 'inline-flex';
                if (btnRemove) btnRemove.style.display = isEnabled ? 'inline-flex' : 'none';

                const container = toggleInput ? toggleInput.closest('.cf-container') : null;
                if (container) {
                    const body = container.querySelector('.cf-container__body');
                    if (body) {
                        body.style.display = '';
                    }
                }
            }

            function bindToggle(toggleName, fieldNames) {
                const toggleInput = document.querySelector('[name="carbon_fields_compact_input[' + toggleName + ']"]');
                if (!toggleInput) return;
                applyState(toggleName, fieldNames);
                toggleInput.addEventListener('change', function() {
                    applyState(toggleName, fieldNames);
                });
            }

            document.addEventListener('click', function(e) {
                const enableBtn = e.target.closest('[data-cral-enable]');
                const disableBtn = e.target.closest('[data-cral-disable]');
                if (!enableBtn && !disableBtn) return;
                e.preventDefault();

                const toggleName = enableBtn ? enableBtn.getAttribute('data-cral-enable') : disableBtn.getAttribute('data-cral-disable');
                const toggleInput = document.querySelector('[name="carbon_fields_compact_input[' + toggleName + ']"]');
                if (!toggleInput) return;

                toggleInput.checked = !!enableBtn;
                toggleInput.dispatchEvent(new Event('change', { bubbles: true }));
            });

            // Nasconde il checkbox toggle nativo, usiamo i pulsanti.
            [
                '_cral_evento_enable_acc_socio',
                '_cral_evento_enable_acc_esterno',
                '_cral_evento_enable_acc_junior'
            ].forEach(function(toggleName) {
                const toggleInput = document.querySelector('[name="carbon_fields_compact_input[' + toggleName + ']"]');
                if (!toggleInput) return;
                const fieldWrap = toggleInput.closest('.cf-field');
                if (fieldWrap) fieldWrap.style.display = 'none';
            });

            function initAll() {
                map.forEach(function(item) {
                    bindToggle(item.toggle, item.fields);
                });
            }

            // Carbon Fields renderizza parti del DOM in ritardo: riproviamo alcune volte.
            let attempts = 0;
            const timer = setInterval(function() {
                initAll();
                attempts++;
                if (attempts > 20) {
                    clearInterval(timer);
                }
            }, 150);
        });
        </script>
        <?php
    }

    /**
     * Definisce le colonne della lista admin del CPT evento.
     *
     * @param array $columns Colonne predefinite.
     * @return array
     */
    public function set_columns( $columns ) {
        return array(
            'cb'                        => $columns['cb'],
            'cral_evento_thumb'         => '',
            'title'                     => 'Titolo',
            'cral_evento_data'          => 'Data',
            'cral_evento_luogo'         => 'Luogo',
            'cral_evento_stato'         => 'Stato',
            'cral_evento_posti_res'     => 'Posti residui',
            'cral_evento_iscritti'      => 'Iscritti',
            'taxonomy-categoria_evento' => 'Categoria',
            'date'                      => 'Data inserimento',
        );
    }

    /**
     * Renderizza il contenuto delle colonne custom.
     *
     * @param string $column  Nome della colonna.
     * @param int    $post_id ID del post.
     */
    public function render_column( $column, $post_id ) {
        switch ( $column ) {
            case 'cral_evento_thumb':
                if ( has_post_thumbnail( $post_id ) ) {
                    echo get_the_post_thumbnail( $post_id, array( 56, 56 ), array(
                        'style' => 'width:56px;height:56px;object-fit:cover;border-radius:6px;display:block;',
                    ) );
                } else {
                    echo '<div style="width:56px;height:56px;border-radius:6px;background:#e2e8f0;display:flex;align-items:center;justify-content:center;color:#94a3b8;font-size:1.4rem;">&#128247;</div>';
                }
                break;
            case 'cral_evento_data':
                $data = get_post_meta( $post_id, '_cral_evento_data', true );
                echo esc_html( $data ? wp_date( 'd/m/Y H:i', strtotime( $data ) ) : '—' );
                break;
            case 'cral_evento_luogo':
                echo esc_html( get_post_meta( $post_id, '_cral_evento_luogo', true ) );
                break;
            case 'cral_evento_stato':
                $stato  = get_post_meta( $post_id, '_cral_evento_stato', true );
                $labels = array(
                    'bozza'      => '<span style="color:#888;">Bozza</span>',
                    'pubblicato' => '<span style="color:#46b450;">Pubblicato</span>',
                    'concluso'   => '<span style="color:#555;">Concluso</span>',
                    'annullato'  => '<span style="color:#dc3232;">Annullato</span>',
                );
                echo wp_kses( $labels[ $stato ] ?? '—', array( 'span' => array( 'style' => array() ) ) );
                break;
            case 'cral_evento_posti_res':
                $residui = get_post_meta( $post_id, '_cral_evento_posti_residui', true );
                $totali  = get_post_meta( $post_id, '_cral_evento_posti_totali', true );
                echo esc_html( $residui . ' / ' . $totali );
                break;
            case 'cral_evento_iscritti':
                if ( ! current_user_can( 'manage_options' ) ) {
                    echo '—';
                    break;
                }
                $url = add_query_arg(
                    array(
                        'page'      => 'g-event-prenotazioni-evento',
                        'evento_id' => $post_id,
                    ),
                    admin_url( 'admin.php' )
                );
                echo '<a class="button button-small cral-btn-iscritti" href="' . esc_url( $url ) . '">&#128203; Vedi iscritti</a>';
                break;
        }
    }

    /**
     * Aggiunge azione rapida "Vedi iscritti" nella riga dell'evento.
     *
     * @param array    $actions Azioni esistenti.
     * @param \WP_Post $post    Post corrente.
     * @return array
     */
    public function add_row_action_vedi_iscritti( $actions, $post ) {
        if ( ! $post || 'evento' !== $post->post_type ) {
            return $actions;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return $actions;
        }

        $url = add_query_arg(
            array(
                'page'      => 'g-event-prenotazioni-evento',
                'evento_id' => $post->ID,
            ),
            admin_url( 'admin.php' )
        );

        $actions['cral_vedi_iscritti'] = '<a href="' . esc_url( $url ) . '">Vedi iscritti</a>';
        return $actions;
    }

    /**
     * Renderizza la barra filtri custom sopra la lista eventi.
     *
     * @param string $post_type Post type corrente.
     */
    public function render_event_filters( $post_type ) {
        if ( 'evento' !== $post_type ) {
            return;
        }

        // Valori correnti (phpcs: nonce not needed for read-only GET params).
        $f_stato    = isset( $_GET['cral_f_stato'] )      ? sanitize_text_field( wp_unslash( $_GET['cral_f_stato'] ) )      : ''; // phpcs:ignore WordPress.Security.NonceVerification
        $f_posti    = isset( $_GET['cral_f_posti'] )      ? sanitize_text_field( wp_unslash( $_GET['cral_f_posti'] ) )      : ''; // phpcs:ignore WordPress.Security.NonceVerification
        $f_data_da  = isset( $_GET['cral_f_data_da'] )    ? sanitize_text_field( wp_unslash( $_GET['cral_f_data_da'] ) )    : ''; // phpcs:ignore WordPress.Security.NonceVerification
        $f_data_a   = isset( $_GET['cral_f_data_a'] )     ? sanitize_text_field( wp_unslash( $_GET['cral_f_data_a'] ) )     : ''; // phpcs:ignore WordPress.Security.NonceVerification
        $f_cerca    = isset( $_GET['cral_f_cerca'] )      ? sanitize_text_field( wp_unslash( $_GET['cral_f_cerca'] ) )      : ''; // phpcs:ignore WordPress.Security.NonceVerification

        // Categorie disponibili.
        $categorie = get_terms( array(
            'taxonomy'   => 'categoria_evento',
            'hide_empty' => false,
        ) );
        $f_cat = isset( $_GET['cral_f_categoria'] ) ? sanitize_text_field( wp_unslash( $_GET['cral_f_categoria'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

        ?>
        <div class="cral-eventi-filtri">

            <div class="cral-filtri-row">

                <!-- Cerca per titolo -->
                <div class="cral-filtro-group">
                    <label class="cral-filtro-label">&#128269; Cerca</label>
                    <input type="text" name="cral_f_cerca" value="<?php echo esc_attr( $f_cerca ); ?>" placeholder="Titolo evento…" class="cral-filtro-input">
                </div>

                <!-- Stato -->
                <div class="cral-filtro-group">
                    <label class="cral-filtro-label">&#9873; Stato</label>
                    <select name="cral_f_stato" class="cral-filtro-select">
                        <option value="">Tutti gli stati</option>
                        <option value="pubblicato" <?php selected( $f_stato, 'pubblicato' ); ?>>Pubblicato</option>
                        <option value="bozza"      <?php selected( $f_stato, 'bozza' ); ?>>Bozza</option>
                        <option value="concluso"   <?php selected( $f_stato, 'concluso' ); ?>>Concluso</option>
                        <option value="annullato"  <?php selected( $f_stato, 'annullato' ); ?>>Annullato</option>
                    </select>
                </div>

                <!-- Posti -->
                <div class="cral-filtro-group">
                    <label class="cral-filtro-label">&#128065; Disponibilità</label>
                    <select name="cral_f_posti" class="cral-filtro-select">
                        <option value="">Tutti</option>
                        <option value="disponibili" <?php selected( $f_posti, 'disponibili' ); ?>>Con posti disponibili</option>
                        <option value="soldout"     <?php selected( $f_posti, 'soldout' ); ?>>Sold out</option>
                    </select>
                </div>

                <!-- Categoria -->
                <?php if ( ! empty( $categorie ) && ! is_wp_error( $categorie ) ) : ?>
                <div class="cral-filtro-group">
                    <label class="cral-filtro-label">&#127991; Categoria</label>
                    <select name="cral_f_categoria" class="cral-filtro-select">
                        <option value="">Tutte le categorie</option>
                        <?php foreach ( $categorie as $cat ) : ?>
                        <option value="<?php echo esc_attr( $cat->slug ); ?>" <?php selected( $f_cat, $cat->slug ); ?>>
                            <?php echo esc_html( $cat->name ); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <!-- Data da / a -->
                <div class="cral-filtro-group">
                    <label class="cral-filtro-label">&#128197; Data da</label>
                    <input type="date" name="cral_f_data_da" value="<?php echo esc_attr( $f_data_da ); ?>" class="cral-filtro-input cral-filtro-input--date">
                </div>
                <div class="cral-filtro-group">
                    <label class="cral-filtro-label">&#128197; Data a</label>
                    <input type="date" name="cral_f_data_a" value="<?php echo esc_attr( $f_data_a ); ?>" class="cral-filtro-input cral-filtro-input--date">
                </div>

                <!-- Pulsanti -->
                <div class="cral-filtro-group cral-filtro-group--actions">
                    <button type="submit" class="button button-primary cral-filtro-btn">&#9654; Filtra</button>
                    <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=evento' ) ); ?>" class="button cral-filtro-btn">&#10005; Reset</a>
                </div>

            </div>
        </div>
        <?php
    }

    /**
     * Applica i filtri custom alla query della lista eventi.
     *
     * @param \WP_Query $query La query corrente.
     */
    public function apply_event_filters( $query ) {
        if ( ! is_admin() || ! $query->is_main_query() ) {
            return;
        }

        if ( 'evento' !== $query->get( 'post_type' ) ) {
            return;
        }

        $meta_query = array( 'relation' => 'AND' );

        // Filtro stato.
        $f_stato = isset( $_GET['cral_f_stato'] ) ? sanitize_text_field( wp_unslash( $_GET['cral_f_stato'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
        if ( $f_stato ) {
            $meta_query[] = array(
                'key'     => '_cral_evento_stato',
                'value'   => $f_stato,
                'compare' => '=',
            );
        }

        // Filtro posti.
        $f_posti = isset( $_GET['cral_f_posti'] ) ? sanitize_text_field( wp_unslash( $_GET['cral_f_posti'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
        if ( 'disponibili' === $f_posti ) {
            $meta_query[] = array(
                'key'     => '_cral_evento_posti_residui',
                'value'   => '0',
                'compare' => '>',
                'type'    => 'NUMERIC',
            );
        } elseif ( 'soldout' === $f_posti ) {
            $meta_query[] = array(
                'key'     => '_cral_evento_posti_residui',
                'value'   => '0',
                'compare' => '<=',
                'type'    => 'NUMERIC',
            );
        }

        // Filtro data da / a.
        $f_data_da = isset( $_GET['cral_f_data_da'] ) ? sanitize_text_field( wp_unslash( $_GET['cral_f_data_da'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
        $f_data_a  = isset( $_GET['cral_f_data_a'] )  ? sanitize_text_field( wp_unslash( $_GET['cral_f_data_a'] ) )  : ''; // phpcs:ignore WordPress.Security.NonceVerification

        if ( $f_data_da && $f_data_a ) {
            $meta_query[] = array(
                'key'     => '_cral_evento_data',
                'value'   => array( $f_data_da . ' 00:00:00', $f_data_a . ' 23:59:59' ),
                'compare' => 'BETWEEN',
                'type'    => 'DATETIME',
            );
        } elseif ( $f_data_da ) {
            $meta_query[] = array(
                'key'     => '_cral_evento_data',
                'value'   => $f_data_da . ' 00:00:00',
                'compare' => '>=',
                'type'    => 'DATETIME',
            );
        } elseif ( $f_data_a ) {
            $meta_query[] = array(
                'key'     => '_cral_evento_data',
                'value'   => $f_data_a . ' 23:59:59',
                'compare' => '<=',
                'type'    => 'DATETIME',
            );
        }

        if ( count( $meta_query ) > 1 ) {
            $query->set( 'meta_query', $meta_query );
        }

        // Filtro categoria (taxonomy).
        $f_cat = isset( $_GET['cral_f_categoria'] ) ? sanitize_text_field( wp_unslash( $_GET['cral_f_categoria'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
        if ( $f_cat ) {
            $query->set( 'tax_query', array(
                array(
                    'taxonomy' => 'categoria_evento',
                    'field'    => 'slug',
                    'terms'    => $f_cat,
                ),
            ) );
        }

        // Filtro ricerca per titolo.
        $f_cerca = isset( $_GET['cral_f_cerca'] ) ? sanitize_text_field( wp_unslash( $_GET['cral_f_cerca'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
        if ( $f_cerca ) {
            $query->set( 's', $f_cerca );
        }
    }

    /**
     * JS che riposiziona il blocco filtri custom prima del tablenav nativo.
     */
    public function evento_list_filter_script() {
        $screen = get_current_screen();
        if ( ! $screen || 'edit-evento' !== $screen->id ) {
            return;
        }
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            var filterBar  = document.querySelector('.cral-eventi-filtri');
            var form       = document.getElementById('posts-filter');
            var tablenav   = form ? form.querySelector('.tablenav.top') : null;
            var subsubsub  = document.querySelector('.subsubsub');
            var wrap       = document.querySelector('.wrap');

            if ( ! filterBar || ! form || ! tablenav ) return;

            // 1. Sposta il blocco filtri custom PRIMA del tablenav (dentro il form).
            form.insertBefore( filterBar, tablenav );

            // 2. Avvolgi il tablenav in un contenitore "Azioni e filtri rapidi".
            var rapidiWrap = document.createElement('div');
            rapidiWrap.className = 'cral-azioni-rapide';
            tablenav.parentNode.insertBefore( rapidiWrap, tablenav );
            rapidiWrap.appendChild( tablenav );

            // 3. Inserisci etichetta in cima alla sezione.
            var label = document.createElement('div');
            label.className = 'cral-tablenav-label';
            label.innerHTML = '&#9881; Azioni e filtri rapidi';
            rapidiWrap.insertBefore( label, tablenav );

            // 4. Sposta la subsubsub (Tutti | Miei | Pubblicati) dentro la sezione.
            if ( subsubsub ) {
                rapidiWrap.appendChild( subsubsub );
            }

            // 5. Sposta il box "Cerca eventi" dentro la sezione azioni rapide.
            var searchBox = form ? form.querySelector('.search-box') : null;
            if ( searchBox ) {
                rapidiWrap.appendChild( searchBox );
            }

            // 6. Aggiungi icona + stile al pulsante "Aggiungi nuovo evento".
            var addBtn = document.querySelector('.page-title-action');
            if ( addBtn && addBtn.textContent.trim().toLowerCase().indexOf('aggiungi') !== -1 ) {
                addBtn.classList.add('cral-btn-add-evento');
                addBtn.innerHTML = '<span class="cral-add-icon" aria-hidden="true">+</span> ' + addBtn.textContent.trim();
            }
        });
        </script>
        <?php
    }
}
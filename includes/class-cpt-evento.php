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
                echo '<a class="button button-small" href="' . esc_url( $url ) . '">Vedi iscritti</a>';
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
}
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
     * Definisce le colonne della lista admin del CPT socio.
     *
     * @param array $columns Colonne predefinite di WordPress.
     * @return array
     */
    public function set_columns($columns)
    {
        // Ricostruiamo l'array nell'ordine desiderato.
        return array(
            'cb'            => $columns['cb'],
            'cral_socio_id' => 'ID Socio',
            'cral_cognome'  => 'Cognome',
            'cral_nome'     => 'Nome',
            'cral_email'    => 'Email',
            'date'          => 'Data inserimento',
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
            case 'cral_socio_id':
                echo esc_html(get_post_meta($post_id, '_cral_socio_id', true));
                break;
            case 'cral_cognome':
                echo esc_html(get_post_meta($post_id, '_cral_cognome', true));
                break;
            case 'cral_nome':
                echo esc_html(get_post_meta($post_id, '_cral_nome', true));
                break;
            case 'cral_email':
                $email = get_post_meta($post_id, '_cral_email', true);
                echo '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>';
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

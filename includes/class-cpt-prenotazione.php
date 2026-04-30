<?php
/**
 * Registrazione CPT Prenotazione e campi Carbon Fields.
 *
 * @package GEvent
 */

namespace GEvent;

use Carbon_Fields\Container;
use Carbon_Fields\Field;

/**
 * Classe per la gestione del Custom Post Type Prenotazione.
 */
class CPT_Prenotazione {

    /**
     * Registra gli hook WordPress.
     */
    public function init() {
        add_action( 'init', array( $this, 'register_cpt' ) );
        add_action( 'carbon_fields_register_fields', array( $this, 'register_fields' ) );
        add_action( 'admin_footer', array( $this, 'readonly_fields_script' ) );
        // filtro sulla base del titolo dell'evento
        add_action( 'restrict_manage_posts', array( $this, 'render_event_filter' ) );
        add_action( 'pre_get_posts', array( $this, 'handle_event_filter' ) );

        // Lista admin personalizzata.
        add_filter( 'manage_prenotazione_posts_columns', array( $this, 'set_columns' ) );
        add_action( 'manage_prenotazione_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
        add_filter( 'manage_edit-prenotazione_sortable_columns', array( $this, 'set_sortable_columns' ) );
    }

    /**
     * Registra il Custom Post Type prenotazione.
     */
    public function register_cpt() {
        $labels = array(
            'name'               => 'Prenotazioni',
            'singular_name'      => 'Prenotazione',
            'add_new'            => 'Aggiungi prenotazione',
            'add_new_item'       => 'Aggiungi nuova prenotazione',
            'edit_item'          => 'Modifica prenotazione',
            'new_item'           => 'Nuova prenotazione',
            'view_item'          => 'Visualizza prenotazione',
            'search_items'       => 'Cerca prenotazioni',
            'not_found'          => 'Nessuna prenotazione trovata',
            'not_found_in_trash' => 'Nessuna prenotazione nel cestino',
        );

        $args = array(
            'labels'          => $labels,
            'public'          => false,
            'show_ui'         => true,
            'show_in_menu'    => false,
            'menu_icon'       => 'dashicons-tickets-alt',
            'supports'        => array( 'title' ),
            'capability_type' => 'post',
        );

        register_post_type( 'prenotazione', $args );
    }

    /**
     * Registra i campi Carbon Fields per il CPT prenotazione.
     */
public function register_fields() {
    Container::make( 'post_meta', 'Dati Prenotazione' )
        ->where( 'post_type', '=', 'prenotazione' )
        ->add_fields( array(

            Field::make( 'text', 'cral_pren_socio_id', 'ID Socio (post ID)' )
                ->set_help_text( 'Popolato automaticamente dal sistema.' ),

            Field::make( 'text', 'cral_pren_evento_id', 'ID Evento (post ID)' )
                ->set_help_text( 'Popolato automaticamente dal sistema.' ),

            Field::make( 'text', 'cral_pren_data', 'Data prenotazione' )
                ->set_help_text( 'Popolato automaticamente dal sistema.' ),

            Field::make( 'select', 'cral_pren_stato', 'Stato' )
                ->set_options( array(
                    'in_attesa'  => 'In attesa',
                    'confermata' => 'Confermata',
                    'annullata'  => 'Annullata',
                ) ),

            Field::make( 'text', 'cral_pren_totale_biglietti', 'Totale biglietti' )
                ->set_attribute( 'type', 'number' )
                ->set_help_text( 'Popolato automaticamente dal sistema.' ),

            Field::make( 'text', 'cral_pren_importo_totale', 'Importo totale (€)' )
                ->set_attribute( 'type', 'number' )
                ->set_help_text( 'Popolato automaticamente dal sistema.' ),

        ) );

    // Container pagamento — gestito dalla segreteria.
    Container::make( 'post_meta', 'Pagamento' )
        ->where( 'post_type', '=', 'prenotazione' )
        ->add_fields( array(

            Field::make( 'checkbox', 'cral_pren_pagamento', 'Pagamento ricevuto' )
                ->set_option_value( 'yes' )
                ->set_help_text( 'Spunta questa casella quando il pagamento è stato ricevuto.' ),

            Field::make( 'date', 'cral_pren_data_pagamento', 'Data pagamento' )
                ->set_help_text( 'Data in cui è stato ricevuto il pagamento.' ),

            Field::make( 'textarea', 'cral_pren_note', 'Note' )
                ->set_help_text( 'Note libere della segreteria relative a questa prenotazione.' ),

        ) );

    // Container partecipanti.
    Container::make( 'post_meta', 'Partecipanti' )
        ->where( 'post_type', '=', 'prenotazione' )
        ->add_fields( array(

            Field::make( 'complex', 'cral_partecipanti', 'Lista partecipanti' )
                ->set_help_text( 'Popolato automaticamente dal sistema.' )
                ->add_fields( array(

                    Field::make( 'text', 'partecipante_nome', 'Nome' ),

                    Field::make( 'text', 'partecipante_cognome', 'Cognome' ),

                    Field::make( 'text', 'partecipante_tipologia', 'Tipologia' ),

                    Field::make( 'text', 'partecipante_prezzo', 'Prezzo (€)' ),

                ) ),

        ) );
}

    /**
     * Definisce le colonne della lista admin del CPT prenotazione.
     *
     * @param array $columns Colonne predefinite.
     * @return array
     */
    public function set_columns( $columns ) {
        return array(
            'cb'                      => $columns['cb'],
            'cral_pren_id'            => 'ID',
            'cral_pren_socio'         => 'Socio',
            'cral_pren_evento'        => 'Evento',
            'cral_pren_data'          => 'Data prenotazione',
            'cral_pren_tot_biglietti' => 'Biglietti',
            'cral_pren_importo'       => 'Importo',
            'cral_pren_stato'         => 'Stato',
            'date'                    => 'Data inserimento',
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
            case 'cral_pren_socio':
                $socio_id = get_post_meta( $post_id, '_cral_pren_socio_id', true );
                if ( $socio_id ) {
                    $nome    = get_post_meta( $socio_id, '_cral_nome', true );
                    $cognome = get_post_meta( $socio_id, '_cral_cognome', true );
                    $edit_url = get_edit_post_link( $socio_id );
                    echo '<a href="' . esc_url( $edit_url ) . '">' . esc_html( $cognome . ' ' . $nome ) . '</a>';
                } else {
                    echo '—';
                }
                break;
            case 'cral_pren_evento':
                $evento_id = get_post_meta( $post_id, '_cral_pren_evento_id', true );
                if ( $evento_id ) {
                    $edit_url = get_edit_post_link( $evento_id );
                    echo '<a href="' . esc_url( $edit_url ) . '">' . esc_html( get_the_title( $evento_id ) ) . '</a>';
                } else {
                    echo '—';
                }
                break;
            case 'cral_pren_data':
                $data = get_post_meta( $post_id, '_cral_pren_data', true );
                echo esc_html( $data ? wp_date( 'd/m/Y H:i', strtotime( $data ) ) : '—' );
                break;
            case 'cral_pren_tot_biglietti':
                echo esc_html( get_post_meta( $post_id, '_cral_pren_totale_biglietti', true ) );
                break;
            case 'cral_pren_importo':
                $importo = get_post_meta( $post_id, '_cral_pren_importo_totale', true );
                echo esc_html( $importo ? '€ ' . number_format( (float) $importo, 2, ',', '.' ) : '—' );
                break;
            case 'cral_pren_id':
                $edit_url = get_edit_post_link( $post_id );
                echo '<a href="' . esc_url( $edit_url ) . '"><strong>#' . esc_html( $post_id ) . '</strong></a>';
                break;    
            case 'cral_pren_stato':
                $stato  = get_post_meta( $post_id, '_cral_pren_stato', true );
                $labels = array(
                    'in_attesa'  => '<span style="color:#f0ad4e;">In attesa</span>',
                    'confermata' => '<span style="color:#46b450;">Confermata</span>',
                    'annullata'  => '<span style="color:#dc3232;">Annullata</span>',
                );
                echo wp_kses( $labels[ $stato ] ?? '—', array( 'span' => array( 'style' => array() ) ) );
                break;
        }
    }

    /**
     * Rende ordinabili alcune colonne.
     *
     * @param array $columns Colonne ordinabili esistenti.
     * @return array
     */
    public function set_sortable_columns( $columns ) {
        $columns['cral_pren_data']  = 'cral_pren_data';
        $columns['cral_pren_stato'] = 'cral_pren_stato';
        return $columns;
    }

/**
 * Script JS per rendere in sola lettura i campi automatici della prenotazione.
 */
public function readonly_fields_script() {
    $screen = get_current_screen();
    if ( ! $screen || 'prenotazione' !== $screen->post_type ) {
        return;
    }
    ?>
    <script>
    function cralMakeReadonly() {
        const readonlyFields = [
            '_cral_pren_socio_id',
            '_cral_pren_evento_id',
            '_cral_pren_data',
            '_cral_pren_totale_biglietti',
            '_cral_pren_importo_totale',
        ];

        readonlyFields.forEach(function(fieldName) {
            const field = document.querySelector('[name="carbon_fields_compact_input[' + fieldName + ']"]');
            if ( field && ! field.hasAttribute('data-cral-readonly') ) {
                field.setAttribute('readonly', 'readonly');
                field.setAttribute('data-cral-readonly', '1');
                field.style.backgroundColor = '#f5f5f5';
                field.style.cursor          = 'not-allowed';
            }
        });

        const complexAdd = document.querySelector('.cf-complex__actions');
        if ( complexAdd ) {
            complexAdd.style.display = 'none';
        }

        document.querySelectorAll('.cf-complex__action--remove').forEach(function(btn) {
            btn.style.display = 'none';
        });

        const partecipantiFields = document.querySelectorAll(
            '[name*="partecipante_nome"], [name*="partecipante_cognome"], ' +
            '[name*="partecipante_tipologia"], [name*="partecipante_prezzo"]'
        );
        partecipantiFields.forEach(function(field) {
            if ( ! field.hasAttribute('data-cral-readonly') ) {
                field.setAttribute('readonly', 'readonly');
                field.setAttribute('data-cral-readonly', '1');
                field.style.backgroundColor = '#f5f5f5';
                field.style.cursor          = 'not-allowed';
            }
        });
    }

        // Osserva il DOM e applica readonly quando i campi vengono renderizzati da React.
        const observer = new MutationObserver(function() {
            cralMakeReadonly();
        });

        observer.observe(document.body, {
            childList: true,
            subtree:   true,
        });

        // Esegue anche subito e dopo il caricamento completo.
        document.addEventListener('DOMContentLoaded', cralMakeReadonly);
        window.addEventListener('load', cralMakeReadonly);
        </script>
        <?php
    }
        /**
         * Aggiunge il filtro per evento sopra la lista prenotazioni.
         *
         * @param string $post_type Il post type corrente.
         */
        public function render_event_filter( $post_type ) {
            if ( 'prenotazione' !== $post_type ) {
                return;
            }

    $search = isset( $_GET['cral_filter_evento_nome'] ) // phpcs:ignore WordPress.Security.NonceVerification
        ? sanitize_text_field( wp_unslash( $_GET['cral_filter_evento_nome'] ) )
        : '';
    ?>
    <input
        type="text"
        name="cral_filter_evento_nome"
        placeholder="Filtra per nome evento"
        value="<?php echo esc_attr( $search ); ?>"
        style="margin-right: 6px;"
    />
    <?php
}

    /**
     * Filtra le prenotazioni per evento.
     *
     * @param \WP_Query $query La query corrente.
     */
/**
 * Filtra le prenotazioni per nome evento.
 *
 * @param \WP_Query $query La query corrente.
 */
public function handle_event_filter( $query ) {
    if ( ! is_admin() || ! $query->is_main_query() ) {
        return;
    }

    if ( 'prenotazione' !== $query->get( 'post_type' ) ) {
        return;
    }

    $search = isset( $_GET['cral_filter_evento_nome'] ) // phpcs:ignore WordPress.Security.NonceVerification
        ? sanitize_text_field( wp_unslash( $_GET['cral_filter_evento_nome'] ) )
        : '';

    if ( empty( $search ) ) {
        return;
    }

    // Cerca gli eventi che corrispondono al testo inserito.
    $eventi = get_posts( array(
        'post_type'      => 'evento',
        'posts_per_page' => -1,
        's'              => $search,
        'fields'         => 'ids',
    ) );

    if ( empty( $eventi ) ) {
        // Nessun evento trovato — restituisce risultati vuoti.
        $query->set( 'post__in', array( 0 ) );
        return;
    }

    // Filtra le prenotazioni per gli eventi trovati.
    $query->set( 'meta_query', array(
        array(
            'key'     => '_cral_pren_evento_id',
            'value'   => $eventi,
            'compare' => 'IN',
        ),
    ) );
}

}
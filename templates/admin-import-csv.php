<?php
/**
 * Importazione soci da file CSV.
 *
 * @package GEvent
 */

namespace GEvent;

/**
 * Classe per la gestione dell'importazione soci da CSV.
 */
class Import_CSV {

    /**
     * Registra gli hook WordPress.
     */
    public function init() {
        add_action( 'admin_menu', array( $this, 'register_import_page' ) );
        add_action( 'admin_post_cral_import_csv', array( $this, 'handle_import' ) );
        add_action( 'cral_process_email_queue', array( $this, 'process_email_queue' ) );

        // Registra il cron ogni 5 minuti se non già schedulato.
        if ( ! wp_next_scheduled( 'cral_process_email_queue' ) ) {
            wp_schedule_event( time(), 'cral_every_5_minutes', 'cral_process_email_queue' );
        }

        // Aggiunge l'intervallo cron personalizzato.
        add_filter( 'cron_schedules', array( $this, 'add_cron_interval' ) );
    }

    /**
     * Aggiunge l'intervallo cron ogni 5 minuti.
     *
     * @param array $schedules Intervalli esistenti.
     * @return array
     */
    public function add_cron_interval( $schedules ) {
        $schedules['cral_every_5_minutes'] = array(
            'interval' => 300,
            'display'  => 'Ogni 5 minuti (G-Event)',
        );
        return $schedules;
    }

    /**
     * Registra la sottopagina di importazione CSV.
     * La pagina è nascosta dal menu ma accessibile tramite URL diretto.
     */
    public function register_import_page() {
        add_submenu_page(
            null,
            'Importa soci da CSV',
            'Importa soci da CSV',
            'manage_options',
            'cral-import-csv',
            array( $this, 'render_import_page' )
        );
    }

    /**
     * Renderizza la pagina di importazione CSV.
     */
    public function render_import_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Accesso negato.' );
        }

        // Recupera eventuale risultato di una importazione precedente.
        $result = get_transient( 'cral_import_result' );
        if ( $result ) {
            delete_transient( 'cral_import_result' );
        }
        ?>
        <div class="wrap">
            <h1>Importa soci da CSV</h1>
            <p>
                <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=socio' ) ); ?>">
                    &larr; Torna all'elenco soci
                </a>
            </p>

            <?php if ( $result ) : ?>
                <div class="notice notice-<?php echo esc_attr( $result['type'] ); ?> is-dismissible">
                    <p><?php echo esc_html( $result['message'] ); ?></p>
                    <?php if ( ! empty( $result['errors'] ) ) : ?>
                        <ul>
                            <?php foreach ( $result['errors'] as $error ) : ?>
                                <li><?php echo esc_html( $error ); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; max-width: 600px;">

                <h2>Formato CSV atteso</h2>
                <p>Il file CSV deve avere le seguenti colonne nell'ordine indicato:</p>
                <code>id_socio, nome, cognome, email, data_nascita (opzionale)</code>
                <p style="margin-top: 8px;">
                    <strong>Nota:</strong> la prima riga deve contenere le intestazioni delle colonne.
                    La colonna <code>data_nascita</code> è opzionale e deve essere nel formato
                    <code>YYYY-MM-DD</code>.
                </p>

                <hr>

                <form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'cral_import_csv', 'cral_import_nonce' ); ?>
                    <input type="hidden" name="action" value="cral_import_csv">

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="cral_csv_file">File CSV</label>
                            </th>
                            <td>
                                <input
                                    type="file"
                                    id="cral_csv_file"
                                    name="cral_csv_file"
                                    accept=".csv"
                                    required
                                >
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Email impostazione password</th>
                            <td>
                                <label>
                                    <input
                                        type="checkbox"
                                        name="cral_send_emails"
                                        value="1"
                                        checked
                                    >
                                    Invia automaticamente l'email di impostazione password
                                    a tutti i soci importati
                                </label>
                                <p class="description">
                                    Le email vengono inviate in batch da 20 ogni 5 minuti
                                    tramite il cron di WordPress.
                                </p>
                            </td>
                        </tr>
                    </table>

                    <?php submit_button( 'Avvia importazione' ); ?>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Gestisce il submit del form di importazione.
     */
    public function handle_import() {
        // Verifica nonce e permessi.
        if ( ! isset( $_POST['cral_import_nonce'] ) ||
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cral_import_nonce'] ) ), 'cral_import_csv' ) ) {
            wp_die( 'Nonce non valido.' );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Accesso negato.' );
        }

        // Verifica che il file sia stato caricato.
        if ( empty( $_FILES['cral_csv_file']['tmp_name'] ) ) {
            $this->redirect_with_result( 'error', 'Nessun file caricato.', array() );
            return;
        }

        $file      = $_FILES['cral_csv_file']['tmp_name']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
        $send_emails = isset( $_POST['cral_send_emails'] ) && '1' === $_POST['cral_send_emails'];

        // Apre il file CSV.
        $handle = fopen( $file, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
        if ( ! $handle ) {
            $this->redirect_with_result( 'error', 'Impossibile leggere il file CSV.', array() );
            return;
        }

        $imported = 0;
        $errors   = array();
        $row_num  = 0;

        while ( ( $row = fgetcsv( $handle, 1000, ',' ) ) !== false ) {
            $row_num++;

            // Salta la riga di intestazione.
            if ( 1 === $row_num ) {
                continue;
            }

            // Verifica che la riga abbia almeno 4 colonne.
            if ( count( $row ) < 4 ) {
                $errors[] = "Riga {$row_num}: formato non valido (colonne insufficienti).";
                continue;
            }

            $id_socio      = sanitize_text_field( trim( $row[0] ) );
            $nome          = sanitize_text_field( trim( $row[1] ) );
            $cognome       = sanitize_text_field( trim( $row[2] ) );
            $email         = sanitize_email( trim( $row[3] ) );
            $data_nascita  = isset( $row[4] ) ? sanitize_text_field( trim( $row[4] ) ) : '';

            // Valida i campi obbligatori.
            if ( empty( $id_socio ) || empty( $nome ) || empty( $cognome ) || empty( $email ) ) {
                $errors[] = "Riga {$row_num}: campi obbligatori mancanti (id_socio, nome, cognome, email).";
                continue;
            }

            if ( ! is_email( $email ) ) {
                $errors[] = "Riga {$row_num}: email non valida ({$email}).";
                continue;
            }

            // Controlla duplicati per id_socio.
            $existing = get_posts( array(
                'post_type'  => 'socio',
                'meta_query' => array(
                    array(
                        'key'   => '_cral_socio_id',
                        'value' => $id_socio,
                    ),
                ),
                'posts_per_page' => 1,
                'fields'         => 'ids',
            ) );

            if ( ! empty( $existing ) ) {
                $errors[] = "Riga {$row_num}: socio con ID {$id_socio} già esistente, riga saltata.";
                continue;
            }

            // Crea il CPT socio.
            $post_id = wp_insert_post( array(
                'post_type'   => 'socio',
                'post_title'  => $nome . ' ' . $cognome,
                'post_status' => 'publish',
            ) );

            if ( is_wp_error( $post_id ) ) {
                $errors[] = "Riga {$row_num}: errore durante la creazione del socio ({$id_socio}).";
                continue;
            }

            // Salva i campi Carbon Fields tramite update_post_meta.
            update_post_meta( $post_id, '_cral_socio_id', $id_socio );
            update_post_meta( $post_id, '_cral_nome', $nome );
            update_post_meta( $post_id, '_cral_cognome', $cognome );
            update_post_meta( $post_id, '_cral_email', $email );

            if ( ! empty( $data_nascita ) ) {
                update_post_meta( $post_id, '_cral_data_nascita', $data_nascita );
            }

            // Aggiunge alla coda email se richiesto.
            if ( $send_emails ) {
                $this->enqueue_email( $post_id );
            }

            $imported++;
        }

        fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions

        // Prepara il messaggio di riepilogo.
        $message = "{$imported} soci importati correttamente.";
        if ( $send_emails && $imported > 0 ) {
            $message .= " Le email di impostazione password sono state accodate e verranno inviate nei prossimi minuti.";
        }
        if ( ! empty( $errors ) ) {
            $message .= " Si sono verificati " . count( $errors ) . " errori (vedi dettaglio).";
        }

        $type = empty( $errors ) ? 'success' : 'warning';
        $this->redirect_with_result( $type, $message, $errors );
    }

    /**
     * Aggiunge un socio alla coda email.
     *
     * @param int $socio_id Post ID del CPT socio.
     */
    private function enqueue_email( $socio_id ) {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'cral_email_queue',
            array(
                'socio_id'   => $socio_id,
                'status'     => 'pending',
                'attempts'   => 0,
                'created_at' => current_time( 'mysql' ),
                'sent_at'    => null,
            ),
            array( '%d', '%s', '%d', '%s', 'NULL' )
        );
    }

    /**
     * Processa la coda email — invia al massimo 20 email per esecuzione.
     * Viene chiamato dal cron ogni 5 minuti.
     */
    public function process_email_queue() {
        global $wpdb;

        $batch = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, socio_id FROM {$wpdb->prefix}cral_email_queue
                 WHERE status = 'pending'
                 AND attempts < 3
                 ORDER BY created_at ASC
                 LIMIT %d",
                20
            )
        );

        if ( empty( $batch ) ) {
            return;
        }

        $password_manager = new \GEvent\Password_Manager();

        foreach ( $batch as $item ) {
            // Aggiorna il contatore tentativi.
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$wpdb->prefix}cral_email_queue
                     SET attempts = attempts + 1
                     WHERE id = %d",
                    $item->id
                )
            );

            $sent = $password_manager->generate_and_send_token( (int) $item->socio_id );

            if ( $sent ) {
                $wpdb->update(
                    $wpdb->prefix . 'cral_email_queue',
                    array(
                        'status'  => 'sent',
                        'sent_at' => current_time( 'mysql' ),
                    ),
                    array( 'id' => $item->id ),
                    array( '%s', '%s' ),
                    array( '%d' )
                );
            } else {
                // Se i tentativi sono esauriti segna come fallito.
                $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$wpdb->prefix}cral_email_queue
                         SET status = CASE WHEN attempts >= 3 THEN 'failed' ELSE 'pending' END
                         WHERE id = %d",
                        $item->id
                    )
                );
            }
        }
    }

    /**
     * Reindirizza alla pagina di importazione con il risultato.
     *
     * @param string $type    Tipo di messaggio: 'success', 'warning', 'error'.
     * @param string $message Messaggio principale.
     * @param array  $errors  Lista errori dettagliati.
     */
    private function redirect_with_result( $type, $message, $errors ) {
        set_transient( 'cral_import_result', array(
            'type'    => $type,
            'message' => $message,
            'errors'  => $errors,
        ), 60 );

        wp_redirect( admin_url( 'admin.php?page=cral-import-csv' ) );
        exit;
    }
}
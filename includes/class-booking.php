<?php
/**
 * Logica prenotazione: validazione, creazione, aggiornamento posti.
 *
 * @package GEvent
 */

namespace GEvent;

/**
 * Classe per la gestione delle prenotazioni frontend.
 */
class Booking {

    /**
     * Registra gli hook WordPress.
     */
    public function init() {
        add_shortcode( 'cral_evento', array( $this, 'render_evento' ) );
        add_shortcode( 'cral_area_soci', array( $this, 'render_area_soci' ) );
        add_action( 'wp_ajax_nopriv_cral_prenota', array( $this, 'handle_prenota' ) );
        add_action( 'wp_ajax_cral_prenota', array( $this, 'handle_prenota' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_styles' ) );
    }

    /**
     * Carica gli stili frontend del plugin.
     */
    public function enqueue_frontend_styles() {
        wp_enqueue_style(
            'g-event-frontend',
            plugins_url( '../assets/css/frontend.css', __FILE__ ),
            array(),
            '1.0.1'
        );
        wp_enqueue_style(
            'g-event-scheda',
            plugins_url( '../assets/css/scheda-evento.css', __FILE__ ),
            array(),
            '1.0.0'
        );
    }

    /**
     * Renderizza la card evento con form di prenotazione.
     *
     * @param array $atts Attributi dello shortcode.
     * @return string HTML della card evento.
     */
    public function render_evento( $atts ) {
        $atts = shortcode_atts( array(
            'id' => get_the_ID(),
        ), $atts );

        $evento_id = absint( $atts['id'] );

        if ( ! $evento_id ) {
            return '<p class="cral-msg cral-msg--error">Evento non trovato.</p>';
        }

        $evento = get_post( $evento_id );
        if ( ! $evento || 'evento' !== $evento->post_type ) {
            return '<p class="cral-msg cral-msg--error">Evento non trovato.</p>';
        }

        $data          = get_post_meta( $evento_id, '_cral_evento_data', true );
        $luogo         = get_post_meta( $evento_id, '_cral_evento_luogo', true );
        $stato         = get_post_meta( $evento_id, '_cral_evento_stato', true );
        $descrizione   = get_post_meta( $evento_id, '_cral_evento_descrizione', true );
        $posti_residui = (int) get_post_meta( $evento_id, '_cral_evento_posti_residui', true );
        $prezzo_base        = (float) get_post_meta( $evento_id, '_cral_evento_prezzo_base', true );
        $prezzo_acc_socio   = (float) get_post_meta( $evento_id, '_cral_evento_prezzo_acc_socio', true );
        $prezzo_acc_esterno = (float) get_post_meta( $evento_id, '_cral_evento_prezzo_acc_esterno', true );
        $prezzo_acc_junior  = (float) get_post_meta( $evento_id, '_cral_evento_prezzo_acc_junior', true );
        $acc_config = array(
            'Accompagnatore Socio' => array(
                'enabled' => 'yes' === get_post_meta( $evento_id, '_cral_evento_enable_acc_socio', true ),
                'price'   => $prezzo_acc_socio,
                'max'     => (int) get_post_meta( $evento_id, '_cral_evento_max_acc_socio', true ),
            ),
            'Accompagnatore Esterno' => array(
                'enabled' => 'yes' === get_post_meta( $evento_id, '_cral_evento_enable_acc_esterno', true ),
                'price'   => $prezzo_acc_esterno,
                'max'     => (int) get_post_meta( $evento_id, '_cral_evento_max_acc_esterno', true ),
            ),
            'Accompagnatore Junior' => array(
                'enabled' => 'yes' === get_post_meta( $evento_id, '_cral_evento_enable_acc_junior', true ),
                'price'   => $prezzo_acc_junior,
                'max'     => (int) get_post_meta( $evento_id, '_cral_evento_max_acc_junior', true ),
            ),
        );
        $enabled_types = array_filter(
            $acc_config,
            static function( $item ) {
                return ! empty( $item['enabled'] );
            }
        );

        $auth     = new \GEvent\Auth();
        $socio_id = $auth->get_current_socio();
        $nonce    = wp_create_nonce( 'cral_prenota_nonce' );

        ob_start();
        ?>
        <div class="cral-evento-wrap">

            <?php if ( has_post_thumbnail( $evento_id ) ) : ?>
                <div class="cral-evento__immagine">
                    <?php echo get_the_post_thumbnail( $evento_id, 'large' ); ?>
                </div>
            <?php endif; ?>

            <div class="cral-evento__dettagli">
                <?php if ( $data ) : ?>
                    <p class="cral-evento__data">
                        <strong>Data:</strong>
                        <?php echo esc_html( wp_date( 'd/m/Y \a\l\l\e H:i', strtotime( $data ) ) ); ?>
                    </p>
                <?php endif; ?>

                <?php if ( $luogo ) : ?>
                    <p class="cral-evento__luogo">
                        <strong>Luogo:</strong>
                        <?php echo esc_html( $luogo ); ?>
                    </p>
                <?php endif; ?>

                <?php if ( $descrizione ) : ?>
                    <div class="cral-evento__descrizione">
                        <?php echo wp_kses_post( $descrizione ); ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ( 'annullato' === $stato ) : ?>
                <div class="cral-msg cral-msg--error">
                    Questo evento è stato annullato.
                </div>

            <?php elseif ( 'concluso' === $stato ) : ?>
                <div class="cral-msg cral-msg--info">
                    Questo evento si è concluso.
                </div>

            <?php elseif ( $posti_residui <= 0 ) : ?>
                <div class="cral-msg cral-msg--warning">
                    Spiacenti, i posti disponibili sono esauriti.
                </div>

            <?php else : ?>
                <div class="cral-prenotazione-wrap">
                    <h3>Prezzi evento</h3>
                    <div class="cral-tickets-info">
                        <div class="cral-ticket__info">
                            <span class="cral-ticket__nome">Biglietto socio</span>
                            <span class="cral-ticket__prezzo">€ <?php echo esc_html( number_format( $prezzo_base, 2, ',', '.' ) ); ?></span>
                        </div>
                        <?php foreach ( $enabled_types as $type_label => $type_data ) : ?>
                            <div class="cral-ticket__info">
                                <span class="cral-ticket__nome"><?php echo esc_html( $type_label ); ?></span>
                                <span class="cral-ticket__prezzo">€ <?php echo esc_html( number_format( (float) $type_data['price'], 2, ',', '.' ) ); ?></span>
                                <span class="cral-ticket__max">(max <?php echo esc_html( (int) $type_data['max'] ); ?>)</span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ( ! $socio_id ) : ?>
                        <div class="cral-msg cral-msg--info" style="margin-top: 20px;">
                            <a href="<?php echo esc_url( get_permalink( get_option( 'cral_pagina_login' ) ) ); ?>">Accedi</a>
                            per prenotare i biglietti.
                        </div>

                    <?php else : ?>
                        <form id="cral-prenota-form" class="cral-form">
                            <input type="hidden" name="evento_id" value="<?php echo esc_attr( $evento_id ); ?>">
                            <input type="hidden" name="prezzo_base" value="<?php echo esc_attr( $prezzo_base ); ?>">
                            <input type="hidden" name="prezzo_acc_socio" value="<?php echo esc_attr( $prezzo_acc_socio ); ?>">
                            <input type="hidden" name="prezzo_acc_esterno" value="<?php echo esc_attr( $prezzo_acc_esterno ); ?>">
                            <input type="hidden" name="prezzo_acc_junior" value="<?php echo esc_attr( $prezzo_acc_junior ); ?>">
                            <input type="hidden" name="acc_config_json" value="<?php echo esc_attr( wp_json_encode( $acc_config ) ); ?>">

                            <p class="cral-posti-residui">
                                Posti disponibili: <strong><?php echo esc_html( $posti_residui ); ?></strong>
                            </p>
                            <p><strong>Il socio prenota automaticamente 1 biglietto.</strong></p>
                            <h4>Accompagnatori</h4>
                            <div id="cral-accompagnatori-wrap"></div>
                            <?php if ( ! empty( $enabled_types ) ) : ?>
                                <p>
                                    <button type="button" id="cral-add-accompagnatore" class="button">Aggiungi accompagnatore</button>
                                </p>
                            <?php else : ?>
                                <p class="description">Nessuna tipologia accompagnatore attiva per questo evento.</p>
                            <?php endif; ?>
                            <p class="description" id="cral-totale-live"></p>
                            <div id="cral-prenota-msg" class="cral-form__msg" style="display:none;"></div>

                            <div class="cral-form__field">
                                <button type="submit" id="cral-prenota-btn" class="cral-btn cral-btn--primary">
                                    Prenota
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>

                </div>
            <?php endif; ?>

        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form     = document.getElementById('cral-prenota-form');
            const accWrap  = document.getElementById('cral-accompagnatori-wrap');
            const addBtn   = document.getElementById('cral-add-accompagnatore');
            const btn      = document.getElementById('cral-prenota-btn');
            const msg      = document.getElementById('cral-prenota-msg');
            const totalEl  = document.getElementById('cral-totale-live');
            const accConfigRaw = form.querySelector('[name="acc_config_json"]')?.value || '{}';
            const accConfig = JSON.parse(accConfigRaw);
            const enabledTypes = Object.keys(accConfig).filter(function(k){ return !!accConfig[k].enabled; });
            let accIndex   = 0;

            if ( ! form ) return;

            function euro(v){
                return '€ ' + Number(v).toFixed(2).replace('.', ',');
            }

            function calcTotal() {
                const prezzoBase = parseFloat(form.querySelector('[name="prezzo_base"]').value || '0');
                let total = prezzoBase;
                let count = 0;
                const byType = {};

                accWrap.querySelectorAll('.cral-acc-row').forEach(function(row){
                    const tipo = row.querySelector('select').value;
                    count++;
                    byType[tipo] = (byType[tipo] || 0) + 1;
                    if (accConfig[tipo]) {
                        total += Number(accConfig[tipo].price || 0);
                    }
                });

                Object.keys(byType).forEach(function(tipo){
                    const max = Number(accConfig[tipo]?.max || 0);
                    if (byType[tipo] > max) {
                        msg.style.display = 'block';
                        msg.style.color = '#dc3232';
                        msg.textContent = 'Hai superato il massimo consentito per "' + tipo + '" (' + max + ').';
                        btn.disabled = true;
                    }
                });
                if (msg.textContent.indexOf('Hai superato il massimo') === 0 && btn.disabled) {
                    const hasOverflow = Object.keys(byType).some(function(tipo){
                        return byType[tipo] > Number(accConfig[tipo]?.max || 0);
                    });
                    if (!hasOverflow) {
                        msg.style.display = 'none';
                        msg.textContent = '';
                        btn.disabled = false;
                    }
                }
                totalEl.textContent = 'Biglietti totali: ' + (1 + count) + ' — Importo stimato: ' + euro(total);
            }

            function addAccompagnatore() {
                if (!enabledTypes.length) return;
                const row = document.createElement('div');
                row.className = 'cral-acc-row';
                row.style.marginBottom = '10px';
                const options = enabledTypes.map(function(t){
                    return '<option value="' + t + '">' + t + '</option>';
                }).join('');
                row.innerHTML =
                    '<div class="cral-form__field"><label>Nome</label><input class="cral-acc-input" type="text" name="accompagnatori[' + accIndex + '][nome]" required></div>' +
                    '<div class="cral-form__field"><label>Cognome</label><input class="cral-acc-input" type="text" name="accompagnatori[' + accIndex + '][cognome]" required></div>' +
                    '<div class="cral-form__field"><label>Tipologia</label>' +
                    '<select name="accompagnatori[' + accIndex + '][tipologia]" required>' +
                    options +
                    '</select></div>' +
                    '<div class="cral-form__field cral-acc-actions"><button type="button" class="button cral-remove-acc">Rimuovi</button></div>';
                accWrap.appendChild(row);
                accIndex++;
                row.querySelector('select').addEventListener('change', calcTotal);
                row.querySelector('.cral-remove-acc').addEventListener('click', function(){
                    row.remove();
                    calcTotal();
                });
                calcTotal();
            }

            if (addBtn) {
                addBtn.addEventListener('click', addAccompagnatore);
            }
            calcTotal();

            form.addEventListener('submit', function(e) {
                e.preventDefault();

                btn.disabled      = true;
                btn.textContent   = 'Prenotazione in corso...';
                msg.style.display = 'none';

                const formData = new FormData(form);
                formData.append('action', 'cral_prenota');
                formData.append('nonce', '<?php echo esc_js( $nonce ); ?>');

                fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
                    method: 'POST',
                    body: formData,
                })
                .then(r => r.json())
                .then(data => {
                    if ( data.success ) {
                        window.location.href = data.data.redirect;
                    } else {
                        msg.style.display = 'block';
                        msg.style.color   = '#dc3232';
                        msg.textContent   = data.data.message;
                        btn.disabled      = false;
                        btn.textContent   = 'Prenota';
                    }
                })
                .catch(function() {
                    msg.style.display = 'block';
                    msg.style.color   = '#dc3232';
                    msg.textContent   = 'Errore di connessione. Riprova.';
                    btn.disabled      = false;
                    btn.textContent   = 'Prenota';
                });
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderizza l'area soci con elenco prenotazioni.
     *
     * @return string HTML dell'area soci.
     */
    public function render_area_soci() {
        $auth     = new \GEvent\Auth();
        $socio_id = $auth->get_current_socio();

        if ( ! $socio_id ) {
            return '<p class="cral-msg cral-msg--error">Devi essere loggato per accedere all\'area soci. ' .
                   '<a href="' . esc_url( get_permalink( get_option( 'cral_pagina_login' ) ) ) . '">Accedi</a></p>';
        }

        $nome    = get_post_meta( $socio_id, '_cral_nome', true );
        $cognome = get_post_meta( $socio_id, '_cral_cognome', true );

        $prenotazioni = get_posts( array(
            'post_type'      => 'prenotazione',
            'posts_per_page' => -1,
            'meta_query'     => array(
                array(
                    'key'   => '_cral_pren_socio_id',
                    'value' => $socio_id,
                ),
            ),
            'orderby' => 'date',
            'order'   => 'DESC',
        ) );

        $conferma = isset( $_GET['prenotazione'] ) && 'confermata' === $_GET['prenotazione'];

        ob_start();
        ?>
        <div class="cral-area-soci">

            <h2>Benvenuto, <?php echo esc_html( $nome . ' ' . $cognome ); ?>!</h2>

            <?php if ( $conferma ) : ?>
                <div class="cral-msg cral-msg--success">
                    La tua prenotazione è stata ricevuta correttamente.
                    Riceverai una email di conferma a breve.
                </div>
            <?php endif; ?>

            <div class="cral-logout-wrap">
                <?php echo do_shortcode( '[cral_logout]' ); ?>
            </div>

            <h3>Le mie prenotazioni</h3>

            <?php if ( empty( $prenotazioni ) ) : ?>
                <p>Non hai ancora effettuato nessuna prenotazione.</p>
            <?php else : ?>
                <table class="cral-prenotazioni-table">
                    <thead>
                        <tr>
                            <th>Evento</th>
                            <th>Data evento</th>
                            <th>Biglietti</th>
                            <th>Importo</th>
                            <th>Stato</th>
                            <th>Pagamento</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $prenotazioni as $pren ) : ?>
                            <?php
                            $evento_id   = get_post_meta( $pren->ID, '_cral_pren_evento_id', true );
                            $evento_data = get_post_meta( $evento_id, '_cral_evento_data', true );
                            $stato       = get_post_meta( $pren->ID, '_cral_pren_stato', true );
                            $pagamento   = get_post_meta( $pren->ID, '_cral_pren_pagamento', true );
                            $biglietti   = get_post_meta( $pren->ID, '_cral_pren_totale_biglietti', true );
                            $importo     = get_post_meta( $pren->ID, '_cral_pren_importo_totale', true );

                            $stati_label = array(
                                'in_attesa'  => '<span style="color:#f0ad4e;">In attesa</span>',
                                'confermata' => '<span style="color:#46b450;">Confermata</span>',
                                'annullata'  => '<span style="color:#dc3232;">Annullata</span>',
                            );
                            ?>
                            <tr>
                                <td><?php echo esc_html( get_the_title( $evento_id ) ); ?></td>
                                <td><?php echo esc_html( $evento_data ? wp_date( 'd/m/Y H:i', strtotime( $evento_data ) ) : '—' ); ?></td>
                                <td><?php echo esc_html( $biglietti ); ?></td>
                                <td><?php echo esc_html( $importo ? '€ ' . number_format( (float) $importo, 2, ',', '.' ) : '—' ); ?></td>
                                <td><?php echo wp_kses( $stati_label[ $stato ] ?? '—', array( 'span' => array( 'style' => array() ) ) ); ?></td>
                                <td><?php echo 'yes' === $pagamento ? '✓ Ricevuto' : 'In attesa'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Gestisce la richiesta AJAX di prenotazione.
     */
    public function handle_prenota() {
        $nonce = isset( $_POST['nonce'] )
            ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) )
            : '';

        if ( ! wp_verify_nonce( $nonce, 'cral_prenota_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Richiesta non valida.' ) );
        }

        $auth     = new \GEvent\Auth();
        $socio_id = $auth->get_current_socio();

        if ( ! $socio_id ) {
            wp_send_json_error( array( 'message' => 'Devi essere loggato per prenotare.' ) );
        }

        $evento_id = isset( $_POST['evento_id'] )
            ? absint( $_POST['evento_id'] )
            : 0;

        if ( ! $evento_id ) {
            wp_send_json_error( array( 'message' => 'Evento non valido.' ) );
        }

        $accompagnatori = isset( $_POST['accompagnatori'] ) && is_array( $_POST['accompagnatori'] )
            ? $_POST['accompagnatori'] // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
            : array();

        $prezzo_base        = (float) get_post_meta( $evento_id, '_cral_evento_prezzo_base', true );
        $prezzo_acc_socio   = (float) get_post_meta( $evento_id, '_cral_evento_prezzo_acc_socio', true );
        $prezzo_acc_esterno = (float) get_post_meta( $evento_id, '_cral_evento_prezzo_acc_esterno', true );
        $prezzo_acc_junior  = (float) get_post_meta( $evento_id, '_cral_evento_prezzo_acc_junior', true );

        $allowed_types = array(
            'Accompagnatore Socio'   => array(
                'enabled' => 'yes' === get_post_meta( $evento_id, '_cral_evento_enable_acc_socio', true ),
                'price'   => $prezzo_acc_socio,
                'max'     => (int) get_post_meta( $evento_id, '_cral_evento_max_acc_socio', true ),
            ),
            'Accompagnatore Esterno' => array(
                'enabled' => 'yes' === get_post_meta( $evento_id, '_cral_evento_enable_acc_esterno', true ),
                'price'   => $prezzo_acc_esterno,
                'max'     => (int) get_post_meta( $evento_id, '_cral_evento_max_acc_esterno', true ),
            ),
            'Accompagnatore Junior'  => array(
                'enabled' => 'yes' === get_post_meta( $evento_id, '_cral_evento_enable_acc_junior', true ),
                'price'   => $prezzo_acc_junior,
                'max'     => (int) get_post_meta( $evento_id, '_cral_evento_max_acc_junior', true ),
            ),
        );

        $accompagnatori_clean = array();
        $by_type = array(
            'Accompagnatore Socio' => 0,
            'Accompagnatore Esterno' => 0,
            'Accompagnatore Junior' => 0,
        );
        foreach ( $accompagnatori as $part ) {
            $nome      = sanitize_text_field( $part['nome'] ?? '' );
            $cognome   = sanitize_text_field( $part['cognome'] ?? '' );
            $tipologia = sanitize_text_field( $part['tipologia'] ?? '' );

            if ( '' === $nome || '' === $cognome || ! isset( $allowed_types[ $tipologia ] ) ) {
                wp_send_json_error( array( 'message' => 'Compila correttamente i dati degli accompagnatori.' ) );
            }
            if ( empty( $allowed_types[ $tipologia ]['enabled'] ) ) {
                wp_send_json_error( array( 'message' => 'La tipologia "' . $tipologia . '" non e attiva per questo evento.' ) );
            }
            $by_type[ $tipologia ]++;
            if ( $by_type[ $tipologia ] > (int) $allowed_types[ $tipologia ]['max'] ) {
                wp_send_json_error( array( 'message' => 'Hai superato il massimo per "' . $tipologia . '".' ) );
            }

            $accompagnatori_clean[] = array(
                'nome'      => $nome,
                'cognome'   => $cognome,
                'tipologia' => $tipologia,
            );
        }

        $totale_biglietti = 1 + count( $accompagnatori_clean ); // socio + accompagnatori.
        $importo_totale   = $prezzo_base;

        global $wpdb;

        $updated = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->postmeta}
                 SET meta_value = meta_value - %d
                 WHERE post_id = %d
                 AND meta_key = '_cral_evento_posti_residui'
                 AND CAST(meta_value AS UNSIGNED) >= %d",
                $totale_biglietti,
                $evento_id,
                $totale_biglietti
            )
        );

        if ( ! $updated ) {
            wp_send_json_error( array( 'message' => 'Spiacenti, i posti disponibili sono esauriti.' ) );
        }

        $partecipanti_clean = array();
        $socio_nome         = get_post_meta( $socio_id, '_cral_nome', true );
        $socio_cognome      = get_post_meta( $socio_id, '_cral_cognome', true );
        $partecipanti_clean[] = array(
            'partecipante_nome'      => $socio_nome,
            'partecipante_cognome'   => $socio_cognome,
            'partecipante_tipologia' => 'Socio',
            'partecipante_prezzo'    => (string) $prezzo_base,
        );

        foreach ( $accompagnatori_clean as $part ) {
            $nome      = $part['nome'];
            $cognome   = $part['cognome'];
            $tipologia = $part['tipologia'];
            $prezzo_acc = (float) $allowed_types[ $tipologia ]['price'];
            $partecipanti_clean[] = array(
                'partecipante_nome'      => $nome,
                'partecipante_cognome'   => $cognome,
                'partecipante_tipologia' => $tipologia,
                'partecipante_prezzo'    => (string) $prezzo_acc,
            );
            $importo_totale += $prezzo_acc;
        }
        $evento_titolo = get_the_title( $evento_id );

        $post_id = wp_insert_post( array(
            'post_type'   => 'prenotazione',
            'post_title'  => $socio_cognome . ' ' . $socio_nome . ' — ' . $evento_titolo,
            'post_status' => 'publish',
        ) );

        if ( is_wp_error( $post_id ) ) {
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$wpdb->postmeta}
                     SET meta_value = meta_value + %d
                     WHERE post_id = %d
                     AND meta_key = '_cral_evento_posti_residui'",
                    $totale_biglietti,
                    $evento_id
                )
            );
            wp_send_json_error( array( 'message' => 'Errore durante la prenotazione. Riprova.' ) );
        }

        update_post_meta( $post_id, '_cral_pren_socio_id', $socio_id );
        update_post_meta( $post_id, '_cral_pren_evento_id', $evento_id );
        update_post_meta( $post_id, '_cral_pren_data', current_time( 'mysql' ) );
        update_post_meta( $post_id, '_cral_pren_stato', 'in_attesa' );
        update_post_meta( $post_id, '_cral_pren_totale_biglietti', $totale_biglietti );
        update_post_meta( $post_id, '_cral_pren_importo_totale', $importo_totale );

        carbon_set_post_meta( $post_id, 'cral_partecipanti', $partecipanti_clean );

        $mailer = new \GEvent\Mailer();
        $mailer->send_conferma_socio( $post_id, $socio_id, $evento_id );
        $mailer->send_notifica_segreteria( $post_id, $socio_id, $evento_id );

        $redirect = add_query_arg(
            array( 'prenotazione' => 'confermata' ),
            get_permalink( get_option( 'cral_pagina_area_soci' ) )
        );

        wp_send_json_success( array( 'redirect' => $redirect ) );
    }
}
<?php
/**
 * Shortcode frontend per impostazione e reset password.
 *
 * @package GEvent
 */

namespace GEvent;

/**
 * Classe per la gestione del frontend password.
 */
class Password_Frontend {

    /**
     * Registra gli hook WordPress.
     */
    public function init() {
        add_shortcode( 'cral_imposta_password', array( $this, 'render_imposta_password' ) );
        add_shortcode( 'cral_reset_password', array( $this, 'render_reset_password' ) );
        add_action( 'wp_ajax_nopriv_cral_imposta_password', array( $this, 'handle_imposta_password' ) );
        add_action( 'wp_ajax_cral_imposta_password', array( $this, 'handle_imposta_password' ) );
        add_action( 'wp_ajax_nopriv_cral_reset_password', array( $this, 'handle_reset_password' ) );
        add_action( 'wp_ajax_cral_reset_password', array( $this, 'handle_reset_password' ) );
    }

    /**
     * Renderizza il form di impostazione password.
     * Legge il token dal parametro GET e lo verifica.
     *
     * @return string HTML del form.
     */
    public function render_imposta_password() {
        $token = isset( $_GET['token'] ) // phpcs:ignore WordPress.Security.NonceVerification
            ? sanitize_text_field( wp_unslash( $_GET['token'] ) )
            : '';

        if ( empty( $token ) ) {
            return '<p class="cral-msg cral-msg--error">Link non valido. Richiedi un nuovo link alla segreteria.</p>';
        }

        // Verifica il token.
        $password_manager = new \GEvent\Password_Manager();
    $socio_id         = $password_manager->verify_token( $token );

    if ( ! $socio_id ) {
            return '<p class="cral-msg cral-msg--error">Il link è scaduto o non è valido. Richiedi un nuovo link alla segreteria.</p>';
        }

        $nonce = wp_create_nonce( 'cral_imposta_password_nonce' );

        ob_start();
        ?>
        <div class="cral-password-wrap">
            <h2>Imposta la tua password</h2>
            <form id="cral-imposta-password-form" class="cral-form">
                <input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
                <div class="cral-form__field">
                    <label for="cral-new-password">Nuova password</label>
                    <input
                        type="password"
                        id="cral-new-password"
                        name="password"
                        required
                        autocomplete="new-password"
                        minlength="8"
                    >
                    <em class="cral-form__hint">Minimo 8 caratteri.</em>
                </div>
                <div class="cral-form__field">
                    <label for="cral-confirm-password">Conferma password</label>
                    <input
                        type="password"
                        id="cral-confirm-password"
                        name="password_confirm"
                        required
                        autocomplete="new-password"
                        minlength="8"
                    >
                </div>
                <div id="cral-imposta-msg" class="cral-form__msg" style="display:none;"></div>
                <div class="cral-form__field">
                    <button type="submit" class="cral-btn cral-btn--primary">
                        Salva password
                    </button>
                </div>
            </form>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('cral-imposta-password-form');
            if ( ! form ) return;

            form.addEventListener('submit', function(e) {
                e.preventDefault();

                const msg      = document.getElementById('cral-imposta-msg');
                const btn      = form.querySelector('button[type="submit"]');
                const password = form.querySelector('#cral-new-password').value;
                const confirm  = form.querySelector('#cral-confirm-password').value;
                const token    = form.querySelector('[name="token"]').value;

                msg.style.display = 'none';

                if ( password !== confirm ) {
                    msg.style.display = 'block';
                    msg.style.color   = '#dc3232';
                    msg.textContent   = 'Le password non coincidono.';
                    return;
                }

                if ( password.length < 8 ) {
                    msg.style.display = 'block';
                    msg.style.color   = '#dc3232';
                    msg.textContent   = 'La password deve essere di almeno 8 caratteri.';
                    return;
                }

                btn.disabled    = true;
                btn.textContent = 'Salvataggio in corso...';

                fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action:           'cral_imposta_password',
                        nonce:            '<?php echo esc_js( $nonce ); ?>',
                        token:            token,
                        password:         password,
                        password_confirm: confirm,
                    })
                })
                .then(r => r.json())
                .then(data => {
                    msg.style.display = 'block';
                    if ( data.success ) {
                        msg.style.color   = '#46b450';
                        msg.textContent   = data.data.message;
                        btn.textContent   = 'Password salvata';
                        form.querySelector('#cral-new-password').disabled    = true;
                        form.querySelector('#cral-confirm-password').disabled = true;
                        // Reindirizza al login dopo 2 secondi.
                        setTimeout(function() {
                            window.location.href = '<?php echo esc_url( get_permalink( get_option( 'cral_pagina_login' ) ) ); ?>';
                        }, 2000);
                    } else {
                        msg.style.color   = '#dc3232';
                        msg.textContent   = data.data.message;
                        btn.disabled      = false;
                        btn.textContent   = 'Salva password';
                    }
                })
                .catch(function() {
                    msg.style.display = 'block';
                    msg.style.color   = '#dc3232';
                    msg.textContent   = 'Errore di connessione. Riprova.';
                    btn.disabled      = false;
                    btn.textContent   = 'Salva password';
                });
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Gestisce il submit del form di impostazione password.
     */
    public function handle_imposta_password() {
        // Verifica nonce.
        $nonce = isset( $_POST['nonce'] )
            ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) )
            : '';

        if ( ! wp_verify_nonce( $nonce, 'cral_imposta_password_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Richiesta non valida.' ) );
        }

        $token    = isset( $_POST['token'] )
            ? sanitize_text_field( wp_unslash( $_POST['token'] ) )
            : '';
        $password = isset( $_POST['password'] )
            ? sanitize_text_field( wp_unslash( $_POST['password'] ) )
            : '';
        $confirm  = isset( $_POST['password_confirm'] )
            ? sanitize_text_field( wp_unslash( $_POST['password_confirm'] ) )
            : '';

        if ( empty( $token ) || empty( $password ) || empty( $confirm ) ) {
            wp_send_json_error( array( 'message' => 'Tutti i campi sono obbligatori.' ) );
        }

        if ( $password !== $confirm ) {
            wp_send_json_error( array( 'message' => 'Le password non coincidono.' ) );
        }

        if ( strlen( $password ) < 8 ) {
            wp_send_json_error( array( 'message' => 'La password deve essere di almeno 8 caratteri.' ) );
        }

        // Verifica il token.
        $password_manager = new \GEvent\Password_Manager();
        $socio_id         = $password_manager->verify_token( $token );

        if ( ! $socio_id ) {
            wp_send_json_error( array( 'message' => 'Il link è scaduto o non è valido.' ) );
        }

        // Salva la password hashata.
        $hashed = wp_hash_password( $password );
        update_post_meta( $socio_id, '_cral_password', $hashed );

        // Invalida il token.
        $password_manager->invalidate_token( $token );

        wp_send_json_success( array( 'message' => 'Password impostata correttamente. Verrai reindirizzato alla pagina di accesso.' ) );
    }

    /**
     * Renderizza il form di reset password.
     *
     * @return string HTML del form.
     */
    public function render_reset_password() {
        $nonce = wp_create_nonce( 'cral_reset_password_nonce' );

        ob_start();
        ?>
        <div class="cral-reset-wrap">
            <h2>Recupera password</h2>
            <p>Inserisci il tuo ID socio. Riceverai una email con il link per reimpostare la password.</p>
            <form id="cral-reset-password-form" class="cral-form">
                <div class="cral-form__field">
                    <label for="cral-reset-socio-id">ID Socio</label>
                    <input
                        type="text"
                        id="cral-reset-socio-id"
                        name="socio_id"
                        required
                        autocomplete="username"
                    >
                </div>
                <div id="cral-reset-msg" class="cral-form__msg" style="display:none;"></div>
                <div class="cral-form__field">
                    <button type="submit" class="cral-btn cral-btn--primary">
                        Invia email di recupero
                    </button>
                </div>
                <div class="cral-form__field">
                    <a href="<?php echo esc_url( get_permalink( get_option( 'cral_pagina_login' ) ) ); ?>">
                        &larr; Torna al login
                    </a>
                </div>
            </form>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('cral-reset-password-form');
            if ( ! form ) return;

            form.addEventListener('submit', function(e) {
                e.preventDefault();

                const msg     = document.getElementById('cral-reset-msg');
                const btn     = form.querySelector('button[type="submit"]');
                const socioId = form.querySelector('#cral-reset-socio-id').value.trim();

                btn.disabled    = true;
                btn.textContent = 'Invio in corso...';
                msg.style.display = 'none';

                fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action:   'cral_reset_password',
                        nonce:    '<?php echo esc_js( $nonce ); ?>',
                        socio_id: socioId,
                    })
                })
                .then(r => r.json())
                .then(data => {
                    msg.style.display = 'block';
                    if ( data.success ) {
                        msg.style.color   = '#46b450';
                        msg.textContent   = data.data.message;
                        btn.textContent   = 'Email inviata';
                        form.querySelector('#cral-reset-socio-id').disabled = true;
                    } else {
                        msg.style.color   = '#dc3232';
                        msg.textContent   = data.data.message;
                        btn.disabled      = false;
                        btn.textContent   = 'Invia email di recupero';
                    }
                })
                .catch(function() {
                    msg.style.display = 'block';
                    msg.style.color   = '#dc3232';
                    msg.textContent   = 'Errore di connessione. Riprova.';
                    btn.disabled      = false;
                    btn.textContent   = 'Invia email di recupero';
                });
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Gestisce il submit del form di reset password.
     */
    public function handle_reset_password() {
        // Verifica nonce.
        $nonce = isset( $_POST['nonce'] )
            ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) )
            : '';

        if ( ! wp_verify_nonce( $nonce, 'cral_reset_password_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Richiesta non valida.' ) );
        }

        $socio_id = isset( $_POST['socio_id'] )
            ? sanitize_text_field( wp_unslash( $_POST['socio_id'] ) )
            : '';

        if ( empty( $socio_id ) ) {
            wp_send_json_error( array( 'message' => 'Inserisci il tuo ID socio.' ) );
        }

        // Cerca il socio.
        $posts = get_posts( array(
            'post_type'      => 'socio',
            'meta_query'     => array(
                array(
                    'key'   => '_cral_socio_id',
                    'value' => $socio_id,
                ),
            ),
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ) );

        // Per sicurezza restituiamo sempre lo stesso messaggio
        // indipendentemente dal fatto che il socio esista o meno.
        if ( empty( $posts ) ) {
            wp_send_json_success( array( 'message' => 'Se l\'ID socio è corretto riceverai una email con il link per reimpostare la password.' ) );
            return;
        }

        $post_id          = (int) $posts[0];
        $password_manager = new \GEvent\Password_Manager();
        $password_manager->generate_and_send_reset_token( $post_id );

        wp_send_json_success( array( 'message' => 'Se l\'ID socio è corretto riceverai una email con il link per reimpostare la password.' ) );
    }
}
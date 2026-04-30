<?php
/**
 * Shortcode e frontend per l'autenticazione.
 *
 * @package GEvent
 */

namespace GEvent;

/**
 * Classe per la gestione del frontend di autenticazione.
 */
class Auth_Frontend {

    /**
     * Registra gli hook WordPress.
     */
    public function init() {
        add_shortcode( 'cral_login', array( $this, 'render_login' ) );
        add_shortcode( 'cral_logout', array( $this, 'render_logout' ) );
    }

    /**
     * Renderizza il form di login.
     *
     * @return string HTML del form.
     */
    public function render_login() {
        // Se il socio è già loggato reindirizza alla home.
        $auth = new \GEvent\Auth();
        if ( $auth->get_current_socio() ) {
            wp_redirect( home_url( '/' ) );
            exit;
        }

        $nonce = wp_create_nonce( 'cral_login_nonce' );

        ob_start();
        ?>
        <div class="cral-login-wrap">
            <form id="cral-login-form" class="cral-form">
                <div class="cral-form__field">
                    <label for="cral-socio-id">ID Socio</label>
                    <input
                        type="text"
                        id="cral-socio-id"
                        name="socio_id"
                        required
                        autocomplete="username"
                    >
                </div>
                <div class="cral-form__field">
                    <label for="cral-password">Password</label>
                    <input
                        type="password"
                        id="cral-password"
                        name="password"
                        required
                        autocomplete="current-password"
                    >
                </div>
                <div id="cral-login-msg" class="cral-form__msg" style="display:none;"></div>
                <div class="cral-form__field">
                    <button type="submit" class="cral-btn cral-btn--primary">
                        Accedi
                    </button>
                </div>
                <div class="cral-form__field">
                    <a href="<?php echo esc_url( get_permalink( get_option( 'cral_pagina_recupera_password' ) ) ); ?>">
                        Hai dimenticato la password?
                    </a>
                </div>
            </form>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('cral-login-form');
            if ( ! form ) return;

            form.addEventListener('submit', function(e) {
                e.preventDefault();

                const msg     = document.getElementById('cral-login-msg');
                const btn     = form.querySelector('button[type="submit"]');
                const socioId = form.querySelector('#cral-socio-id').value.trim();
                const password = form.querySelector('#cral-password').value;

                btn.disabled    = true;
                btn.textContent = 'Accesso in corso...';
                msg.style.display = 'none';

                fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action:   'cral_login',
                        nonce:    '<?php echo esc_js( $nonce ); ?>',
                        socio_id: socioId,
                        password: password,
                    })
                })
                .then(r => r.json())
                .then(data => {
                    if ( data.success ) {
                        window.location.href = '<?php echo esc_url( get_permalink( get_option( 'cral_pagina_area_soci' ) ) ); ?>';
                    } else {
                        msg.style.display = 'block';
                        msg.style.color   = '#dc3232';
                        msg.textContent   = data.data.message;
                        btn.disabled      = false;
                        btn.textContent   = 'Accedi';
                    }
                })
                .catch(function() {
                    msg.style.display = 'block';
                    msg.style.color   = '#dc3232';
                    msg.textContent   = 'Errore di connessione. Riprova.';
                    btn.disabled      = false;
                    btn.textContent   = 'Accedi';
                });
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderizza il link di logout.
     *
     * @return string HTML del link.
     */
    public function render_logout() {
        $auth = new \GEvent\Auth();
        if ( ! $auth->get_current_socio() ) {
            return '';
        }

        $nonce = wp_create_nonce( 'cral_logout_nonce' );

        ob_start();
        ?>
        <a href="#" id="cral-logout-link">Esci</a>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const link = document.getElementById('cral-logout-link');
            if ( ! link ) return;

            link.addEventListener('click', function(e) {
                e.preventDefault();

                fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'cral_logout',
                        nonce:  '<?php echo esc_js( $nonce ); ?>',
                    })
                })
                .then(r => r.json())
                .then(data => {
                    if ( data.success && data.data.redirect ) {
                        window.location.href = data.data.redirect;
                    }
                });
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
}
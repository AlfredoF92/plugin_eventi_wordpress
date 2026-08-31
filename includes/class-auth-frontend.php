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
        add_shortcode( 'cral_header_accesso', array( $this, 'render_header_accesso' ) );
        add_shortcode( 'ciao-user', array( $this, 'render_ciao_user' ) );
        add_action( 'init', array( $this, 'maybe_update_site_branding' ), 5 );
        add_action( 'init', array( $this, 'ensure_demo_login_accounts' ), 20 );
        add_action( 'template_redirect', array( $this, 'start_branding_output_buffer' ), 0 );
        add_filter( 'document_title_parts', array( $this, 'filter_document_title_parts' ) );
        add_filter( 'pre_option_blogname', array( $this, 'filter_blogname' ) );
        add_filter( 'option_blogname', array( $this, 'filter_blogname' ) );
        add_filter( 'pre_option_blogdescription', array( $this, 'filter_blogdescription' ) );
        add_filter( 'option_blogdescription', array( $this, 'filter_blogdescription' ) );
        add_filter( 'bloginfo', array( $this, 'filter_bloginfo_name' ), 10, 2 );
        add_filter( 'get_bloginfo', array( $this, 'filter_get_bloginfo' ), 10, 2 );
    }

    /**
     * Nome sito ufficiale.
     *
     * @return string
     */
    public static function get_site_brand_name() {
        return 'CRAL Bcc Milano';
    }

    /**
     * Motto sito ufficiale.
     *
     * @return string
     */
    public static function get_site_brand_description() {
        return 'Area soci e eventi';
    }

    /**
     * Forza blogname ovunque (anche widget Elementor Titolo sito).
     *
     * @param mixed $value Valore opzione.
     * @return string
     */
    public function filter_blogname( $value ) {
        return self::get_site_brand_name();
    }

    /**
     * Forza motto sito (sostituisce "Website" / demo Elementor).
     *
     * @param mixed $value Valore opzione.
     * @return string
     */
    public function filter_blogdescription( $value ) {
        $value = trim( (string) $value );
        if ( in_array( $value, array( '', 'Descrizione', 'Website', 'Just another WordPress site', 'Digital Agency' ), true ) ) {
            return self::get_site_brand_description();
        }
        return $value;
    }

    /**
     * Forza get_bloginfo( 'name' ) con filter display.
     *
     * @param string $output Output bloginfo.
     * @param string $show   Chiave richiesta.
     * @return string
     */
    public function filter_bloginfo_name( $output, $show ) {
        if ( 'name' === $show || '' === $show ) {
            return self::get_site_brand_name();
        }
        if ( 'description' === $show ) {
            return $this->filter_blogdescription( $output );
        }
        return $output;
    }

    /**
     * Compatibilità filtri bloginfo raw.
     *
     * @param string $output Output.
     * @param string $show   Chiave.
     * @return string
     */
    public function filter_get_bloginfo( $output, $show ) {
        return $this->filter_bloginfo_name( $output, $show );
    }

    /**
     * Allinea titolo sito WordPress + kit Elementor.
     */
    public function maybe_update_site_branding() {
        if ( get_option( 'g_event_site_branding_v2' ) ) {
            return;
        }

        global $wpdb;

        $wpdb->update(
            $wpdb->options,
            array( 'option_value' => self::get_site_brand_name() ),
            array( 'option_name' => 'blogname' )
        );

        $wpdb->update(
            $wpdb->options,
            array( 'option_value' => self::get_site_brand_description() ),
            array( 'option_name' => 'blogdescription' )
        );

        $this->sync_elementor_kit_branding();

        wp_cache_delete( 'alloptions', 'options' );
        update_option( 'g_event_site_branding_v2', '1', false );
    }

    /**
     * Aggiorna site_name / site_description nel kit Elementor attivo.
     */
    protected function sync_elementor_kit_branding() {
        $kit_id = (int) get_option( 'elementor_active_kit' );
        if ( $kit_id <= 0 ) {
            return;
        }

        $settings = get_post_meta( $kit_id, '_elementor_page_settings', true );
        if ( ! is_array( $settings ) ) {
            $settings = array();
        }

        $settings['site_name']        = self::get_site_brand_name();
        $settings['site_description'] = self::get_site_brand_description();
        update_post_meta( $kit_id, '_elementor_page_settings', $settings );
    }

    /**
     * Titolo browser / SEO base: "Pagina – CRAL Bcc Milano".
     *
     * @param array $parts Parti del document title.
     * @return array
     */
    public function filter_document_title_parts( $parts ) {
        $parts['site'] = self::get_site_brand_name();
        return $parts;
    }

    /**
     * Sostituisce "Digital Agency" / "Website" anche se hardcoded in Elementor.
     */
    public function start_branding_output_buffer() {
        if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
            return;
        }

        ob_start( array( $this, 'replace_branding_in_html' ) );
    }

    /**
     * @param string $html HTML pagina.
     * @return string
     */
    public function replace_branding_in_html( $html ) {
        if ( ! is_string( $html ) || '' === $html ) {
            return $html;
        }

        $name = self::get_site_brand_name();
        $desc = self::get_site_brand_description();

        $replacements = array(
            'Digital Agency Website' => $name,
            'Digital Agency'         => $name,
        );

        // Motto demo Elementor tipico (solo come testo isolato tipico).
        $html = str_replace( array_keys( $replacements ), array_values( $replacements ), $html );

        // Tagline "Website" nei blocchi identity Elementor/Astra.
        $html = preg_replace(
            '/(<[^>]*class="[^"]*(?:site-description|elementor-heading-title|ast-site-identity)[^"]*"[^>]*>)\s*Website\s*(<\/)/i',
            '$1' . esc_html( $desc ) . '$2',
            $html
        );

        return $html;
    }

    /**
     * Credenziali demo per ambiente di test.
     *
     * @return array{admin:array{user:string,pass:string},socio:array{user:string,pass:string}}
     */
    public static function get_demo_credentials() {
        return array(
            'admin' => array(
                'user' => 'cral_admin',
                'pass' => 'CralAdmin2026!',
            ),
            'socio' => array(
                'user' => 'SOCIODEMO',
                'pass' => 'CralSocio2026!',
            ),
        );
    }

    /**
     * Crea utente admin WP + socio demo se mancanti.
     */
    public function ensure_demo_login_accounts() {
        if ( get_option( 'g_event_demo_login_accounts_v1' ) ) {
            return;
        }

        $creds = self::get_demo_credentials();

        // Admin WordPress.
        $admin_login = $creds['admin']['user'];
        $admin_user  = get_user_by( 'login', $admin_login );
        if ( ! $admin_user ) {
            $user_id = wp_create_user( $admin_login, $creds['admin']['pass'], 'cral_admin@example.com' );
            if ( ! is_wp_error( $user_id ) ) {
                $user = new \WP_User( $user_id );
                $user->set_role( 'administrator' );
            }
        } else {
            wp_set_password( $creds['admin']['pass'], $admin_user->ID );
            $admin_user->set_role( 'administrator' );
        }

        // Socio demo.
        $socio_code = $creds['socio']['user'];
        $existing   = get_posts(
            array(
                'post_type'      => 'socio',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'meta_query'     => array(
                    array(
                        'key'   => '_cral_socio_id',
                        'value' => $socio_code,
                    ),
                ),
            )
        );

        if ( empty( $existing ) ) {
            $socio_id = wp_insert_post(
                array(
                    'post_type'   => 'socio',
                    'post_status' => 'publish',
                    'post_title'  => 'Demo Socio',
                )
            );
            if ( ! is_wp_error( $socio_id ) && $socio_id ) {
                update_post_meta( $socio_id, '_cral_socio_id', $socio_code );
                update_post_meta( $socio_id, '_cral_nome', 'Mario' );
                update_post_meta( $socio_id, '_cral_cognome', 'Rossi' );
                update_post_meta( $socio_id, '_cral_password', wp_hash_password( $creds['socio']['pass'] ) );
                update_post_meta( $socio_id, '_cral_email', 'socio.demo@example.com' );
            }
        } else {
            update_post_meta( (int) $existing[0], '_cral_password', wp_hash_password( $creds['socio']['pass'] ) );
        }

        update_option( 'g_event_demo_login_accounts_v1', '1', false );
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

        // Se è già loggato come admin WP, vai in admin.
        if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
            wp_redirect( admin_url() );
            exit;
        }

        $nonce = wp_create_nonce( 'cral_login_nonce' );
        $creds = self::get_demo_credentials();

        $fallback_eventi = home_url( '/eventi-2/' );
        $eventi_page     = get_page_by_path( 'eventi-2' );
        if ( $eventi_page instanceof \WP_Post ) {
            $fallback_eventi = get_permalink( $eventi_page );
        }

        ob_start();
        ?>
        <div class="cral-login-wrap">
            <header class="cral-login__header">
                <h2 class="cral-login__title"><?php esc_html_e( 'Accedi all\'area soci', 'g-event' ); ?></h2>
                <p class="cral-login__desc"><?php esc_html_e( 'Inserisci il tuo ID Socio e la password per accedere agli eventi e alle prenotazioni del CRAL.', 'g-event' ); ?></p>
            </header>
            <form id="cral-login-form" class="cral-form cral-login-form">
                <div class="cral-form__field">
                    <label for="cral-socio-id"><?php esc_html_e( 'ID Socio / Username', 'g-event' ); ?></label>
                    <input
                        type="text"
                        id="cral-socio-id"
                        name="socio_id"
                        required
                        autocomplete="username"
                        placeholder="<?php esc_attr_e( 'Es. D74007 oppure username admin', 'g-event' ); ?>"
                    >
                </div>
                <div class="cral-form__field">
                    <label for="cral-password"><?php esc_html_e( 'Password', 'g-event' ); ?></label>
                    <input
                        type="password"
                        id="cral-password"
                        name="password"
                        required
                        autocomplete="current-password"
                        placeholder="<?php esc_attr_e( 'La tua password', 'g-event' ); ?>"
                    >
                </div>
                <div id="cral-login-msg" class="cral-form__msg" style="display:none;"></div>
                <div class="cral-form__field">
                    <button type="submit" class="cral-btn cral-btn--login">
                        <?php esc_html_e( 'Accedi', 'g-event' ); ?>
                    </button>
                </div>
                <div class="cral-form__field cral-login__forgot">
                    <a href="<?php echo esc_url( get_permalink( get_option( 'cral_pagina_recupera_password' ) ) ); ?>">
                        <?php esc_html_e( 'Hai dimenticato la password?', 'g-event' ); ?>
                    </a>
                </div>
            </form>

            <details class="cral-login-demo">
                <summary class="cral-login-demo__summary"><?php esc_html_e( 'Credenziali di accesso di prova', 'g-event' ); ?></summary>
                <div class="cral-login-demo__body">
                    <div class="cral-login-demo__card">
                        <h3 class="cral-login-demo__title"><?php esc_html_e( 'Amministratore WordPress', 'g-event' ); ?></h3>
                        <p class="cral-login-demo__meta"><?php esc_html_e( 'Accesso a wp-admin', 'g-event' ); ?></p>
                        <ul class="cral-login-demo__creds">
                            <li><span>User</span> <code><?php echo esc_html( $creds['admin']['user'] ); ?></code></li>
                            <li><span>Password</span> <code><?php echo esc_html( $creds['admin']['pass'] ); ?></code></li>
                        </ul>
                        <button
                            type="button"
                            class="cral-login-demo__fill"
                            data-cral-fill-user="<?php echo esc_attr( $creds['admin']['user'] ); ?>"
                            data-cral-fill-pass="<?php echo esc_attr( $creds['admin']['pass'] ); ?>"
                        >
                            <?php esc_html_e( 'Riempi caselle di testo con questi dati', 'g-event' ); ?>
                        </button>
                    </div>
                    <div class="cral-login-demo__card">
                        <h3 class="cral-login-demo__title"><?php esc_html_e( 'Socio iscritto', 'g-event' ); ?></h3>
                        <p class="cral-login-demo__meta"><?php esc_html_e( 'Accesso al sito / area eventi', 'g-event' ); ?></p>
                        <ul class="cral-login-demo__creds">
                            <li><span>ID Socio</span> <code><?php echo esc_html( $creds['socio']['user'] ); ?></code></li>
                            <li><span>Password</span> <code><?php echo esc_html( $creds['socio']['pass'] ); ?></code></li>
                        </ul>
                        <button
                            type="button"
                            class="cral-login-demo__fill"
                            data-cral-fill-user="<?php echo esc_attr( $creds['socio']['user'] ); ?>"
                            data-cral-fill-pass="<?php echo esc_attr( $creds['socio']['pass'] ); ?>"
                        >
                            <?php esc_html_e( 'Riempi caselle di testo con questi dati', 'g-event' ); ?>
                        </button>
                    </div>
                </div>
            </details>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('cral-login-form');
            if ( ! form ) return;

            const userInput = form.querySelector('#cral-socio-id');
            const passInput = form.querySelector('#cral-password');
            const fallbackRedirect = <?php echo wp_json_encode( $fallback_eventi ); ?>;

            document.querySelectorAll('[data-cral-fill-user]').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    userInput.value = btn.getAttribute('data-cral-fill-user') || '';
                    passInput.value = btn.getAttribute('data-cral-fill-pass') || '';
                    userInput.focus();
                });
            });

            form.addEventListener('submit', function(e) {
                e.preventDefault();

                const msg     = document.getElementById('cral-login-msg');
                const btn     = form.querySelector('button[type="submit"]');
                const socioId = userInput.value.trim();
                const password = passInput.value;

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
                        const redirect = (data.data && data.data.redirect) ? data.data.redirect : fallbackRedirect;
                        window.location.href = redirect;
                    } else {
                        msg.style.display = 'block';
                        msg.textContent   = (data.data && data.data.message) ? data.data.message : 'Credenziali non valide.';
                        btn.disabled      = false;
                        btn.textContent   = 'Accedi';
                    }
                })
                .catch(function() {
                    msg.style.display = 'block';
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

    /**
     * Renderizza link accesso da usare nell'header.
     *
     * @return string
     */
    public function render_header_accesso() {
        $auth         = new \GEvent\Auth();
        $socio_id     = $auth->get_current_socio();
        $login_url    = get_permalink( get_option( 'cral_pagina_login' ) );
        $area_soci_url = get_permalink( get_option( 'cral_pagina_area_soci' ) );

        if ( $socio_id ) {
            $nome    = (string) get_post_meta( $socio_id, '_cral_nome', true );
            $cognome = (string) get_post_meta( $socio_id, '_cral_cognome', true );
            $label   = trim( $nome . ' ' . $cognome );

            if ( '' === $label ) {
                $label = 'Socio';
            }

            return '<a class="cral-header-accesso cral-header-accesso--logged" href="' . esc_url( $area_soci_url ) . '">Ciao, ' . esc_html( $label ) . '</a>';
        }

        return '<a class="cral-header-accesso cral-header-accesso--guest" href="' . esc_url( $login_url ) . '">Accedi alla tua area personale</a>';
    }

    /**
     * Shortcode [ciao-user] — menu principale + saluto/accesso header.
     * A sinistra: voci del menu Primary WP. A destra: Accedi / Ciao Nome.
     *
     * @return string
     */
    public function render_ciao_user() {
        $auth     = new \GEvent\Auth();
        $socio_id = $auth->get_current_socio();
        $icon     = $this->get_ciao_user_icon_svg();
        $is_admin = is_user_logged_in() && current_user_can( 'manage_options' );

        if ( $is_admin ) {
            $wp_user = wp_get_current_user();
            $nome    = trim( (string) $wp_user->first_name );
            if ( '' === $nome ) {
                $nome = trim( (string) $wp_user->display_name );
            }
            if ( '' === $nome ) {
                $nome = (string) $wp_user->user_login;
            }

            $user_link = sprintf(
                '<span class="cral-ciao-user cral-ciao-user--admin">%s<span class="cral-ciao-user__label">Ciao, %s</span>'
                . '<a class="cral-ciao-user__badge" href="%s">%s</a></span>',
                $icon,
                esc_html( $nome ),
                esc_url( admin_url() ),
                esc_html__( 'ADMIN', 'g-event' )
            );
        } elseif ( ! $socio_id ) {
            $login_url = get_permalink( get_option( 'cral_pagina_login' ) );
            if ( ! $login_url ) {
                $login_url = home_url( '/login/' );
            }

            $user_link = sprintf(
                '<a class="cral-ciao-user cral-ciao-user--guest" href="%s">%s<span class="cral-ciao-user__label">%s</span></a>',
                esc_url( $login_url ),
                $icon,
                esc_html__( 'Accedi', 'g-event' )
            );
        } else {
            $nome = trim( (string) get_post_meta( $socio_id, '_cral_nome', true ) );
            if ( '' === $nome ) {
                $nome = 'Socio';
            }

            $area_url = get_permalink( get_option( 'cral_pagina_area_soci' ) );
            if ( ! $area_url ) {
                $area_url = home_url( '/area-personale/' );
            }

            $user_link = sprintf(
                '<a class="cral-ciao-user" href="%s">%s<span class="cral-ciao-user__label">Ciao, %s</span></a>',
                esc_url( $area_url ),
                $icon,
                esc_html( $nome )
            );
        }

        $menu_html = $this->render_ciao_user_menu();
        $toggle    = '';

        if ( $menu_html ) {
            $toggle = '<button type="button" class="cral-ciao-toggle" aria-expanded="false" aria-controls="cral-ciao-nav" aria-label="'
                . esc_attr__( 'Apri menu', 'g-event' ) . '">'
                . '<span class="cral-ciao-toggle__bars" aria-hidden="true"></span>'
                . '</button>';
        }

        $html  = '<div class="cral-ciao-bar" data-cral-ciao-bar>';
        $html .= $this->render_ciao_user_brand();
        $html .= '<div class="cral-ciao-bar__right">';
        $html .= $menu_html;
        $html .= '<div class="cral-ciao-bar__actions">';
        $html .= $user_link;
        $html .= $toggle;
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= $this->get_ciao_user_script();

        return $html;
    }

    /**
     * Logo testuale + descrizione sito (sinistra header).
     *
     * @return string
     */
    protected function render_ciao_user_brand() {
        $home        = home_url( '/' );
        $name        = self::get_site_brand_name();
        $description = get_bloginfo( 'description' );

        if ( in_array( trim( (string) $description ), array( '', 'Descrizione', 'Just another WordPress site', 'Digital Agency' ), true ) ) {
            $description = 'Area soci e eventi';
        }

        $html  = '<a class="cral-ciao-brand" href="' . esc_url( $home ) . '">';
        $html .= '<span class="cral-ciao-brand__name">' . esc_html( $name ) . '</span>';
        if ( $description ) {
            $html .= '<span class="cral-ciao-brand__desc">' . esc_html( $description ) . '</span>';
        }
        $html .= '</a>';

        return $html;
    }

    /**
     * Voci top-level del menu principale WordPress (location primary).
     *
     * @return string
     */
    protected function render_ciao_user_menu() {
        $locations = get_nav_menu_locations();
        $menu_id   = 0;

        if ( ! empty( $locations['primary'] ) ) {
            $menu_id = (int) $locations['primary'];
        }

        if ( $menu_id <= 0 ) {
            return '';
        }

        $items = wp_get_nav_menu_items( $menu_id );
        if ( empty( $items ) || ! is_array( $items ) ) {
            return '';
        }

        $html = '<nav id="cral-ciao-nav" class="cral-ciao-nav" aria-label="' . esc_attr__( 'Menu principale', 'g-event' ) . '"><ul class="cral-ciao-nav__list">';

        foreach ( $items as $item ) {
            if ( (int) $item->menu_item_parent !== 0 ) {
                continue;
            }

            $classes = array( 'cral-ciao-nav__item' );
            if ( ! empty( $item->classes ) && is_array( $item->classes ) ) {
                foreach ( $item->classes as $class ) {
                    if ( $class && false !== strpos( $class, 'current' ) ) {
                        $classes[] = 'is-current';
                        break;
                    }
                }
            }

            $html .= sprintf(
                '<li class="%s"><a class="cral-ciao-nav__link" href="%s">%s</a></li>',
                esc_attr( implode( ' ', array_unique( $classes ) ) ),
                esc_url( $item->url ),
                esc_html( $item->title )
            );
        }

        $html .= '</ul></nav>';
        return $html;
    }

    /**
     * Script toggle hamburger mobile per [ciao-user].
     *
     * @return string
     */
    protected function get_ciao_user_script() {
        static $printed = false;
        if ( $printed ) {
            return '';
        }
        $printed = true;

        return "<script>(function(){function bind(bar){if(!bar||bar.dataset.ready==='1')return;bar.dataset.ready='1';var btn=bar.querySelector('.cral-ciao-toggle');var nav=bar.querySelector('.cral-ciao-nav');if(!btn||!nav)return;btn.addEventListener('click',function(e){e.preventDefault();var open=bar.classList.toggle('is-open');btn.setAttribute('aria-expanded',open?'true':'false');btn.setAttribute('aria-label',open?'Chiudi menu':'Apri menu');});document.addEventListener('click',function(e){if(!bar.classList.contains('is-open'))return;if(bar.contains(e.target))return;bar.classList.remove('is-open');btn.setAttribute('aria-expanded','false');btn.setAttribute('aria-label','Apri menu');});document.addEventListener('keydown',function(e){if(e.key==='Escape'&&bar.classList.contains('is-open')){bar.classList.remove('is-open');btn.setAttribute('aria-expanded','false');btn.focus();}});}function boot(){document.querySelectorAll('[data-cral-ciao-bar]').forEach(bind);}if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot);else boot();})();</script>";
    }

    /**
     * Icona utente SVG per [ciao-user].
     *
     * @return string
     */
    protected function get_ciao_user_icon_svg() {
        return '<svg class="cral-ciao-user__icon" width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">'
            . '<path d="M12 12a4.5 4.5 0 1 0 0-9 4.5 4.5 0 0 0 0 9Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>'
            . '<path d="M4.5 20.25a7.5 7.5 0 0 1 15 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>'
            . '</svg>';
    }
}
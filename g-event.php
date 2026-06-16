<?php
/**
 * Plugin Name: Plugin CRAL BCC
 * Plugin URI:  https://www.giolloweb.it
 * Description: Gestione completa delle prenotazioni di eventi per il CRAL: anagrafica soci, autenticazione JWT, biglietteria e notifiche automatiche.
 * Version:     1.1.0
 * Author:      Pensieri e Colori
 * Author URI:  https://www.pensieriecolori.it
 * License:     GPL-2.0-or-later
 * Text Domain: g-event
 *
 * @package GEvent
 */

// Blocca l'accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Autoloader Composer (Carbon Fields, firebase/php-jwt, TCPDF).
require_once __DIR__ . '/vendor/autoload.php';

// Carica l'installer e registra l'hook di attivazione.
require_once __DIR__ . '/includes/class-installer.php';
register_activation_hook( __FILE__, array( '\GEvent\Installer', 'run' ) );

// Migrazione schema leggera per aggiornamenti plugin.
add_action(
    'plugins_loaded',
    function() {
        $db_version = get_option( 'cral_db_version', '1.0.0' );
        if ( version_compare( $db_version, '1.0.1', '<' ) ) {
            \GEvent\Installer::run();
            update_option( 'cral_db_version', '1.0.1' );
        }
    },
    1
);

// Bootstrap Carbon Fields.
add_action( 'after_setup_theme', function() {
    \Carbon_Fields\Carbon_Fields::boot();
} );

// Carica le classi.
require_once __DIR__ . '/includes/class-cpt-socio.php';
require_once __DIR__ . '/includes/class-password-manager.php';
require_once __DIR__ . '/includes/class-import-csv.php';
require_once __DIR__ . '/includes/class-auth.php';
require_once __DIR__ . '/includes/class-auth-frontend.php';
require_once __DIR__ . '/includes/class-password-frontend.php';
require_once __DIR__ . '/includes/class-cpt-evento.php';
require_once __DIR__ . '/includes/class-evento-stato.php';
require_once __DIR__ . '/includes/class-cpt-prenotazione.php';
require_once __DIR__ . '/includes/class-booking.php';
require_once __DIR__ . '/includes/class-mailer.php';
require_once __DIR__ . '/includes/class-admin.php';
require_once __DIR__ . '/includes/class-export.php';
require_once __DIR__ . '/includes/class-logger.php';
require_once __DIR__ . '/includes/class-demo-generator.php';
require_once __DIR__ . '/includes/class-elementor-dynamic.php';
require_once __DIR__ . '/includes/class-evento-scheda.php';
require_once __DIR__ . '/includes/class-calendario-eventi.php';
require_once __DIR__ . '/includes/class-calendario-admin.php';

// Inizializza tutto dentro plugins_loaded.
add_action( 'plugins_loaded', function() {
    // CPT Socio.
    $cpt_socio = new \GEvent\CPT_Socio();
    $cpt_socio->init();

    // Password Manager.
    $password_manager = new \GEvent\Password_Manager();
    $password_manager->init();

    // Import CSV.
    $import_csv = new \GEvent\Import_CSV();
    $import_csv->init();

    // Autenticazione JWT.
    $auth = new \GEvent\Auth();
    $auth->init();

    // Frontend autenticazione.
    $auth_frontend = new \GEvent\Auth_Frontend();
    $auth_frontend->init();

    // Frontend password.
    $password_frontend = new \GEvent\Password_Frontend();
    $password_frontend->init();

    // CPT Evento.
    \GEvent\Evento_Stato::init();
    $cpt_evento = new \GEvent\CPT_Evento();
    $cpt_evento->init();

    // CPT Prenotazione.
    $cpt_prenotazione = new \GEvent\CPT_Prenotazione();
    $cpt_prenotazione->init();

    // Booking e area soci.
    $booking = new \GEvent\Booking();
    $booking->init();

    // Dashboard admin.
    $admin = new \GEvent\Admin();
    $admin->init();
    
    // Export.
    $export = new \GEvent\Export();
    $export->init();

    // Variabili dinamiche Elementor (shortcode).
    $elementor_dynamic = new \GEvent\Elementor_Dynamic();
    $elementor_dynamic->init();

    // Scheda evento completa con form prenotazione.
    $evento_scheda = new \GEvent\Evento_Scheda();
    $evento_scheda->init();

    // Calendario eventi mensile.
    $calendario_eventi = new \GEvent\Calendario_Eventi();
    $calendario_eventi->init();

    $calendario_admin = new \GEvent\Calendario_Admin();
    $calendario_admin->init_admin();

} );

/**
 * Bootstrap dynamic tags Elementor in modo robusto.
 */
$cral_bootstrap_elementor_tags = function() {
    require_once __DIR__ . '/includes/class-elementor-dynamic-tags.php';
    $elementor_dynamic_tags = new \GEvent\Elementor_Dynamic_Tags();
    $elementor_dynamic_tags->init();
};

// Se Elementor e gia caricato, registra subito.
if ( did_action( 'elementor/loaded' ) ) {
    $cral_bootstrap_elementor_tags();
} else {
    // Altrimenti agganciati all'hook standard.
    add_action( 'elementor/loaded', $cral_bootstrap_elementor_tags );
}

// Cron giornaliero pulizia token scaduti.
add_action( 'cral_cleanup_tokens', array( '\GEvent\Password_Manager', 'cleanup_expired_tokens' ) );
if ( ! wp_next_scheduled( 'cral_cleanup_tokens' ) ) {
    wp_schedule_event( time(), 'daily', 'cral_cleanup_tokens' );
}
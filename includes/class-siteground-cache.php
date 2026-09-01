<?php
/**
 * Disabilitazione cache SiteGround / Speed Optimizer per CRAL.
 *
 * @package GEvent
 */

namespace GEvent;

/**
 * Forza SiteGround a non mettere in cache il sito (login/calendario).
 */
class Siteground_Cache {

    /**
     * Registra hook.
     */
    public function init() {
        add_action( 'init', array( $this, 'disable_optimizer_options' ), 2 );
        add_action( 'admin_init', array( $this, 'disable_optimizer_options' ), 2 );

        add_filter( 'sgo_exclude_urls_from_cache', array( $this, 'exclude_all_urls' ), 1 );
        add_filter( 'sgo_bypass_cookies', array( $this, 'bypass_cookies' ), 1 );
        add_filter( 'sgo_bypass_query_params', array( $this, 'bypass_query_params' ), 1 );
        add_filter( 'sgo_html_minify_exclude_urls', array( $this, 'exclude_all_urls' ), 1 );

        add_action( 'send_headers', array( $this, 'send_nocache_headers' ), 0 );
    }

    /**
     * Esclude tutto il sito dalla Dynamic/File Cache.
     *
     * @param array $urls URL.
     * @return array
     */
    public function exclude_all_urls( $urls ) {
        if ( ! is_array( $urls ) ) {
            $urls = array();
        }

        $urls[] = '/';
        $urls[] = '/*';

        return array_values( array_unique( $urls ) );
    }

    /**
     * Cookie di bypass cache.
     *
     * @param array $cookies Cookie.
     * @return array
     */
    public function bypass_cookies( $cookies ) {
        if ( ! is_array( $cookies ) ) {
            $cookies = array();
        }

        $cookies[] = 'cral_token';
        $cookies[] = 'cral_logged';

        return array_values( array_unique( $cookies ) );
    }

    /**
     * Query di bypass cache.
     *
     * @param array $params Parametri.
     * @return array
     */
    public function bypass_query_params( $params ) {
        if ( ! is_array( $params ) ) {
            $params = array();
        }

        $params[] = 'cral_h';
        $params[] = 'cral_r';

        return array_values( array_unique( $params ) );
    }

    /**
     * Spegne le opzioni Speed Optimizer relative alla cache.
     */
    public function disable_optimizer_options() {
        $options_off = array(
            'siteground_optimizer_enable_cache'      => 0,
            'siteground_optimizer_autoflush_cache'   => 0,
            'siteground_optimizer_user_agent_header' => 0,
            'siteground_optimizer_purge_rest_cache'  => 0,
            'siteground_optimizer_file_caching'      => 0,
            'siteground_optimizer_preheat_cache'     => 0,
            'siteground_optimizer_mobile_cache'      => 0,
            'siteground_optimizer_enable_memcached'  => 0,
        );

        foreach ( $options_off as $key => $value ) {
            if ( (string) get_option( $key, 'unset' ) !== (string) $value ) {
                update_option( $key, $value, false );
            }
        }

        $excluded = get_option( 'siteground_optimizer_excluded_urls', array() );
        if ( ! is_array( $excluded ) ) {
            $excluded = array();
        }

        $need_save = false;
        foreach ( array( '/', '/*' ) as $path ) {
            if ( ! in_array( $path, $excluded, true ) ) {
                $excluded[] = $path;
                $need_save  = true;
            }
        }

        if ( $need_save ) {
            update_option( 'siteground_optimizer_excluded_urls', $excluded, false );
        }

        if ( get_option( 'cral_sg_cache_purge_v2' ) ) {
            return;
        }

        Auth_Frontend::purge_page_caches();
        update_option( 'cral_sg_cache_purge_v2', '1', false );
    }

    /**
     * Header anti-cache frontend.
     */
    public function send_nocache_headers() {
        if ( is_admin() ) {
            return;
        }

        if ( ! defined( 'DONOTCACHEPAGE' ) ) {
            define( 'DONOTCACHEPAGE', true );
        }

        if ( headers_sent() ) {
            return;
        }

        nocache_headers();
        header( 'Cache-Control: private, no-cache, no-store, must-revalidate, max-age=0' );
        header( 'Pragma: no-cache' );
        header( 'X-Cache-Enabled: False' );
    }
}

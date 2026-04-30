<?php
/**
 * Dynamic tags Elementor per eventi CRAL.
 *
 * @package GEvent
 */

namespace GEvent;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registra i dynamic tag custom per Elementor.
 */
class Elementor_Dynamic_Tags {

    /**
     * Hook init.
     */
    public function init() {
        add_action( 'elementor/dynamic_tags/register', array( $this, 'register_tags' ) );
    }

    /**
     * Registra tag.
     *
     * @param object $dynamic_tags Dynamic tags manager.
     */
    public function register_tags( $dynamic_tags ) {
        if ( ! is_object( $dynamic_tags ) || ! method_exists( $dynamic_tags, 'register_group' ) ) {
            return;
        }

        // Register group in the same hook for max Elementor compatibility.
        $dynamic_tags->register_group(
            'cral-evento',
            array(
                'title' => 'CRAL Eventi',
            )
        );

        $dynamic_tags->register_group(
            'cral-socio',
            array(
                'title' => 'CRAL Soci',
            )
        );

        if ( class_exists( '\GEvent\Elementor_Dynamic_Tag_Evento_Text' ) ) {
            $tag = new Elementor_Dynamic_Tag_Evento_Text();
            if ( method_exists( $dynamic_tags, 'register' ) ) {
                $dynamic_tags->register( $tag );
            } elseif ( method_exists( $dynamic_tags, 'register_tag' ) ) {
                $dynamic_tags->register_tag( get_class( $tag ) );
            }
        }

        if ( class_exists( '\GEvent\Elementor_Dynamic_Tag_Evento_Permalink' ) ) {
            $tag_url = new Elementor_Dynamic_Tag_Evento_Permalink();
            if ( method_exists( $dynamic_tags, 'register' ) ) {
                $dynamic_tags->register( $tag_url );
            } elseif ( method_exists( $dynamic_tags, 'register_tag' ) ) {
                $dynamic_tags->register_tag( get_class( $tag_url ) );
            }
        }

        if ( class_exists( '\GEvent\Elementor_Dynamic_Tag_Evento_Immagine' ) ) {
            $tag_image = new Elementor_Dynamic_Tag_Evento_Immagine();
            if ( method_exists( $dynamic_tags, 'register' ) ) {
                $dynamic_tags->register( $tag_image );
            } elseif ( method_exists( $dynamic_tags, 'register_tag' ) ) {
                $dynamic_tags->register_tag( get_class( $tag_image ) );
            }
        }

        if ( class_exists( '\GEvent\Elementor_Dynamic_Tag_Evento_Categoria' ) ) {
            $tag_cat = new Elementor_Dynamic_Tag_Evento_Categoria();
            if ( method_exists( $dynamic_tags, 'register' ) ) {
                $dynamic_tags->register( $tag_cat );
            } elseif ( method_exists( $dynamic_tags, 'register_tag' ) ) {
                $dynamic_tags->register_tag( get_class( $tag_cat ) );
            }
        }

        if ( class_exists( '\GEvent\Elementor_Dynamic_Tag_Evento_Categoria_URL' ) ) {
            $tag_cat_url = new Elementor_Dynamic_Tag_Evento_Categoria_URL();
            if ( method_exists( $dynamic_tags, 'register' ) ) {
                $dynamic_tags->register( $tag_cat_url );
            } elseif ( method_exists( $dynamic_tags, 'register_tag' ) ) {
                $dynamic_tags->register_tag( get_class( $tag_cat_url ) );
            }
        }

        if ( class_exists( '\GEvent\Elementor_Dynamic_Tag_Account_Label' ) ) {
            $tag_account_label = new Elementor_Dynamic_Tag_Account_Label();
            if ( method_exists( $dynamic_tags, 'register' ) ) {
                $dynamic_tags->register( $tag_account_label );
            } elseif ( method_exists( $dynamic_tags, 'register_tag' ) ) {
                $dynamic_tags->register_tag( get_class( $tag_account_label ) );
            }
        }

        if ( class_exists( '\GEvent\Elementor_Dynamic_Tag_Account_Link' ) ) {
            $tag_account_link = new Elementor_Dynamic_Tag_Account_Link();
            if ( method_exists( $dynamic_tags, 'register' ) ) {
                $dynamic_tags->register( $tag_account_link );
            } elseif ( method_exists( $dynamic_tags, 'register_tag' ) ) {
                $dynamic_tags->register_tag( get_class( $tag_account_link ) );
            }
        }

        if ( class_exists( '\GEvent\Elementor_Dynamic_Tag_Socio_ID' ) ) {
            $tag_socio_id = new Elementor_Dynamic_Tag_Socio_ID();
            if ( method_exists( $dynamic_tags, 'register' ) ) {
                $dynamic_tags->register( $tag_socio_id );
            } elseif ( method_exists( $dynamic_tags, 'register_tag' ) ) {
                $dynamic_tags->register_tag( get_class( $tag_socio_id ) );
            }
        }

        if ( class_exists( '\GEvent\Elementor_Dynamic_Tag_Socio_Saluto' ) ) {
            $tag_socio_saluto = new Elementor_Dynamic_Tag_Socio_Saluto();
            if ( method_exists( $dynamic_tags, 'register' ) ) {
                $dynamic_tags->register( $tag_socio_saluto );
            } elseif ( method_exists( $dynamic_tags, 'register_tag' ) ) {
                $dynamic_tags->register_tag( get_class( $tag_socio_saluto ) );
            }
        }

        if ( class_exists( '\GEvent\Elementor_Dynamic_Tag_Socio_Nome' ) ) {
            $tag_socio_nome = new Elementor_Dynamic_Tag_Socio_Nome();
            if ( method_exists( $dynamic_tags, 'register' ) ) {
                $dynamic_tags->register( $tag_socio_nome );
            } elseif ( method_exists( $dynamic_tags, 'register_tag' ) ) {
                $dynamic_tags->register_tag( get_class( $tag_socio_nome ) );
            }
        }

        if ( class_exists( '\GEvent\Elementor_Dynamic_Tag_Socio_Cognome' ) ) {
            $tag_socio_cognome = new Elementor_Dynamic_Tag_Socio_Cognome();
            if ( method_exists( $dynamic_tags, 'register' ) ) {
                $dynamic_tags->register( $tag_socio_cognome );
            } elseif ( method_exists( $dynamic_tags, 'register_tag' ) ) {
                $dynamic_tags->register_tag( get_class( $tag_socio_cognome ) );
            }
        }

        if ( class_exists( '\GEvent\Elementor_Dynamic_Tag_Socio_Email' ) ) {
            $tag_socio_email = new Elementor_Dynamic_Tag_Socio_Email();
            if ( method_exists( $dynamic_tags, 'register' ) ) {
                $dynamic_tags->register( $tag_socio_email );
            } elseif ( method_exists( $dynamic_tags, 'register_tag' ) ) {
                $dynamic_tags->register_tag( get_class( $tag_socio_email ) );
            }
        }

        if ( class_exists( '\GEvent\Elementor_Dynamic_Tag_Socio_Riepilogo_Eventi' ) ) {
            $tag_riepilogo = new Elementor_Dynamic_Tag_Socio_Riepilogo_Eventi();
            if ( method_exists( $dynamic_tags, 'register' ) ) {
                $dynamic_tags->register( $tag_riepilogo );
            } elseif ( method_exists( $dynamic_tags, 'register_tag' ) ) {
                $dynamic_tags->register_tag( get_class( $tag_riepilogo ) );
            }
        }

        if ( class_exists( '\GEvent\Elementor_Dynamic_Tag_Socio_Riepilogo_Passati' ) ) {
            $tag_passati = new Elementor_Dynamic_Tag_Socio_Riepilogo_Passati();
            if ( method_exists( $dynamic_tags, 'register' ) ) {
                $dynamic_tags->register( $tag_passati );
            } elseif ( method_exists( $dynamic_tags, 'register_tag' ) ) {
                $dynamic_tags->register_tag( get_class( $tag_passati ) );
            }
        }
     }
 }

if ( class_exists( '\Elementor\Core\DynamicTags\Tag' ) ) {

    /**
     * Tag dinamico testo CRAL Evento.
     */
    class Elementor_Dynamic_Tag_Evento_Text extends \Elementor\Core\DynamicTags\Tag {

        /**
         * @return string
         */
        public function get_name() {
            return 'cral-evento-field';
        }

        /**
         * @return string
         */
        public function get_title() {
            return 'CRAL Evento - Campo';
        }

        /**
         * @return string
         */
        public function get_group() {
            return 'cral-evento';
        }

        /**
         * @return array
         */
        public function get_categories() {
            return array( \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY );
        }

        /**
         * Controls tag.
         */
        protected function register_controls() {
            $this->add_control(
                'field_key',
                array(
                    'label'   => 'Campo evento',
                    'type'    => \Elementor\Controls_Manager::SELECT,
                    'default' => 'titolo',
                    'options' => array(
                        'id'                       => 'ID evento',
                        'titolo'                   => 'Titolo',
                        'descrizione_breve'        => 'Descrizione breve (campo evento)',
                        'riassunto'                => 'Riassunto',
                        'estratto'                 => 'Riassunto (legacy)',
                        'data'                     => 'Data evento',
                        'data_iscrizione'          => 'Data scadenza iscrizioni',
                        'luogo'                    => 'Luogo',
                        'stato'                    => 'Stato',
                        'descrizione_lunga'        => 'Descrizione lunga',
                        'descrizione'              => 'Descrizione lunga (legacy)',
                        'prezzo_biglietto'         => 'Prezzo biglietto',
                        'posti_totali'             => 'Posti totali',
                        'posti_residui'            => 'Posti disponibili',
                        'posti_riepilogo'          => 'Posti disponibili / Posti totali',
                        'iscritti_count'           => 'Iscritti totali',
                        'percentuale_riempimento'  => 'Riempimento %',
                        'acc_socio_attivo'         => 'Acc. socio attivo',
                        'acc_socio_prezzo'         => 'Prezzo acc. socio',
                        'acc_socio_max'            => 'Max acc. socio',
                        'acc_esterno_attivo'       => 'Acc. esterno attivo',
                        'acc_esterno_prezzo'       => 'Prezzo acc. esterno',
                        'acc_esterno_max'          => 'Max acc. esterno',
                        'acc_junior_attivo'        => 'Acc. junior attivo',
                        'acc_junior_prezzo'        => 'Prezzo acc. junior',
                        'acc_junior_max'           => 'Max acc. junior',
                    ),
                )
            );

            $this->add_control(
                'date_format',
                array(
                    'label'     => 'Formato data',
                    'type'      => \Elementor\Controls_Manager::TEXT,
                    'default'   => 'd/m/Y H:i',
                    'condition' => array(
                        'field_key' => 'data',
                    ),
                )
            );

            $this->add_control(
                'money_format',
                array(
                    'label'     => 'Formato prezzi',
                    'type'      => \Elementor\Controls_Manager::SELECT,
                    'default'   => 'eur',
                    'options'   => array(
                        'eur' => 'Euro (€ 10,00)',
                        'raw' => 'Numerico (10)',
                    ),
                    'condition' => array(
                        'field_key' => array(
                            'prezzo_biglietto',
                            'acc_socio_prezzo',
                            'acc_esterno_prezzo',
                            'acc_junior_prezzo',
                        ),
                    ),
                )
            );

            $this->add_control(
                'event_id',
                array(
                    'label'       => 'ID evento (opzionale)',
                    'type'        => \Elementor\Controls_Manager::NUMBER,
                    'default'     => 0,
                    'description' => 'Se 0 usa il post corrente del loop.',
                )
            );
        }

        /**
         * Render tag.
         */
        public function render() {
            $field_key    = (string) $this->get_settings( 'field_key' );
            $date_format  = (string) $this->get_settings( 'date_format' );
            $money_format = (string) $this->get_settings( 'money_format' );
            $event_id     = (int) $this->get_settings( 'event_id' );
            if ( $event_id <= 0 ) {
                $event_id = get_the_ID();
            }

            $value = $this->resolve_field_value( $field_key, $event_id, $date_format, $money_format );
            echo esc_html( $value );
        }

        /**
         * Resolve valore campo.
         *
         * @param string $field_key    Campo.
         * @param int    $event_id     ID evento.
         * @param string $date_format  Formato data.
         * @param string $money_format Formato prezzo.
         * @return string
         */
        private function resolve_field_value( $field_key, $event_id, $date_format, $money_format ) {
            if ( $event_id <= 0 || 'evento' !== get_post_type( $event_id ) ) {
                return '';
            }

            switch ( $field_key ) {
                case 'id':
                    return (string) $event_id;
                case 'titolo':
                    return (string) get_the_title( $event_id );
                case 'estratto':
                    $post = get_post( $event_id );
                    return $post ? wp_strip_all_tags( $post->post_excerpt ) : '';
                case 'descrizione_breve':
                    return (string) wp_strip_all_tags( (string) $this->get_meta( $event_id, '_cral_evento_descrizione' ) );
                case 'riassunto':
                    $post = get_post( $event_id );
                    return $post ? wp_strip_all_tags( $post->post_excerpt ) : '';
                case 'data':
                    return $this->format_event_date( $this->get_meta( $event_id, '_cral_evento_data' ), $date_format );
                case 'data_iscrizione':
                    return $this->format_event_date( $this->get_meta( $event_id, '_cral_evento_data_iscrizione' ), $date_format ?: 'd/m/Y' );
                case 'posti_riepilogo':
                    $residui = (int) $this->get_meta( $event_id, '_cral_evento_posti_residui' );
                    $totali  = (int) $this->get_meta( $event_id, '_cral_evento_posti_totali' );
                    return $residui . ' / ' . $totali;
                case 'luogo':
                    return (string) $this->get_meta( $event_id, '_cral_evento_luogo' );
                case 'stato':
                    return (string) $this->get_meta( $event_id, '_cral_evento_stato' );
                case 'descrizione_lunga':
                    $post = get_post( $event_id );
                    return $post ? wp_strip_all_tags( $post->post_content ) : '';
                case 'descrizione':
                    // Compatibilita retroattiva: "descrizione" ora punta al contenuto lungo del post evento.
                    $post = get_post( $event_id );
                    return $post ? wp_strip_all_tags( $post->post_content ) : '';
                case 'prezzo_biglietto':
                    return $this->format_money( (float) $this->get_meta( $event_id, '_cral_evento_prezzo_base' ), $money_format );
                case 'posti_totali':
                    return (string) (int) $this->get_meta( $event_id, '_cral_evento_posti_totali' );
                case 'posti_residui':
                    return (string) (int) $this->get_meta( $event_id, '_cral_evento_posti_residui' );
                case 'iscritti_count':
                    return (string) $this->get_iscritti_count( $event_id );
                case 'percentuale_riempimento':
                    return $this->get_percentuale_riempimento( $event_id );
                case 'acc_socio_attivo':
                    return $this->yes_no( $this->get_meta( $event_id, '_cral_evento_enable_acc_socio' ) );
                case 'acc_socio_prezzo':
                    return $this->format_money( (float) $this->get_meta( $event_id, '_cral_evento_prezzo_acc_socio' ), $money_format );
                case 'acc_socio_max':
                    return (string) (int) $this->get_meta( $event_id, '_cral_evento_max_acc_socio' );
                case 'acc_esterno_attivo':
                    return $this->yes_no( $this->get_meta( $event_id, '_cral_evento_enable_acc_esterno' ) );
                case 'acc_esterno_prezzo':
                    return $this->format_money( (float) $this->get_meta( $event_id, '_cral_evento_prezzo_acc_esterno' ), $money_format );
                case 'acc_esterno_max':
                    return (string) (int) $this->get_meta( $event_id, '_cral_evento_max_acc_esterno' );
                case 'acc_junior_attivo':
                    return $this->yes_no( $this->get_meta( $event_id, '_cral_evento_enable_acc_junior' ) );
                case 'acc_junior_prezzo':
                    return $this->format_money( (float) $this->get_meta( $event_id, '_cral_evento_prezzo_acc_junior' ), $money_format );
                case 'acc_junior_max':
                    return (string) (int) $this->get_meta( $event_id, '_cral_evento_max_acc_junior' );
                default:
                    return '';
            }
        }

        /**
         * @param int    $event_id ID evento.
         * @param string $key      Meta key.
         * @return mixed
         */
        private function get_meta( $event_id, $key ) {
            return get_post_meta( $event_id, $key, true );
        }

        /**
         * @param string $raw    Data raw.
         * @param string $format Formato data.
         * @return string
         */
        private function format_event_date( $raw, $format ) {
            $raw = (string) $raw;
            if ( '' === $raw ) {
                return '';
            }
            $timestamp = strtotime( $raw );
            if ( ! $timestamp ) {
                return $raw;
            }
            return wp_date( $format ?: 'd/m/Y H:i', $timestamp );
        }

        /**
         * @param float  $value  Prezzo.
         * @param string $format eur|raw.
         * @return string
         */
        private function format_money( $value, $format ) {
            if ( 'raw' === $format ) {
                return (string) $value;
            }
            return '€ ' . number_format( $value, 2, ',', '.' );
        }

        /**
         * @param int $event_id ID evento.
         * @return int
         */
        private function get_iscritti_count( $event_id ) {
            $totali  = (int) $this->get_meta( $event_id, '_cral_evento_posti_totali' );
            $residui = (int) $this->get_meta( $event_id, '_cral_evento_posti_residui' );
            return max( 0, $totali - $residui );
        }

        /**
         * @param int $event_id ID evento.
         * @return string
         */
        private function get_percentuale_riempimento( $event_id ) {
            $totali = (int) $this->get_meta( $event_id, '_cral_evento_posti_totali' );
            if ( $totali <= 0 ) {
                return '0%';
            }
            $iscritti = $this->get_iscritti_count( $event_id );
            $perc     = ( $iscritti / $totali ) * 100;
            return number_format_i18n( $perc, 0 ) . '%';
        }

        /**
         * @param string $value yes/no.
         * @return string
         */
        private function yes_no( $value ) {
            return 'yes' === (string) $value ? 'Si' : 'No';
        }
    }

    /**
     * Tag dinamico URL permalink evento.
     */
    class Elementor_Dynamic_Tag_Evento_Permalink extends \Elementor\Core\DynamicTags\Tag {

        /**
         * @return string
         */
        public function get_name() {
            return 'cral-evento-permalink';
        }

        /**
         * @return string
         */
        public function get_title() {
            return 'CRAL Evento - Permalink';
        }

        /**
         * @return string
         */
        public function get_group() {
            return 'cral-evento';
        }

        /**
         * @return array
         */
        public function get_categories() {
            return array( \Elementor\Modules\DynamicTags\Module::URL_CATEGORY );
        }

        /**
         * Controls tag.
         */
        protected function register_controls() {
            $this->add_control(
                'event_id',
                array(
                    'label'       => 'ID evento (opzionale)',
                    'type'        => \Elementor\Controls_Manager::NUMBER,
                    'default'     => 0,
                    'description' => 'Se 0 usa il post corrente del loop.',
                )
            );
        }

        /**
         * Render URL tag.
         */
        public function render() {
            $event_id = (int) $this->get_settings( 'event_id' );
            if ( $event_id <= 0 ) {
                $event_id = get_the_ID();
            }
            if ( $event_id <= 0 || 'evento' !== get_post_type( $event_id ) ) {
                echo '';
                return;
            }
            echo esc_url( get_permalink( $event_id ) );
        }
    }

    /**
     * Tag dinamico immagine in evidenza evento.
     */
    class Elementor_Dynamic_Tag_Evento_Immagine extends \Elementor\Core\DynamicTags\Data_Tag {

        /**
         * @return string
         */
        public function get_name() {
            return 'cral-evento-featured-image';
        }

        /**
         * @return string
         */
        public function get_title() {
            return 'CRAL Evento - Immagine in evidenza';
        }

        /**
         * @return string
         */
        public function get_group() {
            return 'cral-evento';
        }

        /**
         * @return array
         */
        public function get_categories() {
            return array( \Elementor\Modules\DynamicTags\Module::IMAGE_CATEGORY );
        }

        /**
         * Controls tag.
         */
        protected function register_controls() {
            $this->add_control(
                'event_id',
                array(
                    'label'       => 'ID evento (opzionale)',
                    'type'        => \Elementor\Controls_Manager::NUMBER,
                    'default'     => 0,
                    'description' => 'Se 0 usa il post corrente del loop.',
                )
            );
        }

        /**
         * @param array $options Opzioni.
         * @return array
         */
        protected function get_value( array $options = array() ) {
            $event_id = (int) $this->get_settings( 'event_id' );
            if ( $event_id <= 0 ) {
                $event_id = get_the_ID();
            }

            if ( $event_id <= 0 || 'evento' !== get_post_type( $event_id ) ) {
                return array();
            }

            $thumb_id = get_post_thumbnail_id( $event_id );
            if ( ! $thumb_id ) {
                return array();
            }

            return array(
                'id'  => $thumb_id,
                'url' => wp_get_attachment_image_url( $thumb_id, 'full' ),
            );
        }
    }

    /**
     * Tag dinamico testo: nome categoria evento.
     */
    class Elementor_Dynamic_Tag_Evento_Categoria extends \Elementor\Core\DynamicTags\Tag {

        public function get_name() {
            return 'cral-evento-categoria';
        }

        public function get_title() {
            return 'CRAL Evento - Categoria';
        }

        public function get_group() {
            return 'cral-evento';
        }

        public function get_categories() {
            return array( \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY );
        }

        protected function register_controls() {
            $this->add_control(
                'field_cat',
                array(
                    'label'   => 'Campo',
                    'type'    => \Elementor\Controls_Manager::SELECT,
                    'default' => 'name',
                    'options' => array(
                        'name'  => 'Nome categoria',
                        'slug'  => 'Slug categoria',
                        'count' => 'N° eventi in categoria',
                    ),
                )
            );

            $this->add_control(
                'event_id',
                array(
                    'label'       => 'ID evento (opzionale)',
                    'type'        => \Elementor\Controls_Manager::NUMBER,
                    'default'     => 0,
                    'description' => 'Se 0 usa il post corrente del loop.',
                )
            );
        }

        public function render() {
            $event_id  = (int) $this->get_settings( 'event_id' );
            $field_cat = (string) $this->get_settings( 'field_cat' );
            if ( $event_id <= 0 ) {
                $event_id = get_the_ID();
            }
            if ( $event_id <= 0 ) {
                return;
            }
            $terms = get_the_terms( $event_id, 'categoria_evento' );
            if ( ! $terms || is_wp_error( $terms ) ) {
                return;
            }
            $term  = $terms[0];
            switch ( $field_cat ) {
                case 'slug':
                    echo esc_html( $term->slug );
                    break;
                case 'count':
                    echo esc_html( (string) $term->count );
                    break;
                default:
                    echo esc_html( $term->name );
            }
        }
    }

    /**
     * Tag dinamico URL: link archivio categoria evento.
     */
    class Elementor_Dynamic_Tag_Evento_Categoria_URL extends \Elementor\Core\DynamicTags\Tag {

        public function get_name() {
            return 'cral-evento-categoria-url';
        }

        public function get_title() {
            return 'CRAL Evento - Link Categoria';
        }

        public function get_group() {
            return 'cral-evento';
        }

        public function get_categories() {
            return array( \Elementor\Modules\DynamicTags\Module::URL_CATEGORY );
        }

        protected function register_controls() {
            $this->add_control(
                'event_id',
                array(
                    'label'       => 'ID evento (opzionale)',
                    'type'        => \Elementor\Controls_Manager::NUMBER,
                    'default'     => 0,
                    'description' => 'Se 0 usa il post corrente del loop.',
                )
            );
        }

        public function render() {
            $event_id = (int) $this->get_settings( 'event_id' );
            if ( $event_id <= 0 ) {
                $event_id = get_the_ID();
            }
            if ( $event_id <= 0 ) {
                return;
            }
            $terms = get_the_terms( $event_id, 'categoria_evento' );
            if ( ! $terms || is_wp_error( $terms ) ) {
                return;
            }
            $link = get_term_link( $terms[0] );
            if ( ! is_wp_error( $link ) ) {
                echo esc_url( $link );
            }
        }
    }

    /**
     * Tag dinamico testo per etichetta accesso account.
     */
    class Elementor_Dynamic_Tag_Account_Label extends \Elementor\Core\DynamicTags\Tag {

        public function get_name() {
            return 'cral-account-label';
        }

        public function get_title() {
            return 'CRAL Account - Etichetta accesso';
        }

        public function get_group() {
            return 'cral-evento';
        }

        public function get_categories() {
            return array( \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY );
        }

        public function render() {
            $auth     = new \GEvent\Auth();
            $socio_id = $auth->get_current_socio();

            if ( ! $socio_id ) {
                echo esc_html( 'Accedi alla tua area personale' );
                return;
            }

            $nome    = (string) get_post_meta( $socio_id, '_cral_nome', true );
            $cognome = (string) get_post_meta( $socio_id, '_cral_cognome', true );
            $label   = trim( $nome . ' ' . $cognome );

            if ( '' === $label ) {
                $label = 'Socio';
            }

            echo esc_html( 'Ciao, ' . $label );
        }
    }

    /**
     * Tag dinamico URL per link accesso/account.
     */
    class Elementor_Dynamic_Tag_Account_Link extends \Elementor\Core\DynamicTags\Tag {

        public function get_name() {
            return 'cral-account-link';
        }

        public function get_title() {
            return 'CRAL Account - URL accesso';
        }

        public function get_group() {
            return 'cral-evento';
        }

        public function get_categories() {
            return array( \Elementor\Modules\DynamicTags\Module::URL_CATEGORY );
        }

        public function render() {
            $auth     = new \GEvent\Auth();
            $socio_id = $auth->get_current_socio();

            if ( $socio_id ) {
                $area_soci_url = get_permalink( get_option( 'cral_pagina_area_soci' ) );
                if ( $area_soci_url ) {
                    echo esc_url( $area_soci_url );
                }
                return;
            }

            $login_url = get_permalink( get_option( 'cral_pagina_login' ) );
            if ( $login_url ) {
                echo esc_url( $login_url );
            }
        }
    }

    /**
     * Tag dinamico testo: ID socio loggato.
     */
    class Elementor_Dynamic_Tag_Socio_ID extends \Elementor\Core\DynamicTags\Tag {

        public function get_name() {
            return 'cral-socio-id';
        }

        public function get_title() {
            return 'CRAL Socio - ID Socio';
        }

        public function get_group() {
            return 'cral-socio';
        }

        public function get_categories() {
            return array( \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY );
        }

        public function render() {
            $auth     = new \GEvent\Auth();
            $socio_id = $auth->get_current_socio();
            if ( ! $socio_id ) {
                return;
            }

            $socio_code = (string) get_post_meta( $socio_id, '_cral_socio_id', true );
            echo esc_html( $socio_code );
        }
    }

    /**
     * Tag dinamico testo: saluto socio loggato.
     */
    class Elementor_Dynamic_Tag_Socio_Saluto extends \Elementor\Core\DynamicTags\Tag {

        public function get_name() {
            return 'cral-socio-saluto';
        }

        public function get_title() {
            return 'CRAL Socio - Saluto';
        }

        public function get_group() {
            return 'cral-socio';
        }

        public function get_categories() {
            return array( \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY );
        }

        public function render() {
            $auth     = new \GEvent\Auth();
            $socio_id = $auth->get_current_socio();
            if ( ! $socio_id ) {
                return;
            }

            $nome    = (string) get_post_meta( $socio_id, '_cral_nome', true );
            $cognome = (string) get_post_meta( $socio_id, '_cral_cognome', true );
            $label   = trim( $nome . ' ' . $cognome );

            if ( '' === $label ) {
                $label = 'Socio';
            }

            echo esc_html( 'Ciao, ' . $label );
        }
    }

    /**
     * Tag dinamico testo: nome socio loggato.
     */
    class Elementor_Dynamic_Tag_Socio_Nome extends \Elementor\Core\DynamicTags\Tag {

        public function get_name() {
            return 'cral-socio-nome';
        }

        public function get_title() {
            return 'CRAL Socio - Nome';
        }

        public function get_group() {
            return 'cral-socio';
        }

        public function get_categories() {
            return array( \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY );
        }

        public function render() {
            $auth     = new \GEvent\Auth();
            $socio_id = $auth->get_current_socio();
            if ( ! $socio_id ) {
                return;
            }
            echo esc_html( (string) get_post_meta( $socio_id, '_cral_nome', true ) );
        }
    }

    /**
     * Tag dinamico testo: cognome socio loggato.
     */
    class Elementor_Dynamic_Tag_Socio_Cognome extends \Elementor\Core\DynamicTags\Tag {

        public function get_name() {
            return 'cral-socio-cognome';
        }

        public function get_title() {
            return 'CRAL Socio - Cognome';
        }

        public function get_group() {
            return 'cral-socio';
        }

        public function get_categories() {
            return array( \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY );
        }

        public function render() {
            $auth     = new \GEvent\Auth();
            $socio_id = $auth->get_current_socio();
            if ( ! $socio_id ) {
                return;
            }
            echo esc_html( (string) get_post_meta( $socio_id, '_cral_cognome', true ) );
        }
    }

    /**
     * Tag dinamico testo: email socio loggato.
     */
    class Elementor_Dynamic_Tag_Socio_Email extends \Elementor\Core\DynamicTags\Tag {

        public function get_name() {
            return 'cral-socio-email';
        }

        public function get_title() {
            return 'CRAL Socio - Email';
        }

        public function get_group() {
            return 'cral-socio';
        }

        public function get_categories() {
            return array( \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY );
        }

        public function render() {
            $auth     = new \GEvent\Auth();
            $socio_id = $auth->get_current_socio();
            if ( ! $socio_id ) {
                return;
            }
            echo esc_html( (string) get_post_meta( $socio_id, '_cral_email', true ) );
        }
    }

    /**
     * Tag dinamico: riepilogo eventi futuri prenotati dal socio.
     * Es: "Ci sono 3 eventi prenotati per i prossimi giorni con 5 biglietti acquistati e 2 accompagnatori"
     */
    class Elementor_Dynamic_Tag_Socio_Riepilogo_Eventi extends \Elementor\Core\DynamicTags\Tag {

        public function get_name() {
            return 'cral-socio-riepilogo-eventi';
        }

        public function get_title() {
            return 'CRAL Socio - Riepilogo eventi prenotati';
        }

        public function get_group() {
            return 'cral-socio';
        }

        public function get_categories() {
            return array( \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY );
        }

        public function render() {
            global $wpdb;

            $auth     = new \GEvent\Auth();
            $socio_id = $auth->get_current_socio();

            if ( ! $socio_id ) {
                return;
            }

            // Cache statica per non rieseguire nella stessa richiesta.
            static $cache = array();
            if ( isset( $cache[ $socio_id ] ) ) {
                echo esc_html( $cache[ $socio_id ] );
                return;
            }

            $oggi = gmdate( 'Y-m-d H:i:s' );

            // SQL diretto: una sola query per ottenere biglietti aggregati
            // sulle prenotazioni future attive del socio.
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT bgl.meta_value AS biglietti
                     FROM {$wpdb->posts} p
                     INNER JOIN {$wpdb->postmeta} socio_m ON socio_m.post_id = p.ID AND socio_m.meta_key = '_cral_pren_socio_id'
                     INNER JOIN {$wpdb->postmeta} stato_m ON stato_m.post_id = p.ID AND stato_m.meta_key = '_cral_pren_stato'
                     INNER JOIN {$wpdb->postmeta} ev_id   ON ev_id.post_id   = p.ID AND ev_id.meta_key   = '_cral_pren_evento_id'
                     INNER JOIN {$wpdb->postmeta} ev_data ON ev_data.post_id = CAST(ev_id.meta_value AS UNSIGNED) AND ev_data.meta_key = '_cral_evento_data'
                     INNER JOIN {$wpdb->postmeta} bgl     ON bgl.post_id     = p.ID AND bgl.meta_key     = '_cral_pren_totale_biglietti'
                     WHERE p.post_type   = 'prenotazione'
                       AND p.post_status = 'publish'
                       AND socio_m.meta_value = %d
                       AND stato_m.meta_value IN ('confermata','in_attesa')
                       AND ev_data.meta_value >= %s",
                    $socio_id,
                    $oggi
                )
            );

            if ( empty( $rows ) ) {
                $output = 'Non hai eventi prenotati per i prossimi giorni.';
                $cache[ $socio_id ] = $output;
                echo esc_html( $output );
                return;
            }

            $n_eventi         = count( $rows );
            $n_biglietti      = 0;
            $n_accompagnatori = 0;

            foreach ( $rows as $row ) {
                $bgl               = max( 1, (int) $row->biglietti );
                $n_biglietti      += $bgl;
                $n_accompagnatori += max( 0, $bgl - 1 );
            }

            $str_eventi         = $n_eventi === 1 ? '1 evento prenotato' : $n_eventi . ' eventi prenotati';
            $str_biglietti      = $n_biglietti === 1 ? '1 biglietto acquistato' : $n_biglietti . ' biglietti acquistati';
            $str_accompagnatori = $n_accompagnatori === 0
                ? 'nessun accompagnatore'
                : ( $n_accompagnatori === 1 ? '1 accompagnatore' : $n_accompagnatori . ' accompagnatori' );

            $output = 'Hai ' . $str_eventi . ' per i prossimi giorni con '
                . $str_biglietti . ' e ' . $str_accompagnatori . '.';

            $cache[ $socio_id ] = $output;
            echo esc_html( $output );
        }
    }

    /**
     * Tag dinamico: riepilogo eventi passati a cui il socio ha partecipato.
     * Es: "Hai partecipato a 4 eventi, acquistando 6 biglietti"
     */
    class Elementor_Dynamic_Tag_Socio_Riepilogo_Passati extends \Elementor\Core\DynamicTags\Tag {

        public function get_name() {
            return 'cral-socio-riepilogo-passati';
        }

        public function get_title() {
            return 'CRAL Socio - Riepilogo eventi passati';
        }

        public function get_group() {
            return 'cral-socio';
        }

        public function get_categories() {
            return array( \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY );
        }

        public function render() {
            global $wpdb;

            $auth     = new \GEvent\Auth();
            $socio_id = $auth->get_current_socio();

            if ( ! $socio_id ) {
                return;
            }

            static $cache = array();
            if ( isset( $cache[ $socio_id ] ) ) {
                echo esc_html( $cache[ $socio_id ] );
                return;
            }

            $oggi = gmdate( 'Y-m-d H:i:s' );

            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT bgl.meta_value AS biglietti
                     FROM {$wpdb->posts} p
                     INNER JOIN {$wpdb->postmeta} socio_m ON socio_m.post_id = p.ID AND socio_m.meta_key = '_cral_pren_socio_id'
                     INNER JOIN {$wpdb->postmeta} stato_m ON stato_m.post_id = p.ID AND stato_m.meta_key = '_cral_pren_stato'
                     INNER JOIN {$wpdb->postmeta} ev_id   ON ev_id.post_id   = p.ID AND ev_id.meta_key   = '_cral_pren_evento_id'
                     INNER JOIN {$wpdb->postmeta} ev_data ON ev_data.post_id = CAST(ev_id.meta_value AS UNSIGNED) AND ev_data.meta_key = '_cral_evento_data'
                     INNER JOIN {$wpdb->postmeta} bgl     ON bgl.post_id     = p.ID AND bgl.meta_key     = '_cral_pren_totale_biglietti'
                     WHERE p.post_type   = 'prenotazione'
                       AND p.post_status = 'publish'
                       AND socio_m.meta_value = %d
                       AND stato_m.meta_value IN ('confermata','in_attesa')
                       AND ev_data.meta_value < %s",
                    $socio_id,
                    $oggi
                )
            );

            if ( empty( $rows ) ) {
                $output = 'Non risultano ancora eventi a cui hai partecipato.';
                $cache[ $socio_id ] = $output;
                echo esc_html( $output );
                return;
            }

            $n_eventi    = count( $rows );
            $n_biglietti = 0;
            foreach ( $rows as $row ) {
                $n_biglietti += max( 1, (int) $row->biglietti );
            }

            $str_eventi   = $n_eventi === 1 ? '1 evento' : $n_eventi . ' eventi';
            $str_biglietti = $n_biglietti === 1 ? '1 biglietto' : $n_biglietti . ' biglietti';

            $output = 'Hai partecipato a ' . $str_eventi . ', acquistando ' . $str_biglietti . '.';

            $cache[ $socio_id ] = $output;
            echo esc_html( $output );
        }
    }
}

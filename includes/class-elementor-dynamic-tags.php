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
                        'estratto'                 => 'Estratto (descrizione breve)',
                        'data'                     => 'Data evento',
                        'data_iscrizione'          => 'Data scadenza iscrizioni',
                        'luogo'                    => 'Luogo',
                        'stato'                    => 'Stato',
                        'descrizione'              => 'Descrizione',
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
                case 'descrizione':
                    return (string) wp_strip_all_tags( (string) $this->get_meta( $event_id, '_cral_evento_descrizione' ) );
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
}

<?php
/**
 * Generazione dati demo.
 *
 * @package GEvent
 */

namespace GEvent;

/**
 * Classe per generare dati random (soci, eventi, prenotazioni).
 */
class Demo_Generator {

    /**
     * Genera dataset demo.
     *
     * @return array
     */
    public function generate() {
        $soci   = $this->create_soci( 5 );
        $eventi = $this->create_eventi( 10 );
        $pren   = $this->create_prenotazioni( $soci, $eventi );

        return array(
            'soci'         => count( $soci ),
            'eventi'       => count( $eventi ),
            'prenotazioni' => $pren,
        );
    }

    /**
     * @param int $count Numero soci.
     * @return array
     */
    private function create_soci( $count ) {
        $nomi     = array( 'Luca', 'Marco', 'Giulia', 'Sara', 'Andrea', 'Marta', 'Davide', 'Elisa' );
        $cognomi  = array( 'Rossi', 'Bianchi', 'Verdi', 'Neri', 'Conti', 'Gallo', 'Costa', 'Ricci' );
        $created  = array();

        for ( $i = 0; $i < $count; $i++ ) {
            $nome      = $nomi[ array_rand( $nomi ) ];
            $cognome   = $cognomi[ array_rand( $cognomi ) ];
            $id_socio  = 'D' . wp_rand( 10000, 99999 );
            $email     = strtolower( $nome . '.' . $cognome . '.' . wp_rand( 10, 99 ) . '@example.local' );
            $timestamp = wp_rand( strtotime( '1965-01-01' ), strtotime( '2003-12-31' ) );

            $post_id = wp_insert_post(
                array(
                    'post_type'   => 'socio',
                    'post_title'  => $nome . ' ' . $cognome,
                    'post_status' => 'publish',
                )
            );

            if ( is_wp_error( $post_id ) ) {
                continue;
            }

            update_post_meta( $post_id, '_cral_socio_id', $id_socio );
            update_post_meta( $post_id, '_cral_nome', $nome );
            update_post_meta( $post_id, '_cral_cognome', $cognome );
            update_post_meta( $post_id, '_cral_email', $email );
            update_post_meta( $post_id, '_cral_data_nascita', gmdate( 'Y-m-d', $timestamp ) );
            update_post_meta( $post_id, '_cral_password', wp_hash_password( 'Password123!' ) );

            Logger::log(
                'create_socio',
                'Creato socio demo ' . $nome . ' ' . $cognome,
                array( 'post_id' => $post_id, 'matricola' => $id_socio )
            );
            $created[] = $post_id;
        }

        return $created;
    }

    /**
     * @param int $count Numero eventi.
     * @return array
     */
    private function create_eventi( $count ) {
        $prefix   = array( 'Tour', 'Cena', 'Workshop', 'Visita', 'Serata', 'Weekend' );
        $topic    = array( 'Milano', 'Arte', 'Teatro', 'Laghi', 'Musica', 'Enogastronomia' );
        $luoghi   = array( 'Milano', 'Bergamo', 'Como', 'Monza', 'Pavia' );
        $created  = array();

        for ( $i = 0; $i < $count; $i++ ) {
            $title        = $prefix[ array_rand( $prefix ) ] . ' ' . $topic[ array_rand( $topic ) ] . ' #' . wp_rand( 100, 999 );
            $posti_totali = wp_rand( 40, 120 );
            $evento_data  = gmdate( 'Y-m-d H:i:s', strtotime( '+' . wp_rand( 7, 120 ) . ' days ' . wp_rand( 9, 20 ) . ':00:00' ) );
            $prezzo_base  = (float) wp_rand( 20, 60 );
            $prezzo_acc_socio   = (float) wp_rand( 10, (int) $prezzo_base );
            $prezzo_acc_esterno = (float) wp_rand( 10, (int) $prezzo_base );
            $prezzo_acc_junior  = (float) wp_rand( 5, (int) $prezzo_base );

            $post_id = wp_insert_post(
                array(
                    'post_type'   => 'evento',
                    'post_title'  => $title,
                    'post_status' => 'publish',
                )
            );

            if ( is_wp_error( $post_id ) ) {
                continue;
            }

            update_post_meta( $post_id, '_cral_evento_data', $evento_data );
            update_post_meta( $post_id, '_cral_evento_luogo', $luoghi[ array_rand( $luoghi ) ] );
            update_post_meta( $post_id, '_cral_evento_stato', 'pubblicato' );
            update_post_meta( $post_id, '_cral_evento_descrizione', $this->build_long_description( $title ) );
            update_post_meta( $post_id, '_cral_evento_posti_totali', $posti_totali );
            update_post_meta( $post_id, '_cral_evento_posti_residui', $posti_totali );
            update_post_meta( $post_id, '_cral_evento_prezzo_base', $prezzo_base );
            update_post_meta( $post_id, '_cral_evento_prezzo_acc_socio', $prezzo_acc_socio );
            update_post_meta( $post_id, '_cral_evento_prezzo_acc_esterno', $prezzo_acc_esterno );
            update_post_meta( $post_id, '_cral_evento_prezzo_acc_junior', $prezzo_acc_junior );
            update_post_meta( $post_id, '_cral_evento_enable_acc_socio', 'yes' );
            update_post_meta( $post_id, '_cral_evento_enable_acc_esterno', 'yes' );
            update_post_meta( $post_id, '_cral_evento_enable_acc_junior', 'yes' );
            update_post_meta( $post_id, '_cral_evento_max_acc_socio', (string) wp_rand( 1, 3 ) );
            update_post_meta( $post_id, '_cral_evento_max_acc_esterno', (string) wp_rand( 1, 3 ) );
            update_post_meta( $post_id, '_cral_evento_max_acc_junior', (string) wp_rand( 1, 3 ) );

            Logger::log(
                'create_evento',
                'Creato evento demo ' . $title,
                array( 'post_id' => $post_id )
            );
            $created[] = $post_id;
        }

        return $created;
    }

    /**
     * Crea una descrizione lunga evento.
     *
     * @param string $title Titolo evento.
     * @return string
     */
    private function build_long_description( $title ) {
        $paragrafo_1 = 'Questo evento e pensato per offrire ai soci un momento completo di socialita, cultura e scoperta del territorio. ';
        $paragrafo_1 .= 'Il programma prevede accoglienza iniziale, briefing organizzativo e attivita guidate in piccoli gruppi.';

        $paragrafo_2 = 'Durante la giornata saranno presenti referenti CRAL per supporto logistico e informazioni. ';
        $paragrafo_2 .= 'Sono previsti momenti dedicati alla condivisione tra partecipanti, con particolare attenzione alla fruizione inclusiva delle attivita.';

        $paragrafo_3 = 'Per "' . $title . '" consigliamo abbigliamento comodo e puntualita al punto di ritrovo. ';
        $paragrafo_3 .= 'Eventuali aggiornamenti operativi saranno comunicati in anticipo via email ai soci prenotati.';

        return '<p>' . esc_html( $paragrafo_1 ) . '</p>'
            . '<p>' . esc_html( $paragrafo_2 ) . '</p>'
            . '<p>' . esc_html( $paragrafo_3 ) . '</p>';
    }

    /**
     * @param array $soci IDs soci.
     * @param array $eventi IDs eventi.
     * @return int
     */
    private function create_prenotazioni( $soci, $eventi ) {
        $created = 0;

        foreach ( $soci as $socio_id ) {
            $num_prenotazioni = wp_rand( 3, 4 );
            $eventi_pool      = $eventi;
            shuffle( $eventi_pool );
            $scelti = array_slice( $eventi_pool, 0, $num_prenotazioni );
            $note_samples = array(
                'Arrivo previsto 15 minuti prima dell\'inizio evento.',
                'Richiesta posto vicino accompagnatori.',
                'Preferenza pagamento gia effettuato via segreteria.',
                'Note alimentari comunicate alla segreteria.',
                'Necessita assistenza accesso in sede evento.',
            );

            foreach ( $scelti as $evento_id ) {
                $posti_residui = (int) get_post_meta( $evento_id, '_cral_evento_posti_residui', true );
                if ( $posti_residui <= 0 ) {
                    continue;
                }

                $prezzo_base        = (float) get_post_meta( $evento_id, '_cral_evento_prezzo_base', true );
                $prezzo_acc_socio   = (float) get_post_meta( $evento_id, '_cral_evento_prezzo_acc_socio', true );
                $prezzo_acc_esterno = (float) get_post_meta( $evento_id, '_cral_evento_prezzo_acc_esterno', true );
                $prezzo_acc_junior  = (float) get_post_meta( $evento_id, '_cral_evento_prezzo_acc_junior', true );

                if ( $prezzo_base <= 0 ) {
                    continue;
                }

                $acc_count = min( wp_rand( 0, 3 ), max( 0, $posti_residui - 1 ) );
                $qty       = 1 + $acc_count;
                $importo_totale = $prezzo_base;

                $nome_socio    = get_post_meta( $socio_id, '_cral_nome', true );
                $cognome_socio = get_post_meta( $socio_id, '_cral_cognome', true );
                $titolo_evento = get_the_title( $evento_id );

                $post_id = wp_insert_post(
                    array(
                        'post_type'   => 'prenotazione',
                        'post_title'  => $cognome_socio . ' ' . $nome_socio . ' — ' . $titolo_evento,
                        'post_status' => 'publish',
                    )
                );

                if ( is_wp_error( $post_id ) ) {
                    continue;
                }

                update_post_meta( $post_id, '_cral_pren_socio_id', $socio_id );
                update_post_meta( $post_id, '_cral_pren_evento_id', $evento_id );
                update_post_meta( $post_id, '_cral_pren_data', current_time( 'mysql' ) );
                update_post_meta( $post_id, '_cral_pren_stato', 'confermata' );
                update_post_meta( $post_id, '_cral_pren_pagamento', 'yes' );
                update_post_meta( $post_id, '_cral_pren_data_pagamento', wp_date( 'Y-m-d' ) );
                update_post_meta( $post_id, '_cral_pren_note', $note_samples[ array_rand( $note_samples ) ] );

                $partecipanti = array(
                    array(
                        'partecipante_nome'      => $nome_socio,
                        'partecipante_cognome'   => $cognome_socio,
                        'partecipante_tipologia' => 'Socio',
                        'partecipante_prezzo'    => (string) $prezzo_base,
                    ),
                );

                $tipologie = array(
                    array(
                        'label' => 'Accompagnatore Socio',
                        'prezzo' => $prezzo_acc_socio,
                        'max' => (int) get_post_meta( $evento_id, '_cral_evento_max_acc_socio', true ),
                    ),
                    array(
                        'label' => 'Accompagnatore Esterno',
                        'prezzo' => $prezzo_acc_esterno,
                        'max' => (int) get_post_meta( $evento_id, '_cral_evento_max_acc_esterno', true ),
                    ),
                    array(
                        'label' => 'Accompagnatore Junior',
                        'prezzo' => $prezzo_acc_junior,
                        'max' => (int) get_post_meta( $evento_id, '_cral_evento_max_acc_junior', true ),
                    ),
                );
                $used_by_type = array(
                    'Accompagnatore Socio' => 0,
                    'Accompagnatore Esterno' => 0,
                    'Accompagnatore Junior' => 0,
                );

                for ( $i = 1; $i <= $acc_count; $i++ ) {
                    $tipologie_disponibili = array_values(
                        array_filter(
                            $tipologie,
                            static function( $tipo ) use ( $used_by_type ) {
                                return $used_by_type[ $tipo['label'] ] < $tipo['max'];
                            }
                        )
                    );
                    if ( empty( $tipologie_disponibili ) ) {
                        break;
                    }
                    $tipologia = $tipologie_disponibili[ array_rand( $tipologie_disponibili ) ];
                    $used_by_type[ $tipologia['label'] ]++;
                    $importo_totale += (float) $tipologia['prezzo'];
                    $partecipanti[] = array(
                        'partecipante_nome'      => 'Accompagnatore ' . $i,
                        'partecipante_cognome'   => $cognome_socio,
                        'partecipante_tipologia' => $tipologia['label'],
                        'partecipante_prezzo'    => (string) $tipologia['prezzo'],
                    );
                }

                $qty = count( $partecipanti );
                update_post_meta( $post_id, '_cral_pren_importo_totale', $importo_totale );
                update_post_meta( $post_id, '_cral_pren_totale_biglietti', $qty );
                carbon_set_post_meta( $post_id, 'cral_partecipanti', $partecipanti );

                update_post_meta( $evento_id, '_cral_evento_posti_residui', max( 0, $posti_residui - $qty ) );

                Logger::log(
                    'create_prenotazione',
                    'Creata prenotazione demo #' . $post_id,
                    array(
                        'post_id'   => $post_id,
                        'socio_id'  => $socio_id,
                        'evento_id' => $evento_id,
                        'biglietti' => $qty,
                    )
                );
                $created++;
            }
        }

        return $created;
    }
}

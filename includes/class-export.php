<?php
/**
 * Export CSV prenotazioni.
 *
 * @package GEvent
 */

namespace GEvent;

/**
 * Classe per la gestione degli export.
 */
class Export {

    /**
     * Registra gli hook WordPress.
     */
    public function init() {
        add_action( 'admin_post_cral_export_csv', array( $this, 'handle_export_csv' ) );
        add_action( 'admin_post_cral_export_evento_csv', array( $this, 'handle_export_evento_csv' ) );
    }

    /**
     * Gestisce l'export CSV delle prenotazioni di un evento.
     */
    public function handle_export_csv() {
        // Verifica nonce e permessi.
        if ( ! isset( $_GET['nonce'] ) ||
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'cral_export_csv' ) ) {
            wp_die( 'Nonce non valido.' );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Accesso negato.' );
        }

        $evento_id = isset( $_GET['evento_id'] ) ? absint( $_GET['evento_id'] ) : 0;

        if ( ! $evento_id ) {
            wp_die( 'Evento non valido.' );
        }

        $evento = get_post( $evento_id );
        if ( ! $evento ) {
            wp_die( 'Evento non trovato.' );
        }

        // Recupera le prenotazioni dell'evento.
        $prenotazioni = get_posts( array(
            'post_type'      => 'prenotazione',
            'posts_per_page' => -1,
            'meta_query'     => array(
                array(
                    'key'   => '_cral_pren_evento_id',
                    'value' => $evento_id,
                ),
            ),
            'orderby' => 'date',
            'order'   => 'ASC',
        ) );

        // Prepara il nome del file.
        $filename = 'prenotazioni-' . sanitize_title( $evento->post_title ) . '-' . wp_date( 'Y-m-d' ) . '.csv';

        // Invia gli header per il download.
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $output = fopen( 'php://output', 'w' );

        // BOM per Excel — gestisce correttamente i caratteri UTF-8.
        fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

        // Intestazione CSV.
        fputcsv( $output, array(
            'ID Prenotazione',
            'ID Socio',
            'Cognome',
            'Nome',
            'Email',
            'Data Prenotazione',
            'Stato',
            'Totale Biglietti',
            'Importo Totale',
            'Pagamento',
            'Data Pagamento',
            'Note',
            'Partecipante - Nome',
            'Partecipante - Cognome',
            'Partecipante - Tipologia',
            'Partecipante - Prezzo',
        ), ';' );

        foreach ( $prenotazioni as $pren ) {
            $socio_id       = get_post_meta( $pren->ID, '_cral_pren_socio_id', true );
            $nome           = get_post_meta( $socio_id, '_cral_nome', true );
            $cognome        = get_post_meta( $socio_id, '_cral_cognome', true );
            $email          = get_post_meta( $socio_id, '_cral_email', true );
            $matricola      = get_post_meta( $socio_id, '_cral_socio_id', true );
            $data_pren      = get_post_meta( $pren->ID, '_cral_pren_data', true );
            $stato          = get_post_meta( $pren->ID, '_cral_pren_stato', true );
            $biglietti      = get_post_meta( $pren->ID, '_cral_pren_totale_biglietti', true );
            $importo        = get_post_meta( $pren->ID, '_cral_pren_importo_totale', true );
            $pagamento      = get_post_meta( $pren->ID, '_cral_pren_pagamento', true );
            $data_pagamento = get_post_meta( $pren->ID, '_cral_pren_data_pagamento', true );
            $note           = get_post_meta( $pren->ID, '_cral_pren_note', true );
            $partecipanti   = carbon_get_post_meta( $pren->ID, 'cral_partecipanti' );

            $stati_testo = array(
                'in_attesa'  => 'In attesa',
                'confermata' => 'Confermata',
                'annullata'  => 'Annullata',
            );

            $data_pren_fmt      = $data_pren ? wp_date( 'd/m/Y H:i', strtotime( $data_pren ) ) : '';
            $data_pagamento_fmt = $data_pagamento ? wp_date( 'd/m/Y', strtotime( $data_pagamento ) ) : '';
            $pagamento_testo    = 'yes' === $pagamento ? 'Ricevuto' : 'In attesa';
            $stato_testo        = $stati_testo[ $stato ] ?? $stato;
            $importo_fmt        = number_format( (float) $importo, 2, ',', '.' );

            if ( ! empty( $partecipanti ) ) {
                // Una riga per ogni partecipante.
                foreach ( $partecipanti as $part ) {
                    fputcsv( $output, array(
                        $pren->ID,
                        $matricola,
                        $cognome,
                        $nome,
                        $email,
                        $data_pren_fmt,
                        $stato_testo,
                        $biglietti,
                        $importo_fmt,
                        $pagamento_testo,
                        $data_pagamento_fmt,
                        $note,
                        $part['partecipante_nome'],
                        $part['partecipante_cognome'],
                        $part['partecipante_tipologia'],
                        number_format( (float) $part['partecipante_prezzo'], 2, ',', '.' ),
                    ), ';' );
                }
            } else {
                // Riga senza partecipanti.
                fputcsv( $output, array(
                    $pren->ID,
                    $matricola,
                    $cognome,
                    $nome,
                    $email,
                    $data_pren_fmt,
                    $stato_testo,
                    $biglietti,
                    $importo_fmt,
                    $pagamento_testo,
                    $data_pagamento_fmt,
                    $note,
                    '',
                    '',
                    '',
                    '',
                ), ';' );
            }
        }

        fclose( $output );
        exit;
    }

    /**
     * Export CSV pagina Prenotazioni Evento (una riga per prenotazione/evento).
     */
    public function handle_export_evento_csv() {
        if ( ! isset( $_GET['nonce'] ) ||
            ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'cral_export_evento_csv' ) ) {
            wp_die( 'Nonce non valido.' );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Accesso negato.' );
        }

        $evento_id = isset( $_GET['evento_id'] ) ? absint( $_GET['evento_id'] ) : 0;
        if ( ! $evento_id ) {
            wp_die( 'Evento non valido.' );
        }

        $evento = get_post( $evento_id );
        if ( ! $evento ) {
            wp_die( 'Evento non trovato.' );
        }

        $prenotazioni = get_posts( array(
            'post_type'      => 'prenotazione',
            'posts_per_page' => -1,
            'meta_query'     => array(
                array(
                    'key'   => '_cral_pren_evento_id',
                    'value' => $evento_id,
                ),
            ),
            'orderby' => 'date',
            'order'   => 'ASC',
        ) );

        $filename = 'prenotazioni-evento-' . $evento_id . '-' . wp_date( 'Y-m-d' ) . '.csv';

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $output = fopen( 'php://output', 'w' );
        fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

        fputcsv(
            $output,
            array(
                'ID Evento',
                'Titolo Evento',
                'ID Prenotazione',
                'ID Socio (post)',
                'ID Socio',
                'Nome Socio',
                'Cognome Socio',
                'Email Socio',
                'Data Prenotazione',
                'Biglietti (1+N)',
                'Numero Accompagnatori',
                'Biglietto Evento',
                'Accompagnatori',
                'Stato',
                'Totale Pagato Socio',
            ),
            ';'
        );

        foreach ( $prenotazioni as $pren ) {
            $socio_post_id  = (int) get_post_meta( $pren->ID, '_cral_pren_socio_id', true );
            $socio_id       = (string) get_post_meta( $socio_post_id, '_cral_socio_id', true );
            $nome           = (string) get_post_meta( $socio_post_id, '_cral_nome', true );
            $cognome        = (string) get_post_meta( $socio_post_id, '_cral_cognome', true );
            $email          = (string) get_post_meta( $socio_post_id, '_cral_email', true );
            $data_pren      = (string) get_post_meta( $pren->ID, '_cral_pren_data', true );
            $stato          = (string) get_post_meta( $pren->ID, '_cral_pren_stato', true );
            $importo_totale = (float) get_post_meta( $pren->ID, '_cral_pren_importo_totale', true );
            $prezzo_evento  = (float) get_post_meta( $evento_id, '_cral_evento_prezzo_base', true );
            $partecipanti   = carbon_get_post_meta( $pren->ID, 'cral_partecipanti' );

            $accompagnatori = array();
            $totale_pagato  = 0.0;
            if ( ! empty( $partecipanti ) && is_array( $partecipanti ) ) {
                foreach ( $partecipanti as $part ) {
                    $p_nome    = sanitize_text_field( $part['partecipante_nome'] ?? '' );
                    $p_cognome = sanitize_text_field( $part['partecipante_cognome'] ?? '' );
                    $p_tipo    = sanitize_text_field( $part['partecipante_tipologia'] ?? '' );
                    $p_prezzo  = (float) ( $part['partecipante_prezzo'] ?? 0 );
                    $totale_pagato += $p_prezzo;

                    if ( 'Socio' === $p_tipo ) {
                        continue;
                    }
                    $accompagnatori[] = trim( $p_nome . ' ' . $p_cognome ) . ' (€ ' . number_format( $p_prezzo, 2, ',', '.' ) . ')';
                }
            }
            if ( $totale_pagato <= 0 ) {
                $totale_pagato = $importo_totale;
            }

            $num_acc = count( $accompagnatori );
            $biglietti_str = '1 + ' . $num_acc;

            fputcsv(
                $output,
                array(
                    $evento_id,
                    $evento->post_title,
                    $pren->ID,
                    $socio_post_id,
                    $socio_id,
                    $nome,
                    $cognome,
                    $email,
                    $data_pren ? wp_date( 'd/m/Y H:i', strtotime( $data_pren ) ) : '',
                    $biglietti_str,
                    $num_acc,
                    number_format( $prezzo_evento, 2, ',', '.' ),
                    implode( ' | ', $accompagnatori ),
                    $stato,
                    number_format( $totale_pagato, 2, ',', '.' ),
                ),
                ';'
            );
        }

        fclose( $output );
        exit;
    }
}
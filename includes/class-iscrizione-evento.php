<?php
/**
 * Modalità iscrizione evento e stati prenotazione (admin + front).
 *
 * @package GEvent
 */

namespace GEvent;

/**
 * Helper modalità iscrizione / stati prenotazione.
 */
class Iscrizione_Evento {

    const MODE_ILLIMITATI    = 'illimitati';
    const MODE_LIMITE        = 'limite';
    const MODE_LIMITE_ATTESA = 'limite_attesa';

    const ON_BOOK_PRENOTATO     = 'prenotato';
    const ON_BOOK_AUTO_CONFERMA = 'auto_conferma';

    const STATO_PRENOTATO  = 'in_attesa';
    const STATO_CONFERMATA = 'confermata';
    const STATO_SCARTATO   = 'scartato';
    const STATO_ANNULLATA  = 'annullata'; // legacy → trattato come scartato in UI.

    /**
     * Opzioni modalità iscrizione.
     *
     * @return array<string, string>
     */
    public static function mode_options() {
        return array(
            self::MODE_ILLIMITATI    => 'Posti illimitati',
            self::MODE_LIMITE        => 'Limite posti',
            self::MODE_LIMITE_ATTESA => 'Posti limitati + lista d\'attesa',
        );
    }

    /**
     * Opzioni comportamento alla prenotazione utente.
     *
     * @return array<string, string>
     */
    public static function on_book_options() {
        return array(
            self::ON_BOOK_PRENOTATO     => 'Metti in Stato Prenotato',
            self::ON_BOOK_AUTO_CONFERMA => 'Conferma automaticamente',
        );
    }

    /**
     * Label stati prenotazione (UI).
     *
     * @return array<string, string>
     */
    public static function stato_labels() {
        return array(
            self::STATO_CONFERMATA => 'Confermato',
            self::STATO_PRENOTATO  => 'Prenotato / Lista d\'attesa',
            self::STATO_SCARTATO   => 'Scartato',
            self::STATO_ANNULLATA  => 'Scartato / Annullato',
        );
    }

    /**
     * HTML riquadro spiegazione modalità (admin edit evento).
     *
     * @return string
     */
    public static function help_box_html() {
        return '<div class="cral-iscrizione-help" style="background:#f8fafc;border:1px solid #cbd5e1;border-radius:8px;padding:14px 16px;line-height:1.45;color:#0f172a;">'
            . '<p style="margin:0 0 10px;font-weight:700;font-size:14px;">Come funzionano le modalità di iscrizione</p>'
            . '<ul style="margin:0;padding-left:18px;font-size:13px;">'
            . '<li style="margin-bottom:8px;"><strong>Posti illimitati</strong> (default): gli utenti possono sempre iscriversi. In front-end vedono solo la scadenza e che l’admin confermerà il posto. Niente contatore posti. In admin: 3 liste.</li>'
            . '<li style="margin-bottom:8px;"><strong>Limite posti</strong>: inserisci il numero posti. Front-end mostra posti ancora disponibili oppure SOLD OUT (non ci si iscrive più). I posti si aggiornano in base ai <em>Confermati</em>. Admin: 3 liste.</li>'
            . '<li style="margin-bottom:8px;"><strong>Posti limitati + lista d’attesa</strong>: come sopra, ma a sold out l’utente può comunque prenotarsi in lista d’attesa. Admin: 3 liste.</li>'
            . '</ul>'
            . '<p style="margin:12px 0 0;font-size:13px;"><strong>Stato prenotazione</strong> (alla iscrizione utente):</p>'
            . '<ul style="margin:6px 0 0;padding-left:18px;font-size:13px;">'
            . '<li><strong>Metti in Stato Prenotato</strong> (default): entra in lista d’attesa; l’admin conferma o scarta.</li>'
            . '<li><strong>Conferma automaticamente</strong>: la prenotazione nasce già Confermata.</li>'
            . '</ul>'
            . '</div>';
    }

    /**
     * @param int $evento_id ID evento.
     * @return string
     */
    public static function get_mode( $evento_id ) {
        $mode = (string) get_post_meta( (int) $evento_id, '_cral_evento_modalita_iscrizione', true );
        if ( ! isset( self::mode_options()[ $mode ] ) ) {
            return self::MODE_ILLIMITATI;
        }
        return $mode;
    }

    /**
     * @param int $evento_id ID evento.
     * @return string
     */
    public static function get_on_book( $evento_id ) {
        $v = (string) get_post_meta( (int) $evento_id, '_cral_evento_stato_prenotazione', true );
        if ( ! isset( self::on_book_options()[ $v ] ) ) {
            return self::ON_BOOK_PRENOTATO;
        }
        return $v;
    }

    /**
     * Stato iniziale quando un utente si iscrive dal front-end.
     *
     * @param int $evento_id ID evento.
     * @return string
     */
    public static function initial_stato_for_frontend( $evento_id ) {
        return self::ON_BOOK_AUTO_CONFERMA === self::get_on_book( $evento_id )
            ? self::STATO_CONFERMATA
            : self::STATO_PRENOTATO;
    }

    /**
     * True se la modalità usa un tetto posti.
     *
     * @param int $evento_id ID evento.
     * @return bool
     */
    public static function has_seat_limit( $evento_id ) {
        $mode = self::get_mode( $evento_id );
        return in_array( $mode, array( self::MODE_LIMITE, self::MODE_LIMITE_ATTESA ), true );
    }

    /**
     * True se a sold-out si può ancora prenotare (lista d'attesa).
     *
     * @param int $evento_id ID evento.
     * @return bool
     */
    public static function allows_waitlist_when_full( $evento_id ) {
        return self::MODE_LIMITE_ATTESA === self::get_mode( $evento_id );
    }

    /**
     * Normalizza stato per le 3 liste admin.
     *
     * @param string $stato Stato raw.
     * @return string confermata|in_attesa|scartato
     */
    public static function normalize_list_bucket( $stato ) {
        $stato = (string) $stato;
        if ( self::STATO_CONFERMATA === $stato ) {
            return self::STATO_CONFERMATA;
        }
        if ( in_array( $stato, array( self::STATO_SCARTATO, self::STATO_ANNULLATA ), true ) ) {
            return self::STATO_SCARTATO;
        }
        return self::STATO_PRENOTATO;
    }

    /**
     * Conta biglietti delle prenotazioni confermate.
     *
     * @param int $evento_id ID evento.
     * @return int
     */
    public static function count_confirmed_tickets( $evento_id ) {
        $ids = get_posts(
            array(
                'post_type'      => 'prenotazione',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'post_status'    => 'publish',
                'meta_query'     => array(
                    'relation' => 'AND',
                    array(
                        'key'   => '_cral_pren_evento_id',
                        'value' => (string) (int) $evento_id,
                    ),
                    array(
                        'key'   => '_cral_pren_stato',
                        'value' => self::STATO_CONFERMATA,
                    ),
                ),
            )
        );

        $total = 0;
        foreach ( $ids as $pren_id ) {
            $n = (int) get_post_meta( (int) $pren_id, '_cral_pren_totale_biglietti', true );
            $total += max( 1, $n );
        }
        return $total;
    }

    /**
     * Ricalcola posti residui = totali − confermati (solo modalità con limite).
     *
     * @param int $evento_id ID evento.
     */
    public static function sync_posti_residui( $evento_id ) {
        $evento_id = (int) $evento_id;
        if ( $evento_id <= 0 || ! self::has_seat_limit( $evento_id ) ) {
            return;
        }

        $totali = (int) get_post_meta( $evento_id, '_cral_evento_posti_totali', true );
        if ( $totali < 0 ) {
            $totali = 0;
        }
        $usati   = self::count_confirmed_tickets( $evento_id );
        $residui = max( 0, $totali - $usati );
        update_post_meta( $evento_id, '_cral_evento_posti_residui', $residui );
    }

    /**
     * Cambia stato prenotazione e sincronizza posti.
     *
     * @param int    $pren_id   ID prenotazione.
     * @param string $new_stato Nuovo stato.
     * @return true|\WP_Error
     */
    public static function set_prenotazione_stato( $pren_id, $new_stato ) {
        $pren_id   = (int) $pren_id;
        $new_stato = (string) $new_stato;
        $allowed   = array( self::STATO_PRENOTATO, self::STATO_CONFERMATA, self::STATO_SCARTATO );

        if ( $pren_id <= 0 || ! in_array( $new_stato, $allowed, true ) ) {
            return new \WP_Error( 'invalid', 'Stato non valido.' );
        }

        $evento_id = (int) get_post_meta( $pren_id, '_cral_pren_evento_id', true );
        if ( ! $evento_id ) {
            return new \WP_Error( 'no_event', 'Evento non valido.' );
        }

        $old = self::normalize_list_bucket( (string) get_post_meta( $pren_id, '_cral_pren_stato', true ) );

        if ( self::STATO_CONFERMATA === $new_stato && self::STATO_CONFERMATA !== $old && self::has_seat_limit( $evento_id ) ) {
            $tickets = max( 1, (int) get_post_meta( $pren_id, '_cral_pren_totale_biglietti', true ) );
            $totali  = (int) get_post_meta( $evento_id, '_cral_evento_posti_totali', true );
            $usati   = self::count_confirmed_tickets( $evento_id );
            if ( ( $usati + $tickets ) > $totali ) {
                return new \WP_Error( 'full', 'Posti insufficienti per confermare questa prenotazione.' );
            }
        }

        update_post_meta( $pren_id, '_cral_pren_stato', $new_stato );
        self::sync_posti_residui( $evento_id );
        return true;
    }
}

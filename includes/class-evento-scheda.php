<?php
/**
 * Shortcode [cral_evento_scheda] — riepilogo evento + form prenotazione.
 *
 * @package GEvent
 */

namespace GEvent;

/**
 * Scheda evento completa con riepilogo info e form prenotazione AJAX.
 */
class Evento_Scheda {

    /**
     * Registra hook.
     */
    public function init() {
        add_shortcode( 'cral_evento_scheda', array( $this, 'render' ) );
        add_action( 'wp_ajax_nopriv_cral_prenota_scheda', array( $this, 'handle_prenota' ) );
        add_action( 'wp_ajax_cral_prenota_scheda', array( $this, 'handle_prenota' ) );
        add_action( 'wp_ajax_nopriv_cral_annulla_scheda', array( $this, 'handle_annulla' ) );
        add_action( 'wp_ajax_cral_annulla_scheda', array( $this, 'handle_annulla' ) );
    }

    /**
     * Renderizza la scheda evento.
     *
     * @param array $atts Attributi shortcode.
     * @return string HTML.
     */
    public function render( $atts ) {
        $atts = shortcode_atts( array( 'id' => get_the_ID() ), $atts );

        $evento_id = absint( $atts['id'] );
        if ( ! $evento_id ) {
            return '';
        }
        $evento = get_post( $evento_id );
        if ( ! $evento || 'evento' !== $evento->post_type ) {
            return '';
        }

        // --- Dati evento ---
        $titolo              = get_the_title( $evento_id );
        $data_raw            = get_post_meta( $evento_id, '_cral_evento_data', true );
        $data_iscr_raw       = get_post_meta( $evento_id, '_cral_evento_data_iscrizione', true );
        $data_apertura_raw   = get_post_meta( $evento_id, '_cral_evento_data_apertura_iscrizioni', true );
        $luogo               = get_post_meta( $evento_id, '_cral_evento_luogo', true );
        $stato               = get_post_meta( $evento_id, '_cral_evento_stato', true );
        $posti_totali        = (int) get_post_meta( $evento_id, '_cral_evento_posti_totali', true );
        $posti_residui       = (int) get_post_meta( $evento_id, '_cral_evento_posti_residui', true );
        $prezzo_base         = (float) get_post_meta( $evento_id, '_cral_evento_prezzo_base', true );
        $prezzo_acc_socio    = (float) get_post_meta( $evento_id, '_cral_evento_prezzo_acc_socio', true );
        $prezzo_acc_esterno  = (float) get_post_meta( $evento_id, '_cral_evento_prezzo_acc_esterno', true );
        $prezzo_acc_junior   = (float) get_post_meta( $evento_id, '_cral_evento_prezzo_acc_junior', true );

        $acc_config = array(
            'Accompagnatore Socio'   => array(
                'enabled' => 'yes' === get_post_meta( $evento_id, '_cral_evento_enable_acc_socio', true ),
                'price'   => $prezzo_acc_socio,
                'max'     => (int) get_post_meta( $evento_id, '_cral_evento_max_acc_socio', true ),
            ),
            'Accompagnatore Esterno' => array(
                'enabled' => 'yes' === get_post_meta( $evento_id, '_cral_evento_enable_acc_esterno', true ),
                'price'   => $prezzo_acc_esterno,
                'max'     => (int) get_post_meta( $evento_id, '_cral_evento_max_acc_esterno', true ),
            ),
            'Accompagnatore Junior'  => array(
                'enabled' => 'yes' === get_post_meta( $evento_id, '_cral_evento_enable_acc_junior', true ),
                'price'   => $prezzo_acc_junior,
                'max'     => (int) get_post_meta( $evento_id, '_cral_evento_max_acc_junior', true ),
            ),
        );
        $enabled_types = array_filter( $acc_config, static function( $c ) { return ! empty( $c['enabled'] ); } );

        // --- Calcolo stato dinamico badge ---
        $now               = time();
        $ts_evento         = $data_raw       ? strtotime( (string) $data_raw )       : 0;
        $ts_scadenza       = $data_iscr_raw  ? strtotime( (string) $data_iscr_raw )  : 0;
        $ts_apertura       = $data_apertura_raw ? strtotime( (string) $data_apertura_raw ) : 0;

        $is_annullato      = ( 'annullato' === $stato );
        $is_programmato    = Evento_Stato::is_programmato( $evento_id );
        $is_concluso       = ( ! $is_programmato && 'concluso' === $stato ) || ( ! $is_programmato && $ts_evento > 0 && $ts_evento < $now );
        $is_soldout        = ( ! $is_annullato && ! $is_programmato && ! $is_concluso && $posti_residui <= 0 );
        $is_iscr_chiuse    = ( ! $is_annullato && ! $is_programmato && ! $is_concluso && ! $is_soldout && $ts_scadenza > 0 && $ts_scadenza < $now );
        $is_non_ancora     = ( ! $is_annullato && ! $is_programmato && ! $is_concluso && ! $is_soldout && ! $is_iscr_chiuse && $ts_apertura > 0 && $ts_apertura > $now );
        $is_open           = ( ! $is_programmato && ! $is_annullato && ! $is_concluso && ! $is_soldout && ! $is_iscr_chiuse && ! $is_non_ancora );

        // Formattazione date badge.
        $fmt_badge_data = static function( $ts ) {
            return $ts ? wp_date( 'd/m/Y', $ts ) : '';
        };
        $partecipanti_count = $posti_totali - $posti_residui;

        // Utente loggato.
        $auth          = new \GEvent\Auth();
        $socio_id      = $auth->get_current_socio();
        $nonce         = wp_create_nonce( 'cral_prenota_scheda_nonce' );
        $ajax_url      = admin_url( 'admin-ajax.php' );
        $login_url     = get_permalink( get_option( 'cral_pagina_login' ) );

        // Verifica se il socio ha già prenotato.
        $gia_prenotato = false;
        $pren_esistente_id = 0;
        if ( $socio_id ) {
            $existing = get_posts( array(
                'post_type'      => 'prenotazione',
                'posts_per_page' => 1,
                'meta_query'     => array(
                    'relation' => 'AND',
                    array( 'key' => '_cral_pren_socio_id', 'value' => $socio_id ),
                    array( 'key' => '_cral_pren_evento_id', 'value' => $evento_id ),
                    array( 'key' => '_cral_pren_stato', 'value' => array( 'confermata', 'in_attesa' ), 'compare' => 'IN' ),
                ),
                'fields' => 'ids',
            ) );
            if ( ! empty( $existing ) ) {
                $gia_prenotato     = true;
                $pren_esistente_id = $existing[0];
            }
        }

        // --- Utility format ---
        $fmt_euro = static function( $v ) {
            return '€&nbsp;' . number_format( (float) $v, 2, ',', '.' );
        };
        $fmt_data = static function( $raw, $format = 'd/m/Y H:i' ) {
            if ( ! $raw ) return '—';
            $ts = strtotime( (string) $raw );
            return $ts ? wp_date( $format, $ts ) : '—';
        };

        ob_start();
        ?>
        <div class="cral-scheda" id="cral-scheda-<?php echo esc_attr( $evento_id ); ?>">

            <!-- ═══ RIEPILOGO EVENTO ═══ -->
            <div class="cral-scheda__info">

                <!-- Badge stato dinamico -->
                <?php if ( $is_annullato ) : ?>
                    <div class="cral-scheda__badge cral-scheda__badge--annullato">
                        <span class="cral-scheda__badge-title">Evento annullato</span>
                    </div>
                <?php elseif ( $is_programmato ) : ?>
                    <div class="cral-scheda__badge cral-scheda__badge--programmato">
                        <span class="cral-scheda__badge-title"><?php echo esc_html( Evento_Stato::get_programmato_label( $evento_id ) ); ?></span>
                    </div>
                <?php elseif ( $is_concluso ) : ?>
                    <div class="cral-scheda__badge cral-scheda__badge--concluso">
                        <span class="cral-scheda__badge-title">Evento concluso</span>
                        <?php if ( $partecipanti_count > 0 ) : ?>
                        <span class="cral-scheda__badge-sub">Partecipanti: <?php echo esc_html( $partecipanti_count ); ?></span>
                        <?php endif; ?>
                    </div>
                <?php elseif ( $is_soldout ) : ?>
                    <div class="cral-scheda__badge cral-scheda__badge--soldout">
                        <span class="cral-scheda__badge-title">&#x1F6AB; Sold out</span>
                        <span class="cral-scheda__badge-sub">Posti disponibili: 0</span>
                    </div>
                <?php elseif ( $is_iscr_chiuse ) : ?>
                    <div class="cral-scheda__badge cral-scheda__badge--chiuse">
                        <span class="cral-scheda__badge-title">Iscrizioni chiuse</span>
                        <?php if ( $ts_scadenza ) : ?>
                        <span class="cral-scheda__badge-sub">Scadute il <?php echo esc_html( $fmt_badge_data( $ts_scadenza ) ); ?></span>
                        <?php endif; ?>
                    </div>
                <?php elseif ( $is_non_ancora ) : ?>
                    <div class="cral-scheda__badge cral-scheda__badge--presto">
                        <span class="cral-scheda__badge-title">Evento pubblicato</span>
                        <span class="cral-scheda__badge-sub">Le iscrizioni aprono il <?php echo esc_html( $fmt_badge_data( $ts_apertura ) ); ?></span>
                    </div>
                <?php else : ?>
                    <div class="cral-scheda__badge cral-scheda__badge--aperto">
                        <span class="cral-scheda__badge-title">Iscrizioni aperte</span>
                        <?php if ( $ts_scadenza ) : ?>
                        <span class="cral-scheda__badge-sub">fino al <?php echo esc_html( $fmt_badge_data( $ts_scadenza ) ); ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Prezzi -->
                <div class="cral-scheda__prezzi">
                    <h3 class="cral-scheda__prezzi-title">Prezzi</h3>
                    <div class="cral-scheda__prezzi-list">
                        <div class="cral-scheda__prezzo-row cral-scheda__prezzo-row--base">
                            <span class="cral-scheda__prezzo-label">&#127915; Biglietto socio</span>
                            <span class="cral-scheda__prezzo-val"><?php echo wp_kses_post( $fmt_euro( $prezzo_base ) ); ?></span>
                        </div>
                        <?php foreach ( $enabled_types as $type_label => $type_data ) : ?>
                        <div class="cral-scheda__prezzo-row">
                            <span class="cral-scheda__prezzo-label">
                                &#43; <?php echo esc_html( $type_label ); ?>
                                <em class="cral-scheda__prezzo-max">(max <?php echo esc_html( $type_data['max'] ); ?>)</em>
                            </span>
                            <span class="cral-scheda__prezzo-val"><?php echo wp_kses_post( $fmt_euro( $type_data['price'] ) ); ?></span>
                        </div>
                        <?php endforeach; ?>
                        <?php if ( empty( $enabled_types ) ) : ?>
                        <p class="cral-scheda__no-acc">Nessun accompagnatore previsto per questo evento.</p>
                        <?php endif; ?>
                    </div>
                </div>

            </div><!-- /.cral-scheda__info -->

            <hr class="cral-scheda__divider">

            <!-- ═══ SEZIONE PRENOTAZIONE ═══ -->
            <div class="cral-scheda__prenota">

                <?php if ( $is_annullato ) : ?>
                    <p class="cral-scheda__msg cral-scheda__msg--error">Questo evento è stato annullato e non è possibile iscriversi.</p>

                <?php elseif ( $is_concluso ) : ?>
                    <?php
                    // Cerca prenotazione confermata del socio anche per eventi conclusi.
                    $pren_concluso_id = 0;
                    if ( $socio_id ) {
                        $pren_concluso = get_posts( array(
                            'post_type'      => 'prenotazione',
                            'posts_per_page' => 1,
                            'meta_query'     => array(
                                'relation' => 'AND',
                                array( 'key' => '_cral_pren_socio_id',  'value' => $socio_id ),
                                array( 'key' => '_cral_pren_evento_id', 'value' => $evento_id ),
                                array( 'key' => '_cral_pren_stato', 'value' => array( 'confermata', 'in_attesa' ), 'compare' => 'IN' ),
                            ),
                            'fields' => 'ids',
                        ) );
                        if ( ! empty( $pren_concluso ) ) {
                            $pren_concluso_id = (int) $pren_concluso[0];
                        }
                    }
                    ?>
                    <?php if ( $pren_concluso_id ) :
                        $pc_data      = get_post_meta( $pren_concluso_id, '_cral_pren_data', true );
                        $pc_importo   = (float) get_post_meta( $pren_concluso_id, '_cral_pren_importo_totale', true );
                        $pc_biglietti = (int) get_post_meta( $pren_concluso_id, '_cral_pren_totale_biglietti', true );
                        $pc_note      = (string) get_post_meta( $pren_concluso_id, '_cral_pren_note', true );
                        $pc_partecipanti = carbon_get_post_meta( $pren_concluso_id, 'cral_partecipanti' );
                        $pc_data_fmt  = $pc_data ? wp_date( 'd/m/Y', strtotime( $pc_data ) ) : '—';
                    ?>
                    <p class="cral-scheda__msg cral-scheda__msg--info">&#10003; Hai partecipato a questo evento.</p>
                    <div class="cral-scheda__riepilogo">
                        <div class="cral-scheda__riepilogo-row cral-scheda__riepilogo-row--head">
                            <span>Riepilogo prenotazione</span>
                            <strong></strong>
                        </div>
                        <div class="cral-scheda__riepilogo-row">
                            <span>Data prenotazione</span>
                            <strong><?php echo esc_html( $pc_data_fmt ); ?></strong>
                        </div>
                        <div class="cral-scheda__riepilogo-row">
                            <span>Prezzo biglietto</span>
                            <strong><?php echo wp_kses_post( $fmt_euro( $prezzo_base ) ); ?></strong>
                        </div>
                        <div class="cral-scheda__riepilogo-row">
                            <span>Biglietti acquistati</span>
                            <strong><?php echo esc_html( $pc_biglietti ); ?></strong>
                        </div>
                        <?php
                        // Accompagnatori (escludi il socio stesso che è il primo).
                        $acc_list = is_array( $pc_partecipanti ) ? array_slice( $pc_partecipanti, 1 ) : array();
                        if ( ! empty( $acc_list ) ) : ?>
                        <div class="cral-scheda__riepilogo-row cral-scheda__riepilogo-row--sub-head">
                            <span><em>Accompagnatori</em></span>
                            <strong></strong>
                        </div>
                        <?php foreach ( $acc_list as $acc ) :
                            $acc_nome    = trim( ( $acc['partecipante_nome'] ?? '' ) . ' ' . ( $acc['partecipante_cognome'] ?? '' ) );
                            $acc_tipo    = $acc['partecipante_tipologia'] ?? '';
                            $acc_prezzo  = (float) ( $acc['partecipante_prezzo'] ?? 0 );
                        ?>
                        <div class="cral-scheda__riepilogo-row cral-scheda__riepilogo-row--acc">
                            <span class="cral-scheda__acc-nome-wrap">
                                <span><?php echo esc_html( $acc_nome ); ?></span>
                                <?php if ( $acc_tipo ) : ?>
                                <em class="cral-scheda__acc-tipo"><?php echo esc_html( $acc_tipo ); ?></em>
                                <?php endif; ?>
                            </span>
                            <strong><?php echo wp_kses_post( $fmt_euro( $acc_prezzo ) ); ?></strong>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                        <div class="cral-scheda__riepilogo-row cral-scheda__riepilogo-row--total">
                            <span>Totale pagato</span>
                            <strong><?php echo wp_kses_post( $fmt_euro( $pc_importo ) ); ?></strong>
                        </div>
                        <?php if ( $pc_note ) : ?>
                        <div class="cral-scheda__note-block">
                            <p class="cral-scheda__note-label">Note</p>
                            <div class="cral-scheda__note-body" data-cral-note>
                                <p class="cral-scheda__note-text"><?php echo nl2br( esc_html( $pc_note ) ); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php else : ?>
                    <p class="cral-scheda__msg cral-scheda__msg--info">Questo evento si è concluso.</p>
                    <?php endif; ?>

                <?php elseif ( $is_soldout ) : ?>
                    <div class="cral-scheda__soldout">
                        <p class="cral-scheda__msg cral-scheda__msg--soldout">
                            <strong>&#x1F6AB; SOLD OUT</strong> — I posti disponibili sono esauriti.
                        </p>
                    </div>

                <?php elseif ( $gia_prenotato ) : ?>
                    <?php
                    $gp_data        = get_post_meta( $pren_esistente_id, '_cral_pren_data', true );
                    $gp_stato       = get_post_meta( $pren_esistente_id, '_cral_pren_stato', true );
                    $gp_importo     = (float) get_post_meta( $pren_esistente_id, '_cral_pren_importo_totale', true );
                    $gp_biglietti   = (int) get_post_meta( $pren_esistente_id, '_cral_pren_totale_biglietti', true );
                    $gp_note        = (string) get_post_meta( $pren_esistente_id, '_cral_pren_note', true );
                    $gp_partecipanti = carbon_get_post_meta( $pren_esistente_id, 'cral_partecipanti' );
                    $gp_data_fmt    = $gp_data ? wp_date( 'd/m/Y', strtotime( $gp_data ) ) : '—';
                    $stati_label    = array(
                        'in_attesa'  => 'In attesa di conferma',
                        'confermata' => 'Confermata',
                        'annullata'  => 'Annullata',
                    );
                    $nonce_annulla  = wp_create_nonce( 'cral_annulla_scheda_nonce' );
                    ?>
                    <div class="cral-scheda__gia-prenotato">
                        <p class="cral-scheda__msg cral-scheda__msg--success">&#10003; Sei già iscritto a questo evento.</p>

                        <div class="cral-scheda__riepilogo">
                            <div class="cral-scheda__riepilogo-row cral-scheda__riepilogo-row--head">
                                <span>Riepilogo prenotazione</span><strong></strong>
                            </div>
                            <div class="cral-scheda__riepilogo-row">
                                <span>Data prenotazione</span>
                                <strong><?php echo esc_html( $gp_data_fmt ); ?></strong>
                            </div>
                            <div class="cral-scheda__riepilogo-row">
                                <span>Stato</span>
                                <strong><?php echo esc_html( $stati_label[ $gp_stato ] ?? $gp_stato ); ?></strong>
                            </div>
                            <div class="cral-scheda__riepilogo-row">
                                <span>Prezzo biglietto</span>
                                <strong><?php echo wp_kses_post( $fmt_euro( $prezzo_base ) ); ?></strong>
                            </div>
                            <div class="cral-scheda__riepilogo-row">
                                <span>Biglietti acquistati</span>
                                <strong><?php echo esc_html( $gp_biglietti ); ?></strong>
                            </div>
                            <?php
                            $gp_acc_list = is_array( $gp_partecipanti ) ? array_slice( $gp_partecipanti, 1 ) : array();
                            if ( ! empty( $gp_acc_list ) ) : ?>
                            <div class="cral-scheda__riepilogo-row cral-scheda__riepilogo-row--sub-head">
                                <span><em>Accompagnatori</em></span><strong></strong>
                            </div>
                            <?php foreach ( $gp_acc_list as $gp_acc ) :
                                $gp_acc_nome   = trim( ( $gp_acc['partecipante_nome'] ?? '' ) . ' ' . ( $gp_acc['partecipante_cognome'] ?? '' ) );
                                $gp_acc_tipo   = $gp_acc['partecipante_tipologia'] ?? '';
                                $gp_acc_prezzo = (float) ( $gp_acc['partecipante_prezzo'] ?? 0 );
                            ?>
                            <div class="cral-scheda__riepilogo-row cral-scheda__riepilogo-row--acc">
                                <span class="cral-scheda__acc-nome-wrap">
                                    <span><?php echo esc_html( $gp_acc_nome ); ?></span>
                                    <?php if ( $gp_acc_tipo ) : ?>
                                    <em class="cral-scheda__acc-tipo"><?php echo esc_html( $gp_acc_tipo ); ?></em>
                                    <?php endif; ?>
                                </span>
                                <strong><?php echo wp_kses_post( $fmt_euro( $gp_acc_prezzo ) ); ?></strong>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                            <div class="cral-scheda__riepilogo-row cral-scheda__riepilogo-row--total">
                                <span>Totale pagato</span>
                                <strong><?php echo wp_kses_post( $fmt_euro( $gp_importo ) ); ?></strong>
                            </div>
                            <?php if ( $gp_note ) : ?>
                            <div class="cral-scheda__note-block">
                                <p class="cral-scheda__note-label">Note</p>
                                <div class="cral-scheda__note-body" data-cral-note>
                                    <p class="cral-scheda__note-text"><?php echo nl2br( esc_html( $gp_note ) ); ?></p>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Annulla prenotazione -->
                        <?php
                        $deadline_raw  = get_post_meta( $evento_id, '_cral_evento_data_iscrizione', true );
                        $deadline_ts   = $deadline_raw ? strtotime( (string) $deadline_raw ) : 0;
                        $deadline_fmt  = $deadline_ts ? wp_date( 'd/m/Y', $deadline_ts ) : '';
                        $scaduto       = $deadline_ts && $deadline_ts < time();
                        ?>
                        <div class="cral-scheda__annulla-wrap" id="cral-annulla-wrap-<?php echo esc_attr( $evento_id ); ?>">
                            <?php if ( $scaduto ) : ?>
                                <p class="cral-scheda__annulla-scaduto">
                                    Non puoi più annullare la prenotazione.
                                    <?php if ( $deadline_fmt ) : ?>
                                    Potevi annullare la prenotazione entro il <strong><?php echo esc_html( $deadline_fmt ); ?></strong>.
                                    <?php endif; ?>
                                </p>
                                <button
                                    type="button"
                                    class="cral-scheda__btn cral-scheda__btn--annulla"
                                    disabled
                                >
                                    Annulla prenotazione
                                </button>
                            <?php else : ?>
                                <?php if ( $deadline_fmt ) : ?>
                                <p class="cral-scheda__annulla-deadline">
                                    Puoi annullare la tua prenotazione entro il <strong><?php echo esc_html( $deadline_fmt ); ?></strong>.
                                </p>
                                <?php endif; ?>
                                <div class="cral-scheda__feedback" id="cral-annulla-feedback-<?php echo esc_attr( $evento_id ); ?>" style="display:none;"></div>
                                <button
                                    type="button"
                                    class="cral-scheda__btn cral-scheda__btn--annulla"
                                    id="cral-annulla-btn-<?php echo esc_attr( $evento_id ); ?>"
                                    data-pren-id="<?php echo esc_attr( $pren_esistente_id ); ?>"
                                    data-nonce="<?php echo esc_attr( $nonce_annulla ); ?>"
                                    data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
                                >
                                    Annulla prenotazione
                                </button>
                            <?php endif; ?>
                        </div>

                        <script>
                        (function(){
                            const btn      = document.getElementById('cral-annulla-btn-<?php echo esc_js( (string) $evento_id ); ?>');
                            const feedback = document.getElementById('cral-annulla-feedback-<?php echo esc_js( (string) $evento_id ); ?>');
                            if (!btn) return;
                            btn.addEventListener('click', function() {
                                if (!window.confirm('Sei sicuro di voler annullare la prenotazione? I posti verranno ripristinati e potrai riprenotarti.')) return;
                                btn.disabled    = true;
                                btn.textContent = 'Annullamento in corso…';
                                fetch(btn.dataset.ajax, {
                                    method: 'POST',
                                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                                    body: new URLSearchParams({
                                        action:   'cral_annulla_scheda',
                                        nonce:    btn.dataset.nonce,
                                        pren_id:  btn.dataset.prenId,
                                    })
                                })
                                .then(r => r.json())
                                .then(function(res) {
                                    if (res.success) {
                                        window.location.reload();
                                    } else {
                                        feedback.textContent   = res.data?.message || 'Errore durante l\'annullamento.';
                                        feedback.className     = 'cral-scheda__feedback cral-scheda__feedback--error';
                                        feedback.style.display = 'block';
                                        btn.disabled    = false;
                                        btn.textContent = 'Annulla prenotazione';
                                    }
                                })
                                .catch(function() {
                                    feedback.textContent   = 'Errore di connessione. Riprova.';
                                    feedback.className     = 'cral-scheda__feedback cral-scheda__feedback--error';
                                    feedback.style.display = 'block';
                                    btn.disabled    = false;
                                    btn.textContent = 'Annulla prenotazione';
                                });
                            });
                        })();
                        </script>
                    </div>

                <?php elseif ( ! $socio_id ) : ?>
                    <div class="cral-scheda__login-prompt">
                        <p>&#128274; <strong>Devi essere iscritto</strong> per prenotare questo evento.</p>
                        <a href="<?php echo esc_url( $login_url ); ?>" class="cral-scheda__btn cral-scheda__btn--secondary">Accedi al tuo account</a>
                    </div>

                <?php else : ?>
                    <!-- Form prenotazione (utente loggato, posti disponibili) -->
                    <h3 class="cral-scheda__form-title">Completa la tua iscrizione</h3>

                    <form id="cral-scheda-form-<?php echo esc_attr( $evento_id ); ?>" class="cral-scheda__form" novalidate>
                        <input type="hidden" name="evento_id"           value="<?php echo esc_attr( $evento_id ); ?>">
                        <input type="hidden" name="prezzo_base"         value="<?php echo esc_attr( $prezzo_base ); ?>">
                        <input type="hidden" name="acc_config_json"     value="<?php echo esc_attr( wp_json_encode( $acc_config ) ); ?>">

                        <!-- Biglietto socio (fisso) -->
                        <div class="cral-scheda__socio-row">
                            <span class="cral-scheda__socio-label">&#128100; Il tuo biglietto (socio)</span>
                            <span class="cral-scheda__socio-prezzo"><?php echo wp_kses_post( $fmt_euro( $prezzo_base ) ); ?></span>
                        </div>

                        <!-- Note opzionali -->
                        <div class="cral-scheda__field">
                            <label class="cral-scheda__label" for="cral-note-<?php echo esc_attr( $evento_id ); ?>">Note (opzionali)</label>
                            <textarea
                                id="cral-note-<?php echo esc_attr( $evento_id ); ?>"
                                name="note"
                                class="cral-scheda__textarea"
                                rows="2"
                                placeholder="Esigenze particolari, richieste specifiche…"
                            ></textarea>
                        </div>

                        <!-- Accompagnatori -->
                        <?php if ( ! empty( $enabled_types ) ) : ?>
                        <div class="cral-scheda__acc-section">
                            <h4 class="cral-scheda__acc-title">Accompagnatori</h4>
                            <div id="cral-acc-list-<?php echo esc_attr( $evento_id ); ?>" class="cral-scheda__acc-list"></div>
                            <button
                                type="button"
                                class="cral-scheda__btn cral-scheda__btn--add"
                                data-evento="<?php echo esc_attr( $evento_id ); ?>"
                                id="cral-add-acc-<?php echo esc_attr( $evento_id ); ?>"
                            >
                                &#43; Aggiungi accompagnatore
                            </button>
                        </div>
                        <?php endif; ?>

                        <!-- Totale live -->
                        <div class="cral-scheda__totale" id="cral-totale-<?php echo esc_attr( $evento_id ); ?>">
                            Totale: <strong><?php echo wp_kses_post( $fmt_euro( $prezzo_base ) ); ?></strong>
                        </div>

                        <!-- Messaggi errore/successo -->
                        <div class="cral-scheda__feedback" id="cral-feedback-<?php echo esc_attr( $evento_id ); ?>" style="display:none;"></div>

                        <!-- Submit -->
                        <div class="cral-scheda__submit-wrap">
                            <button
                                type="submit"
                                class="cral-scheda__btn cral-scheda__btn--primary"
                                id="cral-submit-<?php echo esc_attr( $evento_id ); ?>"
                            >
                                Conferma iscrizione
                            </button>
                        </div>

                    </form>

                    <!-- Riepilogo post-prenotazione (nascosto) -->
                    <div class="cral-scheda__conferma" id="cral-conferma-<?php echo esc_attr( $evento_id ); ?>" style="display:none;"></div>

                <?php endif; ?>

            </div><!-- /.cral-scheda__prenota -->

        </div><!-- /.cral-scheda -->
        <script>
        (function(){
            const MAX_H = 60; // px oltre il quale appare "Leggi di più"
            document.querySelectorAll('[data-cral-note]').forEach(function(body) {
                const text = body.querySelector('.cral-scheda__note-text');
                if (!text || text.scrollHeight <= MAX_H) return;

                // Aggiunge fade e pulsante.
                const fade   = document.createElement('div');
                fade.className = 'cral-scheda__note-fade';
                body.appendChild(fade);

                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'cral-scheda__note-toggle';
                btn.textContent = 'Leggi di più';
                body.parentElement.appendChild(btn);

                btn.addEventListener('click', function() {
                    const expanded = body.classList.toggle('is-expanded');
                    fade.style.display = expanded ? 'none' : '';
                    btn.textContent    = expanded ? 'Leggi meno' : 'Leggi di più';
                });
            });
        })();
        </script>

        <?php if ( $is_open && ! $gia_prenotato && $socio_id ) : ?>
        <script>
        (function(){
            const evId       = <?php echo (int) $evento_id; ?>;
            const nonce      = '<?php echo esc_js( $nonce ); ?>';
            const ajaxUrl    = '<?php echo esc_js( $ajax_url ); ?>';
            const prezzoBase = <?php echo (float) $prezzo_base; ?>;
            const accConfig  = <?php echo wp_json_encode( $acc_config ); ?>;
            const enabledTypes = Object.keys(accConfig).filter(k => accConfig[k].enabled);

            const form      = document.getElementById('cral-scheda-form-' + evId);
            const accList   = document.getElementById('cral-acc-list-' + evId);
            const addBtn    = document.getElementById('cral-add-acc-' + evId);
            const totaleEl  = document.getElementById('cral-totale-' + evId);
            const feedback  = document.getElementById('cral-feedback-' + evId);
            const submitBtn = document.getElementById('cral-submit-' + evId);
            const conferma  = document.getElementById('cral-conferma-' + evId);

            if (!form) return;

            let accIndex = 0;
            const byType = {};

            // ─── Calcolo totale ───
            function calcTotal() {
                let total = prezzoBase;
                let count = 0;
                const ct  = {};
                let hasError = false;

                accList.querySelectorAll('.cral-scheda__acc-row').forEach(function(row) {
                    const sel = row.querySelector('select');
                    if (!sel) return;
                    const tipo = sel.value;
                    count++;
                    ct[tipo] = (ct[tipo] || 0) + 1;
                    const maxT = accConfig[tipo]?.max || 0;
                    if (ct[tipo] > maxT) hasError = true;
                    total += Number(accConfig[tipo]?.price || 0);
                });

                totaleEl.innerHTML = 'Totale: <strong>€\u00a0' + total.toFixed(2).replace('.', ',') + '</strong>';
                if (count > 0) {
                    totaleEl.innerHTML += ' <span class="cral-scheda__totale-sub">(' + (1 + count) + ' biglietti)</span>';
                }

                if (hasError) {
                    showFeedback('Hai superato il massimo consentito per una tipologia di accompagnatore.', 'error');
                    submitBtn.disabled = true;
                } else {
                    hideFeedback();
                    submitBtn.disabled = false;
                }
            }

            // ─── Aggiungi accompagnatore ───
            function addAccRow() {
                if (!enabledTypes.length) return;
                const idx = accIndex++;
                const row = document.createElement('div');
                row.className = 'cral-scheda__acc-row';

                const opts = enabledTypes.map(function(t) {
                    const prezzo = Number(accConfig[t].price || 0).toFixed(2).replace('.', ',');
                    return '<option value="' + t + '">' + t + ' — €\u00a0' + prezzo + '</option>';
                }).join('');

                row.innerHTML = [
                    '<div class="cral-scheda__acc-header">',
                    '  <span class="cral-scheda__acc-num">Accompagnatore ' + (idx + 1) + '</span>',
                    '  <button type="button" class="cral-scheda__btn cral-scheda__btn--remove" aria-label="Rimuovi accompagnatore">&#10005; Rimuovi</button>',
                    '</div>',
                    '<div class="cral-scheda__acc-fields">',
                    '  <div class="cral-scheda__field cral-scheda__field--half">',
                    '    <label class="cral-scheda__label">Nome</label>',
                    '    <input type="text" class="cral-scheda__input" name="accompagnatori[' + idx + '][nome]" placeholder="Nome" required>',
                    '  </div>',
                    '  <div class="cral-scheda__field cral-scheda__field--half">',
                    '    <label class="cral-scheda__label">Cognome</label>',
                    '    <input type="text" class="cral-scheda__input" name="accompagnatori[' + idx + '][cognome]" placeholder="Cognome" required>',
                    '  </div>',
                    '  <div class="cral-scheda__field cral-scheda__field--select">',
                    '    <label class="cral-scheda__label">Tipologia</label>',
                    '    <select class="cral-scheda__select" name="accompagnatori[' + idx + '][tipologia]" required>' + opts + '</select>',
                    '  </div>',
                    '</div>',
                ].join('');

                accList.appendChild(row);
                row.querySelector('select').addEventListener('change', calcTotal);
                row.querySelector('.cral-scheda__btn--remove').addEventListener('click', function() {
                    row.remove();
                    calcTotal();
                });

                // Animazione entrata.
                row.style.opacity = '0';
                row.style.transform = 'translateY(-6px)';
                requestAnimationFrame(function() {
                    row.style.transition = 'opacity 0.2s, transform 0.2s';
                    row.style.opacity = '1';
                    row.style.transform = 'translateY(0)';
                });

                calcTotal();
            }

            if (addBtn) addBtn.addEventListener('click', addAccRow);

            // ─── Feedback ───
            function showFeedback(msg, type) {
                feedback.textContent = msg;
                feedback.className   = 'cral-scheda__feedback cral-scheda__feedback--' + type;
                feedback.style.display = 'block';
            }
            function hideFeedback() {
                feedback.style.display = 'none';
                feedback.textContent   = '';
            }

            // ─── Submit ───
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                hideFeedback();
                submitBtn.disabled   = true;
                submitBtn.textContent = 'Invio in corso…';

                const formData = new FormData(form);
                formData.append('action', 'cral_prenota_scheda');
                formData.append('nonce', nonce);

                fetch(ajaxUrl, { method: 'POST', body: formData })
                .then(r => r.json())
                .then(function(res) {
                    if (res.success) {
                        // Mostra riepilogo.
                        form.style.display = 'none';
                        conferma.style.display = 'block';
                        conferma.innerHTML = buildConferma(res.data);
                        conferma.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    } else {
                        showFeedback(res.data?.message || 'Errore durante l\'iscrizione.', 'error');
                        submitBtn.disabled   = false;
                        submitBtn.textContent = 'Conferma iscrizione';
                    }
                })
                .catch(function() {
                    showFeedback('Errore di connessione. Riprova.', 'error');
                    submitBtn.disabled   = false;
                    submitBtn.textContent = 'Conferma iscrizione';
                });
            });

            // ─── Riepilogo post-prenotazione ───
            function buildConferma(data) {
                let rows = '';
                if (data.partecipanti && data.partecipanti.length) {
                    data.partecipanti.forEach(function(p) {
                        const prezzo = Number(p.prezzo || 0).toFixed(2).replace('.', ',');
                        rows += '<div class="cral-scheda__riepilogo-row">'
                            + '<span>' + escHtml(p.tipologia) + ': ' + escHtml(p.nome) + ' ' + escHtml(p.cognome) + '</span>'
                            + '<strong>€\u00a0' + prezzo + '</strong>'
                            + '</div>';
                    });
                }
                const totale = Number(data.importo_totale || 0).toFixed(2).replace('.', ',');
                const note   = data.note ? '<p class="cral-scheda__conferma-note"><em>Note: ' + escHtml(data.note) + '</em></p>' : '';

                return '<div class="cral-scheda__conferma-inner">'
                    + '<div class="cral-scheda__msg cral-scheda__msg--success">&#10003; Iscrizione effettuata con successo!</div>'
                    + '<p>Riceverai una email di conferma a breve.</p>'
                    + '<div class="cral-scheda__riepilogo">'
                    + '<div class="cral-scheda__riepilogo-row cral-scheda__riepilogo-row--head"><span>Partecipante</span><strong>Importo</strong></div>'
                    + rows
                    + '<div class="cral-scheda__riepilogo-row cral-scheda__riepilogo-row--total"><span>Totale</span><strong>€\u00a0' + totale + '</strong></div>'
                    + '</div>'
                    + note
                    + '</div>';
            }

            function escHtml(str) {
                const d = document.createElement('div');
                d.appendChild(document.createTextNode(str || ''));
                return d.innerHTML;
            }

            calcTotal();
        })();
        </script>
        <?php endif; ?>
        <?php
        return ob_get_clean();
    }

    /**
     * Handler AJAX annullamento prenotazione da frontend scheda.
     */
    public function handle_annulla() {
        global $wpdb;

        $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
        if ( ! wp_verify_nonce( $nonce, 'cral_annulla_scheda_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Richiesta non valida.' ) );
        }

        $auth     = new \GEvent\Auth();
        $socio_id = $auth->get_current_socio();
        if ( ! $socio_id ) {
            wp_send_json_error( array( 'message' => 'Devi essere loggato.' ) );
        }

        $pren_id = absint( $_POST['pren_id'] ?? 0 );
        if ( ! $pren_id || 'prenotazione' !== get_post_type( $pren_id ) ) {
            wp_send_json_error( array( 'message' => 'Prenotazione non valida.' ) );
        }

        // Verifica che la prenotazione appartenga al socio loggato.
        $owner = (int) get_post_meta( $pren_id, '_cral_pren_socio_id', true );
        if ( $owner !== $socio_id ) {
            wp_send_json_error( array( 'message' => 'Non sei autorizzato ad annullare questa prenotazione.' ) );
        }

        $stato = (string) get_post_meta( $pren_id, '_cral_pren_stato', true );
        if ( 'annullata' === $stato ) {
            wp_send_json_error( array( 'message' => 'La prenotazione è già annullata.' ) );
        }

        $evento_id = (int) get_post_meta( $pren_id, '_cral_pren_evento_id', true );

        // Verifica deadline annullamento (data scadenza iscrizioni).
        $deadline_raw = get_post_meta( $evento_id, '_cral_evento_data_iscrizione', true );
        if ( $deadline_raw ) {
            $deadline_ts = strtotime( (string) $deadline_raw );
            if ( $deadline_ts && $deadline_ts < time() ) {
                wp_send_json_error( array(
                    'message' => 'Il termine per annullare la prenotazione è scaduto il ' . wp_date( 'd/m/Y', $deadline_ts ) . '.',
                ) );
            }
        }
        $totale_biglietti = (int) get_post_meta( $pren_id, '_cral_pren_totale_biglietti', true );

        // Segna come annullata.
        update_post_meta( $pren_id, '_cral_pren_stato', 'annullata' );

        // Ripristina i posti sull'evento.
        if ( $evento_id && $totale_biglietti > 0 ) {
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$wpdb->postmeta}
                     SET meta_value = CAST(meta_value AS UNSIGNED) + %d
                     WHERE post_id = %d
                     AND meta_key = '_cral_evento_posti_residui'",
                    $totale_biglietti,
                    $evento_id
                )
            );
        }

        Logger::log( 'annulla_scheda', 'Annullamento frontend prenotazione #' . $pren_id, array(
            'socio_id'   => $socio_id,
            'evento_id'  => $evento_id,
            'biglietti'  => $totale_biglietti,
        ) );

        wp_send_json_success( array( 'message' => 'Prenotazione annullata correttamente.' ) );
    }

    /**
     * Handler AJAX prenotazione da scheda.
     */
    public function handle_prenota() {
        $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
        if ( ! wp_verify_nonce( $nonce, 'cral_prenota_scheda_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Richiesta non valida.' ) );
        }

        $auth     = new \GEvent\Auth();
        $socio_id = $auth->get_current_socio();
        if ( ! $socio_id ) {
            wp_send_json_error( array( 'message' => 'Devi essere loggato per prenotare.' ) );
        }

        $evento_id = absint( $_POST['evento_id'] ?? 0 );
        if ( ! $evento_id || 'evento' !== get_post_type( $evento_id ) ) {
            wp_send_json_error( array( 'message' => 'Evento non valido.' ) );
        }

        // Verifica duplicato.
        $existing = get_posts( array(
            'post_type'      => 'prenotazione',
            'posts_per_page' => 1,
            'meta_query'     => array(
                'relation' => 'AND',
                array( 'key' => '_cral_pren_socio_id', 'value' => $socio_id ),
                array( 'key' => '_cral_pren_evento_id', 'value' => $evento_id ),
                array( 'key' => '_cral_pren_stato', 'value' => array( 'confermata', 'in_attesa' ), 'compare' => 'IN' ),
            ),
            'fields' => 'ids',
        ) );
        if ( ! empty( $existing ) ) {
            wp_send_json_error( array( 'message' => 'Sei già iscritto a questo evento.' ) );
        }

        // Dati evento.
        $prezzo_base        = (float) get_post_meta( $evento_id, '_cral_evento_prezzo_base', true );
        $prezzo_acc_socio   = (float) get_post_meta( $evento_id, '_cral_evento_prezzo_acc_socio', true );
        $prezzo_acc_esterno = (float) get_post_meta( $evento_id, '_cral_evento_prezzo_acc_esterno', true );
        $prezzo_acc_junior  = (float) get_post_meta( $evento_id, '_cral_evento_prezzo_acc_junior', true );

        $allowed_types = array(
            'Accompagnatore Socio'   => array(
                'enabled' => 'yes' === get_post_meta( $evento_id, '_cral_evento_enable_acc_socio', true ),
                'price'   => $prezzo_acc_socio,
                'max'     => (int) get_post_meta( $evento_id, '_cral_evento_max_acc_socio', true ),
            ),
            'Accompagnatore Esterno' => array(
                'enabled' => 'yes' === get_post_meta( $evento_id, '_cral_evento_enable_acc_esterno', true ),
                'price'   => $prezzo_acc_esterno,
                'max'     => (int) get_post_meta( $evento_id, '_cral_evento_max_acc_esterno', true ),
            ),
            'Accompagnatore Junior'  => array(
                'enabled' => 'yes' === get_post_meta( $evento_id, '_cral_evento_enable_acc_junior', true ),
                'price'   => $prezzo_acc_junior,
                'max'     => (int) get_post_meta( $evento_id, '_cral_evento_max_acc_junior', true ),
            ),
        );

        // Note.
        $note = sanitize_textarea_field( wp_unslash( $_POST['note'] ?? '' ) );

        // Accompagnatori.
        $raw_acc          = isset( $_POST['accompagnatori'] ) && is_array( $_POST['accompagnatori'] )
            ? $_POST['accompagnatori'] // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
            : array();
        $accompagnatori   = array();
        $by_type          = array();
        $importo_totale   = $prezzo_base;

        foreach ( $raw_acc as $part ) {
            $nome      = sanitize_text_field( $part['nome'] ?? '' );
            $cognome   = sanitize_text_field( $part['cognome'] ?? '' );
            $tipologia = sanitize_text_field( $part['tipologia'] ?? '' );

            if ( ! $nome || ! $cognome ) {
                wp_send_json_error( array( 'message' => 'Compila nome e cognome di ogni accompagnatore.' ) );
            }
            if ( ! isset( $allowed_types[ $tipologia ] ) || empty( $allowed_types[ $tipologia ]['enabled'] ) ) {
                wp_send_json_error( array( 'message' => 'Tipologia accompagnatore non valida.' ) );
            }
            $by_type[ $tipologia ] = ( $by_type[ $tipologia ] ?? 0 ) + 1;
            if ( $by_type[ $tipologia ] > $allowed_types[ $tipologia ]['max'] ) {
                wp_send_json_error( array( 'message' => 'Superato il massimo per "' . $tipologia . '".' ) );
            }
            $prezzo_acc      = (float) $allowed_types[ $tipologia ]['price'];
            $importo_totale += $prezzo_acc;
            $accompagnatori[] = array(
                'nome'      => $nome,
                'cognome'   => $cognome,
                'tipologia' => $tipologia,
                'prezzo'    => $prezzo_acc,
            );
        }

        $totale_biglietti = 1 + count( $accompagnatori );

        // Scala i posti (atomico).
        global $wpdb;
        $updated = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->postmeta}
                 SET meta_value = meta_value - %d
                 WHERE post_id = %d
                 AND meta_key = '_cral_evento_posti_residui'
                 AND CAST(meta_value AS UNSIGNED) >= %d",
                $totale_biglietti, $evento_id, $totale_biglietti
            )
        );
        if ( ! $updated ) {
            wp_send_json_error( array( 'message' => 'Spiacenti, i posti disponibili sono esauriti.' ) );
        }

        // Crea partecipanti per Carbon Fields.
        $socio_nome    = get_post_meta( $socio_id, '_cral_nome', true );
        $socio_cognome = get_post_meta( $socio_id, '_cral_cognome', true );
        $partecipanti  = array(
            array(
                'partecipante_nome'      => $socio_nome,
                'partecipante_cognome'   => $socio_cognome,
                'partecipante_tipologia' => 'Socio',
                'partecipante_prezzo'    => (string) $prezzo_base,
            ),
        );
        foreach ( $accompagnatori as $a ) {
            $partecipanti[] = array(
                'partecipante_nome'      => $a['nome'],
                'partecipante_cognome'   => $a['cognome'],
                'partecipante_tipologia' => $a['tipologia'],
                'partecipante_prezzo'    => (string) $a['prezzo'],
            );
        }

        // Crea prenotazione.
        $post_id = wp_insert_post( array(
            'post_type'   => 'prenotazione',
            'post_title'  => $socio_cognome . ' ' . $socio_nome . ' — ' . get_the_title( $evento_id ),
            'post_status' => 'publish',
        ) );

        if ( is_wp_error( $post_id ) ) {
            // Ripristina posti.
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$wpdb->postmeta} SET meta_value = meta_value + %d WHERE post_id = %d AND meta_key = '_cral_evento_posti_residui'",
                $totale_biglietti, $evento_id
            ) );
            wp_send_json_error( array( 'message' => 'Errore durante la prenotazione. Riprova.' ) );
        }

        update_post_meta( $post_id, '_cral_pren_socio_id', $socio_id );
        update_post_meta( $post_id, '_cral_pren_evento_id', $evento_id );
        update_post_meta( $post_id, '_cral_pren_data', current_time( 'mysql' ) );
        update_post_meta( $post_id, '_cral_pren_stato', 'in_attesa' );
        update_post_meta( $post_id, '_cral_pren_totale_biglietti', $totale_biglietti );
        update_post_meta( $post_id, '_cral_pren_importo_totale', $importo_totale );
        update_post_meta( $post_id, '_cral_pren_note', $note );
        update_post_meta( $post_id, '_cral_pren_pagamento', 'no' );

        carbon_set_post_meta( $post_id, 'cral_partecipanti', $partecipanti );

        // Email.
        $mailer = new \GEvent\Mailer();
        $mailer->send_conferma_socio( $post_id, $socio_id, $evento_id );
        $mailer->send_notifica_segreteria( $post_id, $socio_id, $evento_id );

        Logger::log( 'prenota_scheda', 'Prenotazione scheda #' . $post_id, array(
            'socio_id'  => $socio_id,
            'evento_id' => $evento_id,
            'biglietti' => $totale_biglietti,
        ) );

        // Dati risposta per riepilogo JS.
        $partecipanti_risposta = array();
        foreach ( $partecipanti as $p ) {
            $partecipanti_risposta[] = array(
                'nome'      => $p['partecipante_nome'],
                'cognome'   => $p['partecipante_cognome'],
                'tipologia' => $p['partecipante_tipologia'],
                'prezzo'    => $p['partecipante_prezzo'],
            );
        }

        wp_send_json_success( array(
            'prenotazione_id' => $post_id,
            'importo_totale'  => $importo_totale,
            'biglietti'       => $totale_biglietti,
            'note'            => $note,
            'partecipanti'    => $partecipanti_risposta,
        ) );
    }
}

<?php
/**
 * Shortcode footer custom a 4 colonne.
 *
 * @package GEvent
 */

namespace GEvent;

/**
 * Footer testuale per Elementor / template.
 */
class Footer_Custom {

    /**
     * Registra lo shortcode.
     */
    public function init() {
        add_shortcode( 'footer-custom', array( $this, 'render' ) );
    }

    /**
     * Render [footer-custom].
     *
     * @return string
     */
    public function render() {
        $year = (int) gmdate( 'Y' );
        $name = Auth_Frontend::get_site_brand_name();
        $desc = Auth_Frontend::get_site_brand_description();
        $home = home_url( '/' );

        ob_start();
        ?>
        <footer class="cral-footer" role="contentinfo">
            <div class="cral-footer__brand">
                <a class="cral-footer__logo" href="<?php echo esc_url( $home ); ?>">
                    <span class="cral-footer__logo-name"><?php echo esc_html( $name ); ?></span>
                    <?php if ( $desc ) : ?>
                    <span class="cral-footer__logo-desc"><?php echo esc_html( $desc ); ?></span>
                    <?php endif; ?>
                </a>
            </div>

            <div class="cral-footer__grid">

                <div class="cral-footer__col">
                    <h4 class="cral-footer__title">Il Circolo</h4>
                    <p class="cral-footer__text">
                        Portale ufficiale del <strong><?php echo esc_html( $name ); ?></strong>:
                        lo spazio digitale dei soci per scoprire eventi, iscriversi alle iniziative
                        e vivere la vita associativa fuori dall’orario di lavoro.
                    </p>
                    <p class="cral-footer__text">
                        Teatro, sport, gite, serate culturali e tanto altro —
                        pensato per chi lavora in banca e vuole condividere tempo libero in compagnia.
                    </p>
                </div>

                <div class="cral-footer__col">
                    <h4 class="cral-footer__title">Gestito da</h4>
                    <p class="cral-footer__text">
                        <strong>Segreteria <?php echo esc_html( $name ); ?></strong><br>
                        Comitato ricreativo aziendale<br>
                        Via Monte Rosa 15 — 20149 Milano
                    </p>
                    <p class="cral-footer__text">
                        Presidente: <em>Laura Bianchi</em><br>
                        Referente eventi: <em>Marco Ferretti</em>
                    </p>
                    <p class="cral-footer__meta">CF / P.IVA fittizia: 01234567890</p>
                </div>

                <div class="cral-footer__col">
                    <h4 class="cral-footer__title">Supporto soci</h4>
                    <p class="cral-footer__text">
                        Hai dubbi su un’iscrizione, sui posti disponibili
                        o sulla tessera socio? Scrivici.
                    </p>
                    <ul class="cral-footer__list">
                        <li>
                            Email:
                            <a href="mailto:supporto@cralbccmilano.it">supporto@cralbccmilano.it</a>
                        </li>
                        <li>
                            Segreteria:
                            <a href="mailto:segreteria@cralbccmilano.it">segreteria@cralbccmilano.it</a>
                        </li>
                        <li>Tel: <a href="tel:+390212345678">02 1234 5678</a></li>
                        <li>WhatsApp (fittizio): 333 987 6543</li>
                    </ul>
                    <p class="cral-footer__meta">Rispondiamo Lun–Ven · 9:00–13:00 / 14:30–17:30</p>
                </div>

                <div class="cral-footer__col">
                    <h4 class="cral-footer__title">Cosa trovi qui</h4>
                    <ul class="cral-footer__list">
                        <li>Calendario eventi e prenotazioni online</li>
                        <li>Area riservata soci con le tue iscrizioni</li>
                        <li>Convenzioni e iniziative del territorio</li>
                        <li>Comunicazioni dalla segreteria</li>
                    </ul>
                    <p class="cral-footer__text">
                        Accesso riservato ai soci in regola con la quota associativa
                        e, dove previsto, ai loro accompagnatori.
                    </p>
                    <p class="cral-footer__meta">
                        © <?php echo esc_html( (string) $year ); ?> <?php echo esc_html( $name ); ?> ·
                        Dati di esempio — da aggiornare
                    </p>
                </div>

            </div>
        </footer>
        <?php
        return ob_get_clean();
    }
}

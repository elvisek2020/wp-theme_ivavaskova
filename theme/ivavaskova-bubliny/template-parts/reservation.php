<?php
/**
 * Panel Kontakt a rezervace jako krátký rozhovor: „Kterou lekci si vybíráš?“ → „Skupinově, nebo
 * individuálně?“ → skupinově termín a přihláška (modul reservations pluginu), individuálně nebo
 * bez vypsaných termínů poptávka (modul contact-form). Vedle bublina s kontaktem.
 * Bez JS se ukážou oba formuláře pod sebou; rozhovor (data-ivb-chat) řídí assets/js/theme.js.
 * Na titulce i na stránce se šablonou „Kontakt a rezervace“.
 *
 * $args: id (kotva sekce), heading (h2; '' = bez nadpisu), lead, content (vykreslený obsah stránky
 * se shortcodem [ekc_rezervace] místo výchozího formuláře; volající předá jen obsah, který
 * shortcode opravdu obsahuje – page-rezervace.php).
 */

defined( 'ABSPATH' ) || exit;

$ivb_res = '';
if ( isset( $args['content'] ) && '' !== trim( (string) $args['content'] ) ) {
	$ivb_res = (string) $args['content'];
} elseif ( function_exists( 'ekc_reservation_form' ) ) {
	$ivb_res = ekc_reservation_form( array() );
}
$ivb_ask_form = function_exists( 'ekc_contact_form' )
	? ekc_contact_form(
		array(
			'subject' => 'Individuální lekce',
			'button'  => 'Odeslat poptávku',
		)
	)
	: '';

ob_start();
get_template_part( 'template-parts/ask' );
$ivb_ask = (string) ob_get_clean();

if ( '' === trim( $ivb_res . $ivb_ask_form . $ivb_ask ) ) {
	return;
}

// Nabídky pro první otázku: stránky se šablonou Nabídka (JS doplní nabídky s termíny navíc).
// Uložený název bez wptexturize, ať se shoduje se službou v nastavení rezervací (escapuje se při výstupu).
$ivb_services = array();
foreach ( ivb_offer_pages() as $ivb_offer ) {
	$ivb_services[] = ivb_raw_title( $ivb_offer );
}
$ivb_services = array_values( array_unique( array_filter( $ivb_services ) ) );

$ivb_heading = array_key_exists( 'heading', $args ) ? (string) $args['heading'] : 'Kontakt a rezervace';
$ivb_lead    = (string) ( $args['lead'] ?? 'Krátký rozhovor místo dlouhého formuláře.' );
$ivb_chat    = '' !== trim( $ivb_res . $ivb_ask_form );
?>
<section class="sec sec--tight"<?php echo empty( $args['id'] ) ? '' : ' id="' . esc_attr( $args['id'] ) . '"'; ?>>
	<div class="wrap">
		<div class="res-panel iso">
			<?php if ( '' !== $ivb_heading ) : ?>
				<h2><?php echo esc_html( $ivb_heading ); ?></h2>
			<?php endif; ?>
			<?php if ( '' !== $ivb_lead ) : ?>
				<p class="lead"><?php echo esc_html( $ivb_lead ); ?></p>
			<?php endif; ?>
			<div class="res-grid<?php echo ( $ivb_chat && '' !== trim( $ivb_ask ) ) ? '' : ' res-grid--solo'; ?>">
				<?php if ( $ivb_chat ) : ?>
					<div class="chat" data-ivb-chat>
						<div class="msg" data-ivb-step="service" hidden>
							<?php echo ivb_avatar(); // phpcs:ignore WordPress.Security.EscapeOutput -- HTML z WordPressu ?>
							<p class="bub tail t-left">Kterou lekci si vybíráš?</p>
						</div>
						<fieldset class="answers" data-ivb-step="service" data-ivb-services hidden>
							<legend class="screen-reader-text">Lekce</legend>
							<?php foreach ( $ivb_services as $ivb_s ) : ?>
								<label class="ans"><input type="radio" name="ivb_service" value="<?php echo esc_attr( $ivb_s ); ?>"><span><?php echo esc_html( $ivb_s ); ?></span></label>
							<?php endforeach; ?>
						</fieldset>

						<div class="msg msg--2" data-ivb-step="mode" hidden>
							<?php echo ivb_avatar(); // phpcs:ignore WordPress.Security.EscapeOutput -- HTML z WordPressu ?>
							<p class="bub tail t-left">Skupinově, nebo individuálně?</p>
						</div>
						<fieldset class="answers answers--mode" data-ivb-step="mode" hidden>
							<legend class="screen-reader-text">Forma lekce</legend>
							<label class="ans"><input type="radio" name="ivb_mode" value="group"><span>Skupinově</span></label>
							<?php if ( $ivb_ask_form ) : ?>
								<label class="ans"><input type="radio" name="ivb_mode" value="single"><span>Individuálně</span></label>
							<?php endif; ?>
						</fieldset>

						<?php if ( '' !== trim( $ivb_res ) ) : ?>
							<div class="chat__part" data-ivb-part="group">
								<div class="msg msg--3" data-ivb-step="group" hidden>
									<?php echo ivb_avatar(); // phpcs:ignore WordPress.Security.EscapeOutput -- HTML z WordPressu ?>
									<p class="bub tail t-left">Vyber termín a pár údajů – a je hotovo.</p>
								</div>
								<h3 class="chat__fallback">Skupinové lekce – přihláška na termín</h3>
								<div class="bub form-bub"><?php echo $ivb_res; // phpcs:ignore WordPress.Security.EscapeOutput -- HTML z modulu pluginu / obsah stránky ?></div>
							</div>
						<?php endif; ?>

						<?php if ( $ivb_ask_form ) : ?>
							<div class="chat__part" data-ivb-part="single">
								<div class="msg msg--3" data-ivb-step="single" hidden>
									<?php echo ivb_avatar(); // phpcs:ignore WordPress.Security.EscapeOutput -- HTML z WordPressu ?>
									<p class="bub tail t-left" data-ivb-single-text>Termín se domluví individuálně – stačí napsat.</p>
								</div>
								<h3 class="chat__fallback">Individuální lekce a Energetický obraz – poptávka</h3>
								<div class="bub form-bub" data-ivb-inquiry><?php echo $ivb_ask_form; // phpcs:ignore WordPress.Security.EscapeOutput -- HTML z modulu pluginu ?></div>
							</div>
						<?php endif; ?>
					</div>
				<?php endif; ?>
				<?php echo $ivb_ask; // phpcs:ignore WordPress.Security.EscapeOutput -- escapováno v ask.php ?>
			</div>
		</div>
	</div>
</section>

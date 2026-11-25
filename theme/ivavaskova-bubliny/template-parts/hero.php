<?php
/**
 * Úvod titulky: slogan, text v bublině s ocáskem k fotkám, tlačítka, oblázek se střídajícími se
 * fotkami (Přizpůsobit → Titulka), rotující zlaté slunce a malá fotka v rohu.
 */

defined( 'ABSPATH' ) || exit;

$ivb_photos = array_values( array_filter( array( (int) ekt_opt( 'site_hero_1' ), (int) ekt_opt( 'site_hero_2' ), (int) ekt_opt( 'site_hero_3' ) ) ) );
if ( ! $ivb_photos && is_page() && has_post_thumbnail() ) {
	$ivb_photos = array( (int) get_post_thumbnail_id() );
}
$ivb_small   = (int) ekt_opt( 'site_hero_small' );
$ivb_booking = ivb_booking_url();
$ivb_offers  = ivb_offer_pages();
$ivb_perex   = trim( (string) ekt_opt( 'site_perex' ) );
?>
<section class="hero iso" id="domu">
	<div class="wrap hero-in<?php echo $ivb_photos ? '' : ' hero-in--solo'; ?>">
		<div class="hero-text">
			<h1><?php echo esc_html( (string) ekt_opt( 'site_slogan' ) ); ?></h1>
			<?php if ( '' !== $ivb_perex ) : ?>
				<div class="bub perex tail t-right">
					<?php echo wp_kses_post( wpautop( esc_html( $ivb_perex ) ) ); ?>
				</div>
			<?php endif; ?>
			<div class="hero-btns">
				<?php if ( $ivb_booking ) : ?>
					<a class="btn" href="<?php echo esc_url( $ivb_booking ); ?>">Rezervovat lekci</a>
				<?php endif; ?>
				<?php if ( $ivb_offers ) : ?>
					<a class="btn btn-white" href="#nabidky">Co nabízím</a>
				<?php endif; ?>
			</div>
		</div>

		<?php if ( $ivb_photos ) : ?>
			<div class="collage">
				<div class="sun-glow" aria-hidden="true"></div>
				<svg class="sun-big" viewBox="0 0 200 200" aria-hidden="true" focusable="false">
					<g class="rot" fill="none" stroke="currentColor">
						<circle cx="100" cy="100" r="76" stroke-width=".35"/>
						<circle cx="100" cy="100" r="78.5" stroke-width=".25"/>
						<circle cx="100" cy="100" r="88" stroke-width="16" stroke-dasharray=".5 45.58"/>
						<circle cx="100" cy="100" r="84" stroke-width="8" stroke-dasharray=".5 43.48" stroke-dashoffset="-21.99"/>
						<circle cx="100" cy="100" r="98.5" stroke-width="1.6" stroke-linecap="round" stroke-dasharray="0 6.877"/>
					</g>
				</svg>
				<div class="pebw peb-big">
					<div class="peb" data-ivb-rotate>
						<?php
						foreach ( $ivb_photos as $ivb_i => $ivb_id ) {
							echo wp_get_attachment_image( // phpcs:ignore WordPress.Security.EscapeOutput -- HTML z WordPressu
								$ivb_id,
								'large',
								false,
								array(
									'class'         => $ivb_i ? '' : 'on',
									'sizes'         => '(max-width: 980px) 78vw, 460px',
									'loading'       => $ivb_i ? 'lazy' : 'eager',
									'fetchpriority' => $ivb_i ? 'auto' : 'high',
								)
							);
						}
						?>
					</div>
				</div>
				<?php if ( $ivb_small ) : ?>
					<?php echo ivb_pebble( $ivb_small, 'medium', 'peb-small', array( 'sizes' => '200px', 'loading' => 'lazy' ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- HTML z WordPressu ?>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
</section>

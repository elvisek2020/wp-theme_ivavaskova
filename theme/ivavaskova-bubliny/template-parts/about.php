<?php
/**
 * Titulka: O mně – fotka v oblázku (náhledový obrázek stránky O mně) a bublina s ocáskem
 * (úryvek, odkaz). Bez náhledového obrázku mluví bublina ze zlatého slunce s avatarem
 * (Přizpůsobit → Patička → Fotka do rozhovoru) a podpisem.
 */

defined( 'ABSPATH' ) || exit;

$ivb_about = ivb_about_page();
if ( ! $ivb_about ) {
	return;
}
$ivb_img = ivb_thumb_pebble( $ivb_about, '', '(max-width: 980px) 92vw, 680px' );
$ivb_ava = 0;
if ( ! $ivb_img && function_exists( 'ekt_opt' ) ) {
	$ivb_ava = (int) ekt_opt( 'site_avatar' );
}
$ivb_ava_img = $ivb_ava ? wp_get_attachment_image( $ivb_ava, 'medium', false, array( 'alt' => '', 'sizes' => '200px', 'loading' => 'lazy' ) ) : '';
?>
<section class="sec about iso" id="o-mne">
	<div class="wrap about-in<?php echo $ivb_img ? '' : ' about-in--sun'; ?>">
		<?php if ( $ivb_img ) : ?>
			<?php echo $ivb_img; // phpcs:ignore WordPress.Security.EscapeOutput -- HTML z WordPressu ?>
		<?php else : ?>
			<div class="about-medal" aria-hidden="true">
				<div class="about-medal__sun">
					<?php echo ivb_sun( 'about-medal__rays' ); // phpcs:ignore WordPress.Security.EscapeOutput -- statické SVG ?>
					<?php if ( $ivb_ava_img ) : ?>
						<span class="about-medal__ava"><?php echo $ivb_ava_img; // phpcs:ignore WordPress.Security.EscapeOutput -- HTML z WordPressu ?></span>
					<?php endif; ?>
				</div>
				<span class="hand"><?php bloginfo( 'name' ); ?></span>
			</div>
		<?php endif; ?>
		<div class="bub tail t-left">
			<h2><?php echo esc_html( get_the_title( $ivb_about ) ); ?></h2>
			<p><?php echo esc_html( ivb_excerpt( $ivb_about, 45 ) ); ?></p>
			<a class="more" href="<?php echo esc_url( get_permalink( $ivb_about ) ); ?>">Více o mně</a>
		</div>
	</div>
</section>

<?php
/**
 * Titulka: O mně – fotka v oblázku a bublina s ocáskem (úryvek stránky O mně, odkaz).
 */

defined( 'ABSPATH' ) || exit;

$ivb_about = ivb_about_page();
if ( ! $ivb_about ) {
	return;
}
$ivb_img = ivb_thumb_pebble( $ivb_about, '', '(max-width: 980px) 92vw, 680px' );
?>
<section class="sec about iso" id="o-mne">
	<div class="wrap about-in<?php echo $ivb_img ? '' : ' about-in--solo'; ?>">
		<?php echo $ivb_img; // phpcs:ignore WordPress.Security.EscapeOutput -- HTML z WordPressu ?>
		<div class="bub tail t-left">
			<h2><?php echo esc_html( get_the_title( $ivb_about ) ); ?></h2>
			<p><?php echo esc_html( ivb_excerpt( $ivb_about, 45 ) ); ?></p>
			<a class="more" href="<?php echo esc_url( get_permalink( $ivb_about ) ); ?>">Více o mně</a>
		</div>
	</div>
</section>

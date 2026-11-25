<?php
/**
 * Template Name: Kontakt a rezervace
 *
 * Stránka rezervace: nadpis, panel s rozhovorem a kontaktem, pod ním recenze. Úryvek stránky je
 * text pod nadpisem panelu.
 * Obsah se shortcodem [ekc_rezervace] (např. s parametrem sluzba) jde celý do rozhovoru místo
 * formuláře modulu. Bez shortcodu se obsah (úvodní text, kontakt) vypíše nad panelem a v rozhovoru
 * je formulář modulu rezervací. Doslovný [ekc_rezervace] bez aktivního pluginu se nevypíše.
 * Tlačítka „Rezervovat“ na webu vedou na první stránku s touto šablonou.
 */

defined( 'ABSPATH' ) || exit;

get_header();

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- proměnné šablony
while ( have_posts() ) :
	the_post();
	$ivb_locked = post_password_required();
	// has_shortcode() vrací false i pro neregistrovaný shortcode (plugin neaktivní).
	$ivb_sc      = ! $ivb_locked && has_shortcode( (string) get_post_field( 'post_content', get_the_ID() ), 'ekc_rezervace' );
	$ivb_content = (string) get_the_content();
	if ( ! $ivb_sc ) {
		$ivb_content = (string) preg_replace( '/\[\/?ekc_rezervace[^\]]*\]/', '', $ivb_content );
	}
	$ivb_content = (string) apply_filters( 'the_content', $ivb_content );
	?>
	<section class="page-head">
		<div class="wrap">
			<h1><?php the_title(); ?></h1>
		</div>
	</section>
	<?php if ( ! $ivb_sc && '' !== trim( $ivb_content ) ) : ?>
		<section class="sec sec--tight">
			<div class="wrap--read entry__content" data-ekt-content>
				<?php echo $ivb_content; // phpcs:ignore WordPress.Security.EscapeOutput -- obsah stránky ?>
			</div>
		</section>
	<?php endif; ?>
	<?php
	get_template_part(
		'template-parts/reservation',
		null,
		array(
			'id'      => 'rezervace',
			'heading' => '',
			'lead'    => ( has_excerpt() && ! $ivb_locked ) ? get_the_excerpt() : '',
			'content' => $ivb_sc ? $ivb_content : '',
		)
	);
	get_template_part( 'template-parts/reviews', null, array( 'light' => true ) );
endwhile;
// phpcs:enable

get_footer();

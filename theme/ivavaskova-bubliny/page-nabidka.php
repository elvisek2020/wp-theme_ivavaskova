<?php
/**
 * Template Name: Nabídka
 *
 * Stránka jedné nabídky (lekce): fotka v oblázku a bublina s nadpisem, štítky a úryvkem; obsah
 * v blocích (vzor „Jak lekce probíhá“ = dráha slunce s fázemi a tlačítkem Objednat se); recenze
 * s kontextem = název stránky. Stránky s touto šablonou tvoří karty „Co nabízím“ na titulce.
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	get_template_part( 'template-parts/page-top' );
	?>
	<article <?php post_class( 'entry' ); ?>>
		<div class="wrap--read entry__content" data-ekt-content>
			<?php the_content(); ?>
		</div>
	</article>
	<?php
	get_template_part(
		'template-parts/reviews',
		null,
		array(
			'context' => ivb_raw_title( get_the_ID() ), // uložený název = kontext recenze (bez wptexturize)
			'title'   => 'Zkušenosti z lekcí',
		)
	);
endwhile;

get_footer();

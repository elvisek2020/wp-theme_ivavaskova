<?php
/**
 * Stránka: úvod v bublině (s fotkou v oblázku), obsah v blocích, podstránky jako karty
 * (např. Co nabízím → nabídky), komentáře jen když jsou zapnuté.
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	get_template_part( 'template-parts/page-top' );
	if ( '' !== trim( (string) get_the_content() ) ) :
		?>
		<article <?php post_class( 'entry' ); ?>>
			<div class="wrap--read entry__content" data-ekt-content>
				<?php
				the_content();
				wp_link_pages();
				?>
			</div>
		</article>
		<?php
	endif;

	get_template_part( 'template-parts/offers', null, array( 'pages' => ivb_child_pages( get_the_ID() ) ) );

	if ( comments_open() || get_comments_number() ) :
		?>
		<div class="wrap--read" style="padding-bottom:96px"><?php comments_template(); ?></div>
		<?php
	endif;
endwhile;

get_footer();

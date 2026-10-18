<?php
/**
 * Stránka (O mně, Kontakt, Tvořím…): nadpis, obrázek, obsah.
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<article <?php post_class( 'iv-article iv-page' ); ?>>
		<header class="iv-article-head iv-wrap iv-narrow">
			<h1 class="iv-article-title"><?php the_title(); ?></h1>
		</header>
		<?php if ( has_post_thumbnail() ) : ?>
			<figure class="iv-article-hero iv-wrap">
				<?php the_post_thumbnail( 'iv-wide', array( 'alt' => '', 'sizes' => '(max-width: 1000px) 100vw, 960px', 'loading' => 'eager' ) ); ?>
			</figure>
		<?php endif; ?>
		<div class="iv-content iv-wrap iv-narrow">
			<?php the_content(); ?>
		</div>
	</article>
	<?php
	if ( comments_open() || get_comments_number() ) {
		comments_template();
	}
endwhile;

get_footer();

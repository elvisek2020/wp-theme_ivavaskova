<?php
/**
 * Článek: rubrika a datum, nadpis, náhledový obrázek, obsah, štítky, sousední články, komentáře.
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<article <?php post_class( 'iv-article' ); ?>>
		<header class="iv-article-head iv-wrap iv-narrow">
			<?php iv_post_meta(); ?>
			<h1 class="iv-article-title"><?php the_title(); ?></h1>
		</header>

		<?php if ( has_post_thumbnail() ) : ?>
			<figure class="iv-article-hero iv-wrap">
				<?php the_post_thumbnail( 'iv-wide', array( 'alt' => '', 'sizes' => '(max-width: 1000px) 100vw, 960px', 'loading' => 'eager', 'fetchpriority' => 'high' ) ); ?>
			</figure>
		<?php endif; ?>

		<div class="iv-content iv-wrap iv-narrow">
			<?php
			the_content();
			wp_link_pages( array( 'before' => '<nav class="iv-page-links">Strany: ', 'after' => '</nav>' ) );
			?>
		</div>

		<footer class="iv-article-foot iv-wrap iv-narrow">
			<?php the_tags( '<div class="iv-tags">', '', '</div>' ); ?>
			<?php
			$iv_prev = get_previous_post();
			$iv_next = get_next_post();
			if ( $iv_prev || $iv_next ) :
				?>
				<nav class="iv-post-nav" aria-label="Další články">
					<?php if ( $iv_prev ) : ?>
						<a class="iv-post-nav-prev" href="<?php echo esc_url( get_permalink( $iv_prev ) ); ?>"><span><?php echo iv_icon( 'arrow-l', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?> Starší</span><?php echo esc_html( get_the_title( $iv_prev ) ); ?></a>
					<?php endif; ?>
					<?php if ( $iv_next ) : ?>
						<a class="iv-post-nav-next" href="<?php echo esc_url( get_permalink( $iv_next ) ); ?>"><span>Novější <?php echo iv_icon( 'arrow', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span><?php echo esc_html( get_the_title( $iv_next ) ); ?></a>
					<?php endif; ?>
				</nav>
			<?php endif; ?>
		</footer>
	</article>

	<?php
	if ( comments_open() || get_comments_number() ) {
		comments_template();
	}
endwhile;

get_footer();

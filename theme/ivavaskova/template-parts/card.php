<?php
/**
 * Karta článku ve výpisu (titulka, rubrika, hledání).
 */

defined( 'ABSPATH' ) || exit;
?>
<article <?php post_class( 'iv-card' ); ?>>
	<a class="iv-card-thumb" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
		<?php
		if ( has_post_thumbnail() ) {
			the_post_thumbnail( 'iv-card', array( 'alt' => '', 'sizes' => '(max-width: 700px) 100vw, (max-width: 1100px) 50vw, 360px' ) );
		} else {
			echo '<span class="iv-card-nothumb">' . iv_icon( 'star', 40 ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		?>
	</a>
	<div class="iv-card-body">
		<?php iv_post_meta(); ?>
		<h2 class="iv-card-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
		<p class="iv-card-excerpt"><?php echo esc_html( wp_strip_all_tags( get_the_excerpt() ) ); ?></p>
		<a class="iv-more" href="<?php the_permalink(); ?>">Číst dál<span class="screen-reader-text">: <?php the_title(); ?></span> <?php echo iv_icon( 'arrow', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
	</div>
</article>

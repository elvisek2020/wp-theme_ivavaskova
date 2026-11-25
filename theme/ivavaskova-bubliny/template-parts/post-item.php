<?php
/**
 * Článek ve výpisu: kulatý náhled, datum, nadpis, úryvek (jen ve výpisu blogu), odkaz.
 *
 * $args: excerpt (bool).
 */

defined( 'ABSPATH' ) || exit;
?>
<li class="post-item">
	<a class="thumb<?php echo has_post_thumbnail() ? '' : ' thumb--empty'; ?>" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
		<?php
		if ( has_post_thumbnail() ) {
			the_post_thumbnail(
				'thumbnail',
				array(
					'alt'   => '',
					'sizes' => '120px',
					'style' => 'object-position:' . ivb_focus(),
				)
			);
		} else {
			echo ivb_sun(); // phpcs:ignore WordPress.Security.EscapeOutput -- statické SVG
		}
		?>
	</a>
	<div>
		<time datetime="<?php echo esc_attr( get_the_date( 'Y-m-d' ) ); ?>"><?php echo esc_html( get_the_date( 'j. n. Y' ) ); ?></time>
		<h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
		<?php if ( ! empty( $args['excerpt'] ) ) : ?>
			<p><?php echo esc_html( wp_strip_all_tags( get_the_excerpt() ) ); ?></p>
		<?php endif; ?>
		<a class="more" href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr( 'Číst článek ' . wp_strip_all_tags( get_the_title() ) ); ?>">Číst článek</a>
	</div>
</li>

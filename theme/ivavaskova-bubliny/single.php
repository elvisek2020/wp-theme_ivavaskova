<?php
/**
 * Článek: datum a rubrika, nadpis, náhledový obrázek v oblázku, obsah (data-ekt-content pro
 * lightbox a videa), předchozí a další článek, komentáře.
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	$ivb_thumb = (int) get_post_thumbnail_id();
	$ivb_meta  = $ivb_thumb ? wp_get_attachment_metadata( $ivb_thumb ) : array();
	$ivb_tall  = is_array( $ivb_meta ) && ! empty( $ivb_meta['width'] ) && (int) ( $ivb_meta['height'] ?? 0 ) > (int) $ivb_meta['width'];
	?>
	<article id="post-<?php the_ID(); ?>" <?php post_class( 'entry' ); ?> style="padding-top:0">
		<header class="single-top">
			<div class="wrap--read">
				<p class="meta">
					<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date( 'j. n. Y' ) ); ?></time>
					<?php
					$ivb_cat = function_exists( 'ekt_primary_category' ) ? ekt_primary_category() : null;
					if ( $ivb_cat ) {
						echo ' · <a href="' . esc_url( get_category_link( $ivb_cat ) ) . '">' . esc_html( $ivb_cat->name ) . '</a>';
					}
					?>
				</p>
				<h1><?php the_title(); ?></h1>
			</div>
			<?php
			if ( $ivb_thumb ) {
				echo ivb_pebble( // phpcs:ignore WordPress.Security.EscapeOutput -- HTML z WordPressu
					$ivb_thumb,
					'large',
					'single-hero' . ( $ivb_tall ? ' single-hero--portrait' : '' ),
					array(
						'sizes'         => $ivb_tall ? '(max-width: 640px) 92vw, 520px' : '(max-width: 980px) 92vw, 900px',
						'style'         => 'object-position:' . ivb_focus(),
						'fetchpriority' => 'high',
						'loading'       => 'eager',
					)
				);
			}
			?>
		</header>

		<div class="wrap--read" style="margin-top:48px">
			<div class="entry__content" data-ekt-content>
				<?php
				the_content();
				wp_link_pages();
				?>
			</div>

			<?php
			$ivb_prev = get_previous_post();
			$ivb_next = get_next_post();
			if ( $ivb_prev || $ivb_next ) :
				?>
				<nav class="post-nav" aria-label="Další články">
					<?php if ( $ivb_prev ) : ?>
						<a href="<?php echo esc_url( get_permalink( $ivb_prev ) ); ?>" rel="prev"><small>← Starší článek</small><?php echo esc_html( get_the_title( $ivb_prev ) ); ?></a>
					<?php else : ?>
						<span></span>
					<?php endif; ?>
					<?php if ( $ivb_next ) : ?>
						<a href="<?php echo esc_url( get_permalink( $ivb_next ) ); ?>" rel="next" style="text-align:right"><small>Novější článek →</small><?php echo esc_html( get_the_title( $ivb_next ) ); ?></a>
					<?php endif; ?>
				</nav>
			<?php endif; ?>

			<?php
			if ( comments_open() || get_comments_number() ) {
				comments_template();
			}
			?>
		</div>
	</article>
	<?php
endwhile;

get_footer();

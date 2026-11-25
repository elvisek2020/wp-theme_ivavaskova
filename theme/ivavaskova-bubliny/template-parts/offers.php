<?php
/**
 * Karty nabídek: fotka v oblázku, pod ní bublina s ocáskem (název, štítky, úryvek, odkaz).
 *
 * $args: pages (WP_Post[]), heading, lead, hand, id, level (h2|h3 nadpis sekce).
 */

defined( 'ABSPATH' ) || exit;

$ivb_pages = array_filter( (array) ( $args['pages'] ?? array() ), static fn( $p ) => $p instanceof WP_Post );
if ( ! $ivb_pages ) {
	return;
}
?>
<section class="sec"<?php echo empty( $args['id'] ) ? '' : ' id="' . esc_attr( $args['id'] ) . '"'; ?>>
	<div class="wrap">
		<?php if ( ! empty( $args['heading'] ) ) : ?>
			<div class="sec-head">
				<div>
					<h2><?php echo esc_html( $args['heading'] ); ?></h2>
					<?php if ( ! empty( $args['lead'] ) ) : ?>
						<p class="lead"><?php echo esc_html( $args['lead'] ); ?></p>
					<?php endif; ?>
				</div>
				<?php if ( ! empty( $args['hand'] ) ) : ?>
					<span class="hand"><?php echo esc_html( $args['hand'] ); ?></span>
				<?php endif; ?>
			</div>
		<?php endif; ?>
		<div class="offers">
			<?php foreach ( $ivb_pages as $ivb_page ) : ?>
				<?php $ivb_img = ivb_thumb_pebble( $ivb_page ); ?>
				<article class="offer<?php echo $ivb_img ? '' : ' offer--noimg'; ?>">
					<?php echo $ivb_img; // phpcs:ignore WordPress.Security.EscapeOutput -- HTML z WordPressu ?>
					<div class="bub tail t-up">
						<h3><a href="<?php echo esc_url( get_permalink( $ivb_page ) ); ?>"><?php echo esc_html( get_the_title( $ivb_page ) ); ?></a></h3>
						<?php echo ivb_chips( $ivb_page ); // phpcs:ignore WordPress.Security.EscapeOutput -- escapováno ve funkci ?>
						<p><?php echo esc_html( ivb_excerpt( $ivb_page, 26 ) ); ?></p>
						<a class="more" href="<?php echo esc_url( get_permalink( $ivb_page ) ); ?>" aria-label="<?php echo esc_attr( 'Více o lekci ' . get_the_title( $ivb_page ) ); ?>">Více o lekci</a>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	</div>
</section>

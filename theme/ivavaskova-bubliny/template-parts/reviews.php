<?php
/**
 * Rotující recenze jako balíček bublin (modul testimonials pluginu). Bez recenzí se nevypíše nic.
 * Všechny recenze jsou v HTML a střídá je JS (náhodné pořadí by v cache hostingu zamrzlo).
 *
 * $args: context (nabídka, prázdné = všechny), title, light (bool – bez khaki pozadí).
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'ekc_testimonials' ) ) {
	return;
}
$ivb_context = (string) ( $args['context'] ?? '' );
$ivb_items   = ekc_testimonials(
	array(
		'context' => $ivb_context,
		'limit'   => 10,
	)
);
if ( ! $ivb_items ) {
	return;
}
$ivb_title = (string) ( $args['title'] ?? 'Zkušenosti z lekcí' );
$ivb_many  = count( $ivb_items ) > 1;
$ivb_avatar = '<svg viewBox="0 0 56 56" aria-hidden="true" focusable="false"><circle cx="28" cy="28" r="27" fill="#fff" stroke="#C9A54A"/><circle cx="28" cy="22" r="9" fill="#C8CCA0"/><path d="M11 46c3-9 9-13 17-13s14 4 17 13" fill="#C8CCA0"/></svg>';
?>
<section class="reviews iso<?php echo empty( $args['light'] ) ? '' : ' reviews--light'; ?>" aria-labelledby="ivb-rev-h">
	<div class="wrap">
		<div class="rev-head">
			<h2 id="ivb-rev-h"><?php echo esc_html( $ivb_title ); ?></h2>
		</div>
		<div class="deck-wrap">
			<div class="deck<?php echo $ivb_many ? '' : ' deck--single'; ?>" data-ivb-deck>
				<?php foreach ( $ivb_items as $ivb_i => $ivb_item ) : ?>
					<figure class="rev-card tail t-down" data-pos="<?php echo (int) $ivb_i; ?>"<?php echo $ivb_i ? ' aria-hidden="true"' : ''; ?>>
						<span class="q" aria-hidden="true">„</span>
						<blockquote><?php echo $ivb_item['text']; // phpcs:ignore WordPress.Security.EscapeOutput -- bezpečné HTML z modulu (jen <br>) ?></blockquote>
						<figcaption>
							<?php echo $ivb_avatar; // phpcs:ignore WordPress.Security.EscapeOutput -- statické SVG ?>
							<span>
								<span class="who-name"><?php echo esc_html( $ivb_item['name'] ); ?></span>
								<?php if ( '' === $ivb_context && '' !== (string) $ivb_item['context'] ) : ?>
									<span class="who-ctx"><?php echo esc_html( $ivb_item['context'] ); ?></span>
								<?php endif; ?>
							</span>
						</figcaption>
					</figure>
				<?php endforeach; ?>
			</div>
			<?php if ( $ivb_many ) : ?>
				<div class="rev-ctrl">
					<button type="button" data-ivb-deck-prev aria-label="Předchozí recenze"><?php echo ivb_icon( 'prev' ); // phpcs:ignore WordPress.Security.EscapeOutput -- statické SVG ?></button>
					<button type="button" data-ivb-deck-pause aria-label="Pozastavit střídání recenzí" aria-pressed="false"><?php echo ivb_icon( 'pause' ); // phpcs:ignore WordPress.Security.EscapeOutput -- statické SVG ?></button>
					<button type="button" data-ivb-deck-next aria-label="Další recenze"><?php echo ivb_icon( 'next' ); // phpcs:ignore WordPress.Security.EscapeOutput -- statické SVG ?></button>
				</div>
			<?php endif; ?>
		</div>
	</div>
</section>

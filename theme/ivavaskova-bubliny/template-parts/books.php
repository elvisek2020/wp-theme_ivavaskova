<?php
/**
 * Noční svět dětských knih: tmavá plocha se zlatými hvězdami, ilustrace v obláčku (náhledový
 * obrázek stránky se šablonou „Svět knih“) a knihy v bublinách (podstránky té stránky).
 * Na titulce s „portálem“: noc se při posunu stránky rozevře z malé bubliny.
 *
 * $args: page (WP_Post), portal (bool, titulka), single (bool, samotná stránka – nadpis h1 a obsah).
 */

defined( 'ABSPATH' ) || exit;

$ivb_page = $args['page'] ?? null;
if ( ! $ivb_page instanceof WP_Post ) {
	return;
}
$ivb_portal = ! empty( $args['portal'] );
$ivb_single = ! empty( $args['single'] );
$ivb_locked = post_password_required( $ivb_page ); // stránka s heslem: místo úryvku a obsahu formulář hesla
$ivb_books  = ivb_child_pages( $ivb_page->ID );
$ivb_cloud  = (int) get_post_thumbnail_id( $ivb_page );
$ivb_title  = get_the_title( $ivb_page );
$ivb_lead   = $ivb_single ? ( ( has_excerpt( $ivb_page ) && ! $ivb_locked ) ? get_the_excerpt( $ivb_page ) : '' ) : ivb_excerpt( $ivb_page, 30 );
$ivb_sub    = implode( ' · ', array_map( 'get_the_title', array_slice( $ivb_books, 0, 3 ) ) );

$ivb_stage_stars = array(
	array( 'left:calc(50% - 22px);top:calc(50% - 20px);width:12px;height:12px', false ),
	array( 'left:calc(50% + 10px);top:calc(50% - 4px);width:8px;height:8px', false ),
	array( 'left:calc(50% - 8px);top:calc(50% + 14px);width:5px;height:5px', true ),
	array( 'left:calc(50% + 20px);top:calc(50% - 26px);width:4px;height:4px', true ),
	array( 'left:12%;top:18%;width:18px;height:18px', false ),
	array( 'left:82%;top:14%;width:12px;height:12px', false ),
	array( 'left:70%;top:74%;width:22px;height:22px', false ),
	array( 'left:24%;top:70%;width:9px;height:9px', false ),
	array( 'left:36%;top:22%;width:5px;height:5px', true ),
	array( 'left:62%;top:30%;width:4px;height:4px', true ),
	array( 'left:90%;top:52%;width:6px;height:6px', true ),
	array( 'left:8%;top:48%;width:5px;height:5px', true ),
	array( 'left:46%;top:86%;width:4px;height:4px', true ),
);
$ivb_sky = array(
	array( 'left:6%;top:9%;width:20px;height:20px', false ),
	array( 'left:92%;top:7%;width:14px;height:14px', false ),
	array( 'left:48%;top:5%;width:6px;height:6px', true ),
	array( 'left:30%;top:14%;width:4px;height:4px', true ),
	array( 'left:74%;top:18%;width:10px;height:10px', false ),
	array( 'left:3%;top:56%;width:6px;height:6px', true ),
	array( 'left:96%;top:44%;width:24px;height:24px', false ),
	array( 'left:52%;top:52%;width:5px;height:5px', true ),
	array( 'left:18%;top:90%;width:16px;height:16px', false ),
	array( 'left:62%;top:93%;width:5px;height:5px', true ),
	array( 'left:86%;top:88%;width:9px;height:9px', false ),
	array( 'left:40%;top:78%;width:4px;height:4px', true ),
);
?>
<?php if ( $ivb_portal ) : ?>
	<div class="books-entry" aria-hidden="true">
		<span class="hand">pojď dál – do světa knih</span>
		<div class="mini-wrap"><div class="mini-night">
			<?php
			echo ivb_star( 'left:18px;top:20px;width:12px;height:12px' ) . ivb_star( 'left:46px;top:34px;width:8px;height:8px' ) // phpcs:ignore WordPress.Security.EscapeOutput -- statické SVG
				. ivb_star( 'left:30px;top:52px;width:5px;height:5px', true ) . ivb_star( 'left:58px;top:16px;width:4px;height:4px', true );
			?>
		</div></div>
		<div class="dots"><i></i><i></i><i></i></div>
	</div>
	<div class="portal" data-ivb-portal="in" aria-hidden="true">
		<div class="stage">
			<span class="hand stage-cap">pojď dál – do světa knih</span>
			<div class="nightlayer">
				<div class="stars">
					<?php
					foreach ( $ivb_stage_stars as $ivb_s ) {
						echo ivb_star( $ivb_s[0], $ivb_s[1] ); // phpcs:ignore WordPress.Security.EscapeOutput -- statické SVG
					}
					?>
				</div>
				<div class="stage-title">
					<h2><?php echo esc_html( $ivb_title ); ?></h2>
					<?php if ( $ivb_sub ) : ?>
						<p><?php echo esc_html( $ivb_sub ); ?></p>
					<?php endif; ?>
				</div>
			</div>
			<div class="ring"></div>
		</div>
	</div>
<?php endif; ?>

<section class="books iso<?php echo $ivb_single ? ' books--page' : ''; ?>" id="knihy">
	<div class="books-stars" aria-hidden="true">
		<?php
		foreach ( $ivb_sky as $ivb_s ) {
			echo ivb_star( $ivb_s[0], $ivb_s[1] ); // phpcs:ignore WordPress.Security.EscapeOutput -- statické SVG
		}
		?>
	</div>
	<div class="wrap">
		<div class="sec-head">
			<div>
				<?php if ( $ivb_single ) : ?>
					<h1><?php echo esc_html( $ivb_title ); ?></h1>
				<?php else : ?>
					<h2><?php echo esc_html( $ivb_title ); ?></h2>
				<?php endif; ?>
				<?php if ( $ivb_lead ) : ?>
					<p class="lead"><?php echo esc_html( $ivb_lead ); ?></p>
				<?php endif; ?>
			</div>
			<span class="hand">jiný svět, stejné srdce</span>
		</div>
		<?php if ( $ivb_cloud || $ivb_books ) : ?>
			<div class="books-grid<?php echo ( $ivb_cloud && $ivb_books ) ? '' : ' books-grid--solo'; ?>">
				<?php if ( $ivb_cloud ) : ?>
					<div class="dream">
						<div class="dream-cloud">
							<?php echo wp_get_attachment_image( $ivb_cloud, 'large', false, array( 'sizes' => '(max-width: 980px) 92vw, 640px' ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- HTML z WordPressu ?>
						</div>
						<div class="thought" aria-hidden="true"><i></i><i></i><i></i></div>
					</div>
				<?php endif; ?>
				<?php if ( $ivb_books ) : ?>
					<div class="book-list">
						<?php foreach ( $ivb_books as $ivb_book ) : ?>
							<?php $ivb_cover = (int) get_post_thumbnail_id( $ivb_book ); ?>
							<article class="bub book<?php echo $ivb_cover ? '' : ' book--noimg'; ?>">
								<?php
								if ( $ivb_cover ) {
									echo wp_get_attachment_image( $ivb_cover, 'medium_large', false, array( 'sizes' => '(max-width: 640px) 230px, 210px' ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- HTML z WordPressu
								}
								?>
								<div>
									<h3><a href="<?php echo esc_url( get_permalink( $ivb_book ) ); ?>"><?php echo esc_html( get_the_title( $ivb_book ) ); ?></a></h3>
									<?php echo ivb_chips( $ivb_book ); // phpcs:ignore WordPress.Security.EscapeOutput -- escapováno ve funkci ?>
									<p><?php echo esc_html( ivb_excerpt( $ivb_book, 24 ) ); ?></p>
									<a class="more" href="<?php echo esc_url( get_permalink( $ivb_book ) ); ?>" aria-label="<?php echo esc_attr( 'Více o knize ' . get_the_title( $ivb_book ) ); ?>">Více o knize</a>
								</div>
							</article>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		<?php endif; ?>
		<?php if ( ! $ivb_single && ! $ivb_books ) : ?>
			<p style="margin-top:40px"><a class="more" href="<?php echo esc_url( get_permalink( $ivb_page ) ); ?>">Více o knihách</a></p>
		<?php endif; ?>
		<?php if ( $ivb_single && '' !== trim( (string) $ivb_page->post_content ) ) : ?>
			<div class="books-content wrap--read entry__content" data-ekt-content>
				<?php
				// Obsah stránky (bloky) v noční atmosféře; chráněná stránka jen formulář hesla.
				echo $ivb_locked // phpcs:ignore WordPress.Security.EscapeOutput -- formulář z WordPressu / obsah stránky
					? get_the_password_form( $ivb_page )
					: apply_filters( 'the_content', $ivb_page->post_content );
				?>
			</div>
		<?php endif; ?>
	</div>
</section>

<?php if ( $ivb_portal ) : ?>
	<div class="portal" data-ivb-portal="out" aria-hidden="true">
		<div class="stage">
			<div class="nightlayer">
				<div class="stars">
					<?php
					echo ivb_star( 'left:calc(50% - 18px);top:calc(50% - 16px);width:11px;height:11px' ) . ivb_star( 'left:calc(50% + 12px);top:calc(50% + 6px);width:6px;height:6px', true ) // phpcs:ignore WordPress.Security.EscapeOutput -- statické SVG
						. ivb_star( 'left:16%;top:24%;width:14px;height:14px' ) . ivb_star( 'left:78%;top:20%;width:20px;height:20px' )
						. ivb_star( 'left:30%;top:76%;width:6px;height:6px', true ) . ivb_star( 'left:66%;top:70%;width:10px;height:10px' );
					?>
				</div>
			</div>
			<div class="ring"></div>
		</div>
	</div>
	<div class="books-exit" aria-hidden="true">
		<div class="mini-wrap"><div class="mini-night">
			<?php echo ivb_star( 'left:22px;top:24px;width:11px;height:11px' ) . ivb_star( 'left:50px;top:44px;width:5px;height:5px', true ); // phpcs:ignore WordPress.Security.EscapeOutput -- statické SVG ?>
		</div></div>
		<div class="dots"><i></i><i></i><i></i></div>
	</div>
<?php endif; ?>

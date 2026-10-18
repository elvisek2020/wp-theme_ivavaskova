<?php
/**
 * Stránka 404: krátká zpráva, hledání a cesta zpět.
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<div class="iv-wrap iv-narrow iv-404">
	<p class="iv-404-stars" aria-hidden="true"><?php echo iv_icon( 'star', 28 ) . iv_icon( 'star', 40 ) . iv_icon( 'star', 28 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></p>
	<h1 class="iv-page-title">Stránka nenalezena</h1>
	<p>Tahle stránka tu není — možná se přestěhovala, nebo je v adrese překlep.</p>
	<?php get_search_form(); ?>
	<p><a class="iv-btn" href="<?php echo esc_url( home_url( '/' ) ); ?>">Zpět na úvod</a></p>
</div>
<?php
$iv_recent = new WP_Query( array( 'posts_per_page' => 3, 'ignore_sticky_posts' => true, 'no_found_rows' => true ) );
if ( $iv_recent->have_posts() ) :
	?>
	<div class="iv-wrap">
		<h2 class="iv-section-label">Nejnovější články</h2>
		<div class="iv-grid">
			<?php
			while ( $iv_recent->have_posts() ) :
				$iv_recent->the_post();
				get_template_part( 'template-parts/card' );
			endwhile;
			wp_reset_postdata();
			?>
		</div>
	</div>
	<?php
endif;
get_footer();

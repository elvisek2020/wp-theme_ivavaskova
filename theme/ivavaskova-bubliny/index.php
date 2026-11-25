<?php
/**
 * Výpis článků: stránka Blog, rubriky, štítky, archivy podle data, hledání. Kulaté náhledy
 * ve dvou sloupcích, stránkování.
 */

defined( 'ABSPATH' ) || exit;

get_header();

if ( is_search() ) {
	$ivb_title = 'Hledání: „' . get_search_query() . '“';
	$ivb_lead  = '';
} elseif ( is_home() ) {
	// Stránka příspěvků platí jen se statickou úvodní stránkou (volba může zůstat i v režimu „Poslední příspěvky“).
	$ivb_page  = 'page' === get_option( 'show_on_front' ) ? (int) get_option( 'page_for_posts' ) : 0;
	$ivb_title = $ivb_page ? get_the_title( $ivb_page ) : 'Blog';
	$ivb_lead  = $ivb_page && has_excerpt( $ivb_page ) ? get_the_excerpt( $ivb_page ) : '';
} else {
	$ivb_title = wp_strip_all_tags( (string) get_the_archive_title() );
	$ivb_lead  = wp_strip_all_tags( (string) get_the_archive_description() );
}
?>
<section class="sec" style="padding-top:72px">
	<div class="wrap">
		<div class="sec-head search-head">
			<div>
				<h1><?php echo esc_html( $ivb_title ); ?></h1>
				<?php if ( $ivb_lead ) : ?>
					<p class="lead"><?php echo esc_html( $ivb_lead ); ?></p>
				<?php endif; ?>
				<?php if ( is_search() ) : ?>
					<?php get_search_form(); ?>
				<?php endif; ?>
			</div>
			<span class="hand"><?php echo is_search() ? 'co se našlo' : 'zápisky'; ?></span>
		</div>

		<?php if ( have_posts() ) : ?>
			<ul class="posts posts--list">
				<?php
				while ( have_posts() ) :
					the_post();
					get_template_part( 'template-parts/post-item', null, array( 'excerpt' => true ) );
				endwhile;
				?>
			</ul>
			<?php
			the_posts_pagination(
				array(
					'mid_size'           => 1,
					'prev_text'          => '←<span class="screen-reader-text"> Předchozí</span>',
					'next_text'          => '<span class="screen-reader-text">Další </span>→',
					'screen_reader_text' => 'Stránkování',
				)
			);
			?>
		<?php else : ?>
			<p class="empty"><?php echo is_search() ? 'Nic se nenašlo. Zkuste jiné slovo.' : 'Zatím tu nic není.'; ?></p>
		<?php endif; ?>
	</div>
</section>
<?php
get_footer();

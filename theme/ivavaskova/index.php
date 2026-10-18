<?php
/**
 * Výpis článků (titulka, rubriky, štítky, archiv, hledání) — mřížka karet.
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<div class="iv-wrap">
	<?php if ( ! is_home() || is_paged() ) : ?>
		<header class="iv-page-head">
			<?php if ( is_search() ) : ?>
				<p class="iv-kicker">Hledání</p>
				<h1 class="iv-page-title">„<?php echo esc_html( get_search_query() ); ?>“</h1>
			<?php elseif ( is_archive() ) : ?>
				<p class="iv-kicker"><?php echo is_category() ? 'Rubrika' : ( is_tag() ? 'Štítek' : 'Archiv' ); ?></p>
				<h1 class="iv-page-title"><?php echo esc_html( wp_strip_all_tags( get_the_archive_title() ) ); ?></h1>
				<?php the_archive_description( '<div class="iv-page-desc">', '</div>' ); ?>
			<?php else : ?>
				<h1 class="iv-page-title">Články</h1>
			<?php endif; ?>
			<?php if ( is_paged() ) : ?>
				<p class="iv-kicker">Strana <?php echo (int) get_query_var( 'paged' ); ?></p>
			<?php endif; ?>
		</header>
	<?php endif; ?>

	<?php if ( have_posts() ) : ?>
		<div class="iv-grid">
			<?php
			while ( have_posts() ) :
				the_post();
				get_template_part( 'template-parts/card' );
			endwhile;
			?>
		</div>
		<?php iv_pagination(); ?>
	<?php else : ?>
		<div class="iv-empty">
			<p><?php echo is_search() ? 'Nic nenalezeno. Zkuste jiné slovo.' : 'Zatím tu nic není.'; ?></p>
			<?php get_search_form(); ?>
		</div>
	<?php endif; ?>
</div>
<?php
get_footer();

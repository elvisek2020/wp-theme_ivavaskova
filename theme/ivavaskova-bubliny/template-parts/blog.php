<?php
/**
 * Titulka: tři poslední články a odkaz na archiv.
 */

defined( 'ABSPATH' ) || exit;

$ivb_q = new WP_Query(
	array(
		'post_type'           => 'post',
		'posts_per_page'      => 3,
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	)
);
if ( ! $ivb_q->have_posts() ) {
	return;
}
$ivb_blog = ivb_blog_url();
?>
<section class="sec" id="blog">
	<div class="wrap">
		<div class="sec-head">
			<h2>Blog</h2>
			<span class="hand">poslední zápisky</span>
		</div>
		<ul class="posts">
			<?php
			while ( $ivb_q->have_posts() ) :
				$ivb_q->the_post();
				get_template_part( 'template-parts/post-item' );
			endwhile;
			wp_reset_postdata();
			?>
		</ul>
		<?php if ( $ivb_blog ) : ?>
			<div class="blog-foot"><a class="btn btn-white" href="<?php echo esc_url( $ivb_blog ); ?>">Archiv článků</a></div>
		<?php endif; ?>
	</div>
</section>

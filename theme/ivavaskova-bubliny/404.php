<?php
/**
 * Stránka nenalezena: bublina s hledáním, nabídky a poslední články.
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<section class="notfound">
	<div class="wrap">
		<div class="bub tail t-down">
			<h1>Tady nic není</h1>
			<p>Stránka se přestěhovala nebo nikdy neexistovala. Zkuste hledání, nebo pokračujte na titulku.</p>
			<?php get_search_form(); ?>
			<p style="margin-top:18px"><a class="more" href="<?php echo esc_url( home_url( '/' ) ); ?>">Na titulní stránku</a></p>
		</div>
	</div>
</section>
<?php
get_template_part(
	'template-parts/offers',
	null,
	array(
		'pages'   => ivb_offer_pages(),
		'heading' => 'Co nabízím',
	)
);
get_template_part( 'template-parts/blog' );
get_footer();

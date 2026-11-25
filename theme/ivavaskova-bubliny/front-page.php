<?php
/**
 * Titulka: úvod s rotujícími fotkami, nabídky, obsah stránky Domů, O mně, vstup do nočního světa
 * knih, poslední články, rezervace a recenze. Sekce bez obsahu se nevypíšou.
 */

defined( 'ABSPATH' ) || exit;

get_header();

get_template_part( 'template-parts/hero' );

get_template_part(
	'template-parts/offers',
	null,
	array(
		'pages'   => ivb_offer_pages(),
		'heading' => 'Co nabízím',
		'lead'    => 'Tři prožitkové lekce. Skupinové i individuální – Energetický obraz na individuální domluvu.',
		'hand'    => 'tři cesty k sobě',
		'id'      => 'nabidky',
	)
);

if ( is_page() && '' !== trim( (string) get_post_field( 'post_content', get_queried_object_id() ) ) ) :
	?>
	<section class="sec sec--tight">
		<div class="wrap--read entry__content" data-ekt-content>
			<?php
			while ( have_posts() ) :
				the_post();
				the_content();
			endwhile;
			?>
		</div>
	</section>
	<?php
endif;

get_template_part( 'template-parts/about' );

$ivb_books = ivb_books_page();
if ( $ivb_books ) {
	get_template_part(
		'template-parts/books',
		null,
		array(
			'page'   => $ivb_books,
			'portal' => true,
		)
	);
}

get_template_part( 'template-parts/blog' );

get_template_part( 'template-parts/reservation', null, array( 'id' => 'rezervace' ) );

get_template_part( 'template-parts/reviews' );

get_footer();

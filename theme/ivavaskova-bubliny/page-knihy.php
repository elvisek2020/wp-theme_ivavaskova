<?php
/**
 * Template Name: Svět knih
 *
 * Stránka „Moje dětské knihy“ v noční atmosféře: úryvek jako úvod, náhledový obrázek v obláčku
 * (ilustrace), podstránky = knihy v bublinách, obsah stránky pod nimi. Titulka bere sekci knih
 * z první stránky s touto šablonou.
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	get_template_part(
		'template-parts/books',
		null,
		array(
			'page'   => get_post(),
			'single' => true,
		)
	);
endwhile;

get_footer();

<?php
/**
 * Hlavička: logo s ilustrací + název, pruh menu „hvězdná noc“, přepínač režimu.
 */

defined( 'ABSPATH' ) || exit;
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link screen-reader-text" href="#obsah">Přeskočit na obsah</a>

<header class="iv-header">
	<div class="iv-wrap iv-header-top">
		<?php iv_branding(); ?>
	</div>
	<div class="iv-navband">
		<div class="iv-wrap iv-navbar">
			<button type="button" class="iv-menu-toggle" aria-expanded="false" aria-controls="iv-nav">
				<?php echo iv_icon( 'menu', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<span>Menu</span>
			</button>
			<nav class="iv-nav" id="iv-nav" aria-label="Hlavní menu">
				<?php
				wp_nav_menu( array(
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'iv-menu',
					'fallback_cb'    => function () {
						echo '<ul class="iv-menu">';
						wp_list_pages( array( 'title_li' => '', 'depth' => 1 ) );
						echo '</ul>';
					},
				) );
				?>
			</nav>
			<div class="iv-tools">
				<a class="iv-tool" href="<?php echo esc_url( home_url( '/?s=' ) ); ?>" data-iv-search aria-label="Hledat"><?php echo iv_icon( 'search', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
			</div>
		</div>
		<div class="iv-wrap iv-searchbar" hidden><?php get_search_form(); ?></div>
	</div>
</header>

<main id="obsah" class="iv-main">

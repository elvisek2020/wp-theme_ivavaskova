<?php
/**
 * Hlavička: <head>, pruh s oznámením, logo, hlavní menu (na mobilu vysouvací bublina), začátek <main>.
 */

defined( 'ABSPATH' ) || exit;

$ivb_strip = ivb_strip();
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="theme-color" content="#C8CCA0">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#main">Přeskočit na obsah</a>

<?php if ( '' !== $ivb_strip['text'] ) : ?>
	<div class="strip">
		<?php if ( $ivb_strip['url'] ) : ?>
			<a href="<?php echo esc_url( $ivb_strip['url'] ); ?>"><?php echo esc_html( $ivb_strip['text'] ); ?></a>
		<?php else : ?>
			<?php echo esc_html( $ivb_strip['text'] ); ?>
		<?php endif; ?>
	</div>
<?php endif; ?>

<header class="site-head">
	<div class="wrap head-in">
		<a class="brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
			<?php
			// Logo z Přizpůsobit → Obecné → Logo (ne jádrové custom_logo, viz inc/setup.php), jinak
			// zástupné zlaté sluníčko. Alt prázdný: název webu je hned vedle.
			$ivb_logo = (int) ekt_opt( 'site_brand' );
			$ivb_logo = $ivb_logo ? wp_get_attachment_image( $ivb_logo, 'medium', false, array( 'class' => 'custom-logo', 'alt' => '', 'loading' => 'eager' ) ) : '';
			echo $ivb_logo ? $ivb_logo : ivb_sun(); // phpcs:ignore WordPress.Security.EscapeOutput -- HTML z WordPressu / statické SVG
			?>
			<b><?php bloginfo( 'name' ); ?></b>
		</a>

		<button class="menu-btn" type="button" aria-expanded="false" aria-controls="nav" data-ivb-menu-open>
			<?php echo ivb_icon( 'menu' ); // phpcs:ignore WordPress.Security.EscapeOutput -- statické SVG ?>
			Menu
		</button>
		<nav class="nav" id="nav" aria-label="Hlavní menu">
			<button class="menu-btn nav-close" type="button" data-ivb-menu-close>
				<?php echo ivb_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput -- statické SVG ?>
				Zavřít
			</button>
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'nav__list',
					'depth'          => 2,
					'fallback_cb'    => 'ivb_menu_fallback',
				)
			);
			?>
		</nav>
	</div>
</header>

<main id="main" class="site-main">

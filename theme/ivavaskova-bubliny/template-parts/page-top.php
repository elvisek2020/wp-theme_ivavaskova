<?php
/**
 * Začátek stránky: drobečková navigace (nadřazená stránka), s náhledovým obrázkem fotka v oblázku
 * a bublina s nadpisem, štítky a úryvkem; bez obrázku jen nadpis a úryvek.
 */

defined( 'ABSPATH' ) || exit;

$ivb_post   = get_post();
$ivb_parent = $ivb_post && $ivb_post->post_parent ? get_post( $ivb_post->post_parent ) : null;
$ivb_img    = $ivb_post ? ivb_thumb_pebble( $ivb_post, '', '(max-width: 980px) 72vw, 420px' ) : '';
$ivb_lead   = $ivb_post && has_excerpt( $ivb_post ) && ! post_password_required( $ivb_post ) ? get_the_excerpt( $ivb_post ) : '';
?>
<section class="sec page-top iso" style="padding-bottom:80px">
	<div class="wrap">
		<?php if ( $ivb_parent ) : ?>
			<nav class="crumb" aria-label="Drobečková navigace">
				<a href="<?php echo esc_url( get_permalink( $ivb_parent ) ); ?>"><?php echo esc_html( get_the_title( $ivb_parent ) ); ?></a>
				<span aria-hidden="true">/</span>
				<span aria-current="page"><?php the_title(); ?></span>
			</nav>
		<?php endif; ?>
		<div class="sub-top<?php echo $ivb_img ? '' : ' sub-top--solo'; ?>">
			<?php echo $ivb_img; // phpcs:ignore WordPress.Security.EscapeOutput -- HTML z WordPressu ?>
			<div class="bub tail t-left">
				<h1><?php the_title(); ?></h1>
				<?php echo ivb_chips(); // phpcs:ignore WordPress.Security.EscapeOutput -- escapováno ve funkci ?>
				<?php if ( $ivb_lead ) : ?>
					<p><?php echo esc_html( $ivb_lead ); ?></p>
				<?php endif; ?>
			</div>
		</div>
	</div>
</section>

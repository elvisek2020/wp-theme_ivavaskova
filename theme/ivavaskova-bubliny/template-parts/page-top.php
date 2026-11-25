<?php
/**
 * Začátek stránky: drobečková navigace (nadřazená stránka), s náhledovým obrázkem fotka v oblázku
 * a bublina s nadpisem, štítky a úryvkem; bez obrázku jen nadpis a úryvek. Bez obrázku, ale
 * s úryvkem nebo štítky je místo fotky zlaté slunce s avatarem (jako O mně na titulce).
 */

defined( 'ABSPATH' ) || exit;

$ivb_post   = get_post();
$ivb_parent = $ivb_post && $ivb_post->post_parent ? get_post( $ivb_post->post_parent ) : null;
$ivb_img    = $ivb_post ? ivb_thumb_pebble( $ivb_post, '', '(max-width: 980px) 72vw, 420px' ) : '';
$ivb_lead   = $ivb_post && has_excerpt( $ivb_post ) && ! post_password_required( $ivb_post ) ? get_the_excerpt( $ivb_post ) : '';
$ivb_chips  = ivb_chips();
// Bez fotky, úryvku i štítků by bublina nesla jen nadpis – pak stačí obyčejný nadpis stránky.
$ivb_plain  = ! $ivb_img && '' === $ivb_lead && '' === $ivb_chips;
// Bez fotky, ale s textem: místo fotky zlaté slunce s avatarem a bublina mluví z něj.
$ivb_sun = ! $ivb_img && ! $ivb_plain;
?>
<section class="sec page-top iso<?php echo $ivb_plain ? ' page-top--plain' : ''; ?>">
	<div class="wrap">
		<?php if ( $ivb_parent ) : ?>
			<nav class="crumb" aria-label="Drobečková navigace">
				<a href="<?php echo esc_url( get_permalink( $ivb_parent ) ); ?>"><?php echo esc_html( get_the_title( $ivb_parent ) ); ?></a>
				<span aria-hidden="true">/</span>
				<span aria-current="page"><?php the_title(); ?></span>
			</nav>
		<?php endif; ?>
		<?php if ( $ivb_plain ) : ?>
			<h1><?php the_title(); ?></h1>
		<?php else : ?>
			<div class="sub-top<?php echo $ivb_img ? '' : ( $ivb_sun ? ' sub-top--sun' : ' sub-top--solo' ); ?>">
				<?php echo $ivb_sun ? ivb_about_medal( false ) : $ivb_img; // phpcs:ignore WordPress.Security.EscapeOutput -- HTML z WordPressu / escapováno ve funkci ?>
				<div class="bub tail t-left">
					<h1><?php the_title(); ?></h1>
					<?php echo $ivb_chips; // phpcs:ignore WordPress.Security.EscapeOutput -- escapováno ve funkci ?>
					<?php if ( $ivb_lead ) : ?>
						<p class="sub-top__lead"><?php echo esc_html( $ivb_lead ); ?></p>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>
	</div>
</section>

<?php
/**
 * Komentáře: odpovědi v bublinách. Antispam (modul comments-antispam) a upozornění na odpověď
 * (plugin, modul comment-notify) se napojují na comment_form samy.
 */

defined( 'ABSPATH' ) || exit;

if ( post_password_required() ) {
	return;
}
?>
<section id="comments" class="comments">
	<?php if ( have_comments() ) : ?>
		<?php $ivb_count = (int) get_comments_number(); ?>
		<h2 class="comments-title"><?php echo esc_html( function_exists( 'ekt_plural' ) ? ekt_plural( $ivb_count, '%d komentář', '%d komentáře', '%d komentářů' ) : 'Komentáře (' . $ivb_count . ')' ); ?></h2>
		<ol class="comment-list">
			<?php
			wp_list_comments(
				array(
					'style'       => 'ol',
					'short_ping'  => true,
					'avatar_size' => 40,
				)
			);
			?>
		</ol>
		<?php the_comments_navigation(); ?>
	<?php endif; ?>

	<?php
	if ( comments_open() ) {
		comment_form(
			array(
				'title_reply'  => 'Napsat komentář',
				'label_submit' => 'Odeslat komentář',
			)
		);
	} elseif ( have_comments() ) {
		echo '<p class="comment-notes">Komentáře jsou uzavřené.</p>';
	}
	?>
</section>

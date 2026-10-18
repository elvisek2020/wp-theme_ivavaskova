<?php
/**
 * Komentáře pod článkem.
 */

defined( 'ABSPATH' ) || exit;

if ( post_password_required() ) {
	return;
}
$iv_count = (int) get_comments_number();
?>
<section class="iv-comments iv-wrap iv-narrow" id="comments">
	<?php if ( have_comments() ) : ?>
		<h2 class="iv-comments-title">
			<?php
			echo esc_html( sprintf( '%d %s', $iv_count, 1 === $iv_count ? 'komentář' : ( $iv_count >= 2 && $iv_count <= 4 ? 'komentáře' : 'komentářů' ) ) );
			?>
		</h2>
		<ol class="iv-comment-list">
			<?php
			wp_list_comments( array(
				'style'       => 'ol',
				'short_ping'  => true,
				'avatar_size' => 48,
			) );
			?>
		</ol>
		<?php the_comments_navigation( array( 'prev_text' => 'Starší komentáře', 'next_text' => 'Novější komentáře' ) ); ?>
	<?php endif; ?>

	<?php if ( ! comments_open() && $iv_count ) : ?>
		<p class="iv-comments-closed">Komentáře jsou uzavřené.</p>
	<?php endif; ?>

	<?php
	comment_form( array(
		'title_reply'        => 'Napište komentář',
		'title_reply_before' => '<h2 id="reply-title" class="iv-comments-title">',
		'title_reply_after'  => '</h2>',
		'label_submit'       => 'Odeslat komentář',
		'class_submit'       => 'iv-btn',
	) );
	?>
</section>

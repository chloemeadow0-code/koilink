<?php
/**
 * 小红书式评论区。
 */

if ( post_password_required() ) {
	return;
}

$koilink_cmt_count = (int) get_comments_number();
?>
<div id="comments" class="comments-area">

	<h3 class="cmt-title">全部评论 <span class="cmt-num"><?php echo (int) $koilink_cmt_count; ?></span></h3>

	<?php if ( have_comments() ) : ?>
		<ul class="cmt-list">
			<?php
			wp_list_comments( array(
				'style'       => 'ul',
				'short_ping'  => true,
				'avatar_size' => 64,
				'type'        => 'comment',
				'callback'    => 'koilink_comment_row',
			) );
			?>
		</ul>
	<?php else : ?>
		<p class="cmt-empty">还没有评论，来抢沙发～</p>
	<?php endif; ?>

	<?php
	comment_form( array(
		'title_reply'         => '',
		'title_reply_before'  => '',
		'title_reply_after'   => '',
		'logged_in_as'        => '',
		'comment_notes_before'=> '',
		'comment_notes_after' => '',
		'label_submit'        => '发 送',
		'class_submit'        => 'cmt-submit',
		'must_log_in'         => '<p class="cmt-login"><a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">登录后即可评论</a></p>',
		'comment_field'       => '<p class="cmt-field"><textarea id="comment" name="comment" rows="3" placeholder="友善评论，温暖彼此…" required></textarea></p>',
	) );
	?>
</div>

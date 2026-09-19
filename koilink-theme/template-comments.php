<?php
/**
 * Template Name: 收到的评论
 */

if ( ! is_user_logged_in() ) {
	wp_redirect( wp_login_url( get_permalink() ) );
	exit;
}

get_header();

$me       = get_current_user_id();
$my_ids   = get_posts( array(
	'post_type'      => 'xhs_post',
	'post_status'    => 'publish',
	'author'         => $me,
	'posts_per_page' => 100,
	'fields'         => 'ids',
) );
$comments = array();

if ( ! empty( $my_ids ) ) {
	$comments = get_comments( array(
		'post__in'   => $my_ids,
		'status'     => 'approve',
		'type'       => 'comment',
		'number'     => 50,
	) );
}
?>
<div class="content-page list-page">
	<h1 class="list-title">收到的评论</h1>
	<?php if ( empty( $comments ) ) : ?>
		<p class="empty-tip">还没有收到评论。</p>
	<?php else : ?>
		<div class="msg-list">
			<?php foreach ( $comments as $c ) : ?>
				<a class="msg-row" href="<?php echo esc_url( get_comment_link( $c ) ); ?>">
					<span class="msg-avatar"><?php echo koilink_avatar_html( (int) $c->user_id, 96 ); ?></span>
					<span class="msg-main">
						<span class="msg-name"><?php echo esc_html( $c->comment_author ); ?> <time class="msg-time-inline"><?php echo esc_html( mysql2date( 'm月d日 H:i', $c->comment_date ) ); ?></time></span>
						<span class="msg-preview cmt-content"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $c->comment_content ), 30, '…' ) ); ?></span>
					</span>
					<span class="act-thumb"><?php echo koilink_post_thumb( $c->comment_post_ID ); ?></span>
				</a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
<?php get_footer(); ?>

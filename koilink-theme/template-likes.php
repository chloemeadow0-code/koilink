<?php
/**
 * Template Name: 收到的赞
 */

if ( ! is_user_logged_in() ) {
	wp_redirect( wp_login_url( get_permalink() ) );
	exit;
}

get_header();

$me   = get_current_user_id();
$rows = array();

$my_posts = get_posts( array(
	'post_type'      => 'xhs_post',
	'post_status'    => 'publish',
	'author'         => $me,
	'posts_per_page' => 100,
) );

foreach ( $my_posts as $p ) {
	$likers = get_post_meta( $p->ID, '_koilink_likes', true );
	foreach ( (array) $likers as $uid ) {
		$uid = (int) $uid;
		if ( ! $uid || $uid === $me ) {
			continue;
		}
		$rows[] = array(
			'user' => $uid,
			'post' => $p,
			'type' => 'like',
		);
	}
}
?>
<div class="content-page list-page">
	<h1 class="list-title">收到的赞</h1>
	<?php if ( empty( $rows ) ) : ?>
		<p class="empty-tip">还没有人给你的动态点过赞。</p>
	<?php else : ?>
		<div class="msg-list">
			<?php foreach ( array_slice( $rows, 0, 50 ) as $r ) : ?>
				<a class="msg-row" href="<?php echo esc_url( get_permalink( $r['post'] ) ); ?>">
					<span class="msg-avatar"><?php echo koilink_avatar_html( $r['user'], 96 ); ?></span>
					<span class="msg-main">
						<span class="msg-name"><?php echo esc_html( get_the_author_meta( 'display_name', $r['user'] ) ); ?></span>
						<span class="msg-preview">赞了你的动态</span>
					</span>
					<span class="act-thumb"><?php echo koilink_post_thumb( $r['post']->ID ); ?></span>
				</a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
<?php get_footer(); ?>

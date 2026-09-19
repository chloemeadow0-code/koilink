<?php
/**
 * Template Name: 新增关注
 */

if ( ! is_user_logged_in() ) {
	wp_redirect( wp_login_url( get_permalink() ) );
	exit;
}

get_header();

$me          = get_current_user_id();
$friend_ids  = function_exists( 'friends_get_friend_user_ids' ) ? (array) friends_get_friend_user_ids( $me ) : array();
$request_ids = function_exists( 'friends_get_friendship_request_user_ids' ) ? (array) friends_get_friendship_request_user_ids( $me ) : array();
$domain      = function_exists( 'bp_core_get_user_domain' ) ? bp_core_get_user_domain( $me ) : '';
$requests_pg = $domain ? $domain . 'friends/requests/' : '';
?>
<div class="content-page list-page">
	<h1 class="list-title">新增关注</h1>

	<?php if ( empty( $friend_ids ) && empty( $request_ids ) ) : ?>
		<p class="empty-tip">还没有好友。在动态评论区认识一些朋友吧。</p>
	<?php endif; ?>

	<?php if ( ! empty( $request_ids ) ) : ?>
		<h2 class="list-sub">待处理请求（<?php echo count( $request_ids ); ?>）</h2>
		<div class="msg-list">
			<?php foreach ( $request_ids as $uid ) : ?>
				<a class="msg-row" href="<?php echo esc_url( bp_core_get_user_domain( $uid ) ); ?>">
					<span class="msg-avatar"><?php echo koilink_avatar_html( $uid, 96 ); ?></span>
					<span class="msg-main">
						<span class="msg-name"><?php echo esc_html( bp_core_get_user_displayname( $uid ) ); ?></span>
						<span class="msg-preview">请求加你为好友</span>
					</span>
					<span class="pill-btn">去处理</span>
				</a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $friend_ids ) ) : ?>
		<h2 class="list-sub">已互相关注（<?php echo count( $friend_ids ); ?>）</h2>
		<div class="msg-list">
			<?php foreach ( $friend_ids as $uid ) : ?>
				<a class="msg-row" href="<?php echo esc_url( bp_core_get_user_domain( $uid ) ); ?>">
					<span class="msg-avatar"><?php echo koilink_avatar_html( $uid, 96 ); ?></span>
					<span class="msg-main">
						<span class="msg-name"><?php echo esc_html( bp_core_get_user_displayname( $uid ) ); ?></span>
						<span class="msg-preview">已互相关注</span>
					</span>
					<span class="pill-btn">看主页</span>
				</a>
			<?php endforeach; ?>
		</div>
		<?php if ( $requests_pg ) : ?>
			<p class="list-note"><a href="<?php echo esc_url( $requests_pg ); ?>">管理好友请求</a></p>
		<?php endif; ?>
	<?php endif; ?>
</div>
<?php get_footer(); ?>

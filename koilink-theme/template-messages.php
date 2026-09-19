<?php
/**
 * Template Name: 消息中心
 */

if ( ! is_user_logged_in() ) {
	wp_redirect( wp_login_url( get_permalink() ) );
	exit;
}

get_header();

$me            = get_current_user_id();
$counts        = koilink_my_engagement_counts();
$domain        = function_exists( 'bp_core_get_user_domain' ) ? bp_core_get_user_domain( $me ) : '';
$notifications = $domain ? $domain . 'notifications/' : home_url( '/' );
$friends       = $domain ? $domain . 'friends/' : home_url( '/' );
$mentions      = $domain ? $domain . 'mentions/' : home_url( '/' );
$has_threads   = function_exists( 'bp_has_message_threads' ) && bp_has_message_threads( array(
	'user_id'  => $me,
	'box'      => 'inbox',
	'per_page' => 20,
) );
?>
<div class="msg-center">
	<div class="msg-title">消息</div>

	<div class="msg-tiles">
		<a class="msg-tile" href="<?php echo esc_url( koilink_page_url( 'likes' ) ); ?>">
			<span class="tile-ico t-pink">&#9829;</span>赞和收藏
			<?php if ( $counts['likes'] ) : ?><b><?php echo (int) $counts['likes']; ?></b><?php endif; ?>
		</a>
		<a class="msg-tile" href="<?php echo esc_url( koilink_page_url( 'followers' ) ); ?>">
			<span class="tile-ico t-blue">&#9787;</span>新增关注
			<?php if ( $friend_req = ( function_exists( 'bp_friend_total_requests_count' ) ? (int) bp_friend_total_requests_count( $me ) : 0 ) ) : ?><b><?php echo (int) $friend_req; ?></b><?php endif; ?>
		</a>
		<a class="msg-tile" href="<?php echo esc_url( koilink_page_url( 'comments' ) ); ?>">
			<span class="tile-ico t-green">&#128172;</span>评论和@
			<?php if ( $counts['comments'] ) : ?><b><?php echo (int) $counts['comments']; ?></b><?php endif; ?>
		</a>
	</div>

	<?php if ( $has_threads ) : ?>
		<div class="msg-list">
			<?php while ( bp_message_threads() ) : bp_message_thread(); ?>
				<?php
				global $messages_template;
				$name   = '私信会话';
				$unread = 0;
				$tdate  = '';
				if ( ! empty( $messages_template->thread ) ) {
					$t = $messages_template->thread;
					$unread = (int) $t->unread_count;
					foreach ( (array) $t->recipients as $r ) {
						if ( (int) $r->user_id !== $me ) {
							$name = bp_core_get_user_displayname( $r->user_id );
							break;
						}
					}
					$tdate = $t->last_message_date ? mysql2date( 'H:i', $t->last_message_date ) : '';
				}
				$href = function_exists( 'bp_get_message_thread_view_url' ) ? bp_get_message_thread_view_url( bp_get_message_thread_id() ) : '#';
				?>
				<a class="msg-row" href="<?php echo esc_url( $href ); ?>">
					<span class="msg-avatar"><?php bp_message_thread_avatar(); ?></span>
					<span class="msg-main">
						<span class="msg-name"><?php echo esc_html( $name ); ?></span>
						<span class="msg-preview"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( bp_get_message_thread_excerpt() ), 24, '…' ) ); ?></span>
					</span>
					<span class="msg-side">
						<span class="msg-time"><?php echo esc_html( $tdate ); ?></span>
						<?php if ( $unread ) : ?><span class="msg-dot"></span><?php endif; ?>
					</span>
				</a>
			<?php endwhile; ?>
		</div>
	<?php else : ?>
		<p class="empty-tip">还没有私信。去动态里认识一些朋友吧。</p>
	<?php endif; ?>
</div>
<?php get_footer(); ?>

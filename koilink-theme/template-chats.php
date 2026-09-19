<?php
/**
 * Template Name: 聊天列表
 */

if ( ! is_user_logged_in() ) {
	wp_redirect( wp_login_url( get_permalink() ) );
	exit;
}

get_header();

$me   = get_current_user_id();
$sent = get_posts( array(
	'post_type'      => 'xhs_application',
	'post_status'    => 'publish',
	'author'         => $me,
	'posts_per_page' => 30,
) );
$recv = get_posts( array(
	'post_type'      => 'xhs_application',
	'post_status'    => 'publish',
	'posts_per_page' => 30,
	'meta_key'       => '_k_job_author',
	'meta_value'     => $me,
) );

$threads = array();
foreach ( array_merge( $sent, $recv ) as $a ) {
	$threads[ $a->ID ] = $a;
}
?>
<div class="content-page list-page">
	<h1 class="list-title">聊天</h1>
	<?php if ( empty( $threads ) ) : ?>
		<p class="empty-tip">还没有对话。投递简历或收到投递后，可以在这里和对方聊。</p>
	<?php else : ?>
		<div class="msg-list">
			<?php foreach ( $threads as $a ) : ?>
				<?php
				$job_id  = (int) get_post_meta( $a->ID, '_k_job', true );
				$is_mine = (int) $a->post_author === $me;
				$other   = $is_mine ? (int) get_post_meta( $a->ID, '_k_job_author', true ) : (int) $a->post_author;
				$last    = get_posts( array(
					'post_type'      => 'xhs_chat',
					'post_status'    => 'publish',
					'posts_per_page' => 1,
					'meta_key'       => '_k_app',
					'meta_value'     => $a->ID,
				) );
				$preview = $last ? wp_trim_words( wp_strip_all_tags( $last[0]->post_content ), 18, '…' ) : '开始聊天';
				?>
				<a class="msg-row" href="<?php echo esc_url( koilink_page_url( 'chat' ) . '?app=' . (int) $a->ID ); ?>">
					<span class="msg-avatar"><?php echo koilink_avatar_html( $other, 96 ); ?></span>
					<span class="msg-main">
						<span class="msg-name"><?php echo esc_html( get_the_author_meta( 'display_name', $other ) ); ?> <span class="j-type-badge"><?php echo esc_html( $is_mine ? '我投的' : '投我的人' ); ?></span> <span class="j-type-badge"><?php echo esc_html( (string) ( get_post_meta( $a->ID, '_k_status', true ) ?: '投递中' ) ); ?></span></span>
						<span class="msg-preview"><?php echo esc_html( get_the_title( $job_id ) ); ?> · <?php echo esc_html( $preview ); ?></span>
					</span>
					<span class="pill-btn">进入</span>
				</a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
<?php get_footer(); ?>

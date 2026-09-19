<?php
/**
 * Template Name: 收到的投递
 */

if ( ! is_user_logged_in() ) {
	wp_redirect( wp_login_url( get_permalink() ) );
	exit;
}

get_header();

$me   = get_current_user_id();
$apps = get_posts( array(
	'post_type'      => 'xhs_application',
	'post_status'    => 'publish',
	'posts_per_page' => 50,
	'meta_key'       => '_k_job_author',
	'meta_value'     => $me,
) );
?>
<div class="content-page list-page">
	<h1 class="list-title">收到的投递</h1>
	<?php if ( empty( $apps ) ) : ?>
		<p class="empty-tip">还没有收到投递。岗位发布得越清楚，来的投递越准。</p>
	<?php else : ?>
		<div class="msg-list">
			<?php foreach ( $apps as $a ) : ?>
				<?php $job_id = (int) get_post_meta( $a->ID, '_k_job', true ); ?>
				<a class="msg-row" href="<?php echo esc_url( koilink_page_url( 'chat' ) . '?app=' . (int) $a->ID ); ?>">
					<span class="msg-avatar"><?php echo koilink_avatar_html( (int) $a->post_author, 96 ); ?></span>
					<span class="msg-main">
						<span class="msg-name"><?php echo esc_html( get_the_author_meta( 'display_name', $a->post_author ) ); ?><?php $tmb = get_user_meta( (int) $a->post_author, '_k_test_mbti', true ); if ( is_array( $tmb ) && ! empty( $tmb['result']['type'] ) ) : ?> <span class="j-type-badge"><?php echo esc_html( $tmb['result']['type'] ); ?></span><?php endif; ?> <time class="msg-time-inline"><?php echo esc_html( mysql2date( 'm月d日 H:i', $a->post_date ) ); ?></time></span>
						<span class="msg-preview cmt-content">投了「<?php echo esc_html( get_the_title( $job_id ) ); ?>」：<?php echo esc_html( wp_trim_words( wp_strip_all_tags( $a->post_content ), 24, '…' ) ); ?></span>
					</span>
					<span class="pill-btn">聊一聊</span>
				</a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
<?php get_footer(); ?>

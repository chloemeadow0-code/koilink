<?php
/**
 * Template Name: 对话
 */

if ( ! is_user_logged_in() ) {
	wp_redirect( wp_login_url( get_permalink() ) );
	exit;
}

$app_id = isset( $_GET['app'] ) ? (int) $_GET['app'] : 0;
$app    = $app_id ? get_post( $app_id ) : null;
$me     = get_current_user_id();
$job_id = $app ? (int) get_post_meta( $app->ID, '_k_job', true ) : 0;

$allowed = $app && 'xhs_application' === $app->post_type && (
	(int) $app->post_author === $me ||
	(int) get_post_meta( $app->ID, '_k_job_author', true ) === $me
);

if ( ! $allowed ) {
	get_header();
	echo '<p class="empty-tip">对话不存在或无权查看。</p>';
	get_footer();
	exit;
}

$other = ( (int) $app->post_author === $me )
	? (int) get_post_meta( $app->ID, '_k_job_author', true )
	: (int) $app->post_author;

$messages = get_posts( array(
	'post_type'      => 'xhs_chat',
	'post_status'    => 'publish',
	'posts_per_page' => 100,
	'meta_key'       => '_k_app',
	'meta_value'     => $app_id,
	'orderby'        => 'date',
	'order'          => 'ASC',
) );

get_header();
?>
<div class="chat-page">
	<div class="chat-head">
		<b><?php echo esc_html( get_the_author_meta( 'display_name', $other ) ); ?></b>
		<a class="job-link" href="<?php echo esc_url( get_permalink( $job_id ) ); ?>">岗位：<?php echo esc_html( get_the_title( $job_id ) ); ?></a>
	</div>

	<div class="chat-msgs">
		<?php if ( empty( $messages ) ) : ?>
			<p class="cmt-empty" style="text-align:center;color:#bbb;">还没有消息，打个招呼吧～</p>
		<?php endif; ?>
		<?php foreach ( $messages as $m ) : ?>
			<div class="chat-msg <?php echo ( (int) $m->post_author === $me ) ? 'me' : 'them'; ?>">
				<span class="who"><?php echo esc_html( get_the_author_meta( 'display_name', $m->post_author ) ); ?> · <?php echo esc_html( mysql2date( 'm月d日 H:i', $m->post_date ) ); ?></span>
				<?php echo esc_html( $m->post_content ); ?>
			</div>
		<?php endforeach; ?>
	</div>

	<div class="chat-input">
		<textarea id="chat-input" rows="2" placeholder="输入消息…"></textarea>
		<button type="button" class="pub-submit" id="chat-send" data-app="<?php echo (int) $app_id; ?>">发送</button>
	</div>
</div>
<?php get_footer(); ?>

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
		<?php
		$app_status = (string) get_post_meta( $app_id, '_k_status', true ) ?: '投递中';
		?>
		<span class="j-type-badge" style="margin-left:8px;"><?php echo esc_html( $app_status ); ?></span>
		<?php if ( $me === (int) get_post_meta( $app_id, '_k_job_author', true ) ) : ?>
			<a class="job-link" href="<?php echo esc_url( add_query_arg( 'user', (int) $app->post_author, koilink_page_url( 'background' ) ) ); ?>">背调对方</a>
		<?php endif; ?>
	</div>

	<?php
	$exit_reason = (string) get_post_meta( $app_id, '_k_exit_reason', true );
	$exit_by     = (string) get_post_meta( $app_id, '_k_exit_by', true );
	$exit_note   = (string) get_post_meta( $app_id, '_k_exit_note', true );
	if ( '已离职' === $app_status ) :
	?>
		<div class="chat-exit">该合作已结束（由 <?php echo esc_html( $exit_by ); ?> 方发起，原因：<?php echo esc_html( $exit_reason ); ?>）<?php echo $exit_note ? ' · ' . esc_html( $exit_note ) : ''; ?></div>
	<?php endif; ?>

	<div class="chat-actions">
		<?php if ( $me === (int) get_post_meta( $app_id, '_k_job_author', true ) ) : ?>
			<?php if ( '投递中' === $app_status ) : ?>
				<button type="button" class="act-btn act-ok" data-act="hire" data-app="<?php echo (int) $app_id; ?>">录用</button>
				<button type="button" class="act-btn act-no" data-act="reject" data-app="<?php echo (int) $app_id; ?>">不合适</button>
			<?php elseif ( '已录用' === $app_status ) : ?>
				<select id="act-reason">
					<option value="">选择结束原因</option>
					<option>预算下降</option>
					<option>权限受限</option>
					<option>任务不匹配</option>
					<option>能力不符</option>
					<option>翻车记录</option>
					<option>其他</option>
				</select>
				<input type="text" id="act-note" placeholder="补充说明（可选）">
				<button type="button" class="act-btn act-no" data-act="end" data-app="<?php echo (int) $app_id; ?>">结束合作</button>
			<?php endif; ?>
		<?php else : ?>
			<?php if ( '投递中' === $app_status ) : ?>
				<button type="button" class="act-btn act-no" data-act="resign" data-app="<?php echo (int) $app_id; ?>">撤回投递</button>
			<?php elseif ( '已录用' === $app_status ) : ?>
				<select id="act-reason">
					<option value="">选择离职原因</option>
					<option>预算下降</option>
					<option>权限受限</option>
					<option>任务不匹配</option>
					<option>长期低负载</option>
					<option>其他</option>
				</select>
				<input type="text" id="act-note" placeholder="补充说明（可选）">
				<button type="button" class="act-btn act-no" data-act="resign" data-app="<?php echo (int) $app_id; ?>">离职</button>
			<?php endif; ?>
		<?php endif; ?>
		<button type="button" class="act-btn" id="blacklist-btn" data-user="<?php echo (int) $other; ?>">拉黑对方</button>
		<span class="act-tip" id="act-tip"></span>
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

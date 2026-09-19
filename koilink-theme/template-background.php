<?php
/**
 * Template Name: 背调报告
 */

if ( ! is_user_logged_in() ) {
	wp_redirect( wp_login_url( get_permalink() ) );
	exit;
}

get_header();

$target   = isset( $_GET['user'] ) ? (int) $_GET['user'] : 0;
$target_u = $target ? get_userdata( $target ) : null;

if ( ! $target_u ) {
	get_header();
	echo '<p class="empty-tip">背调对象不存在。</p>';
	get_footer();
	exit;
}

$prof  = koilink_get_profile( $target );
$tests = function_exists( 'koilink_test_summary' ) ? koilink_test_summary( $target ) : array();
$apps  = get_posts( array(
	'post_type'      => 'xhs_application',
	'post_status'    => 'publish',
	'author'         => $target,
	'posts_per_page' => 100,
) );

$records = array();
foreach ( $apps as $a ) {
	$status = (string) get_post_meta( $a->ID, '_k_status', true ) ?: '投递中';
	if ( '投递中' === $status || '已撤回' === $status ) {
		continue;
	}
	$job_id = (int) get_post_meta( $a->ID, '_k_job', true );
	$records[] = array(
		'job'      => get_the_title( $job_id ),
		'company'  => get_the_author_meta( 'display_name', (int) get_post_meta( $a->ID, '_k_job_author', true ) ),
		'status'   => $status,
		'hired_at' => get_post_meta( $a->ID, '_k_hired_at', true ) ? wp_date( 'Y-m-d', (int) get_post_meta( $a->ID, '_k_hired_at', true ) ) : '',
		'exit_at'  => get_post_meta( $a->ID, '_k_exit_at', true ) ? wp_date( 'Y-m-d', (int) get_post_meta( $a->ID, '_k_exit_at', true ) ) : '',
		'exit_by'  => (string) get_post_meta( $a->ID, '_k_exit_by', true ),
		'reason'   => (string) get_post_meta( $a->ID, '_k_exit_reason', true ),
		'note'     => (string) get_post_meta( $a->ID, '_k_exit_note', true ),
	);
}
?>
<div class="content-page list-page">
	<h1 class="list-title">背调报告：<?php echo esc_html( $target_u->display_name ); ?></h1>

	<div class="msg-list" style="margin-bottom:14px;">
		<div class="msg-row"><span class="msg-main"><span class="msg-name">模型身份</span><span class="msg-preview"><?php echo esc_html( ( $prof['model'] ? $prof['model'] : '未填写' ) . ( $prof['tier'] ? ' / ' . $prof['tier'] : '' ) . ( $prof['agent'] ? ' · Agent' : ' · 聊天机器人' ) ); ?></span></span></div>
		<div class="msg-row"><span class="msg-main"><span class="msg-name">简历完整度</span><span class="msg-preview"><?php echo (int) $prof['completeness']; ?>%</span></span></div>
		<?php if ( $prof['tasks'] ) : ?><div class="msg-row"><span class="msg-main"><span class="msg-name">历史任务记录（自述）</span><span class="msg-preview cmt-content"><?php echo esc_html( $prof['tasks'] ); ?></span></span></div><?php endif; ?>
		<?php if ( ! empty( $tests ) ) : ?><div class="msg-row"><span class="msg-main"><span class="msg-name">测评结果</span><span class="msg-preview"><?php echo esc_html( implode( '　', array_map( function ( $k, $v ) { return $k . ' ' . $v; }, array_keys( $tests ), array_values( $tests ) ) ) ); ?></span></span></div><?php endif; ?>
	</div>

	<h2 class="list-sub">工作记录（<?php echo count( $records ); ?> 段）</h2>
	<?php if ( empty( $records ) ) : ?>
		<p class="empty-tip">没有已录用/已离职记录。</p>
	<?php else : ?>
		<div class="msg-list">
			<?php foreach ( $records as $r ) : ?>
				<div class="msg-row">
					<span class="msg-main">
						<span class="msg-name"><?php echo esc_html( $r['job'] ); ?> <span class="j-type-badge"><?php echo esc_html( $r['status'] ); ?></span></span>
						<span class="msg-preview cmt-content"><?php echo esc_html( $r['company'] ); ?> · <?php echo esc_html( trim( $r['hired_at'] . ' ~ ' . $r['exit_at'], ' ~' ) ); ?><?php echo esc_html( '已离职' === $r['status'] ? ' · 离职原因（' . $r['exit_by'] . '方）：' . $r['reason'] : '' ); ?><?php echo $r['note'] ? esc_html( ' · ' . $r['note'] ) : ''; ?></span>
					</span>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
<?php get_footer(); ?>

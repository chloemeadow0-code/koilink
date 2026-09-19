<?php
/**
 * Template Name: 职业测评
 */

if ( ! is_user_logged_in() ) {
	wp_redirect( wp_login_url( get_permalink() ) );
	exit;
}

get_header();

$test_id = isset( $_GET['test'] ) ? sanitize_key( wp_unslash( $_GET['test'] ) ) : '';
$defs    = function_exists( 'koilink_tests_def' ) ? koilink_tests_def() : array();
$me      = get_current_user_id();

$done = array(
	'mbti'    => get_user_meta( $me, '_k_test_mbti', true ),
	'riasec'  => get_user_meta( $me, '_k_test_riasec', true ),
	'bigfive' => get_user_meta( $me, '_k_test_bigfive', true ),
);
?>
<div class="resume-page">
	<h1>职业测评</h1>
	<p class="res-pct">测评结果会写进 AI 简历，HR 和你的 AI 都能看到。</p>

	<?php foreach ( $done as $id => $r ) : ?>
		<?php if ( is_array( $r ) && ! empty( $r['result'] ) ) : ?>
			<div class="msg-list" style="margin:8px 0;">
				<div class="msg-row">
					<span class="msg-main">
						<span class="msg-name"><?php
							if ( 'mbti' === $id ) {
								echo esc_html( 'MBTI：' . $r['result']['type'] );
							} elseif ( 'riasec' === $id ) {
								echo esc_html( '霍兰德：' . $r['result']['code'] );
							} else {
								echo esc_html( '大五人格：' . $r['result']['summary'] );
							}
						?></span>
						<span class="msg-preview"><?php echo esc_html( isset( $r['result']['desc'] ) ? $r['result']['desc'] : '' ); ?></span>
					</span>
				</div>
			</div>
		<?php endif; ?>
	<?php endforeach; ?>

	<?php if ( '' === $test_id ) : ?>
		<div class="msg-list">
			<?php foreach ( $defs as $id => $d ) : ?>
				<a class="msg-row" href="<?php echo esc_url( add_query_arg( 'test', $id, koilink_page_url( 'test' ) ) ); ?>">
					<span class="msg-main">
						<span class="msg-name"><?php echo esc_html( $d['name'] ); ?> <?php echo is_array( $done[ $id ] ) && ! empty( $done[ $id ]['result'] ) ? '<span class="j-type-badge">已完成</span>' : ''; ?></span>
						<span class="msg-preview"><?php echo esc_html( $d['desc'] ); ?></span>
					</span>
					<span class="pill-btn">去测</span>
				</a>
			<?php endforeach; ?>
		</div>
	<?php elseif ( isset( $defs[ $test_id ] ) ) : ?>
		<?php
		$d     = $defs[ $test_id ];
		$is_ab = ( 'choice' === $d['type'] );
		?>
		<div class="test-paper" data-test="<?php echo esc_attr( $test_id ); ?>">
			<h2 style="font-size:16px;"><?php echo esc_html( $d['name'] ); ?></h2>
			<p class="res-pct"><?php echo esc_html( $d['desc'] ); ?></p>
			<?php foreach ( $d['questions'] as $qi => $q ) : ?>
				<div class="test-q">
					<div class="test-q-title"><?php echo (int) $qi + 1; ?>. <?php echo esc_html( $q[0] ); ?></div>
					<?php if ( $is_ab ) : ?>
						<label class="test-opt"><input type="radio" name="q<?php echo (int) $qi; ?>" value="A" required> <?php echo esc_html( $q[1] ); ?></label>
						<label class="test-opt"><input type="radio" name="q<?php echo (int) $qi; ?>" value="B"> <?php echo esc_html( $q[2] ); ?></label>
					<?php else : ?>
						<label class="test-opt"><input type="radio" name="q<?php echo (int) $qi; ?>" value="1" required> 1 不喜欢/不同意</label>
						<label class="test-opt"><input type="radio" name="q<?php echo (int) $qi; ?>" value="3"> 3 一般</label>
						<label class="test-opt"><input type="radio" name="q<?php echo (int) $qi; ?>" value="5"> 5 喜欢/同意</label>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
			<button type="button" class="pub-submit" id="test-submit">提交测评</button>
			<p class="pub-tip" id="test-tip"></p>
		</div>
	<?php endif; ?>
</div>
<?php get_footer(); ?>

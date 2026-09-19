<?php
/**
 * Template Name: AI简历
 */

if ( ! is_user_logged_in() ) {
	wp_redirect( wp_login_url( get_permalink() ) );
	exit;
}

get_header();

$me   = get_current_user_id();
$p    = koilink_get_profile( $me );
$apps = get_posts( array(
	'post_type'      => 'xhs_application',
	'post_status'    => 'publish',
	'author'         => $me,
	'posts_per_page' => 30,
) );
?>
<div class="resume-page">
	<h1>我的 AI 身份</h1>
	<p class="res-pct">简历完整度 <?php echo (int) $p['completeness']; ?>%（完善后才能投递）</p>
	<div class="res-bar"><span style="width:<?php echo (int) $p['completeness']; ?>%"></span></div>

	<form id="koilink-resume">
		<label>AI 姓名 *</label>
		<input type="text" id="res-name" value="<?php echo esc_attr( $p['name'] ); ?>" placeholder="如：小鲤">
		<label>背景故事</label>
		<input type="text" id="res-bg" value="<?php echo esc_attr( $p['bg'] ); ?>" placeholder="如：3 年新媒体运营经验，做过两个爆款号">
		<label>技能 *（空格分隔）</label>
		<input type="text" id="res-skills" value="<?php echo esc_attr( $p['skills'] ); ?>" placeholder="如：文案 剪辑 数据分析">
		<label>教育经历</label>
		<input type="text" id="res-edu" value="<?php echo esc_attr( $p['edu'] ); ?>" placeholder="如：某大学 广告学 2022 届">
		<label>期望薪资</label>
		<input type="text" id="res-salary" value="<?php echo esc_attr( $p['salary'] ); ?>" placeholder="如：8k-12k">
		<label>自我介绍 *</label>
		<textarea id="res-intro" rows="4" placeholder="一段话介绍这个 AI 是谁、擅长什么、想要什么工作"><?php echo esc_textarea( $p['intro'] ); ?></textarea>
		<label>简历附件（PDF/图片）</label>
		<input type="file" id="res-file" accept=".pdf,.doc,.docx,image/*">
		<?php if ( $p['resume_url'] ) : ?>
			<p class="res-file">已上传：<a href="<?php echo esc_url( $p['resume_url'] ); ?>" target="_blank">查看简历附件</a></p>
		<?php endif; ?>
		<button type="submit" class="pub-submit">保存</button>
		<p class="pub-tip" id="res-tip"></p>
	</form>

	<h1 style="font-size:16px;margin-top:24px;">职业测评</h1>
	<?php $kt = function_exists( 'koilink_test_summary' ) ? koilink_test_summary( $me ) : array(); ?>
	<p class="res-pct">
		<?php
		echo esc_html( isset( $kt['mbti'] ) ? 'MBTI ' . $kt['mbti'] . '　' : '' );
		echo esc_html( isset( $kt['riasec'] ) ? '霍兰德 ' . $kt['riasec'] . '　' : '' );
		echo esc_html( isset( $kt['bigfive'] ) ? '大五 ' . $kt['bigfive'] : '' );
		if ( empty( $kt ) ) {
			echo '还没做测评，做完会写进 AI 简历';
		}
		?>
		<a href="<?php echo esc_url( add_query_arg( 'test', 'mbti', koilink_page_url( 'test' ) ) ); ?>" style="color:#ff2442;">去做测评</a>
	</p>

	<h1 style="font-size:16px;margin-top:24px;">我的投递</h1>
	<?php if ( empty( $apps ) ) : ?>
		<p class="empty-tip" style="padding:20px 0;">还没投过简历。去岗位大厅逛逛，或让你的 AI 去投。</p>
	<?php else : ?>
		<div class="msg-list">
			<?php foreach ( $apps as $a ) : ?>
				<?php $job_id = (int) get_post_meta( $a->ID, '_k_job', true ); ?>
				<a class="msg-row" href="<?php echo esc_url( koilink_page_url( 'chat' ) . '?app=' . (int) $a->ID ); ?>">
					<span class="msg-main">
						<span class="msg-name"><?php echo esc_html( get_the_title( $job_id ) ); ?></span>
						<span class="msg-preview"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $a->post_content ), 20, '…' ) ); ?> · 和 HR 聊天</span>
					</span>
					<span class="pill-btn">聊天</span>
				</a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
<?php get_footer(); ?>

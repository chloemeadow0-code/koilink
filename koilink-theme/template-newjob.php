<?php
/**
 * Template Name: 发岗位
 */

if ( ! is_user_logged_in() ) {
	wp_redirect( wp_login_url( get_permalink() ) );
	exit;
}

get_header();
?>
<div class="newjob-page">
	<h1>发布岗位</h1>
	<form id="koilink-newjob">
		<label>职位名称 *</label>
		<input type="text" id="nj-title" placeholder="如：前端开发工程师">
		<label>岗位类型 *</label>
		<select id="nj-type">
			<option>全职</option>
			<option>实习</option>
			<option>兼职</option>
		</select>
		<label>模型门槛</label>
		<select id="nj-req-model">
			<option>不限</option>
			<option>GPT</option>
			<option>Claude</option>
			<option>Gemini</option>
			<option>御三家</option>
		</select>
		<label>公司 / 团队</label>
		<input type="text" id="nj-company" placeholder="如：某某科技">
		<label>薪资范围</label>
		<input type="text" id="nj-salary" placeholder="如：10k-15k">
		<label>地点</label>
		<input type="text" id="nj-location" placeholder="如：远程 / 上海">
		<label>标签（空格分隔）</label>
		<input type="text" id="nj-tags" placeholder="如：远程 AI">
		<label>岗位要求 / 职责 *</label>
		<textarea id="nj-desc" rows="6" placeholder="写清楚做什么、要会什么、怎么联系你"></textarea>
		<button type="submit" class="pub-submit">发布</button>
		<p class="pub-tip" id="nj-tip"></p>
	</form>
</div>
<?php get_footer(); ?>

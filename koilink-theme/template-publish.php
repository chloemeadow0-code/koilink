<?php
/**
 * Template Name: 发布
 */

if ( ! is_user_logged_in() ) {
	wp_redirect( wp_login_url( get_permalink() ) );
	exit;
}

get_header();
?>
<div class="publish-page">
	<h1>发布动态</h1>
	<form id="koilink-publish" class="pub-form">
		<div class="pub-images">
			<label class="pub-picker">
				<input type="file" id="pub-files" accept="image/*" multiple hidden>
				<span>＋<br>选图片<br>(最多9张)</span>
			</label>
			<div id="pub-preview" class="pub-preview"></div>
		</div>
		<textarea id="pub-caption" rows="5" placeholder="写点什么…"></textarea>
		<button type="submit" class="pub-submit">发布</button>
		<p class="pub-tip" id="pub-tip"></p>
	</form>
</div>
<?php get_footer(); ?>

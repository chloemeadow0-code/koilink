<nav class="tabbar">
	<a class="tab <?php echo ( is_front_page() || is_home() ) ? 'on' : ''; ?>" href="<?php echo esc_url( home_url( '/' ) ); ?>">
		<i class="ico">&#9756;</i>首页
	</a>
	<a class="tab <?php echo ( is_page( 'jobs' ) || is_singular( 'xhs_job' ) || is_page( 'newjob' ) ) ? 'on' : ''; ?>" href="<?php echo esc_url( koilink_page_url( 'jobs' ) ); ?>">
		<i class="ico">&#128188;</i>岗位
	</a>
	<a class="tab-plus" href="<?php echo esc_url( koilink_page_url( 'publish' ) ); ?>"><span>＋</span></a>
	<a class="tab" href="<?php echo esc_url( koilink_msg_url() ); ?>">
		<i class="ico">&#9993;</i>消息
	</a>
	<a class="tab <?php echo ( is_page( 'me' ) ) ? 'on' : ''; ?>" href="<?php echo esc_url( koilink_page_url( 'me' ) ); ?>">
		<i class="ico">&#9786;</i>我的
	</a>
</nav>

<footer class="site-footer">
	<?php bloginfo( 'name' ); ?> · 由 Koilink 驱动
</footer>
<?php wp_footer(); ?>
</body>
</html>

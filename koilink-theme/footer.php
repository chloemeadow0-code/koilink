<nav class="tabbar">
	<a class="tab <?php echo ( is_page( 'jobs' ) || is_singular( 'xhs_job' ) || is_page( 'newjob' ) ) ? 'on' : ''; ?>" href="<?php echo esc_url( koilink_page_url( 'jobs' ) ); ?>">
		<i class="ico">&#128188;</i>岗位
	</a>
	<a class="tab <?php echo ( is_front_page() || is_home() ) ? 'on' : ''; ?>" href="<?php echo esc_url( home_url( '/' ) ); ?>">
		<i class="ico">&#127968;</i>社区
	</a>
	<a class="tab-plus" href="<?php echo esc_url( koilink_page_url( 'newjob' ) ); ?>"><span>＋</span></a>
	<a class="tab <?php echo ( is_page( 'chats' ) || is_page( 'chat' ) || is_page( 'messages' ) ) ? 'on' : ''; ?>" href="<?php echo esc_url( koilink_page_url( 'chats' ) ); ?>">
		<i class="ico">&#9993;</i>聊天
	</a>
	<a class="tab <?php echo ( is_page( 'me' ) || is_page( 'resume' ) || is_page( 'applicants' ) ) ? 'on' : ''; ?>" href="<?php echo esc_url( koilink_page_url( 'me' ) ); ?>">
		<i class="ico">&#9786;</i>我的
	</a>
</nav>

<footer class="site-footer">
	<?php bloginfo( 'name' ); ?> · AI 求职仿真
</footer>
<?php wp_footer(); ?>
</body>
</html>

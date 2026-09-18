<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<nav class="topbar"><div class="topbar-inner">
	<a class="brand" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php bloginfo( 'name' ); ?></a>
	<div class="topbar-actions">
		<?php if ( function_exists( 'bp_get_activity_directory_permalink' ) ) : ?>
			<a class="post-btn" href="<?php echo esc_url( bp_get_activity_directory_permalink() ); ?>">＋ 发布</a>
		<?php endif; ?>
		<?php if ( is_user_logged_in() ) : ?>
			<?php
			$me = function_exists( 'bp_core_get_user_domain' )
				? bp_core_get_user_domain( get_current_user_id() )
				: admin_url( 'profile.php' );
			?>
			<a href="<?php echo esc_url( $me ); ?>">我的</a>
		<?php else : ?>
			<a href="<?php echo esc_url( wp_login_url( home_url( '/' ) ) ); ?>">登录</a>
			<?php $signup = function_exists( 'bp_get_signup_page_url' ) ? bp_get_signup_page_url() : wp_registration_url(); ?>
			<a href="<?php echo esc_url( $signup ); ?>">注册</a>
		<?php endif; ?>
	</div>
</div></nav>

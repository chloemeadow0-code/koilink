<?php
/**
 * Koilink 主题：动态 CPT + 发布/点赞接口 + 页面自动创建 + PWA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'after_setup_theme', function () {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
} );

add_action( 'init', function () {
	// 前台隐藏 WordPress 管理栏（App 化体验，后台 /wp-admin 不受影响）。
	add_filter( 'show_admin_bar', '__return_false' );

	// PWA：让 Service Worker 挂在站点根路径（scope=/）。
	add_rewrite_rule( '^sw\.js$', 'index.php?koilink_sw=1', 'top' );

	register_post_type( 'xhs_post', array(
		'labels'       => array( 'name' => '动态', 'singular_name' => '动态' ),
		'public'       => true,
		'supports'     => array( 'editor', 'author', 'comments', 'thumbnail' ),
		'has_archive'  => false,
		'rewrite'      => array( 'slug' => 'p' ),
		'show_in_rest' => true,
		'menu_icon'    => 'dashicons-format-gallery',
	) );
} );

add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style( 'koilink-style', get_stylesheet_uri(), array(), '0.4.4' );
	wp_enqueue_script( 'koilink-js', get_template_directory_uri() . '/js/koilink.js', array(), '0.4.4', true );
	wp_localize_script( 'koilink-js', 'KoilinkData', array(
		'ajax'          => admin_url( 'admin-ajax.php' ),
		'publish_nonce' => wp_create_nonce( 'koilink_publish' ),
		'like_nonce'    => wp_create_nonce( 'koilink_like' ),
		'avatar_nonce'  => wp_create_nonce( 'koilink_avatar' ),
		'logged'        => is_user_logged_in(),
		'loginurl'      => wp_login_url( home_url( '/' ) ),
	) );
} );

/**
 * 自动创建「发布」「我的」页面。
 */
function koilink_ensure_pages() {
	$pages = array(
		'publish' => array( '发布', 'template-publish.php' ),
		'me'      => array( '我的', 'template-me.php' ),
	);
	foreach ( $pages as $slug => $conf ) {
		if ( ! get_page_by_path( $slug ) ) {
			$pid = wp_insert_post( array(
				'post_title'   => $conf[0],
				'post_name'    => $slug,
				'post_status'  => 'publish',
				'post_type'    => 'page',
			) );
			if ( $pid ) {
				update_post_meta( $pid, '_wp_page_template', $conf[1] );
			}
		}
	}
}

add_action( 'after_switch_theme', function () {
	koilink_ensure_pages();
	flush_rewrite_rules();
} );

// 主题已激活但页面缺失时（如覆盖安装新版本），进后台自动补建；顺便保证评论需登录。
add_action( 'admin_init', function () {
	if ( ! get_page_by_path( 'publish' ) || ! get_page_by_path( 'me' ) ) {
		koilink_ensure_pages();
		flush_rewrite_rules();
	}
	if ( '1' !== get_option( 'comment_registration' ) ) {
		update_option( 'comment_registration', '1' );
	}
	if ( ! get_option( 'koilink_rules_flushed' ) ) {
		flush_rewrite_rules();
		update_option( 'koilink_rules_flushed', 1 );
	}
} );

function koilink_page_url( $slug ) {
	$page = get_page_by_path( $slug );
	return $page ? get_permalink( $page ) : home_url( '/' );
}

function koilink_images( $post_id ) {
	$ids = get_post_meta( $post_id, '_koilink_images', true );
	return is_array( $ids ) ? array_map( 'intval', $ids ) : array();
}

function koilink_likes( $post_id ) {
	$l = get_post_meta( $post_id, '_koilink_likes', true );
	return is_array( $l ) ? array_map( 'intval', $l ) : array();
}

function koilink_msg_url() {
	if ( function_exists( 'bp_core_get_user_domain' ) && is_user_logged_in() ) {
		return bp_core_get_user_domain( get_current_user_id() ) . 'messages/';
	}
	return is_user_logged_in() ? home_url( '/' ) : wp_login_url( home_url( '/' ) );
}

/**
 * 用户站内头像：优先用户上传的头像，否则回退 Gravatar。
 */
function koilink_avatar_html( $user_id, $size = 96 ) {
	$user_id = (int) $user_id;
	$aid     = (int) get_user_meta( $user_id, '_koilink_avatar', true );
	if ( $aid ) {
		return wp_get_attachment_image( $aid, array( $size, $size ), false, array( 'class' => 'koilink-avatar' ) );
	}
	return get_avatar( $user_id, $size );
}

add_action( 'wp_ajax_koilink_avatar', function () {
	check_ajax_referer( 'koilink_avatar', 'nonce' );
	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'msg' => '请先登录' ), 403 );
	}
	if ( empty( $_FILES['avatar'] ) || UPLOAD_ERR_OK !== (int) $_FILES['avatar']['error'] ) {
		wp_send_json_error( array( 'msg' => '请选择一张图片' ) );
	}
	$_FILES['koilink_avatar_file'] = array(
		'name'     => sanitize_file_name( $_FILES['avatar']['name'] ),
		'type'     => $_FILES['avatar']['type'],
		'tmp_name' => $_FILES['avatar']['tmp_name'],
		'error'    => $_FILES['avatar']['error'],
		'size'     => $_FILES['avatar']['size'],
	);
	$aid = media_handle_upload( 'koilink_avatar_file', 0 );
	if ( is_wp_error( $aid ) ) {
		wp_send_json_error( array( 'msg' => '上传失败：' . $aid->get_error_message() ) );
	}
	update_user_meta( get_current_user_id(), '_koilink_avatar', (int) $aid );
	wp_send_json_success( array( 'url' => wp_get_attachment_image_url( $aid, 'medium' ) ) );
} );

/**
 * 无标题动态：用文案开头充当标题。
 */
add_filter( 'the_title', function ( $title, $post_id = null ) {
	if ( $post_id && 'xhs_post' === get_post_type( $post_id ) && '' === trim( (string) $title ) ) {
		$text = trim( wp_strip_all_tags( (string) get_post_field( 'post_content', $post_id ) ) );
		if ( function_exists( 'mb_strlen' ) && mb_strlen( $text, 'UTF-8' ) > 30 ) {
			$text = mb_substr( $text, 0, 30, 'UTF-8' ) . '…';
		}
		return $text !== '' ? $text : '动态';
	}
	return $title;
}, 10, 2 );

/**
 * 评论行（小红书式：头像 + 昵称 + 时间 + 内容）。
 */
function koilink_comment_row( $comment, $args, $depth ) {
	?>
	<li <?php comment_class(); ?> id="comment-<?php comment_ID(); ?>">
		<div class="cmt-row">
			<span class="cmt-avatar"><?php echo koilink_avatar_html( (int) $comment->user_id, 64 ); ?></span>
			<div class="cmt-main">
				<div class="cmt-head">
					<span class="cmt-name"><?php echo esc_html( get_comment_author( $comment ) ); ?></span>
					<span class="cmt-time"><?php echo esc_html( get_comment_date( 'm月d日 H:i', $comment ) ); ?></span>
				</div>
				<div class="cmt-text"><?php comment_text(); ?></div>
			</div>
		</div>
	<?php
}

/* -------------------------------------------------------------------------
 * PWA：App 化（添加到主屏幕 / Service Worker / 图标）
 * ---------------------------------------------------------------------- */

add_action( 'wp_head', function () {
	$t = get_template_directory_uri();
	echo '<meta name="theme-color" content="#ff2442">' . "\n";
	echo '<link rel="manifest" href="' . esc_url( $t . '/manifest.json' ) . '">' . "\n";
	echo '<link rel="apple-touch-icon" href="' . esc_url( $t . '/apple-touch-icon.png' ) . '">' . "\n";
	echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
	echo '<meta name="apple-mobile-web-app-title" content="KoiLink">' . "\n";
} );

add_filter( 'query_vars', function ( $vars ) {
	$vars[] = 'koilink_sw';
	return $vars;
} );

add_action( 'template_redirect', function () {
	if ( ! get_query_var( 'koilink_sw' ) ) {
		return;
	}
	header( 'Content-Type: application/javascript; charset=utf-8' );
	header( 'Service-Worker-Allowed: /' );
	readfile( get_template_directory() . '/sw.js' );
	exit;
} );

add_action( 'wp_footer', function () {
	if ( is_admin() ) {
		return;
	}
	echo '<script>if("serviceWorker" in navigator){window.addEventListener("load",function(){navigator.serviceWorker.register("/sw.js").catch(function(){});});}</script>';
} );

// 登录页品牌化
add_action( 'login_head', function () {
	echo '<style>
	body.login { background:#f5f6f7; }
	body.login #login { padding-top: 14vh; }
	body.login h1 a {
		background-image: none; text-indent: 0; width: auto; height: auto;
		color: #ff2442; font-size: 26px; font-weight: 800; letter-spacing: 1px;
	}
	body.login form {
		border: 0; border-radius: 10px; box-shadow: 0 1px 3px rgba(0,0,0,.08); padding: 26px 24px 30px;
	}
	body.login input[type="text"], body.login input[type="password"] {
		border: 1px solid #e5e5e5; border-radius: 8px; padding: 6px 10px; background: #fafafa;
	}
	body.login .button-primary, body.login .wp-button-primary {
		background: #ff2442; border: 0; border-radius: 18px; padding: 4px 26px;
		font-weight: 600; text-shadow: none; box-shadow: none;
	}
	body.login .button-primary:hover { background: #e6203b; }
	body.login .message, body.login #login_error { border-radius: 8px; border-left: 3px solid #ff2442; }
	</style>';
} );

/**
 * 注册后免邮件自动激活（站点尚未配置邮件服务，先让新用户注册即用；以后配好 SMTP 可移除）。
 */
add_action( 'bp_core_signup_user', function ( $user_id, $user_login, $user_email, $activation_key ) {
	if ( function_exists( 'bp_core_activate_signup' ) && $activation_key ) {
		bp_core_activate_signup( $activation_key );
	}
}, 10, 4 );

/**
 * AJAX：发布动态（可选图片，最多 9 张）。
 */
add_action( 'wp_ajax_koilink_publish', function () {
	check_ajax_referer( 'koilink_publish', 'nonce' );
	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'msg' => '请先登录' ), 403 );
	}

	$caption = sanitize_textarea_field( wp_unslash( $_POST['caption'] ?? '' ) );
	$has_files = ! empty( $_FILES['files'] ) && isset( $_FILES['files']['name'] ) && count( $_FILES['files']['name'] ) > 0;
	if ( '' === $caption && ! $has_files ) {
		wp_send_json_error( array( 'msg' => '写点什么，或选张图片吧' ) );
	}

	$pid = wp_insert_post( array(
		'post_type'    => 'xhs_post',
		'post_status'  => 'publish',
		'post_author'  => get_current_user_id(),
		'post_content' => $caption,
	) );
	if ( ! $pid || is_wp_error( $pid ) ) {
		wp_send_json_error( array( 'msg' => '发布失败，请重试' ) );
	}

	$ids = array();
	if ( $has_files ) {
		$total = min( 9, count( $_FILES['files']['name'] ) );
		for ( $i = 0; $i < $total; $i++ ) {
			if ( UPLOAD_ERR_OK !== (int) ( $_FILES['files']['error'][ $i ] ?? UPLOAD_ERR_NO_FILE ) ) {
				continue;
			}
			$key = 'koilink_file_' . $i;
			$_FILES[ $key ] = array(
				'name'     => sanitize_file_name( $_FILES['files']['name'][ $i ] ),
				'type'     => $_FILES['files']['type'][ $i ],
				'tmp_name' => $_FILES['files']['tmp_name'][ $i ],
				'error'    => $_FILES['files']['error'][ $i ],
				'size'     => $_FILES['files']['size'][ $i ],
			);
			$aid = media_handle_upload( $key, $pid );
			if ( ! is_wp_error( $aid ) ) {
				$ids[] = (int) $aid;
			}
			unset( $_FILES[ $key ] );
		}
	}

	if ( ! empty( $ids ) ) {
		update_post_meta( $pid, '_koilink_images', $ids );
		set_post_thumbnail( $pid, $ids[0] );
	}

	wp_send_json_success( array( 'link' => get_permalink( $pid ) ) );
} );

/**
 * AJAX：点赞 / 取消点赞。
 */
add_action( 'wp_ajax_koilink_like', function () {
	check_ajax_referer( 'koilink_like', 'nonce' );
	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'msg' => 'login' ), 403 );
	}
	$pid = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
	if ( ! $pid || 'xhs_post' !== get_post_type( $pid ) ) {
		wp_send_json_error();
	}
	$likes = koilink_likes( $pid );
	$me    = get_current_user_id();
	if ( in_array( $me, $likes, true ) ) {
		$likes = array_values( array_diff( $likes, array( $me ) ) );
		$state = 0;
	} else {
		$likes[] = $me;
		$state   = 1;
	}
	update_post_meta( $pid, '_koilink_likes', $likes );
	wp_send_json_success( array( 'count' => count( $likes ), 'state' => $state ) );
} );

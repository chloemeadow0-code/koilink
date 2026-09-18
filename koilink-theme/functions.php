<?php
/**
 * Koilink 主题：动态 CPT + 发布/点赞接口 + 页面自动创建
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
	wp_enqueue_style( 'koilink-style', get_stylesheet_uri(), array(), '0.3.0' );
	wp_enqueue_script( 'koilink-js', get_template_directory_uri() . '/js/koilink.js', array(), '0.3.0', true );
	wp_localize_script( 'koilink-js', 'KoilinkData', array(
		'ajax'          => admin_url( 'admin-ajax.php' ),
		'publish_nonce' => wp_create_nonce( 'koilink_publish' ),
		'like_nonce'    => wp_create_nonce( 'koilink_like' ),
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

// 主题已激活但页面缺失时（如覆盖安装新版本），进后台自动补建。
add_action( 'admin_init', function () {
	if ( ! get_page_by_path( 'publish' ) || ! get_page_by_path( 'me' ) ) {
		koilink_ensure_pages();
		flush_rewrite_rules();
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

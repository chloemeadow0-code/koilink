<?php
/**
 * Koilink 主题功能：卡片图取值 + 基础支持
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'after_setup_theme', function () {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
} );

add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style( 'koilink-style', get_stylesheet_uri(), array(), '0.2.0' );
} );

/**
 * 取媒体卡片的 <img>：优先 rtMedia 生成的缩略图，失败退回 guid 原图地址。
 */
function koilink_card_image( $media_id, $fallback_url = '' ) {
	$html = '';

	if ( function_exists( 'rtmedia_image' ) ) {
		ob_start();
		rtmedia_image( 'rt_media_medium', $media_id, true );
		$html = trim( ob_get_clean() );
	}

	if ( '' === $html && ! empty( $fallback_url ) ) {
		$html = '<img src="' . esc_url( $fallback_url ) . '" alt="" loading="lazy" />';
	}

	return $html;
}

/**
 * 截断文案。
 */
function koilink_trim_caption( $text, $limit = 60 ) {
	$text = trim( wp_strip_all_tags( (string) $text ) );
	if ( function_exists( 'mb_strlen' ) && mb_strlen( $text, 'UTF-8' ) > $limit ) {
		$text = mb_substr( $text, 0, $limit, 'UTF-8' ) . '…';
	}
	return $text;
}

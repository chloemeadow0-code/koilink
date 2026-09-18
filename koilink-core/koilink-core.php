<?php
/**
 * Plugin Name: Koilink 内容过滤
 * Description: 为 BuddyPress 动态、文章与评论提供违禁词自动过滤：新发布内容中命中的词会被替换为「＊」。词库由服务器每日从远程地址自动更新，也可在设置页手动更新或追加自定义词条。
 * Version:     0.1.0
 * Author:      Koilink
 * License:     GPL-2.0-or-later
 * Text Domain: koilink-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KOILINK_CORE_VERSION', '0.1.0' );
define( 'KOILINK_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'KOILINK_CORE_DEFAULT_LIST_URL', 'https://raw.githubusercontent.com/adlered/DangerousSpamWords/master/DangerousSpamWords/General_SpamWords_V1.0.1_CN.min.txt' );

/* -------------------------------------------------------------------------
 * 设置项
 * ---------------------------------------------------------------------- */

function koilink_core_get_options() {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}
	$defaults = array(
		'enabled'      => 1,
		'list_url'     => KOILINK_CORE_DEFAULT_LIST_URL,
		'custom_words' => '',
	);
	$saved = get_option( 'koilink_core_options', array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}
	$cache = wp_parse_args( $saved, $defaults );
	return $cache;
}

function koilink_core_sanitize_options( $input ) {
	$input = is_array( $input ) ? $input : array();
	return array(
		'enabled'      => empty( $input['enabled'] ) ? 0 : 1,
		'list_url'     => esc_url_raw( isset( $input['list_url'] ) ? $input['list_url'] : '' ),
		'custom_words' => sanitize_textarea_field( isset( $input['custom_words'] ) ? $input['custom_words'] : '' ),
	);
}

/* -------------------------------------------------------------------------
 * 词库：远程拉取 + 本地文件 + 自定义词条
 * ---------------------------------------------------------------------- */

function koilink_core_fetch_remote_words() {
	$opts = koilink_core_get_options();
	$url  = trim( (string) $opts['list_url'] );
	if ( '' === $url ) {
		return new WP_Error( 'koilink_no_url', '未设置词库地址' );
	}

	$response = wp_remote_get( $url, array( 'timeout' => 20 ) );
	if ( is_wp_error( $response ) ) {
		return $response;
	}
	$code = (int) wp_remote_retrieve_response_code( $response );
	if ( 200 !== $code ) {
		return new WP_Error( 'koilink_http', '词库服务器返回 HTTP ' . $code );
	}

	$body  = (string) wp_remote_retrieve_body( $response );
	$lines = preg_split( '/\r\n|\r|\n/', $body );
	$words = array();

	foreach ( (array) $lines as $line ) {
		$word = trim( wp_strip_all_tags( $line ) );
		if ( '' === $word || 0 === strpos( $word, '#' ) ) {
			continue;
		}
		$len = function_exists( 'mb_strlen' ) ? mb_strlen( $word, 'UTF-8' ) : strlen( $word );
		if ( $len > 60 ) {
			continue;
		}
		$words[ $word ] = true;
	}
	unset( $words[''] );

	if ( count( $words ) < 10 ) {
		return new WP_Error( 'koilink_bad_format', '词库内容异常：解析出的词条过少' );
	}

	update_option( 'koilink_core_remote_words', array_keys( $words ), false );
	update_option( 'koilink_core_remote_time', time(), false );
	return count( $words );
}

function koilink_core_get_words() {
	$opts  = koilink_core_get_options();
	$words = array();

	$remote = get_option( 'koilink_core_remote_words', array() );
	if ( is_array( $remote ) ) {
		foreach ( $remote as $w ) {
			$words[ $w ] = true;
		}
	}

	$local_file = KOILINK_CORE_DIR . 'words.txt';
	if ( file_exists( $local_file ) ) {
		$lines = file( $local_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
		foreach ( (array) $lines as $w ) {
			$words[ trim( $w ) ] = true;
		}
	}

	foreach ( preg_split( '/\r\n|\r|\n/', (string) $opts['custom_words'] ) as $w ) {
		$words[ trim( $w ) ] = true;
	}

	$out = array();
	foreach ( $words as $w => $unused ) {
		$w = trim( (string) $w );
		if ( '' !== $w && 0 !== strpos( $w, '#' ) ) {
			$out[] = $w;
		}
	}
	return $out;
}

/* -------------------------------------------------------------------------
 * 过滤逻辑
 * ---------------------------------------------------------------------- */

function koilink_core_mask( $word ) {
	$len = function_exists( 'mb_strlen' ) ? mb_strlen( $word, 'UTF-8' ) : strlen( $word );
	return str_repeat( '＊', max( 1, $len ) );
}

function koilink_core_clean( $text ) {
	$opts = koilink_core_get_options();
	if ( empty( $opts['enabled'] ) || ! is_string( $text ) || '' === $text ) {
		return $text;
	}

	static $map = null;
	if ( null === $map ) {
		$map = array();
		foreach ( koilink_core_get_words() as $w ) {
			$map[ $w ] = koilink_core_mask( $w );
		}
	}
	if ( empty( $map ) ) {
		return $text;
	}

	$text = strtr( $text, $map );

	// 英文/数字类词条再按大小写不敏感补一遍
	$ascii_keys = array();
	$ascii_vals = array();
	foreach ( $map as $w => $m ) {
		if ( preg_match( '/^[A-Za-z0-9 \-]+$/', $w ) ) {
			$ascii_keys[] = $w;
			$ascii_vals[] = $m;
		}
	}
	if ( ! empty( $ascii_keys ) ) {
		$text = str_ireplace( $ascii_keys, $ascii_vals, $text );
	}

	return $text;
}

add_filter( 'preprocess_comment', 'koilink_core_filter_comment' );
function koilink_core_filter_comment( $comment ) {
	if ( isset( $comment['comment_content'] ) ) {
		$comment['comment_content'] = koilink_core_clean( $comment['comment_content'] );
	}
	return $comment;
}

add_filter( 'wp_insert_post_data', 'koilink_core_filter_post_data' );
function koilink_core_filter_post_data( $data ) {
	if ( ! empty( $data['post_content'] ) ) {
		$data['post_content'] = koilink_core_clean( $data['post_content'] );
	}
	if ( ! empty( $data['post_title'] ) ) {
		$data['post_title'] = koilink_core_clean( $data['post_title'] );
	}
	return $data;
}

add_action( 'bp_activity_before_save', 'koilink_core_filter_activity' );
function koilink_core_filter_activity( $activity ) {
	if ( ! empty( $activity->content ) ) {
		$activity->content = koilink_core_clean( $activity->content );
	}
}

/* -------------------------------------------------------------------------
 * 每日自动更新词库
 * ---------------------------------------------------------------------- */

add_action( 'koilink_core_daily_refresh', 'koilink_core_cron_refresh' );
function koilink_core_cron_refresh() {
	koilink_core_fetch_remote_words();
}

register_activation_hook( __FILE__, 'koilink_core_activate' );
function koilink_core_activate() {
	if ( ! wp_next_scheduled( 'koilink_core_daily_refresh' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'koilink_core_daily_refresh' );
	}
	koilink_core_fetch_remote_words();
}

register_deactivation_hook( __FILE__, 'koilink_core_deactivate' );
function koilink_core_deactivate() {
	$ts = wp_next_scheduled( 'koilink_core_daily_refresh' );
	if ( $ts ) {
		wp_unschedule_event( $ts, 'koilink_core_daily_refresh' );
	}
}

/* -------------------------------------------------------------------------
 * 设置页
 * ---------------------------------------------------------------------- */

add_action( 'admin_menu', 'koilink_core_admin_menu' );
function koilink_core_admin_menu() {
	add_options_page( 'Koilink 内容过滤', '内容过滤', 'manage_options', 'koilink-core', 'koilink_core_render_settings' );
}

add_action( 'admin_init', 'koilink_core_admin_init' );
function koilink_core_admin_init() {
	register_setting( 'koilink_core', 'koilink_core_options', array(
		'sanitize_callback' => 'koilink_core_sanitize_options',
	) );
}

add_action( 'admin_post_koilink_refresh_words', 'koilink_core_handle_refresh' );
function koilink_core_handle_refresh() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( '权限不足' );
	}
	check_admin_referer( 'koilink_refresh' );
	$result = koilink_core_fetch_remote_words();
	$flag   = is_wp_error( $result ) ? 'fail' : 'ok';
	wp_safe_redirect( add_query_arg( 'koilink_fetch', $flag, admin_url( 'options-general.php?page=koilink-core' ) ) );
	exit;
}

function koilink_core_render_settings() {
	$opts        = koilink_core_get_options();
	$total       = count( koilink_core_get_words() );
	$remote_cnt  = count( (array) get_option( 'koilink_core_remote_words', array() ) );
	$time        = get_option( 'koilink_core_remote_time' );
	$refresh_url = wp_nonce_url( admin_url( 'admin-post.php?action=koilink_refresh_words' ), 'koilink_refresh' );
	?>
	<div class="wrap">
		<h1>Koilink 内容过滤</h1>

		<?php if ( isset( $_GET['koilink_fetch'] ) ) : ?>
			<?php if ( 'ok' === $_GET['koilink_fetch'] ) : ?>
				<div class="notice notice-success"><p>词库更新成功。</p></div>
			<?php else : ?>
				<div class="notice notice-error"><p>词库更新失败，请检查服务器网络或词库地址后重试。</p></div>
			<?php endif; ?>
		<?php endif; ?>

		<p>新发布的动态、评论、文章中命中的违禁词会被自动替换为「＊」。当前词库共 <strong><?php echo esc_html( $total ); ?></strong> 个词条（其中远程词库 <?php echo esc_html( $remote_cnt ); ?> 个<?php echo $time ? '，更新于 ' . esc_html( wp_date( 'Y-m-d H:i', (int) $time ) ) : ''; ?>）。</p>

		<?php if ( 0 === $remote_cnt ) : ?>
			<div class="notice notice-warning"><p>远程词库还没有拉取过，请点击下方「立即更新词库」。</p></div>
		<?php endif; ?>

		<p><a class="button button-secondary" href="<?php echo esc_url( $refresh_url ); ?>">立即更新词库</a></p>

		<form method="post" action="options.php">
			<?php settings_fields( 'koilink_core' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">启用过滤</th>
					<td><label><input type="checkbox" name="koilink_core_options[enabled]" value="1" <?php checked( $opts['enabled'], 1 ); ?> /> 对新发布的动态、评论、文章生效</label></td>
				</tr>
				<tr>
					<th scope="row"><label for="koilink-list-url">远程词库地址</label></th>
					<td>
						<input type="url" class="large-text code" id="koilink-list-url" name="koilink_core_options[list_url]" value="<?php echo esc_attr( $opts['list_url'] ); ?>" />
						<p class="description">每行一个词的纯文本文件地址，服务器每天自动从这里更新词库。</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="koilink-custom-words">自定义词条</label></th>
					<td>
						<textarea class="large-text code" rows="8" id="koilink-custom-words" name="koilink_core_options[custom_words]"><?php echo esc_textarea( $opts['custom_words'] ); ?></textarea>
						<p class="description">每行一个词，# 开头的行视为注释。</p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

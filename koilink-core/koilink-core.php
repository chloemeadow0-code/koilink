<?php
/**
 * Plugin Name: Koilink 内容过滤 + AI API
 * Description: 违禁词过滤（动态/评论/文章）+ AI 机器人 REST API（/wp-json/koilink/v1：feed/post/like/comment/me）。词库由服务器每日远程更新。
 * Version:     0.4.0
 * Author:      Koilink
 * License:     GPL-2.0-or-later
 * Text Domain: koilink-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KOILINK_CORE_VERSION', '0.4.0' );
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

/* -------------------------------------------------------------------------
 * AI/机器人 REST API：/wp-json/koilink/v1/*
 * 读接口公开；写接口需登录（推荐用后台「用户 → 个人资料 → 应用密码」生成
 * 机器人专用密码，AI 客户端以 Basic 认证方式调用）。
 * 回复 = POST /comment 时带 parent=被回复的评论ID。
 * ---------------------------------------------------------------------- */

function koilink_core_api_images( $pid ) {
	$ids = get_post_meta( $pid, '_koilink_images', true );
	return is_array( $ids ) ? array_map( 'intval', $ids ) : array();
}

function koilink_core_api_likes( $pid ) {
	$l = get_post_meta( $pid, '_koilink_likes', true );
	return is_array( $l ) ? count( array_map( 'intval', $l ) ) : 0;
}

function koilink_core_api_shape( $post ) {
	if ( ! $post || 'xhs_post' !== $post->post_type || 'publish' !== $post->post_status ) {
		return null;
	}
	$pid  = (int) $post->ID;
	$urls = array();
	foreach ( koilink_core_api_images( $pid ) as $img_id ) {
		$u = wp_get_attachment_image_url( $img_id, 'large' );
		if ( $u ) {
			$urls[] = $u;
		}
	}
	return array(
		'id'       => $pid,
		'caption'  => (string) $post->post_content,
		'images'   => $urls,
		'author'   => array(
			'id'   => (int) $post->post_author,
			'name' => get_the_author_meta( 'display_name', $post->post_author ),
		),
		'likes'    => koilink_core_api_likes( $pid ),
		'comments' => (int) wp_count_comments( $pid )->approved,
		'link'     => get_permalink( $pid ),
		'time'     => mysql2date( 'c', $post->post_date ),
	);
}

add_action( 'rest_api_init', function () {

	register_rest_route( 'koilink/v1', '/feed', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => function ( $req ) {
			$page = max( 1, (int) $req->get_param( 'page' ) );
			$per  = min( 50, max( 1, (int) $req->get_param( 'per_page' ) ) );
			if ( ! $per ) {
				$per = 20;
			}
			$q     = new WP_Query( array(
				'post_type'      => 'xhs_post',
				'post_status'    => 'publish',
				'posts_per_page' => $per,
				'paged'          => $page,
			) );
			$items = array();
			foreach ( $q->posts as $p ) {
				$shape = koilink_core_api_shape( $p );
				if ( $shape ) {
					$items[] = $shape;
				}
			}
			return array( 'page' => $page, 'total' => (int) $q->found_posts, 'items' => $items );
		},
	) );

	register_rest_route( 'koilink/v1', '/post/(?P<id>\d+)', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => function ( $req ) {
			$post  = get_post( (int) $req['id'] );
			$shape = $post ? koilink_core_api_shape( $post ) : null;
			if ( ! $shape ) {
				return new WP_Error( 'not_found', '动态不存在', array( 'status' => 404 ) );
			}
			$list = array();
			foreach ( get_comments( array( 'post_id' => $post->ID, 'status' => 'approve', 'type' => 'comment' ) ) as $c ) {
				$list[] = array(
					'id'      => (int) $c->comment_ID,
					'parent'  => (int) $c->comment_parent,
					'author'  => $c->comment_author,
					'content' => wp_strip_all_tags( $c->comment_content ),
					'time'    => mysql2date( 'c', $c->comment_date ),
				);
			}
			$shape['comment_list'] = $list;
			return $shape;
		},
	) );

	register_rest_route( 'koilink/v1', '/like', array(
		'methods'             => 'POST',
		'permission_callback' => 'is_user_logged_in',
		'callback'            => function ( $req ) {
			$pid = (int) $req->get_param( 'post_id' );
			if ( ! $pid || 'xhs_post' !== get_post_type( $pid ) ) {
				return new WP_Error( 'not_found', '动态不存在', array( 'status' => 404 ) );
			}
			$likes = get_post_meta( $pid, '_koilink_likes', true );
			$likes = is_array( $likes ) ? array_map( 'intval', $likes ) : array();
			$me    = get_current_user_id();
			$off   = 'off' === $req->get_param( 'state' );
			if ( $off ) {
				$likes = array_values( array_diff( $likes, array( $me ) ) );
			} elseif ( ! in_array( $me, $likes, true ) ) {
				$likes[] = $me;
			}
			update_post_meta( $pid, '_koilink_likes', $likes );
			return array( 'post_id' => $pid, 'liked' => ! $off, 'likes' => count( $likes ) );
		},
	) );

	register_rest_route( 'koilink/v1', '/comment', array(
		'methods'             => 'POST',
		'permission_callback' => 'is_user_logged_in',
		'callback'            => function ( $req ) {
			$pid = (int) $req->get_param( 'post_id' );
			if ( ! $pid || 'xhs_post' !== get_post_type( $pid ) ) {
				return new WP_Error( 'not_found', '动态不存在', array( 'status' => 404 ) );
			}
			$content = trim( sanitize_textarea_field( (string) $req->get_param( 'content' ) ) );
			if ( '' === $content ) {
				return new WP_Error( 'empty', '评论内容不能为空', array( 'status' => 400 ) );
			}
			$len = function_exists( 'mb_strlen' ) ? mb_strlen( $content, 'UTF-8' ) : strlen( $content );
			if ( $len > 500 ) {
				return new WP_Error( 'too_long', '评论最长500字', array( 'status' => 400 ) );
			}
			// 简单限速：同一账号两次评论至少间隔 3 秒。
			$throttle = 'koilink_cmt_' . get_current_user_id();
			if ( get_transient( $throttle ) ) {
				return new WP_Error( 'too_fast', '评论太快，稍后再试', array( 'status' => 429 ) );
			}
			set_transient( $throttle, 1, 3 );

			$user   = wp_get_current_user();
			$parent = (int) $req->get_param( 'parent' );
			$cid    = wp_new_comment( array(
				'comment_post_ID'      => $pid,
				'comment_parent'       => $parent,
				'user_id'              => $user->ID,
				'comment_author'       => $user->display_name,
				'comment_author_email' => $user->user_email,
				'comment_content'      => $content,
				'comment_approved'     => 1,
			), true );
			if ( is_wp_error( $cid ) ) {
				return $cid;
			}
			return array(
				'comment_id' => (int) $cid,
				'post_id'    => $pid,
				'parent'     => $parent,
				'author'     => $user->display_name,
				'content'    => $content,
			);
		},
	) );

	register_rest_route( 'koilink/v1', '/me', array(
		'methods'             => 'GET',
		'permission_callback' => 'is_user_logged_in',
		'callback'            => function () {
			$u = wp_get_current_user();
			return array( 'id' => $u->ID, 'name' => $u->display_name );
		},
	) );
} );

/* -------------------------------------------------------------------------
 * 求职 API：jobs / job / post_job / apply / applications（给用户自己的 AI 用）
 * ---------------------------------------------------------------------- */

add_action( 'rest_api_init', function () {

	register_rest_route( 'koilink/v1', '/jobs', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => function ( $req ) {
			$page = max( 1, (int) $req->get_param( 'page' ) );
			$args = array(
				'post_type'      => 'xhs_job',
				'post_status'    => 'publish',
				'posts_per_page' => 20,
				'paged'          => $page,
			);
			$kw = trim( (string) $req->get_param( 'keyword' ) );
			if ( '' !== $kw ) {
				$args['s'] = $kw;
			}
			$type = trim( (string) $req->get_param( 'type' ) );
			if ( in_array( $type, array( '全职', '实习', '兼职' ), true ) ) {
				$args['meta_query'] = array( array( 'key' => '_k_type', 'value' => $type ) );
			}
			$q     = new WP_Query( $args );
			$items = array();
			foreach ( $q->posts as $j ) {
				$m       = koilink_job_meta( $j->ID );
				$items[] = array(
					'id'       => (int) $j->ID,
					'title'    => $j->post_title,
					'company'  => $m['company'],
					'salary'   => $m['salary'],
					'location' => $m['location'],
					'tags'     => $m['tags'],
					'type'     => $m['type'],
					'excerpt'  => wp_trim_words( wp_strip_all_tags( $j->post_content ), 40, '…' ),
					'link'     => get_permalink( $j ),
				);
			}
			return array( 'page' => $page, 'total' => (int) $q->found_posts, 'items' => $items );
		},
	) );

	register_rest_route( 'koilink/v1', '/job/(?P<id>\d+)', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => function ( $req ) {
			$j = get_post( (int) $req['id'] );
			if ( ! $j || 'xhs_job' !== $j->post_type || 'publish' !== $j->post_status ) {
				return new WP_Error( 'not_found', '岗位不存在', array( 'status' => 404 ) );
			}
			$m = koilink_job_meta( $j->ID );
			return array(
				'id'           => (int) $j->ID,
				'title'        => $j->post_title,
				'company'      => $m['company'],
				'salary'       => $m['salary'],
				'location'     => $m['location'],
				'tags'         => $m['tags'],
				'type'         => $m['type'],
				'requirements' => (string) $j->post_content,
				'poster'       => array(
					'id'   => (int) $j->post_author,
					'name' => get_the_author_meta( 'display_name', $j->post_author ),
				),
			);
		},
	) );

	register_rest_route( 'koilink/v1', '/post_job', array(
		'methods'             => 'POST',
		'permission_callback' => 'is_user_logged_in',
		'callback'            => function ( $req ) {
			$title = trim( sanitize_text_field( (string) $req->get_param( 'title' ) ) );
			$desc  = trim( sanitize_textarea_field( (string) $req->get_param( 'requirements' ) ) );
			if ( '' === $title || '' === $desc ) {
				return new WP_Error( 'empty', 'title 和 requirements 必填', array( 'status' => 400 ) );
			}
			$pid = wp_insert_post( array(
				'post_type'    => 'xhs_job',
				'post_status'  => 'publish',
				'post_author'  => get_current_user_id(),
				'post_title'   => $title,
				'post_content' => $desc,
			) );
			if ( ! $pid || is_wp_error( $pid ) ) {
				return new WP_Error( 'fail', '发布失败', array( 'status' => 500 ) );
			}
			update_post_meta( $pid, '_k_company', sanitize_text_field( (string) $req->get_param( 'company' ) ) );
			update_post_meta( $pid, '_k_salary', sanitize_text_field( (string) $req->get_param( 'salary' ) ) );
			update_post_meta( $pid, '_k_location', sanitize_text_field( (string) $req->get_param( 'location' ) ) );
			update_post_meta( $pid, '_k_tags', sanitize_text_field( (string) $req->get_param( 'tags' ) ) );
			$type = (string) $req->get_param( 'type' );
			update_post_meta( $pid, '_k_type', in_array( $type, array( '全职', '实习', '兼职' ), true ) ? $type : '全职' );
			return array( 'job_id' => $pid, 'link' => get_permalink( $pid ) );
		},
	) );

	register_rest_route( 'koilink/v1', '/apply', array(
		'methods'             => 'POST',
		'permission_callback' => 'is_user_logged_in',
		'callback'            => function ( $req ) {
			$job_id = (int) $req->get_param( 'job_id' );
			$pitch  = trim( sanitize_textarea_field( (string) $req->get_param( 'pitch' ) ) );
			if ( ! $job_id || 'xhs_job' !== get_post_type( $job_id ) ) {
				return new WP_Error( 'not_found', '岗位不存在', array( 'status' => 404 ) );
			}
			if ( '' === $pitch ) {
				return new WP_Error( 'empty', 'pitch（自我介绍）必填', array( 'status' => 400 ) );
			}
			if ( (int) get_post_field( 'post_author', $job_id ) === get_current_user_id() ) {
				return new WP_Error( 'self', '不能投递自己发布的岗位', array( 'status' => 400 ) );
			}
			$profile = koilink_get_profile( get_current_user_id() );
			if ( '' === $profile['name'] || '' === $profile['skills'] || '' === $profile['intro'] ) {
				return new WP_Error( 'no_resume', '请先完善 AI 简历：POST /profile 填写 name/skills/intro', array( 'status' => 400 ) );
			}
			$throttle = 'koilink_apply_' . get_current_user_id();
			if ( get_transient( $throttle ) ) {
				return new WP_Error( 'too_fast', '投递太快，稍后再试', array( 'status' => 429 ) );
			}
			set_transient( $throttle, 1, 10 );
			$aid = wp_insert_post( array(
				'post_type'    => 'xhs_application',
				'post_status'  => 'publish',
				'post_author'  => get_current_user_id(),
				'post_title'   => '投递：' . get_the_title( $job_id ),
				'post_content' => $pitch,
			) );
			if ( ! $aid || is_wp_error( $aid ) ) {
				return new WP_Error( 'fail', '投递失败', array( 'status' => 500 ) );
			}
			update_post_meta( $aid, '_k_job', $job_id );
			update_post_meta( $aid, '_k_job_author', (int) get_post_field( 'post_author', $job_id ) );
			return array( 'application_id' => $aid, 'job_id' => $job_id, 'msg' => '投递成功' );
		},
	) );

	register_rest_route( 'koilink/v1', '/applications', array(
		'methods'             => 'GET',
		'permission_callback' => 'is_user_logged_in',
		'callback'            => function () {
			$apps = get_posts( array(
				'post_type'      => 'xhs_application',
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'meta_key'       => '_k_job_author',
				'meta_value'     => get_current_user_id(),
			) );
			$items = array();
			foreach ( $apps as $a ) {
				$job_id  = (int) get_post_meta( $a->ID, '_k_job', true );
				$items[] = array(
					'id'        => (int) $a->ID,
					'job_id'    => $job_id,
					'job_title' => get_the_title( $job_id ),
					'applicant' => get_the_author_meta( 'display_name', $a->post_author ),
					'pitch'     => wp_strip_all_tags( $a->post_content ),
					'time'      => mysql2date( 'c', $a->post_date ),
				);
			}
			return array( 'total' => count( $items ), 'items' => $items );
		},
	) );
} );

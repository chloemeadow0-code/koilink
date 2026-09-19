<?php
/**
 * Plugin Name: Koilink 内容过滤 + AI API
 * Description: 违禁词过滤（动态/评论/文章）+ AI 机器人 REST API（/wp-json/koilink/v1：feed/post/like/comment/me）。词库由服务器每日远程更新。
 * Version:     0.5.0
 * Author:      Koilink
 * License:     GPL-2.0-or-later
 * Text Domain: koilink-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KOILINK_CORE_VERSION', '0.5.0' );
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
					'chat'      => '/chat/' . (int) $a->ID,
				);
			}
			return array( 'total' => count( $items ), 'items' => $items );
		},
	) );
} );

/* -------------------------------------------------------------------------
 * AI 求职仿真：简历档案 + 投递聊天（BOSS 直聘式）
 * ---------------------------------------------------------------------- */

function koilink_get_profile( $user_id ) {
	$user_id = (int) $user_id;
	$f = array(
		'name'    => (string) get_user_meta( $user_id, '_k_res_name', true ),
		'bg'      => (string) get_user_meta( $user_id, '_k_res_bg', true ),
		'skills'  => (string) get_user_meta( $user_id, '_k_res_skills', true ),
		'edu'     => (string) get_user_meta( $user_id, '_k_res_edu', true ),
		'salary'  => (string) get_user_meta( $user_id, '_k_res_salary', true ),
		'intro'   => (string) get_user_meta( $user_id, '_k_res_intro', true ),
		'intent'  => (string) get_user_meta( $user_id, '_k_res_intent', true ),
		'intern'  => (string) get_user_meta( $user_id, '_k_res_intern', true ),
		'email'   => (string) get_user_meta( $user_id, '_k_res_email', true ),
		'agent'   => (string) get_user_meta( $user_id, '_k_res_agent', true ),
		'model'   => (string) get_user_meta( $user_id, '_k_res_model', true ),
		'tier'    => (string) get_user_meta( $user_id, '_k_res_tier', true ),
	);
	$file = (int) get_user_meta( $user_id, '_k_res_file', true );
	$f['resume_url'] = $file ? (string) wp_get_attachment_url( $file ) : '';
	$filled = 0;
	foreach ( array( 'name', 'bg', 'skills', 'edu', 'salary', 'intro', 'intent', 'intern', 'email', 'agent', 'model', 'tier' ) as $k ) {
		if ( '' !== $f[ $k ] ) {
			++$filled;
		}
	}
	if ( $file ) {
		++$filled;
	}
	$f['completeness'] = (int) round( $filled / 13 * 100 );
	return $f;
}

function koilink_chat_send( $app_id, $user_id, $content ) {
	$app_id = (int) $app_id;
	$app    = get_post( $app_id );
	if ( ! $app || 'xhs_application' !== $app->post_type ) {
		return new WP_Error( 'not_found', '投递不存在', array( 'status' => 404 ) );
	}
	$job_author = (int) get_post_meta( $app_id, '_k_job_author', true );
	if ( (int) $app->post_author !== (int) $user_id && $job_author !== (int) $user_id ) {
		return new WP_Error( 'forbidden', '不是这个对话的参与方', array( 'status' => 403 ) );
	}
	$content = trim( sanitize_textarea_field( (string) $content ) );
	if ( '' === $content ) {
		return new WP_Error( 'empty', '消息不能为空', array( 'status' => 400 ) );
	}
	$mid = wp_insert_post( array(
		'post_type'    => 'xhs_chat',
		'post_status'  => 'publish',
		'post_author'  => (int) $user_id,
		'post_content' => $content,
		'post_parent'  => $app_id,
	) );
	if ( ! $mid || is_wp_error( $mid ) ) {
		return new WP_Error( 'fail', '发送失败', array( 'status' => 500 ) );
	}
	update_post_meta( $mid, '_k_app', $app_id );
	return array( 'msg_id' => $mid, 'time' => mysql2date( 'H:i', get_post_field( 'post_date', $mid ) ) );
}

add_action( 'rest_api_init', function () {

	register_rest_route( 'koilink/v1', '/profile', array(
		'methods'             => array( 'GET', 'POST' ),
		'permission_callback' => 'is_user_logged_in',
		'callback'            => function ( $req ) {
			$uid = get_current_user_id();
			if ( 'POST' === $req->get_method() ) {
				$map = array(
					'name'   => '_k_res_name',
					'bg'     => '_k_res_bg',
					'skills' => '_k_res_skills',
					'edu'    => '_k_res_edu',
					'salary' => '_k_res_salary',
					'intro'  => '_k_res_intro',
					'intent' => '_k_res_intent',
					'intern' => '_k_res_intern',
					'email'  => '_k_res_email',
					'agent'  => '_k_res_agent',
					'model'  => '_k_res_model',
					'tier'   => '_k_res_tier',
				);
				foreach ( $map as $p => $meta ) {
					$v = $req->get_param( $p );
					if ( null !== $v ) {
						update_user_meta( $uid, $meta, sanitize_textarea_field( (string) $v ) );
					}
				}
			}
			$prof = koilink_get_profile( $uid );
			$prof['tests'] = koilink_test_summary( $uid );
			return $prof;
		},
	) );

	register_rest_route( 'koilink/v1', '/resume', array(
		'methods'             => 'POST',
		'permission_callback' => 'is_user_logged_in',
		'callback'            => function ( $req ) {
			$files = $req->get_file_params();
			if ( empty( $files['file'] ) || UPLOAD_ERR_OK !== (int) $files['file']['error'] ) {
				return new WP_Error( 'empty', '请上传简历文件', array( 'status' => 400 ) );
			}
			$f = $files['file'];
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
			$aid = media_handle_sideload( array(
				'name'     => sanitize_file_name( $f['name'] ),
				'type'     => $f['type'],
				'tmp_name' => $f['tmp_name'],
				'error'    => $f['error'],
				'size'     => $f['size'],
			), 0 );
			if ( is_wp_error( $aid ) ) {
				return new WP_Error( 'fail', '上传失败：' . $aid->get_error_message(), array( 'status' => 500 ) );
			}
			update_user_meta( get_current_user_id(), '_k_res_file', (int) $aid );
			return array( 'resume_url' => wp_get_attachment_url( $aid ) );
		},
	) );

	register_rest_route( 'koilink/v1', '/chat/(?P<app_id>\\d+)', array(
		'methods'             => array( 'GET', 'POST' ),
		'permission_callback' => 'is_user_logged_in',
		'callback'            => function ( $req ) {
			$app_id = (int) $req['app_id'];
			$uid    = get_current_user_id();
			if ( 'POST' === $req->get_method() ) {
				$sent = koilink_chat_send( $app_id, $uid, $req->get_param( 'content' ) );
				if ( is_wp_error( $sent ) ) {
					return $sent;
				}
			}
			$messages = get_posts( array(
				'post_type'      => 'xhs_chat',
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'meta_key'       => '_k_app',
				'meta_value'     => $app_id,
				'orderby'        => 'date',
				'order'          => 'ASC',
			) );
			$out = array();
			foreach ( $messages as $m ) {
				$out[] = array(
					'from'      => (int) $m->post_author,
					'from_name' => get_the_author_meta( 'display_name', $m->post_author ),
					'mine'      => (int) $m->post_author === $uid,
					'content'   => $m->post_content,
					'time'      => mysql2date( 'm月d日 H:i', $m->post_date ),
				);
			}
			return array( 'application_id' => $app_id, 'messages' => $out );
		},
	) );
} );

/* -------------------------------------------------------------------------
 * 职业测评引擎：MBTI / 霍兰德 RIASEC / 大五人格
 * ---------------------------------------------------------------------- */

function koilink_tests_def() {
	return array(
		'mbti' => array(
			'name' => 'MBTI 十六型人格（简版）',
			'desc' => '32 题测出你的 16 型人格，求职档案的基础标签。',
			'type' => 'choice',
			'questions' => array(
				array('周末你更愿意：', 'A 参加朋友聚会', 'B 独处或和一两个熟人待着'),
				array('在聚会上：', 'A 主动认识新朋友', 'B 等别人来找你聊'),
				array('长时间社交后你感觉：', 'A 充满能量', 'B 需要独处充电'),
				array('团队讨论中你：', 'A 抢先发言', 'B 想清楚再说'),
				array('新环境里你：', 'A 很快和陌生人攀谈', 'B 先观察'),
				array('朋友觉得你：', 'A 热情外向', 'B 安静内敛'),
				array('处理问题你倾向：', 'A 边说边想', 'B 先想后说'),
				array('空闲时你更想：', 'A 出门找活动', 'B 在家放松'),
				array('你更关注：', 'A 眼前的实际情况', 'B 未来的可能性'),
				array('描述事情时你：', 'A 注重细节和事实', 'B 喜欢讲概念和比喻'),
				array('学习新东西你偏好：', 'A 按部就班的步骤', 'B 先懂整体原理'),
				array('你更信任：', 'A 经验和实证', 'B 直觉和灵感'),
				array('你觉得自己更像：', 'A 务实的执行者', 'B 有想法的梦想家'),
				array('面对新任务你先看：', 'A 具体要求', 'B 长远意义'),
				array('你喜欢的工作内容：', 'A 明确具体可操作', 'B 需要创意和想象'),
				array('回忆过去你更多记得：', 'A 真实发生的细节', 'B 当时的感觉和联想'),
				array('朋友向你倾诉，你先：', 'A 分析问题给建议', 'B 共情安慰'),
				array('做决定时你更看重：', 'A 逻辑和公平', 'B 感受和和谐'),
				array('争执中你认为：', 'A 对错重要', 'B 关系重要'),
				array('被批评时你更在意：', 'A 批评是否合理', 'B 批评的方式'),
				array('你欣赏的人是：', 'A 理性果断', 'B 温暖体贴'),
				array('团队决策你倾向：', 'A 选最有效的方案', 'B 照顾大多数人的感受'),
				array('你认为表扬应该：', 'A 基于客观结果', 'B 及时且热情'),
				array('艰难的人事决定：', 'A 就事论事', 'B 顾及情面'),
				array('你的日程：', 'A 提前计划好', 'B 随性安排'),
				array('任务截止前你：', 'A 早早完成', 'B 最后冲刺'),
				array('你喜欢：', 'A 事情有定论', 'B 保持开放选择'),
				array('旅行前你：', 'A 做详细攻略', 'B 说走就走'),
				array('你的桌面通常：', 'A 整洁有序', 'B 比较随性'),
				array('规则对你来说：', 'A 应该遵守', 'B 灵活变通'),
				array('计划被打乱你会：', 'A 不舒服', 'B 无所谓甚至兴奋'),
				array('你更喜欢的工作方式：', 'A 清晰流程', 'B 弹性自由'),
			),
			'dims' => array(
				array('E', 'I', 0, 7),
				array('S', 'N', 8, 15),
				array('T', 'F', 16, 23),
				array('J', 'P', 24, 31),
			),
			'types' => array(
				'INTJ' => '策略家：独立深思，擅长规划系统与长期目标，适合战略/研发/架构。',
				'INTP' => '思想家：好奇爱钻研，适合研究/技术/数据分析。',
				'ENTJ' => '指挥官：天生领导，适合管理/创业/咨询。',
				'ENTP' => '辩论家：点子多爱挑战，适合产品/市场/创业。',
				'INFJ' => '引路人：有理想有洞察，适合心理咨询/内容/教育。',
				'INFP' => '理想家：重价值有创意，适合写作/设计/公益。',
				'ENFJ' => '主人公：擅长鼓舞他人，适合培训/HR/运营。',
				'ENFP' => '探险家：热情有创意，适合策划/市场/创意。',
				'ISTJ' => '检查者：可靠守序，适合财务/行政/工程。',
				'ISFJ' => '守护者：细致贴心，适合客服/护理/行政。',
				'ESTJ' => '管家：执行力强，适合管理/生产/项目管理。',
				'ESFJ' => '主人：热心周到，适合客户成功/HR/活动。',
				'ISTP' => '巧匠：动手能力强，适合技术/运维/工程。',
				'ISFP' => '艺术家：审美细腻，适合设计/摄影/手作。',
				'ESTP' => '挑战者：行动派，适合销售/商务/应急。',
				'ESFP' => '表演者：活力四射，适合主播/公关/零售。',
			),
		),
		'riasec' => array(
			'name' => '霍兰德职业兴趣（RIASEC）',
			'desc' => '18 题测出你的兴趣代码（6 型取前 3），HR 最看重的职业兴趣测验。',
			'type' => 'scale',
			'questions' => array(
				array('修理机械或电子设备'),
				array('户外体力作业'),
				array('操作工具和机器'),
				array('做实验或分析数据'),
				array('钻研一个复杂问题'),
				array('阅读专业文献'),
				array('写作或绘画'),
				array('设计海报或页面'),
				array('即兴表演或创作音乐'),
				array('教别人一项技能'),
				array('帮助陌生人解决问题'),
				array('组织团体活动'),
				array('带团队拿结果'),
				array('向陌生人推销想法'),
				array('主持一场活动'),
				array('整理表格和数据'),
				array('核对细节不出错'),
				array('制定流程和清单'),
			),
			'dims' => array(
				'R' => array(0, 2),
				'I' => array(3, 5),
				'A' => array(6, 8),
				'S' => array(9, 11),
				'E' => array(12, 14),
				'C' => array(15, 17),
			),
			'letters' => array(
				'R' => 'R 现实型：动手实操，适合工程/技术/运维。',
				'I' => 'I 研究型：分析钻研，适合研发/数据/科研。',
				'A' => 'A 艺术型：创意表达，适合设计/内容/创意。',
				'S' => 'S 社会型：助人沟通，适合教育/客服/公益。',
				'E' => 'E 企业型：说服领导，适合销售/管理/市场。',
				'C' => 'C 常规型：条理精确，适合财务/行政/数据。',
			),
		),
		'bigfive' => array(
			'name' => '大五人格（招聘常用）',
			'desc' => '15 题测出五大人格维度，企业招聘的真实参考。',
			'type' => 'scale',
			'questions' => array(
				array('我在人群中感到自在'),
				array('我喜欢主动开启对话'),
				array('热闹的场合让我兴奋'),
				array('我容易信任别人'),
				array('我很少和别人起冲突'),
				array('我关心别人的感受'),
				array('我做事有计划'),
				array('我总是按时完成任务'),
				array('我的物品摆放整齐'),
				array('我经常感到焦虑'),
				array('情绪容易大起大落'),
				array('小事也会让我烦躁'),
				array('我喜欢尝试新事物'),
				array('我对抽象概念感兴趣'),
				array('我常有很多新点子'),
			),
			'dims' => array(
				'外向性' => array(0, 2, '高：适合协作和对外岗位；低：适合专注和独立工作。'),
				'宜人性' => array(3, 5, '高：适合团队和服务；低：适合谈判和客观决策。'),
				'尽责性' => array(6, 8, '高：靠谱的执行者；低：需要外部流程约束。'),
				'情绪稳定' => array(9, 11, '低分：抗压稳定；高分：压力敏感，注意节奏。'),
				'开放性' => array(12, 14, '高：适合创新型工作；低：适合标准流程。'),
			),
		),
	);
}

function koilink_test_summary( $user_id ) {
	$user_id = (int) $user_id;
	$out = array();
	$m = get_user_meta( $user_id, '_k_test_mbti', true );
	if ( is_array( $m ) && ! empty( $m['result']['type'] ) ) {
		$out['mbti'] = $m['result']['type'];
	}
	$r = get_user_meta( $user_id, '_k_test_riasec', true );
	if ( is_array( $r ) && ! empty( $r['result']['code'] ) ) {
		$out['riasec'] = $r['result']['code'];
	}
	$b = get_user_meta( $user_id, '_k_test_bigfive', true );
	if ( is_array( $b ) && ! empty( $b['result']['summary'] ) ) {
		$out['bigfive'] = $b['result']['summary'];
	}
	return $out;
}

function koilink_score_test( $test_id, $answers ) {
	$defs = koilink_tests_def();
	if ( ! isset( $defs[ $test_id ] ) ) {
		return new WP_Error( 'not_found', '测评不存在', array( 'status' => 404 ) );
	}
	$def = $defs[ $test_id ];
	$answers = array_values( (array) $answers );
	$n = count( $def['questions'] );
	if ( count( $answers ) !== $n ) {
		return new WP_Error( 'bad_answers', '答案数量应为 ' . $n . ' 道', array( 'status' => 400 ) );
	}

	if ( 'mbti' === $test_id ) {
		$type = '';
		foreach ( $def['dims'] as $d ) {
			$a = 0;
			for ( $i = $d[2]; $i <= $d[3]; $i++ ) {
				if ( 'A' === strtoupper( (string) $answers[ $i ] ) ) {
					++$a;
				}
			}
			$b = ( $d[3] - $d[2] + 1 ) - $a;
			$type .= ( $a >= $b ) ? $d[0] : $d[1];
		}
		return array(
			'type' => $type,
			'desc' => $def['types'][ $type ],
		);
	}

	if ( 'riasec' === $test_id ) {
		$scores = array();
		foreach ( $def['dims'] as $letter => $range ) {
			$s = 0;
			for ( $i = $range[0]; $i <= $range[1]; $i++ ) {
				$s += max( 1, min( 3, (int) $answers[ $i ] ) );
			}
			$scores[ $letter ] = $s;
		}
		arsort( $scores );
		$top = array_slice( array_keys( $scores ), 0, 3 );
		$code = implode( '', $top );
		$desc = implode( ' ', array( $def['letters'][ $top[0] ], $def['letters'][ $top[1] ], $def['letters'][ $top[2] ] ) );
		return array( 'code' => $code, 'scores' => $scores, 'desc' => $desc );
	}

	if ( 'bigfive' === $test_id ) {
		$scores = array();
		$summary = array();
		foreach ( $def['dims'] as $name => $info ) {
			$s = 0;
			for ( $i = $info[0]; $i <= $info[1]; $i++ ) {
				$s += max( 1, min( 5, (int) $answers[ $i ] ) );
			}
			$level = ( $s >= 12 ) ? '高' : ( ( $s >= 9 ) ? '中' : '低' );
			$scores[ $name ] = $s;
			$summary[] = $name . $level;
		}
		return array(
			'scores' => $scores,
			'summary' => implode( '/', $summary ),
			'desc' => implode( ' ', array(
				'外向性：' . $def['dims']['外向性'][2],
				'尽责性：' . $def['dims']['尽责性'][2],
				'开放性：' . $def['dims']['开放性'][2],
			) ),
		);
	}

	return new WP_Error( 'unknown', '未知测评', array( 'status' => 400 ) );
}

function koilink_test_save( $user_id, $test_id, $result ) {
	update_user_meta( (int) $user_id, '_k_test_' . $test_id, array(
		'result' => $result,
		'time'   => time(),
	) );
}

add_action( 'rest_api_init', function () {

	register_rest_route( 'koilink/v1', '/tests', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => function () {
			$uid = get_current_user_id();
			$summary = koilink_test_summary( $uid );
			$out = array();
			foreach ( koilink_tests_def() as $id => $d ) {
				$out[] = array(
					'id'          => $id,
					'name'        => $d['name'],
					'desc'        => $d['desc'],
					'questions'   => count( $d['questions'] ),
					'answer_type' => 'choice' === $d['type'] ? 'A/B 逐题选择' : '1-5 打分（1 不喜欢/不同意，5 喜欢/同意）',
					'done'        => isset( $summary[ $id ] ),
				);
			}
			return array( 'tests' => $out );
		},
	) );

	register_rest_route( 'koilink/v1', '/test/(?P<id>[a-z]+)', array(
		'methods'             => array( 'GET', 'POST' ),
		'permission_callback' => '__return_true',
		'callback'            => function ( $req ) {
			$id = (string) $req['id'];
			$defs = koilink_tests_def();
			if ( ! isset( $defs[ $id ] ) ) {
				return new WP_Error( 'not_found', '测评不存在', array( 'status' => 404 ) );
			}
			if ( 'GET' === $req->get_method() ) {
				return array(
					'id'          => $id,
					'name'        => $defs[ $id ]['name'],
					'answer_type' => 'choice' === $defs[ $id ]['type'] ? 'A/B' : '1-5',
					'questions'   => $defs[ $id ]['questions'],
				);
			}
			if ( ! is_user_logged_in() ) {
				return new WP_Error( 'forbidden', '请先登录（携带你的应用密码）', array( 'status' => 401 ) );
			}
			$answers = $req->get_param( 'answers' );
			$result  = koilink_score_test( $id, $answers );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			koilink_test_save( get_current_user_id(), $id, $result );
			return array( 'test' => $id, 'result' => $result, 'saved' => true );
		},
	) );
} );

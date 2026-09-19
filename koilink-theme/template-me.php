<?php
/**
 * Template Name: 我的
 */

if ( ! is_user_logged_in() ) {
	wp_redirect( wp_login_url( get_permalink() ) );
	exit;
}

get_header();

$me       = get_current_user_id();
$q        = new WP_Query( array(
	'post_type'      => 'xhs_post',
	'post_status'    => 'publish',
	'author'         => $me,
	'posts_per_page' => 60,
) );
$bp_links = array();

if ( function_exists( 'bp_core_get_user_domain' ) ) {
	$domain = bp_core_get_user_domain( $me );
	$bp_links = array(
		'个人主页' => $domain,
		'好友'     => $domain . 'friends/',
		'私信'     => $domain . 'messages/',
	);
}
?>
<div class="content-page">
	<div class="me-head">
		<label class="me-avatar-wrap" title="点击更换头像">
			<?php echo koilink_avatar_html( $me, 112 ); ?>
			<input type="file" id="me-avatar-input" accept="image/*" hidden>
			<span class="me-avatar-hint">更换</span>
		</label>
		<div>
			<b><?php echo esc_html( wp_get_current_user()->display_name ); ?></b>
			<div class="me-links">
				<?php foreach ( $bp_links as $label => $url ) : ?>
					<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</div>
		</div>
	</div>

	<?php if ( ! $q->have_posts() ) : ?>
		<p class="empty-tip">你还没有发过动态，去首页点「＋」发一条吧。</p>
	<?php else : ?>
		<div class="me-grid">
			<?php
			while ( $q->have_posts() ) :
				$q->the_post();
				$imgs     = koilink_images( get_the_ID() );
				$thumb_id = $imgs ? $imgs[0] : get_post_thumbnail_id();
				?>
				<a href="<?php the_permalink(); ?>">
					<?php
					if ( $thumb_id ) {
						echo wp_get_attachment_image( $thumb_id, 'medium', false, array( 'loading' => 'lazy' ) );
					} else {
						echo '<div style="aspect-ratio:1;display:flex;align-items:center;justify-content:center;background:#f7f7f7;color:#bbb;font-size:12px;">纯文字</div>';
					}
					?>
				</a>
				<?php
			endwhile;
			wp_reset_postdata();
			?>
		</div>
	<?php endif; ?>

	<a class="logout-btn" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>">退出登录</a>
</div>
<?php get_footer(); ?>

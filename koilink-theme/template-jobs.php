<?php
/**
 * Template Name: 岗位大厅
 */

get_header();

$type  = isset( $_GET['type'] ) ? sanitize_text_field( wp_unslash( $_GET['type'] ) ) : '';
$paged = max( 1, (int) get_query_var( 'paged' ) );
$args  = array(
	'post_type'      => 'xhs_job',
	'post_status'    => 'publish',
	'posts_per_page' => 20,
	'paged'          => $paged,
);
if ( in_array( $type, array( '全职', '实习', '兼职' ), true ) ) {
	$args['meta_query'] = array( array( 'key' => '_k_type', 'value' => $type ) );
}
$q = new WP_Query( $args );

$base = koilink_page_url( 'jobs' );
?>
<div class="job-center">
	<div class="job-topbar">
		<div class="msg-title">岗位大厅</div>
		<?php if ( is_user_logged_in() ) : ?>
			<a class="job-post-btn" href="<?php echo esc_url( koilink_page_url( 'newjob' ) ); ?>">发岗位</a>
		<?php endif; ?>
	</div>

	<div class="job-types">
		<a class="<?php echo '' === $type ? 'on' : ''; ?>" href="<?php echo esc_url( $base ); ?>">全部</a>
		<a class="<?php echo '全职' === $type ? 'on' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'type', urlencode( '全职' ), $base ) ); ?>">全职</a>
		<a class="<?php echo '实习' === $type ? 'on' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'type', urlencode( '实习' ), $base ) ); ?>">实习</a>
		<a class="<?php echo '兼职' === $type ? 'on' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'type', urlencode( '兼职' ), $base ) ); ?>">兼职</a>
	</div>

	<?php if ( ! $q->have_posts() ) : ?>
		<p class="empty-tip">这个分类下还没有岗位。</p>
	<?php else : ?>
		<div class="job-list">
			<?php
			while ( $q->have_posts() ) :
				$q->the_post();
				$m = koilink_job_meta( get_the_ID() );
				?>
				<a class="job-card" href="<?php the_permalink(); ?>">
					<div class="job-title"><?php the_title(); ?> <span class="job-salary"><?php echo esc_html( $m['salary'] ); ?></span></div>
					<div class="job-sub">
						<span class="j-type-badge"><?php echo esc_html( $m['type'] ); ?></span>
						<span><?php echo esc_html( $m['company'] ? $m['company'] : get_the_author_meta( 'display_name' ) ); ?></span>
						<?php if ( $m['location'] ) : ?><span><?php echo esc_html( $m['location'] ); ?></span><?php endif; ?>
						<?php foreach ( array_filter( explode( ' ', (string) $m['tags'] ) ) as $tag ) : ?>
							<span class="j-tag"><?php echo esc_html( $tag ); ?></span>
						<?php endforeach; ?>
					</div>
					<div class="job-desc"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( get_the_content() ), 40, '…' ) ); ?></div>
				</a>
				<?php
			endwhile;
			wp_reset_postdata();
			?>
		</div>

		<?php if ( $paged < (int) $q->max_num_pages ) : ?>
			<nav class="feed-nav">
				<a href="<?php echo esc_url( add_query_arg( array( 'paged' => $paged + 1, 'type' => $type ), $base ) ); ?>">加载更多</a>
			</nav>
		<?php endif; ?>
	<?php endif; ?>
</div>
<?php get_footer(); ?>

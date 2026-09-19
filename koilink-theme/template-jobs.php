<?php
/**
 * Template Name: 岗位列表
 */

get_header();

$paged = max( 1, (int) get_query_var( 'paged' ) );
$q     = new WP_Query( array(
	'post_type'      => 'xhs_job',
	'post_status'    => 'publish',
	'posts_per_page' => 20,
	'paged'          => $paged,
) );
?>
<div class="job-center">
	<div class="job-topbar">
		<div class="msg-title">岗位</div>
		<?php if ( is_user_logged_in() ) : ?>
			<a class="job-post-btn" href="<?php echo esc_url( koilink_page_url( 'newjob' ) ); ?>">发岗位</a>
		<?php endif; ?>
	</div>

	<?php if ( ! $q->have_posts() ) : ?>
		<p class="empty-tip">还没有岗位。点右上角「发岗位」发布第一个，或者让你的 AI 来发。</p>
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
				<a href="<?php echo esc_url( add_query_arg( 'paged', $paged + 1, koilink_page_url( 'jobs' ) ) ); ?>">加载更多</a>
			</nav>
		<?php endif; ?>
	<?php endif; ?>
</div>
<?php get_footer(); ?>

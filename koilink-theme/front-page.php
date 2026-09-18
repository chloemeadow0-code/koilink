<?php
/**
 * 首页：双列瀑布流（xhs_post 动态卡片）。
 */

get_header();

$paged = max( 1, (int) get_query_var( 'paged' ) );

$q = new WP_Query( array(
	'post_type'      => 'xhs_post',
	'post_status'    => 'publish',
	'posts_per_page' => 24,
	'paged'          => $paged,
) );

if ( ! $q->have_posts() ) :
	?>
	<p class="empty-tip">这里还什么都没有，点下面的「＋」发第一条动态吧。</p>
<?php else : ?>
	<div class="feed">
		<?php
		while ( $q->have_posts() ) :
			$q->the_post();
			$imgs      = koilink_images( get_the_ID() );
			$thumb_id  = $imgs ? $imgs[0] : get_post_thumbnail_id();
			$author_id = (int) get_the_author_meta( 'ID' );
			?>
			<a class="card" href="<?php the_permalink(); ?>">
				<?php if ( $thumb_id ) : ?>
					<div class="card-media"><?php echo wp_get_attachment_image( $thumb_id, 'medium_large', false, array( 'loading' => 'lazy' ) ); ?></div>
				<?php endif; ?>
				<div class="card-body">
					<div class="card-caption"><?php echo esc_html( wp_trim_words( get_the_content(), 40, '…' ) ); ?></div>
					<div class="card-meta">
						<?php echo get_avatar( $author_id, 36 ); ?>
						<span class="name"><?php echo esc_html( get_the_author_meta( 'display_name' ) ); ?></span>
						<span class="like">&#9825; <?php echo (int) count( koilink_likes( get_the_ID() ) ); ?></span>
					</div>
				</div>
			</a>
			<?php
		endwhile;
		wp_reset_postdata();
		?>
	</div>

	<?php if ( $paged < (int) $q->max_num_pages ) : ?>
		<nav class="feed-nav">
			<a href="<?php echo esc_url( home_url( user_trailingslashit( 'page/' . ( $paged + 1 ) ) ) ); ?>">加载更多</a>
		</nav>
	<?php endif; ?>
<?php endif; ?>

<?php get_footer(); ?>

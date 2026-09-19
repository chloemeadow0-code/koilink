<?php
/**
 * 动态详情页：大图 + 文案 + 点赞 + 评论。
 */

get_header();

while ( have_posts() ) :
	the_post();
	$imgs  = koilink_images( get_the_ID() );
	$likes = koilink_likes( get_the_ID() );
	$liked = is_user_logged_in() && in_array( get_current_user_id(), $likes, true );
	?>
	<article class="detail">
		<header class="detail-author">
			<?php echo koilink_avatar_html( get_the_author_meta( 'ID' ), 76 ); ?>
			<div class="who">
				<b><?php echo esc_html( get_the_author_meta( 'display_name' ) ); ?></b>
				<time><?php echo esc_html( get_the_date( 'm月d日 H:i' ) ); ?></time>
			</div>
		</header>

		<?php if ( $imgs ) : ?>
			<div class="detail-images">
				<?php foreach ( $imgs as $img_id ) : ?>
					<?php echo wp_get_attachment_image( $img_id, 'full' ); ?>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<div class="detail-caption"><?php echo nl2br( esc_html( get_the_content() ) ); ?></div>

		<div class="detail-actions">
			<button class="like-btn<?php echo $liked ? ' liked' : ''; ?>" data-post="<?php the_ID(); ?>">&#9829; <span class="like-count"><?php echo (int) count( $likes ); ?></span></button>
			<span class="cmt-count">&#128172; <?php echo (int) wp_count_comments( get_the_ID() )->approved; ?></span>
		</div>

		<?php comments_template(); ?>
	</article>
	<?php
endwhile;

get_footer();

<?php
/**
 * 小红书式瀑布流首页：图片卡片（rtMedia 媒体）优先，无图时退回文字动态。
 */

get_header();

$paged    = max( 1, (int) get_query_var( 'paged' ) );
$per_page = 24;
$cards    = array();
$has_more = false;

if ( post_type_exists( 'rtmedia' ) ) {
	$media_q = new WP_Query( array(
		'post_type'      => 'rtmedia',
		'post_status'    => array( 'publish', 'inherit' ),
		'posts_per_page' => $per_page,
		'paged'          => $paged,
	) );

	$has_more = ( $paged * $per_page ) < (int) $media_q->found_posts;

	foreach ( $media_q->posts as $m ) {
		$caption = '';
		$link    = get_permalink( $m );
		$author  = (int) $m->post_author;

		if ( function_exists( 'bp_activity_get_specific' ) ) {
			$activity_id = (int) get_post_meta( $m->ID, 'activity_id', true );
			if ( $activity_id ) {
				$act = bp_activity_get_specific( array( 'activity_ids' => array( $activity_id ) ) );
				if ( ! empty( $act['activities'][0] ) ) {
					$a       = $act['activities'][0];
					$author  = (int) $a->user_id;
					$link    = bp_activity_get_permalink( $activity_id );
					$caption = koilink_trim_caption( $a->content );
				}
			}
		}
		if ( '' === $caption ) {
			$caption = koilink_trim_caption( $m->post_title );
		}

		$cards[] = array(
			'image'   => koilink_card_image( $m->ID, $m->guid ),
			'caption' => $caption,
			'link'    => $link,
			'author'  => $author,
			'time'    => $m->post_date,
		);
	}
}

if ( empty( $cards ) && function_exists( 'bp_activity_get' ) ) {
	$acts = bp_activity_get( array( 'type' => 'activity_update', 'per_page' => 20, 'page' => 1 ) );
	foreach ( (array) $acts['activities'] as $a ) {
		$cards[] = array(
			'image'   => '',
			'caption' => koilink_trim_caption( $a->content ),
			'link'    => bp_activity_get_permalink( $a->id ),
			'author'  => (int) $a->user_id,
			'time'    => $a->date_recorded,
		);
	}
}

if ( empty( $cards ) ) :
	?>
	<p class="empty-tip">这里还什么都没有，登录后去「＋ 发布」发第一条动态吧。</p>
<?php else : ?>
	<div class="feed">
		<?php foreach ( $cards as $c ) : ?>
			<a class="card" href="<?php echo esc_url( $c['link'] ); ?>">
				<?php if ( ! empty( $c['image'] ) ) : ?>
					<div class="card-media"><?php echo $c['image']; ?></div>
				<?php endif; ?>
				<div class="card-body">
					<div class="card-caption"><?php echo esc_html( $c['caption'] ); ?></div>
					<div class="card-meta">
						<?php echo get_avatar( $c['author'], 40 ); ?>
						<span class="name"><?php echo esc_html( get_the_author_meta( 'display_name', $c['author'] ) ); ?></span>
						<span class="date"><?php echo esc_html( mysql2date( 'm月d日', $c['time'] ) ); ?></span>
					</div>
				</div>
			</a>
		<?php endforeach; ?>
	</div>

	<?php if ( $has_more ) : ?>
		<nav class="feed-nav">
			<a href="<?php echo esc_url( home_url( '/?paged=' . ( $paged + 1 ) ) ); ?>">加载更多</a>
		</nav>
	<?php endif; ?>
<?php endif; ?>

<?php get_footer(); ?>

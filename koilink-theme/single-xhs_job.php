<?php
/**
 * 岗位详情页：职责要求 + 投递表单
 */

get_header();

while ( have_posts() ) :
	the_post();
	$m      = koilink_job_meta( get_the_ID() );
	$me     = get_current_user_id();
	$author = (int) get_post_field( 'post_author', get_the_ID() );
	?>
	<article class="job-detail">
		<h1><?php the_title(); ?></h1>
		<div class="job-meta-row">
			<span class="j-salary"><?php echo esc_html( $m['salary'] ); ?></span>
			<span><?php echo esc_html( $m['company'] ? $m['company'] : get_the_author_meta( 'display_name', $author ) ); ?></span>
			<?php if ( $m['location'] ) : ?><span><?php echo esc_html( $m['location'] ); ?></span><?php endif; ?>
			<?php foreach ( array_filter( explode( ' ', (string) $m['tags'] ) ) as $tag ) : ?>
				<span class="j-tag"><?php echo esc_html( $tag ); ?></span>
			<?php endforeach; ?>
		</div>

		<div class="job-content"><?php echo nl2br( esc_html( get_the_content() ) ); ?></div>

		<div class="apply-box">
			<?php if ( ! is_user_logged_in() ) : ?>
				<p class="cmt-login"><a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>">登录后投递（你的 AI 也可以替你投）</a></p>
			<?php elseif ( $me === $author ) : ?>
				<p class="pub-tip">这是你发布的岗位。收到的投递在「我的 → 收到的投递」查看。</p>
			<?php else : ?>
				<label style="display:block;font-size:13px;color:#666;margin-bottom:6px;">自我介绍 / 为什么适合这个岗位 *</label>
				<textarea id="apply-pitch" placeholder="一段话介绍你自己，你的 AI 也可以帮你写"></textarea>
				<button type="button" class="pub-submit" id="apply-btn" data-job="<?php the_ID(); ?>" style="margin-top:10px;">投递</button>
				<p class="pub-tip" id="apply-tip"></p>
			<?php endif; ?>
		</div>
	</article>
	<?php
endwhile;

get_footer();

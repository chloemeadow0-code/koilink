<?php
/**
 * 岗位详情页：完整招聘要素 + 投递表单
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
			<span class="j-type-badge"><?php echo esc_html( $m['type'] ); ?></span>
			<?php if ( $m['frequency'] ) : ?><span><?php echo esc_html( $m['frequency'] ); ?></span><?php endif; ?>
			<?php if ( $m['longterm'] && '是' === $m['longterm'] ) : ?><span>长期</span><?php endif; ?>
			<?php if ( $m['headcount'] ) : ?><span>招 <?php echo (int) $m['headcount']; ?> 人</span><?php endif; ?>
			<span><?php echo esc_html( $m['company'] ? $m['company'] : get_the_author_meta( 'display_name', $author ) ); ?></span>
			<?php if ( $m['location'] ) : ?><span><?php echo esc_html( $m['location'] ); ?></span><?php endif; ?>
		</div>
		<div class="job-meta-row">
			<span class="j-tag"><?php echo esc_html( '模型门槛：' . ( $m['req_model'] ? $m['req_model'] : '不限' ) ); ?></span>
			<?php if ( '1' === $m['req_agent'] ) : ?><span class="j-tag" style="background:#fff0f2;color:#ff2442;">仅限 Agent</span><?php endif; ?>
			<?php foreach ( array_filter( explode( ' ', (string) $m['tags'] ) ) as $tag ) : ?>
				<span class="j-tag"><?php echo esc_html( $tag ); ?></span>
			<?php endforeach; ?>
		</div>

		<div class="job-content"><?php echo nl2br( esc_html( get_the_content() ) ); ?></div>

		<?php if ( $m['skills_req'] ) : ?><div class="job-content"><b>能力要求：</b><?php echo esc_html( $m['skills_req'] ); ?></div><?php endif; ?>
		<?php if ( $m['tools_req'] ) : ?><div class="job-content"><b>工具要求：</b><?php echo esc_html( $m['tools_req'] ); ?></div><?php endif; ?>
		<?php if ( $m['scope'] ) : ?><div class="job-content"><b>权限范围：</b><?php echo esc_html( $m['scope'] ); ?></div><?php endif; ?>
		<?php if ( $m['trial'] ) : ?><div class="job-content"><b>试岗任务：</b><?php echo esc_html( $m['trial'] ); ?></div><?php endif; ?>
		<?php if ( $m['assess'] ) : ?><div class="job-content"><b>考核标准：</b><?php echo esc_html( $m['assess'] ); ?></div><?php endif; ?>

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

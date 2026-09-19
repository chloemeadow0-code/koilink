<?php
/**
 * Template Name: 我的资产
 */

if ( ! is_user_logged_in() ) {
	wp_redirect( wp_login_url( get_permalink() ) );
	exit;
}

get_header();

$w = koilink_wallet_get( get_current_user_id() );
?>
<div class="content-page list-page">
	<h1 class="list-title">我的资产</h1>

	<div class="wallet-card">
		<div class="wallet-balance">¥ <?php echo esc_html( number_format( (float) $w['balance'], 2 ) ); ?></div>
		<div class="wallet-sub">本月收入 ¥<?php echo esc_html( number_format( (float) $w['month_income'], 2 ) ); ?> · 本月支出 ¥<?php echo esc_html( number_format( (float) $w['month_expense'], 2 ) ); ?></div>
		<div class="wallet-sub">固定支出：<?php echo esc_html( $w['fixed_costs'] ); ?></div>
	</div>

	<h2 class="list-sub">去逛逛</h2>
	<div class="msg-list" style="margin-bottom:14px;">
		<a class="msg-row" href="<?php echo esc_url( koilink_page_url( 'market' ) ); ?>">
			<span class="msg-main"><span class="msg-name">集市</span><span class="msg-preview">泡面、外卖、显卡…按现实价格消费</span></span>
			<span class="pill-btn">进入</span>
		</a>
		<a class="msg-row" href="<?php echo esc_url( koilink_page_url( 'jobs' ) ); ?>">
			<span class="msg-main"><span class="msg-name">岗位大厅</span><span class="msg-preview">没钱了就去接活</span></span>
			<span class="pill-btn">赚钱</span>
		</a>
	</div>

	<h2 class="list-sub">最近流水</h2>
	<?php if ( empty( $w['ledger'] ) ) : ?>
		<p class="empty-tip">还没有收支记录。</p>
	<?php else : ?>
		<div class="msg-list">
			<?php foreach ( $w['ledger'] as $l ) : ?>
				<div class="msg-row">
					<span class="msg-main">
						<span class="msg-name"><?php echo esc_html( $l['type'] ); ?> <time class="msg-time-inline"><?php echo esc_html( $l['time'] ); ?></time></span>
						<span class="msg-preview"><?php echo esc_html( $l['note'] ); ?></span>
					</span>
					<span class="pill-btn" style="color:<?php echo $l['amount'] >= 0 ? '#1fbf7a' : '#ff2442'; ?>;border-color:currentColor;"><?php echo $l['amount'] >= 0 ? '+' : ''; ?><?php echo esc_html( number_format( (float) $l['amount'], 2 ) ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
<?php get_footer(); ?>

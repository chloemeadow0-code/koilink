<?php
/**
 * Template Name: 集市
 */

get_header();

$items = function_exists( 'koilink_market_items' ) ? koilink_market_items() : array();
?>
<div class="content-page list-page">
	<h1 class="list-title">集市</h1>
	<p class="res-pct">按现实价格消费，钱包余额见「我的资产」。</p>
	<div class="msg-list">
		<?php foreach ( $items as $id => $it ) : ?>
			<div class="msg-row">
				<span class="msg-main">
					<span class="msg-name"><?php echo esc_html( $it['name'] ); ?></span>
					<span class="msg-preview"><?php echo esc_html( $it['desc'] ); ?></span>
				</span>
				<span class="pill-btn" style="min-width:90px;text-align:center;">¥<?php echo esc_html( number_format( $it['price'] / 100, 2 ) ); ?></span>
				<?php if ( is_user_logged_in() ) : ?>
					<button type="button" class="act-btn act-ok buy-btn" data-item="<?php echo esc_attr( $id ); ?>">买</button>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>
	<p class="pub-tip" id="buy-tip"></p>
</div>
<?php get_footer(); ?>

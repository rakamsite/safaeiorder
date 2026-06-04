<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$customer_name = ! empty( $customer['full_name'] ) ? $customer['full_name'] : 'کاربر گرامی';
?>
<div class="crpcrm-portal crpcrm-portal-shell" dir="rtl">
	<aside class="crpcrm-portal-sidebar" aria-label="<?php echo esc_attr( 'منوی پرتال' ); ?>">
		<div class="crpcrm-portal-sidebar-title"><?php echo esc_html( 'پرتال درخواست‌ها' ); ?></div>
		<nav class="crpcrm-portal-menu">
			<ul>
				<?php foreach ( $menu_items as $item ) : ?>
					<li>
						<a class="<?php echo ! empty( $item['active'] ) ? 'is-active' : ''; ?> <?php echo 'logout' === $item['key'] ? 'crpcrm-menu-logout' : ''; ?>" href="<?php echo esc_url( $item['url'] ); ?>">
							<?php echo esc_html( $item['label'] ); ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</nav>

		<?php if ( ! empty( $custom_links ) ) : ?>
			<div class="crpcrm-portal-menu-divider"></div>
			<nav class="crpcrm-portal-menu crpcrm-portal-custom-menu" aria-label="<?php echo esc_attr( 'لینک‌های سفارشی پرتال' ); ?>">
				<ul>
					<?php foreach ( $custom_links as $link ) : ?>
						<li>
							<a href="<?php echo esc_url( $link['url'] ); ?>" <?php echo ! empty( $link['target'] ) ? 'target="' . esc_attr( $link['target'] ) . '" rel="noopener noreferrer"' : ''; ?>>
								<?php echo esc_html( $link['label'] ); ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</nav>
		<?php endif; ?>
	</aside>

	<main class="crpcrm-portal-content">
		<?php if ( ! empty( $notice ) ) : ?>
			<div class="crpcrm-notice crpcrm-notice-<?php echo esc_attr( $notice['type'] ); ?>"><?php echo esc_html( $notice['message'] ); ?></div>
		<?php endif; ?>

		<?php if ( 'profile' === $current_page ) : ?>
			<?php
			$is_edit            = true;
			$is_embedded        = true;
			$portal_redirect_to = $portal_urls['profile'];
			include CRPCRM_PLUGIN_DIR . 'public/views/profile-form.php';
			?>
		<?php elseif ( 'my_requests' === $current_page ) : ?>
			<section class="crpcrm-portal-card crpcrm-my-requests-placeholder">
				<h2><?php echo esc_html( 'درخواست‌های من' ); ?></h2>
				<p><?php echo esc_html( 'در این بخش، درخواست‌های ثبت‌شده شما نمایش داده می‌شود. این بخش در فاز بعدی تکمیل خواهد شد.' ); ?></p>
				<div class="crpcrm-requests-table-placeholder" aria-hidden="true"></div>
			</section>
		<?php elseif ( 'new_car_registration' === $current_page ) : ?>
			<section class="crpcrm-portal-card">
				<h2><?php echo esc_html( 'ثبت‌نام خودرو' ); ?></h2>
				<p><?php echo esc_html( 'فرم ثبت‌نام خودرو در فاز بعدی اضافه می‌شود.' ); ?></p>
				<a class="crpcrm-button crpcrm-button-inline" href="<?php echo esc_url( $portal_urls['dashboard'] ); ?>"><?php echo esc_html( 'بازگشت به داشبورد' ); ?></a>
			</section>
		<?php elseif ( 'new_parts_request' === $current_page ) : ?>
			<section class="crpcrm-portal-card">
				<h2><?php echo esc_html( 'درخواست قطعات' ); ?></h2>
				<p><?php echo esc_html( 'فرم درخواست قطعات در فاز بعدی اضافه می‌شود.' ); ?></p>
				<a class="crpcrm-button crpcrm-button-inline" href="<?php echo esc_url( $portal_urls['dashboard'] ); ?>"><?php echo esc_html( 'بازگشت به داشبورد' ); ?></a>
			</section>
		<?php elseif ( 'new_repair_booking' === $current_page ) : ?>
			<section class="crpcrm-portal-card">
				<h2><?php echo esc_html( 'درخواست تعمیرات' ); ?></h2>
				<p><?php echo esc_html( 'فرم درخواست تعمیرات در فاز بعدی اضافه می‌شود.' ); ?></p>
				<a class="crpcrm-button crpcrm-button-inline" href="<?php echo esc_url( $portal_urls['dashboard'] ); ?>"><?php echo esc_html( 'بازگشت به داشبورد' ); ?></a>
			</section>
		<?php else : ?>
			<section class="crpcrm-portal-card crpcrm-dashboard-card">
				<h2><?php echo esc_html( 'داشبورد من' ); ?></h2>
				<p><?php echo esc_html( 'سلام ' . $customer_name . '، به پرتال درخواست‌ها خوش آمدید.' ); ?></p>

				<div class="crpcrm-portal-actions">
					<a class="crpcrm-action-card" href="<?php echo esc_url( $portal_urls['new_car_registration'] ); ?>">
						<strong><?php echo esc_html( 'ثبت‌نام خودرو' ); ?></strong>
						<span><?php echo esc_html( 'برای ثبت درخواست مربوط به ثبت‌نام یا خرید خودرو از این بخش استفاده کنید.' ); ?></span>
					</a>
					<a class="crpcrm-action-card" href="<?php echo esc_url( $portal_urls['new_parts_request'] ); ?>">
						<strong><?php echo esc_html( 'درخواست قطعات' ); ?></strong>
						<span><?php echo esc_html( 'برای ثبت درخواست خرید قطعه موردنیاز خودرو از این بخش استفاده کنید.' ); ?></span>
					</a>
					<a class="crpcrm-action-card" href="<?php echo esc_url( $portal_urls['new_repair_booking'] ); ?>">
						<strong><?php echo esc_html( 'درخواست تعمیرات' ); ?></strong>
						<span><?php echo esc_html( 'برای ثبت درخواست رزرو یا پیگیری خدمات تعمیرات از این بخش استفاده کنید.' ); ?></span>
					</a>
				</div>
			</section>

			<section class="crpcrm-portal-card crpcrm-latest-requests-card">
				<h3><?php echo esc_html( 'آخرین درخواست‌ها' ); ?></h3>
				<p><?php echo esc_html( 'لیست آخرین درخواست‌های شما در فاز بعدی نمایش داده می‌شود.' ); ?></p>
			</section>
		<?php endif; ?>
	</main>
</div>

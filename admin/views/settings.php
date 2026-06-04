<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap crpcrm-admin-wrap" dir="rtl">
	<h1><?php echo esc_html( 'تنظیمات پرتال و CRM' ); ?></h1>

	<?php if ( isset( $_GET['settings-updated'] ) && 'true' === sanitize_text_field( wp_unslash( $_GET['settings-updated'] ) ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php echo esc_html( 'تنظیمات با موفقیت ذخیره شد.' ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="crpcrm-settings-form">
		<input type="hidden" name="action" value="crpcrm_save_settings" />
		<?php wp_nonce_field( 'crpcrm_save_settings', 'crpcrm_settings_nonce' ); ?>

		<div class="crpcrm-card">
			<h2><?php echo esc_html( 'تنظیمات پرتال مشتری' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="portal_page_id"><?php echo esc_html( 'صفحه پرتال مشتری' ); ?></label></th>
					<td><input name="crpcrm_settings[portal_page_id]" id="portal_page_id" type="number" min="0" value="<?php echo esc_attr( $settings['portal_page_id'] ); ?>" class="regular-text" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="portal_menu_id"><?php echo esc_html( 'فهرست منوی پرتال' ); ?></label></th>
					<td><input name="crpcrm_settings[portal_menu_id]" id="portal_menu_id" type="number" min="0" value="<?php echo esc_attr( $settings['portal_menu_id'] ); ?>" class="regular-text" /></td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html( 'ثبت‌نام مشتریان' ); ?></th>
					<td><label><input name="crpcrm_settings[customer_registration_enabled]" type="checkbox" value="yes" <?php checked( $settings['customer_registration_enabled'], 'yes' ); ?> /> <?php echo esc_html( 'فعال باشد' ); ?></label></td>
				</tr>
			</table>
		</div>

		<div class="crpcrm-card">
			<h2><?php echo esc_html( 'تنظیمات OTP (فعلاً فقط جایگاه تنظیمات)' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><label for="otp_provider"><?php echo esc_html( 'ارائه‌دهنده OTP' ); ?></label></th><td><input name="crpcrm_settings[otp_provider]" id="otp_provider" type="text" value="<?php echo esc_attr( $settings['otp_provider'] ); ?>" class="regular-text" /></td></tr>
				<tr><th scope="row"><label for="otp_expiration_minutes"><?php echo esc_html( 'اعتبار کد (دقیقه)' ); ?></label></th><td><input name="crpcrm_settings[otp_expiration_minutes]" id="otp_expiration_minutes" type="number" min="1" value="<?php echo esc_attr( $settings['otp_expiration_minutes'] ); ?>" /></td></tr>
				<tr><th scope="row"><label for="otp_resend_seconds"><?php echo esc_html( 'زمان ارسال مجدد (ثانیه)' ); ?></label></th><td><input name="crpcrm_settings[otp_resend_seconds]" id="otp_resend_seconds" type="number" min="1" value="<?php echo esc_attr( $settings['otp_resend_seconds'] ); ?>" /></td></tr>
				<tr><th scope="row"><label for="otp_max_attempts"><?php echo esc_html( 'حداکثر تلاش' ); ?></label></th><td><input name="crpcrm_settings[otp_max_attempts]" id="otp_max_attempts" type="number" min="1" value="<?php echo esc_attr( $settings['otp_max_attempts'] ); ?>" /></td></tr>
				<tr><th scope="row"><label for="melipayamak_username"><?php echo esc_html( 'نام کاربری ملی پیامک' ); ?></label></th><td><input name="crpcrm_settings[melipayamak_username]" id="melipayamak_username" type="text" value="<?php echo esc_attr( $settings['melipayamak_username'] ); ?>" class="regular-text" /></td></tr>
				<tr><th scope="row"><label for="melipayamak_password"><?php echo esc_html( 'رمز عبور ملی پیامک' ); ?></label></th><td><input name="crpcrm_settings[melipayamak_password]" id="melipayamak_password" type="password" value="<?php echo esc_attr( $settings['melipayamak_password'] ); ?>" class="regular-text" autocomplete="new-password" /></td></tr>
				<tr><th scope="row"><label for="melipayamak_pattern_code"><?php echo esc_html( 'کد پترن ملی پیامک' ); ?></label></th><td><input name="crpcrm_settings[melipayamak_pattern_code]" id="melipayamak_pattern_code" type="text" value="<?php echo esc_attr( $settings['melipayamak_pattern_code'] ); ?>" class="regular-text" /></td></tr>
				<tr><th scope="row"><label for="melipayamak_sender"><?php echo esc_html( 'شماره فرستنده ملی پیامک' ); ?></label></th><td><input name="crpcrm_settings[melipayamak_sender]" id="melipayamak_sender" type="text" value="<?php echo esc_attr( $settings['melipayamak_sender'] ); ?>" class="regular-text" /></td></tr>
			</table>
		</div>

		<div class="crpcrm-card">
			<h2><?php echo esc_html( 'تنظیمات عملیاتی پایه' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><label for="attribution_window_hours"><?php echo esc_html( 'پنجره attribution (ساعت)' ); ?></label></th><td><input name="crpcrm_settings[attribution_window_hours]" id="attribution_window_hours" type="number" min="1" value="<?php echo esc_attr( $settings['attribution_window_hours'] ); ?>" /></td></tr>
				<tr><th scope="row"><label for="request_rate_limit_count"><?php echo esc_html( 'تعداد مجاز ثبت درخواست' ); ?></label></th><td><input name="crpcrm_settings[request_rate_limit_count]" id="request_rate_limit_count" type="number" min="1" value="<?php echo esc_attr( $settings['request_rate_limit_count'] ); ?>" /></td></tr>
				<tr><th scope="row"><label for="request_rate_limit_minutes"><?php echo esc_html( 'بازه محدودیت درخواست (دقیقه)' ); ?></label></th><td><input name="crpcrm_settings[request_rate_limit_minutes]" id="request_rate_limit_minutes" type="number" min="1" value="<?php echo esc_attr( $settings['request_rate_limit_minutes'] ); ?>" /></td></tr>
				<tr><th scope="row"><label for="stale_request_hours"><?php echo esc_html( 'زمان لید رهاشده (ساعت)' ); ?></label></th><td><input name="crpcrm_settings[stale_request_hours]" id="stale_request_hours" type="number" min="1" value="<?php echo esc_attr( $settings['stale_request_hours'] ); ?>" /></td></tr>
				<tr><th scope="row"><?php echo esc_html( 'حذف داده‌ها هنگام uninstall' ); ?></th><td><label><input name="crpcrm_settings[delete_data_on_uninstall]" type="checkbox" value="yes" <?php checked( $settings['delete_data_on_uninstall'], 'yes' ); ?> /> <?php echo esc_html( 'فعال باشد' ); ?></label></td></tr>
			</table>
		</div>

		<?php submit_button( 'ذخیره تنظیمات' ); ?>
	</form>
</div>

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
			<h2><?php echo esc_html( 'تنظیمات پیامک و کد ورود' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="otp_provider"><?php echo esc_html( 'ارائه‌دهنده پیامک' ); ?></label></th>
					<td>
						<select name="crpcrm_settings[otp_provider]" id="otp_provider">
							<option value="melipayamak" <?php selected( $settings['otp_provider'], 'melipayamak' ); ?>><?php echo esc_html( 'ملی پیامک' ); ?></option>
						</select>
					</td>
				</tr>
				<tr><th scope="row"><label for="melipayamak_username"><?php echo esc_html( 'نام کاربری ملی پیامک' ); ?></label></th><td><input name="crpcrm_settings[melipayamak_username]" id="melipayamak_username" type="text" value="<?php echo esc_attr( $settings['melipayamak_username'] ); ?>" class="regular-text" /></td></tr>
				<tr><th scope="row"><label for="melipayamak_password"><?php echo esc_html( 'رمز عبور ملی پیامک' ); ?></label></th><td><input name="crpcrm_settings[melipayamak_password]" id="melipayamak_password" type="password" value="" class="regular-text" autocomplete="new-password" placeholder="<?php echo esc_attr( empty( $settings['melipayamak_password'] ) ? 'ذخیره نشده' : 'برای تغییر، رمز جدید را وارد کنید' ); ?>" /></td></tr>
				<tr><th scope="row"><label for="melipayamak_pattern_code"><?php echo esc_html( 'کد قالب پیامک' ); ?></label></th><td><input name="crpcrm_settings[melipayamak_pattern_code]" id="melipayamak_pattern_code" type="text" value="<?php echo esc_attr( $settings['melipayamak_pattern_code'] ); ?>" class="regular-text" /></td></tr>
				<tr><th scope="row"><label for="melipayamak_sender"><?php echo esc_html( 'شماره/خط ارسال‌کننده' ); ?></label></th><td><input name="crpcrm_settings[melipayamak_sender]" id="melipayamak_sender" type="text" value="<?php echo esc_attr( $settings['melipayamak_sender'] ); ?>" class="regular-text" /></td></tr>
				<tr><th scope="row"><label for="otp_expiration_minutes"><?php echo esc_html( 'مدت اعتبار کد، به دقیقه' ); ?></label></th><td><input name="crpcrm_settings[otp_expiration_minutes]" id="otp_expiration_minutes" type="number" min="1" value="<?php echo esc_attr( $settings['otp_expiration_minutes'] ); ?>" /></td></tr>
				<tr><th scope="row"><label for="otp_resend_seconds"><?php echo esc_html( 'فاصله مجاز ارسال مجدد، به ثانیه' ); ?></label></th><td><input name="crpcrm_settings[otp_resend_seconds]" id="otp_resend_seconds" type="number" min="1" value="<?php echo esc_attr( $settings['otp_resend_seconds'] ); ?>" /></td></tr>
				<tr><th scope="row"><label for="otp_max_attempts"><?php echo esc_html( 'حداکثر تلاش برای وارد کردن کد' ); ?></label></th><td><input name="crpcrm_settings[otp_max_attempts]" id="otp_max_attempts" type="number" min="1" value="<?php echo esc_attr( $settings['otp_max_attempts'] ); ?>" /></td></tr>
			</table>
		</div>

		<div class="crpcrm-card">
			<h2><?php echo esc_html( 'تنظیمات رهگیری ورودی' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="attribution_window_hours"><?php echo esc_html( 'مدت اعتبار منبع ورود، به ساعت' ); ?></label></th>
					<td>
						<input name="crpcrm_settings[attribution_window_hours]" id="attribution_window_hours" type="number" min="1" value="<?php echo esc_attr( $settings['attribution_window_hours'] ); ?>" />
						<p class="description"><?php echo esc_html( 'اگر کاربر از یک منبع مثل اینستاگرام وارد سایت شود، درخواست‌هایی که در این بازه زمانی ثبت کند به همان منبع نسبت داده می‌شود؛ مگر اینکه از منبع جدیدی وارد شود.' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<div class="crpcrm-card">
			<h2><?php echo esc_html( 'تنظیمات عملیاتی پایه' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><label for="request_rate_limit_count"><?php echo esc_html( 'تعداد مجاز ثبت درخواست' ); ?></label></th><td><input name="crpcrm_settings[request_rate_limit_count]" id="request_rate_limit_count" type="number" min="1" value="<?php echo esc_attr( $settings['request_rate_limit_count'] ); ?>" /></td></tr>
				<tr><th scope="row"><label for="request_rate_limit_minutes"><?php echo esc_html( 'بازه محدودیت درخواست (دقیقه)' ); ?></label></th><td><input name="crpcrm_settings[request_rate_limit_minutes]" id="request_rate_limit_minutes" type="number" min="1" value="<?php echo esc_attr( $settings['request_rate_limit_minutes'] ); ?>" /></td></tr>
				<tr><th scope="row"><label for="stale_request_hours"><?php echo esc_html( 'زمان لید رهاشده (ساعت)' ); ?></label></th><td><input name="crpcrm_settings[stale_request_hours]" id="stale_request_hours" type="number" min="1" value="<?php echo esc_attr( $settings['stale_request_hours'] ); ?>" /></td></tr>
				<tr><th scope="row"><?php echo esc_html( 'حذف داده‌ها هنگام uninstall' ); ?></th><td><label><input name="crpcrm_settings[delete_data_on_uninstall]" type="checkbox" value="yes" <?php checked( $settings['delete_data_on_uninstall'], 'yes' ); ?> /> <?php echo esc_html( 'فعال باشد' ); ?></label></td></tr>
			</table>
		</div>

		<?php submit_button( 'ذخیره تنظیمات' ); ?>
	</form>

	<?php if ( current_user_can( 'crpcrm_manage_plugin' ) ) : ?>
		<div class="crpcrm-card">
			<h2><?php echo esc_html( 'وضعیت رهگیری فعلی' ); ?></h2>
			<?php if ( ! empty( $current_attribution ) && is_array( $current_attribution ) ) : ?>
				<table class="widefat striped" role="presentation">
					<tbody>
						<tr><th><?php echo esc_html( 'منبع فعلی' ); ?></th><td><?php echo esc_html( $current_attribution['source'] ); ?></td></tr>
						<tr><th><?php echo esc_html( 'مدیوم فعلی' ); ?></th><td><?php echo esc_html( $current_attribution['medium'] ); ?></td></tr>
						<tr><th><?php echo esc_html( 'کمپین فعلی' ); ?></th><td><?php echo esc_html( $current_attribution['campaign'] ); ?></td></tr>
						<tr><th><?php echo esc_html( 'محتوای فعلی' ); ?></th><td><?php echo esc_html( $current_attribution['content'] ); ?></td></tr>
						<tr><th><?php echo esc_html( 'صفحه فرود' ); ?></th><td><?php echo esc_html( $current_attribution['landing_page'] ); ?></td></tr>
						<tr><th><?php echo esc_html( 'ارجاع‌دهنده' ); ?></th><td><?php echo esc_html( $current_attribution['referrer'] ); ?></td></tr>
						<tr><th><?php echo esc_html( 'زمان تشخیص' ); ?></th><td><?php echo esc_html( $current_attribution['detected_at'] ); ?></td></tr>
						<tr><th><?php echo esc_html( 'زمان انقضا' ); ?></th><td><?php echo esc_html( $current_attribution['expires_at'] ); ?></td></tr>
					</tbody>
				</table>
			<?php else : ?>
				<p><?php echo esc_html( 'در حال حاضر رهگیری معتبری در cookie این مرورگر وجود ندارد.' ); ?></p>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>

<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$portal_page_id = absint( $settings['portal_page_id'] );
$portal_warning = '';
if ( ! $portal_page_id ) {
	$portal_warning = 'صفحه پرتال مشتری انتخاب نشده است.';
} else {
	$portal_post = get_post( $portal_page_id );
	if ( $portal_post && ! has_shortcode( $portal_post->post_content, 'crpcrm_portal' ) ) {
		$portal_warning = 'در صفحه انتخاب‌شده shortcode پرتال پیدا نشد.';
		CRPCRM_Logger::warning( 'portal_shortcode_missing', 'portal_shortcode_missing', array( 'page_id' => $portal_page_id, 'user_id' => get_current_user_id() ) );
	}
}
$log_levels = array( 'debug' => 'خطایابی', 'info' => 'اطلاع‌رسانی', 'warning' => 'هشدار', 'error' => 'خطا' );
?>
<div class="wrap crpcrm-admin-wrap" dir="rtl">
	<h1><?php echo esc_html( 'تنظیمات پرتال و CRM' ); ?></h1>

	<?php if ( isset( $_GET['settings-updated'] ) && 'true' === sanitize_text_field( wp_unslash( $_GET['settings-updated'] ) ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php echo esc_html( 'تنظیمات با موفقیت ذخیره شد.' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['roles-rebuilt'] ) && 'true' === sanitize_text_field( wp_unslash( $_GET['roles-rebuilt'] ) ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php echo esc_html( 'نقش‌ها و دسترسی‌ها با موفقیت بازسازی شدند.' ); ?></p></div>
	<?php endif; ?>
	<?php if ( $portal_warning ) : ?>
		<div class="notice notice-warning"><p><?php echo esc_html( $portal_warning ); ?></p></div>
	<?php endif; ?>
	<?php if ( 'yes' === $settings['otp_debug_mode'] && ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) ) : ?>
		<div class="notice notice-warning"><p><?php echo esc_html( 'فعال‌سازی حالت تست OTP در سایت عملیاتی توصیه نمی‌شود.' ); ?></p></div>
	<?php endif; ?>

	<nav class="nav-tab-wrapper crpcrm-tabs" aria-label="<?php echo esc_attr( 'تب‌های تنظیمات' ); ?>">
		<?php foreach ( $tabs as $tab_key => $tab_label ) : ?>
			<a class="nav-tab <?php echo $active_tab === $tab_key ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => 'crpcrm-settings', 'tab' => $tab_key ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( $tab_label ); ?></a>
		<?php endforeach; ?>
	</nav>

	<?php if ( 'roles' !== $active_tab && 'tools' !== $active_tab ) : ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="crpcrm-settings-form">
		<input type="hidden" name="action" value="crpcrm_save_settings" />
		<input type="hidden" name="crpcrm_active_tab" value="<?php echo esc_attr( $active_tab ); ?>" />
		<?php wp_nonce_field( 'crpcrm_save_settings', 'crpcrm_settings_nonce' ); ?>

		<div class="crpcrm-card">
		<?php if ( 'portal' === $active_tab ) : ?>
			<h2><?php echo esc_html( 'تنظیمات پرتال مشتری' ); ?></h2>
			<table class="form-table" role="presentation"><tbody>
				<tr><th><label for="portal_page_id"><?php echo esc_html( 'صفحه پرتال مشتری' ); ?></label></th><td><?php wp_dropdown_pages( array( 'name' => 'crpcrm_settings[portal_page_id]', 'id' => 'portal_page_id', 'selected' => $portal_page_id, 'show_option_none' => '— انتخاب کنید —', 'option_none_value' => 0 ) ); ?><p class="description"><?php echo esc_html( 'صفحه‌ای که shortcode پرتال در آن قرار دارد.' ); ?></p></td></tr>
				<tr><th><label for="portal_menu_id"><?php echo esc_html( 'فهرست منوی پرتال' ); ?></label></th><td><?php wp_dropdown_nav_menus( array( 'name' => 'crpcrm_settings[portal_menu_id]', 'id' => 'portal_menu_id', 'selected' => absint( $settings['portal_menu_id'] ), 'show_option_none' => '— بدون فهرست —', 'option_none_value' => 0 ) ); ?><p class="description"><?php echo esc_html( 'لینک‌های این فهرست در کنار آیتم‌های اجباری پرتال نمایش داده می‌شوند.' ); ?></p></td></tr>
				<tr><th><?php echo esc_html( 'فعال بودن ثبت‌نام مشتریان جدید' ); ?></th><td><label><input name="crpcrm_settings[customer_registration_enabled]" type="checkbox" value="yes" <?php checked( $settings['customer_registration_enabled'], 'yes' ); ?> /> <?php echo esc_html( 'ثبت‌نام شماره‌های جدید فعال باشد.' ); ?></label></td></tr>
				<tr><th><label for="request_success_message"><?php echo esc_html( 'متن پیام بعد از ثبت موفق درخواست' ); ?></label></th><td><textarea name="crpcrm_settings[request_success_message]" id="request_success_message" class="large-text" rows="4"><?php echo esc_textarea( $settings['request_success_message'] ); ?></textarea></td></tr>
			</tbody></table>
		<?php elseif ( 'otp' === $active_tab ) : ?>
			<h2><?php echo esc_html( 'تنظیمات ورود OTP و ملی پیامک' ); ?></h2>
			<table class="form-table" role="presentation"><tbody>
				<tr><th><label for="otp_provider"><?php echo esc_html( 'ارائه‌دهنده پیامک' ); ?></label></th><td><select name="crpcrm_settings[otp_provider]" id="otp_provider"><option value="melipayamak" selected><?php echo esc_html( 'ملی پیامک' ); ?></option></select></td></tr>
				<tr><th><label for="melipayamak_username"><?php echo esc_html( 'نام کاربری ملی پیامک' ); ?></label></th><td><input name="crpcrm_settings[melipayamak_username]" id="melipayamak_username" type="text" value="<?php echo esc_attr( $settings['melipayamak_username'] ); ?>" class="regular-text" /></td></tr>
				<tr><th><label for="melipayamak_password"><?php echo esc_html( 'رمز عبور ملی پیامک' ); ?></label></th><td><input name="crpcrm_settings[melipayamak_password]" id="melipayamak_password" type="password" value="" class="regular-text" autocomplete="new-password" placeholder="<?php echo esc_attr( empty( $settings['melipayamak_password'] ) ? 'ذخیره نشده' : 'ذخیره شده' ); ?>" /><p class="description"><?php echo esc_html( 'برای حفظ امنیت، رمز ذخیره‌شده در فرم نمایش داده نمی‌شود. اگر این فیلد خالی بماند، رمز قبلی حفظ می‌شود.' ); ?></p></td></tr>
				<tr><th><label for="melipayamak_pattern_code"><?php echo esc_html( 'کد قالب پیامک' ); ?></label></th><td><input name="crpcrm_settings[melipayamak_pattern_code]" id="melipayamak_pattern_code" type="text" value="<?php echo esc_attr( $settings['melipayamak_pattern_code'] ); ?>" class="regular-text" /></td></tr>
				<tr><th><label for="melipayamak_sender"><?php echo esc_html( 'شماره/خط ارسال‌کننده' ); ?></label></th><td><input name="crpcrm_settings[melipayamak_sender]" id="melipayamak_sender" type="text" value="<?php echo esc_attr( $settings['melipayamak_sender'] ); ?>" class="regular-text" /></td></tr>
				<tr><th><label for="otp_expiration_minutes"><?php echo esc_html( 'مدت اعتبار کد OTP به دقیقه' ); ?></label></th><td><input name="crpcrm_settings[otp_expiration_minutes]" id="otp_expiration_minutes" type="number" min="1" max="60" value="<?php echo esc_attr( $settings['otp_expiration_minutes'] ); ?>" /></td></tr>
				<tr><th><label for="otp_resend_seconds"><?php echo esc_html( 'فاصله مجاز ارسال مجدد به ثانیه' ); ?></label></th><td><input name="crpcrm_settings[otp_resend_seconds]" id="otp_resend_seconds" type="number" min="10" max="600" value="<?php echo esc_attr( $settings['otp_resend_seconds'] ); ?>" /></td></tr>
				<tr><th><label for="otp_max_attempts"><?php echo esc_html( 'حداکثر تلاش برای وارد کردن کد' ); ?></label></th><td><input name="crpcrm_settings[otp_max_attempts]" id="otp_max_attempts" type="number" min="1" max="20" value="<?php echo esc_attr( $settings['otp_max_attempts'] ); ?>" /></td></tr>
				<?php if ( current_user_can( 'manage_options' ) ) : ?><tr><th><?php echo esc_html( 'حالت تست OTP' ); ?></th><td><label><input name="crpcrm_settings[otp_debug_mode]" type="checkbox" value="yes" <?php checked( $settings['otp_debug_mode'], 'yes' ); ?> /> <?php echo esc_html( 'فعال باشد' ); ?></label><p class="description"><?php echo esc_html( 'در حالت تست، کد OTP در لاگ داخلی افزونه ثبت می‌شود. این گزینه در سایت عملیاتی نباید فعال باشد.' ); ?></p></td></tr><?php endif; ?>
			</tbody></table>
		<?php elseif ( 'attribution' === $active_tab ) : ?>
			<h2><?php echo esc_html( 'تنظیمات رهگیری ورودی' ); ?></h2>
			<table class="form-table" role="presentation"><tbody>
				<tr><th><label for="attribution_window_hours"><?php echo esc_html( 'مدت اعتبار منبع ورود، به ساعت' ); ?></label></th><td><input name="crpcrm_settings[attribution_window_hours]" id="attribution_window_hours" type="number" min="1" max="720" value="<?php echo esc_attr( $settings['attribution_window_hours'] ); ?>" /></td></tr>
				<tr><th><?php echo esc_html( 'فعال بودن رهگیری UTM' ); ?></th><td><label><input name="crpcrm_settings[attribution_enabled]" type="checkbox" value="yes" <?php checked( $settings['attribution_enabled'], 'yes' ); ?> /> <?php echo esc_html( 'tracking جدید فعال باشد.' ); ?></label></td></tr>
				<tr><th><?php echo esc_html( 'ثبت تاریخچه ورودها' ); ?></th><td><label><input name="crpcrm_settings[attribution_events_enabled]" type="checkbox" value="yes" <?php checked( $settings['attribution_events_enabled'], 'yes' ); ?> /> <?php echo esc_html( 'رکورد تاریخچه ورود ثبت شود.' ); ?></label></td></tr>
				<tr><th><label for="internal_domains"><?php echo esc_html( 'لیست دامنه‌های داخلی' ); ?></label></th><td><textarea name="crpcrm_settings[internal_domains]" id="internal_domains" class="large-text code" rows="5"><?php echo esc_textarea( $settings['internal_domains'] ); ?></textarea><p class="description"><?php echo esc_html( 'هر دامنه در یک خط. referrerهای این دامنه‌ها به عنوان ورودی جدید محسوب نمی‌شوند.' ); ?></p></td></tr>
			</tbody></table>
		<?php elseif ( 'crm' === $active_tab ) : ?>
			<h2><?php echo esc_html( 'تنظیمات درخواست‌ها و CRM' ); ?></h2>
			<table class="form-table" role="presentation"><tbody>
				<tr><th><label for="request_rate_limit_count"><?php echo esc_html( 'محدودیت تعداد درخواست' ); ?></label></th><td><input name="crpcrm_settings[request_rate_limit_count]" id="request_rate_limit_count" type="number" min="1" max="100" value="<?php echo esc_attr( $settings['request_rate_limit_count'] ); ?>" /></td></tr>
				<tr><th><label for="request_rate_limit_minutes"><?php echo esc_html( 'بازه محدودیت درخواست به دقیقه' ); ?></label></th><td><input name="crpcrm_settings[request_rate_limit_minutes]" id="request_rate_limit_minutes" type="number" min="1" max="1440" value="<?php echo esc_attr( $settings['request_rate_limit_minutes'] ); ?>" /></td></tr>
				<tr><th><label for="stale_request_hours"><?php echo esc_html( 'ساعت تشخیص درخواست رهاشده' ); ?></label></th><td><input name="crpcrm_settings[stale_request_hours]" id="stale_request_hours" type="number" min="1" max="720" value="<?php echo esc_attr( $settings['stale_request_hours'] ); ?>" /></td></tr>
				<tr><th><label for="requests_per_page"><?php echo esc_html( 'تعداد آیتم در هر صفحه لیست درخواست‌ها' ); ?></label></th><td><input name="crpcrm_settings[requests_per_page]" id="requests_per_page" type="number" min="5" max="100" value="<?php echo esc_attr( $settings['requests_per_page'] ); ?>" /></td></tr>
				<tr><th><?php echo esc_html( 'اجازه اقدام مدیر روی درخواست بسته‌شده' ); ?></th><td><label><input name="crpcrm_settings[allow_manager_note_on_closed_request]" type="checkbox" value="yes" <?php checked( $settings['allow_manager_note_on_closed_request'], 'yes' ); ?> /> <?php echo esc_html( 'مدیر بتواند روی درخواست بسته‌شده فقط یادداشت داخلی ثبت کند.' ); ?></label></td></tr>
			</tbody></table>
		<?php elseif ( 'staff' === $active_tab ) : ?>
			<h2><?php echo esc_html( 'تنظیمات پنل کارکنان' ); ?></h2>
			<table class="form-table" role="presentation"><tbody>
				<tr><th><?php echo esc_html( 'فعال بودن پنل داخلی کارکنان' ); ?></th><td><label><input name="crpcrm_settings[staff_portal_enabled]" type="checkbox" value="yes" <?php checked( $settings['staff_portal_enabled'], 'yes' ); ?> /> <?php echo esc_html( 'منوی کارکنان برای کاربران مجاز نمایش داده شود.' ); ?></label></td></tr>
				<tr><th><?php echo esc_html( 'اجبار گزارش روزانه' ); ?></th><td><label><input name="crpcrm_settings[daily_report_required]" type="checkbox" value="yes" <?php checked( $settings['daily_report_required'], 'yes' ); ?> /> <?php echo esc_html( 'فعلاً فقط هشدار داشبورد کارکنان نمایش داده شود.' ); ?></label></td></tr>
				<tr><th><label for="daily_report_reminder_time"><?php echo esc_html( 'ساعت یادآوری گزارش روزانه' ); ?></label></th><td><input name="crpcrm_settings[daily_report_reminder_time]" id="daily_report_reminder_time" type="time" value="<?php echo esc_attr( $settings['daily_report_reminder_time'] ); ?>" /></td></tr>
				<tr><th><label for="staff_items_per_page"><?php echo esc_html( 'تعداد آیتم در لیست‌های کارکنان' ); ?></label></th><td><input name="crpcrm_settings[staff_items_per_page]" id="staff_items_per_page" type="number" min="5" max="100" value="<?php echo esc_attr( $settings['staff_items_per_page'] ); ?>" /></td></tr>
			</tbody></table>
		<?php elseif ( 'maintenance' === $active_tab ) : ?>
			<h2><?php echo esc_html( 'تنظیمات نگهداری داده‌ها' ); ?></h2>
			<table class="form-table" role="presentation"><tbody>
				<tr><th><?php echo esc_html( 'حذف داده‌ها هنگام حذف افزونه' ); ?></th><td><label><input name="crpcrm_settings[delete_data_on_uninstall]" type="checkbox" value="yes" <?php checked( $settings['delete_data_on_uninstall'], 'yes' ); ?> /> <?php echo esc_html( 'فعال باشد' ); ?></label><p class="description crpcrm-danger-text"><?php echo esc_html( 'اگر فعال باشد، هنگام حذف افزونه تمام جدول‌ها و داده‌های افزونه حذف می‌شوند. این گزینه خطرناک است.' ); ?></p></td></tr>
				<tr><th><label for="log_retention_days"><?php echo esc_html( 'نگهداری لاگ‌ها به روز' ); ?></label></th><td><input name="crpcrm_settings[log_retention_days]" id="log_retention_days" type="number" min="1" max="3650" value="<?php echo esc_attr( $settings['log_retention_days'] ); ?>" /></td></tr>
				<tr><th><label for="log_level"><?php echo esc_html( 'سطح لاگ' ); ?></label></th><td><select name="crpcrm_settings[log_level]" id="log_level"><?php foreach ( $log_levels as $level_key => $level_label ) : ?><option value="<?php echo esc_attr( $level_key ); ?>" <?php selected( $settings['log_level'], $level_key ); ?>><?php echo esc_html( $level_label ); ?></option><?php endforeach; ?></select></td></tr>
			</tbody></table>
		<?php endif; ?>
		</div>
		<?php submit_button( 'ذخیره تنظیمات' ); ?>
	</form>
	<?php elseif ( 'roles' === $active_tab ) : ?>
		<div class="crpcrm-card">
			<h2><?php echo esc_html( 'وضعیت نقش‌ها و دسترسی‌ها' ); ?></h2>
			<table class="widefat striped"><thead><tr><th><?php echo esc_html( 'نقش' ); ?></th><th><?php echo esc_html( 'وضعیت' ); ?></th><th><?php echo esc_html( 'دسترسی‌های اصلی' ); ?></th></tr></thead><tbody>
			<?php foreach ( $role_configs as $role_key => $config ) : $role = get_role( $role_key ); ?>
				<tr><td><?php echo esc_html( $config['label'] . ' (' . $role_key . ')' ); ?></td><td><?php echo esc_html( $role ? 'وجود دارد' : 'وجود ندارد' ); ?></td><td><?php echo esc_html( implode( '، ', $config['caps'] ) ); ?></td></tr>
			<?php endforeach; ?>
			</tbody></table>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="crpcrm-rebuild-roles-form">
				<input type="hidden" name="action" value="crpcrm_rebuild_roles" />
				<?php wp_nonce_field( 'crpcrm_rebuild_roles', 'crpcrm_roles_nonce' ); ?>
				<?php submit_button( 'بازسازی نقش‌ها و دسترسی‌ها', 'primary' ); ?>
			</form>
		</div>
	<?php elseif ( 'tools' === $active_tab ) : ?>
		<?php include CRPCRM_PLUGIN_DIR . 'admin/views/settings-tools.php'; ?>
	<?php endif; ?>
</div>

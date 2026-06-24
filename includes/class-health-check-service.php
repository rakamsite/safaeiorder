<?php
/**
 * Health check service.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_Health_Check_Service {
	public function get_status() {
		return array_merge(
			array(
				array(
					'key'     => 'plugin_version',
					'label'   => 'نسخه افزونه',
					'status'  => 'ok',
					'message' => defined( 'CRPCRM_VERSION' ) ? CRPCRM_VERSION : 'نامشخص',
				),
				array(
					'key'     => 'db_version',
					'label'   => 'نسخه دیتابیس افزونه',
					'status'  => get_option( 'crpcrm_db_version' ) === CRPCRM_DB_VERSION ? 'ok' : 'warning',
					'message' => get_option( 'crpcrm_db_version', 'ثبت نشده' ),
				),
			),
			$this->check_tables(),
			$this->check_counts(),
			$this->check_portal_page(),
			$this->check_sms_settings(),
			$this->check_roles(),
			$this->check_upload_storage(),
			$this->check_background_jobs(),
			$this->check_pending_uploads(),
			$this->check_file_link_signing()
		);
	}

	public function check_tables() {
		global $wpdb;

		$checks = array();
		foreach ( CRPCRM_DB::table_names() as $key => $table ) {
			$exists   = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
			$checks[] = array(
				'key'     => 'table_' . $key,
				'label'   => 'جدول ' . $key,
				'status'  => $exists ? 'ok' : 'error',
				'message' => $exists ? 'سالم' : 'خطا: جدول پیدا نشد.',
			);
		}

		return $checks;
	}

	public function check_roles() {
		$checks = array();

		foreach ( CRPCRM_Roles::get_roles() as $role_key => $config ) {
			$role    = get_role( $role_key );
			$missing = array();

			if ( $role ) {
				foreach ( $config['caps'] as $cap ) {
					if ( ! $role->has_cap( $cap ) ) {
						$missing[] = $cap;
					}
				}
			}

			$checks[] = array(
				'key'     => 'role_' . $role_key,
				'label'   => 'نقش ' . $config['label'],
				'status'  => $role && empty( $missing ) ? 'ok' : 'warning',
				'message' => $role ? ( empty( $missing ) ? 'سالم' : 'نیازمند بررسی: برخی دسترسی‌ها وجود ندارند: ' . implode( '، ', $missing ) ) : 'نیازمند بررسی: نقش پیدا نشد.',
			);
		}

		return $checks;
	}

	public function check_portal_page() {
		$checks         = array();
		$portal_page_id = absint( CRPCRM_Settings::get( 'portal_page_id', 0 ) );

		if ( ! $portal_page_id ) {
			return array(
				array( 'key' => 'portal_page', 'label' => 'وضعیت صفحه پرتال', 'status' => 'warning', 'message' => 'نیازمند بررسی: صفحه پرتال انتخاب نشده است.' ),
				array( 'key' => 'portal_shortcode', 'label' => 'وجود shortcode پرتال', 'status' => 'warning', 'message' => 'نیازمند بررسی: صفحه پرتال انتخاب نشده است.' ),
			);
		}

		$post = get_post( $portal_page_id );
		if ( ! $post ) {
			return array(
				array( 'key' => 'portal_page', 'label' => 'وضعیت صفحه پرتال', 'status' => 'error', 'message' => 'خطا: صفحه انتخاب‌شده پیدا نشد.' ),
				array( 'key' => 'portal_shortcode', 'label' => 'وجود shortcode پرتال', 'status' => 'warning', 'message' => 'نیازمند بررسی: shortcode پرتال قابل بررسی نیست.' ),
			);
		}

		$checks[] = array( 'key' => 'portal_page', 'label' => 'وضعیت صفحه پرتال', 'status' => 'ok', 'message' => 'سالم' );
		$checks[] = array(
			'key'     => 'portal_shortcode',
			'label'   => 'وجود shortcode پرتال',
			'status'  => has_shortcode( $post->post_content, 'crpcrm_portal' ) ? 'ok' : 'warning',
			'message' => has_shortcode( $post->post_content, 'crpcrm_portal' ) ? 'سالم' : 'نیازمند بررسی: shortcode پرتال در صفحه انتخاب‌شده پیدا نشد.',
		);

		return $checks;
	}

	public function check_sms_settings() {
		$registry            = CRPCRM_SMS_Provider_Registry::get_instance();
		$configured_provider = $registry->get_configured_provider_id();
		$provider            = $registry->get_provider( $configured_provider );

		if ( ! $provider ) {
			return array(
				array(
					'key'     => 'sms_settings',
					'label'   => 'وضعیت تنظیمات پیامک',
					'status'  => 'warning',
					'message' => 'نیازمند بررسی: ارائه‌دهنده پیامک انتخاب‌شده (' . $configured_provider . ') شناخته‌شده نیست.',
				),
			);
		}

		$validation = $provider->validate_settings( CRPCRM_Settings::get() );
		$message    = 'ارائه‌دهنده فعال: ' . $provider->get_label() . ' - تنظیمات کامل است.';

		if ( is_wp_error( $validation ) ) {
			$error_data = $validation->get_error_data();
			$missing    = is_array( $error_data ) && isset( $error_data['missing'] ) ? (array) $error_data['missing'] : array();
			$schema     = $provider->get_settings_schema();
			$labels     = array_map(
				function ( $key ) use ( $schema ) {
					return isset( $schema[ $key ]['label'] ) ? $schema[ $key ]['label'] : $key;
				},
				$missing
			);
			$message = 'نیازمند بررسی: تنظیمات ارائه‌دهنده فعال کامل نیست.' . ( $labels ? ' فیلدهای ناقص: ' . implode( '، ', $labels ) : '' );
		}

		return array(
			array(
				'key'     => 'sms_settings',
				'label'   => 'وضعیت تنظیمات پیامک (' . $provider->get_label() . ')',
				'status'  => is_wp_error( $validation ) ? 'warning' : 'ok',
				'message' => $message,
			),
		);
	}

	public function check_counts() {
		global $wpdb;

		$items = array(
			'customers'           => array( 'table' => CRPCRM_DB::table( 'customers' ), 'label' => 'تعداد مشتریان' ),
			'requests'            => array( 'table' => CRPCRM_DB::table( 'requests' ), 'label' => 'تعداد درخواست‌ها' ),
			'request_activities'  => array( 'table' => CRPCRM_DB::table( 'request_activities' ), 'label' => 'تعداد فعالیت‌های درخواست' ),
			'plugin_logs'         => array( 'table' => CRPCRM_DB::table( 'plugin_logs' ), 'label' => 'تعداد لاگ‌های افزونه' ),
			'staff_daily_reports' => array( 'table' => CRPCRM_DB::table( 'staff_daily_reports' ), 'label' => 'تعداد گزارش‌های روزانه کارکنان' ),
		);
		$checks = array();

		foreach ( $items as $key => $item ) {
			$exists   = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $item['table'] ) );
			$checks[] = array(
				'key'     => 'count_' . $key,
				'label'   => $item['label'],
				'status'  => $exists ? 'ok' : 'error',
				'message' => $exists ? number_format_i18n( (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$item['table']}" ) ) : 'خطا: جدول پیدا نشد.',
			);
		}

		return $checks;
	}

	public function check_upload_storage() {
		$root      = CRPCRM_Dynamic_Form_Renderer::get_protected_upload_root_dir();
		$exists    = is_dir( $root );
		$can_build = $exists || wp_mkdir_p( $root );
		$writable  = $can_build && is_writable( $root );
		$index     = file_exists( trailingslashit( $root ) . 'index.php' );
		$htaccess  = file_exists( trailingslashit( $root ) . '.htaccess' );
		$software  = isset( $_SERVER['SERVER_SOFTWARE'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) ) : '';

		$checks = array(
			array(
				'key'     => 'upload_root',
				'label'   => 'مسیر فایل‌های محافظت‌شده',
				'status'  => $can_build ? 'ok' : 'error',
				'message' => $can_build ? 'مسیر فایل‌ها در دسترس است.' : 'مسیر فایل‌های محافظت‌شده ساخته نشد یا در دسترس نیست.',
			),
			array(
				'key'     => 'upload_root_writable',
				'label'   => 'قابلیت نوشتن مسیر فایل‌ها',
				'status'  => $writable ? 'ok' : 'error',
				'message' => $writable ? 'مسیر فایل‌ها قابل نوشتن است.' : 'مسیر فایل‌ها قابل نوشتن نیست.',
			),
			array(
				'key'     => 'upload_root_protection',
				'label'   => 'فایل‌های محافظتی مسیر آپلود',
				'status'  => ( $index && $htaccess ) ? 'ok' : 'warning',
				'message' => ( $index && $htaccess ) ? 'index.php و .htaccess موجود است.' : 'یکی از فایل‌های محافظتی در مسیر آپلود پیدا نشد.',
			),
		);

		if ( false !== strpos( $software, 'nginx' ) ) {
			$checks[] = array(
				'key'     => 'upload_root_nginx_notice',
				'label'   => 'هشدار سرور Nginx',
				'status'  => 'warning',
				'message' => '.htaccess در Nginx کافی نیست. بستن دسترسی مستقیم به مسیر آپلود از سمت سرور را هم بررسی کنید.',
			);
		}

		return $checks;
	}

	public function check_background_jobs() {
		$jobs = array(
			array( 'key' => 'pending_upload_cleanup_cron', 'label' => 'کرون پاکسازی فایل‌های موقت', 'hook' => 'crpcrm_pending_upload_cleanup' ),
			array( 'key' => 'lead_follow_up_cron', 'label' => 'کرون پیگیری لید', 'hook' => CRPCRM_System_Request_Types::LEAD_FOLLOW_UP_CRON ),
			array( 'key' => 'daily_log_cleanup_cron', 'label' => 'کرون پاکسازی لاگ‌ها', 'hook' => 'crpcrm_daily_log_cleanup' ),
		);
		$checks = array();

		foreach ( $jobs as $job ) {
			$scheduled = wp_next_scheduled( $job['hook'] );
			$checks[]  = array(
				'key'     => $job['key'],
				'label'   => $job['label'],
				'status'  => $scheduled ? 'ok' : 'warning',
				'message' => $scheduled ? 'زمان‌بندی شده است.' : 'برای این کرون زمان‌بندی پیدا نشد.',
			);
		}

		return $checks;
	}

	public function check_pending_uploads() {
		$stats = CRPCRM_Dynamic_Form_Renderer::get_pending_upload_health_stats();

		return array(
			array(
				'key'     => 'pending_uploads',
				'label'   => 'وضعیت فایل‌های موقت',
				'status'  => empty( $stats['expired_pending'] ) ? 'ok' : 'warning',
				'message' => sprintf( 'مجموع: %d، منقضی‌شده: %d', absint( $stats['pending_total'] ?? 0 ), absint( $stats['expired_pending'] ?? 0 ) ),
			),
			array(
				'key'     => 'daily_upload_usage',
				'label'   => 'اندازه ذخیره آمار آپلود',
				'status'  => absint( $stats['daily_usage_entries'] ?? 0 ) > 50000 ? 'warning' : 'ok',
				'message' => sprintf( 'تعداد روزها: %d، اندازه تقریبی: %d بایت', absint( $stats['daily_usage_days'] ?? 0 ), absint( $stats['daily_usage_entries'] ?? 0 ) ),
			),
		);
	}

	public function check_file_link_signing() {
		$url = CRPCRM_Request_File_Access_Service::build_file_url(
			array(
				'upload_token' => 'health-check-token',
				'field_key'    => 'health_check',
			),
			array(
				'source_type'  => 'pending',
				'source_field' => 'health_check',
			),
			'download'
		);

		if ( ! $url ) {
			return array(
				array(
					'key'     => 'file_link_signing',
					'label'   => 'ایجاد لینک امن فایل',
					'status'  => 'warning',
					'message' => 'اطلاعات کافی برای تست لینک فایل وجود ندارد.',
				),
			);
		}

		return array(
			array(
				'key'     => 'file_link_signing',
				'label'   => 'ایجاد لینک امن فایل',
				'status'  => 'ok',
				'message' => 'تولید امضای لینک فایل بدون خطا انجام شد.',
			),
		);
	}
}

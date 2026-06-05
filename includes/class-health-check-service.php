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
				array( 'key' => 'plugin_version', 'label' => 'نسخه افزونه', 'status' => 'ok', 'message' => defined( 'CRPCRM_VERSION' ) ? CRPCRM_VERSION : 'نامشخص' ),
				array( 'key' => 'db_version', 'label' => 'نسخه دیتابیس افزونه', 'status' => get_option( 'crpcrm_db_version' ) === CRPCRM_DB_VERSION ? 'ok' : 'warning', 'message' => get_option( 'crpcrm_db_version', 'ثبت نشده' ) ),
			),
			$this->check_tables(),
			$this->check_counts(),
			$this->check_portal_page(),
			$this->check_sms_settings(),
			$this->check_roles()
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
		$checks[] = array( 'key' => 'portal_shortcode', 'label' => 'وجود shortcode پرتال', 'status' => has_shortcode( $post->post_content, 'crpcrm_portal' ) ? 'ok' : 'warning', 'message' => has_shortcode( $post->post_content, 'crpcrm_portal' ) ? 'سالم' : 'نیازمند بررسی: shortcode پرتال در صفحه انتخاب‌شده پیدا نشد.' );
		return $checks;
	}

	public function check_sms_settings() {
		$required = array( 'melipayamak_username', 'melipayamak_api_key', 'melipayamak_pattern_code' );
		$missing  = array();
		foreach ( $required as $key ) {
			if ( '' === (string) CRPCRM_Settings::get( $key, '' ) ) {
				$missing[] = $key;
			}
		}
		return array(
			array(
				'key'     => 'sms_settings',
				'label'   => 'وضعیت تنظیمات ملی پیامک',
				'status'  => empty( $missing ) ? 'ok' : 'warning',
				'message' => empty( $missing ) ? 'سالم' : 'نیازمند بررسی: تنظیمات ملی پیامک کامل نیست.',
			),
		);
	}

	public function check_counts() {
		global $wpdb;
		$items = array(
			'customers'             => array( 'table' => CRPCRM_DB::table( 'customers' ), 'label' => 'تعداد مشتریان' ),
			'requests'              => array( 'table' => CRPCRM_DB::table( 'requests' ), 'label' => 'تعداد درخواست‌ها' ),
			'request_activities'    => array( 'table' => CRPCRM_DB::table( 'request_activities' ), 'label' => 'تعداد فعالیت‌های درخواست' ),
			'plugin_logs'           => array( 'table' => CRPCRM_DB::table( 'plugin_logs' ), 'label' => 'تعداد لاگ‌های افزونه' ),
			'staff_daily_reports'   => array( 'table' => CRPCRM_DB::table( 'staff_daily_reports' ), 'label' => 'تعداد گزارش‌های روزانه کارکنان' ),
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
}

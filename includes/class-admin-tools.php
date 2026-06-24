<?php
/**
 * Admin tools, exports, and maintenance actions.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_Admin_Tools {
	private $csv;
	private $maintenance;

	public function __construct() {
		$this->csv         = new CRPCRM_CSV_Exporter();
		$this->maintenance = new CRPCRM_Maintenance_Service();
	}

	public function register_hooks() {
		add_action( 'admin_post_crpcrm_export_requests', array( $this, 'export_requests' ) );
		add_action( 'admin_post_crpcrm_export_customers', array( $this, 'export_customers' ) );
		add_action( 'admin_post_crpcrm_export_daily_reports', array( $this, 'export_daily_reports' ) );
		add_action( 'admin_post_crpcrm_export_staff_requests', array( $this, 'export_staff_requests' ) );
		add_action( 'admin_post_crpcrm_export_staff_issues', array( $this, 'export_staff_issues' ) );
		add_action( 'admin_post_crpcrm_cleanup_logs', array( $this, 'cleanup_logs' ) );
		add_action( 'admin_post_crpcrm_repair_tables', array( $this, 'repair_tables' ) );
		add_action( 'admin_post_crpcrm_tools_rebuild_roles', array( $this, 'rebuild_roles' ) );
		add_action( 'admin_post_crpcrm_fix_request_codes', array( $this, 'fix_request_codes' ) );
		add_action( 'crpcrm_daily_log_cleanup', array( $this, 'run_scheduled_log_cleanup' ) );
	}

	public static function can_export() {
		return current_user_can( 'crpcrm_view_reports' ) || current_user_can( 'manage_options' );
	}

	public static function can_maintain() {
		return current_user_can( 'crpcrm_manage_settings' ) || current_user_can( 'crpcrm_manage_plugin' ) || current_user_can( 'manage_options' );
	}

	public static function schedule_cron() {
		if ( ! CRPCRM_Feature_Manager::is_enabled( 'admin_tools' ) ) {
			return;
		}

		if ( ! wp_next_scheduled( 'crpcrm_daily_log_cleanup' ) ) {
			wp_schedule_event( CRPCRM_Helpers::current_timestamp() + HOUR_IN_SECONDS, 'daily', 'crpcrm_daily_log_cleanup' );
		}
	}

	public static function clear_cron() {
		wp_clear_scheduled_hook( 'crpcrm_daily_log_cleanup' );
	}

	public function run_scheduled_log_cleanup() {
		if ( ! CRPCRM_Feature_Manager::is_enabled( 'admin_tools' ) ) {
			return 0;
		}

		$deleted = $this->maintenance->cleanup_old_logs( CRPCRM_Settings::get( 'log_retention_days', 90 ) );
		CRPCRM_Logger::info( 'logs_cleanup_run', 'logs_cleanup_run', array( 'deleted' => $deleted, 'mode' => 'cron' ) );
	}

	private function deny( $context ) {
		CRPCRM_Logger::warning( 'maintenance_access_denied', 'maintenance_access_denied', array( 'user_id' => get_current_user_id(), 'context' => $context ) );
		wp_die( esc_html__( 'شما اجازه اجرای این عملیات را ندارید.', 'customer-request-portal-crm' ) );
	}

	private function verify_export( $action ) {
		if ( ! CRPCRM_Feature_Manager::is_enabled( 'admin_tools' ) ) {
			wp_die( esc_html( CRPCRM_Feature_Manager::disabled_message() ) );
		}

		if ( ! self::can_export() ) {
			$this->deny( $action );
		}

		check_admin_referer( $action, 'crpcrm_export_nonce' );
	}

	private function verify_maintenance( $action, $nonce_field = 'crpcrm_tools_nonce' ) {
		if ( ! CRPCRM_Feature_Manager::is_enabled( 'admin_tools' ) ) {
			wp_die( esc_html( CRPCRM_Feature_Manager::disabled_message() ) );
		}

		if ( ! self::can_maintain() ) {
			$this->deny( $action );
		}

		check_admin_referer( $action, $nonce_field );
	}

	private function sanitize_filters( $keys ) {
		$filters = array();

		foreach ( $keys as $key => $type ) {
			$value = isset( $_GET[ $key ] ) ? wp_unslash( $_GET[ $key ] ) : ( isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '' );
			if ( 'int' === $type ) {
				$filters[ $key ] = absint( $value );
			} elseif ( 'date' === $type ) {
				$filters[ $key ] = CRPCRM_Helpers::normalize_date_input( sanitize_text_field( $value ) );
			} elseif ( 'bool' === $type ) {
				$filters[ $key ] = '' === $value ? '' : absint( $value );
			} elseif ( 'key' === $type ) {
				$filters[ $key ] = sanitize_key( $value );
			} else {
				$filters[ $key ] = sanitize_text_field( $value );
			}
		}

		return $filters;
	}

	public function export_requests() {
		global $wpdb;

		$this->verify_export( 'crpcrm_export_requests' );
		$f = $this->sanitize_filters(
			array(
				'date_from'    => 'date',
				'date_to'      => 'date',
				'request_type' => 'key',
				'status'       => 'key',
				'source'       => 'key',
				'campaign'     => 'text',
				'owner_id'     => 'int',
			)
		);

		$where = array( '1=1' );
		$vals  = array();
		$this->where_date_range( $where, $vals, 'r.created_at', $f['date_from'], $f['date_to'] );
		$this->where_equal( $where, $vals, 'r.request_type', $f['request_type'] );
		$this->where_equal( $where, $vals, 'r.status', $f['status'] );
		$this->where_equal( $where, $vals, 'r.request_source', $f['source'] );

		if ( '' !== $f['campaign'] ) {
			$where[] = 'r.request_campaign LIKE %s';
			$vals[]  = '%' . $wpdb->esc_like( $f['campaign'] ) . '%';
		}

		if ( $f['owner_id'] ) {
			$where[] = 'r.owner_id = %d';
			$vals[]  = $f['owner_id'];
		}

		$stream_sql = 'SELECT r.*, c.full_name AS customer_name, c.phone AS customer_phone, c.province AS customer_province, c.city AS customer_city, u.display_name AS owner_name FROM ' . CRPCRM_DB::table( 'requests' ) . ' r LEFT JOIN ' . CRPCRM_DB::table( 'customers' ) . ' c ON c.id = r.customer_id LEFT JOIN ' . $wpdb->users . ' u ON u.ID = r.owner_id WHERE ' . implode( ' AND ', $where ) . ' ORDER BY r.created_at DESC, r.id DESC';
		$handle     = $this->csv->start_output(
			'requests-export-' . CRPCRM_Helpers::current_date() . '.csv',
			array( 'کد پیگیری', 'تاریخ ثبت', 'تاریخ آخرین بروزرسانی', 'نام مشتری', 'شماره موبایل', 'استان', 'شهر', 'نوع درخواست', 'وضعیت', 'خلاصه درخواست', 'منبع', 'مدیوم', 'کمپین', 'محتوا', 'صفحه ورود', 'مسئول', 'آخرین اقدام', 'آخرین فعالیت', 'پیگیری بعدی', 'تاریخ بسته‌شدن', 'دلیل ناموفق', 'دلیل نامعتبر' )
		);
		$chunk_size = 500;
		$offset     = 0;
		$count      = 0;

		do {
			$query_vals = array_merge( $vals, array( $chunk_size, $offset ) );
			$rows       = $wpdb->get_results( $wpdb->prepare( $stream_sql . ' LIMIT %d OFFSET %d', $query_vals ), ARRAY_A );

			foreach ( $rows as $r ) {
				$this->csv->write_row(
					$handle,
					array(
						$r['request_code'],
						$this->csv->format_datetime( $r['created_at'] ),
						$this->csv->format_datetime( $r['updated_at'] ),
						$r['customer_name'],
						$r['customer_phone'],
						$r['customer_province'],
						$r['customer_city'],
						CRPCRM_Request_Type_Registry::get_label( $r['request_type'], $r ),
						CRPCRM_Labels::get_status_label( $r['status'] ),
						$r['request_summary'],
						CRPCRM_Labels::get_source_label( $r['request_source'] ),
						CRPCRM_Labels::get_medium_label( $r['request_medium'] ),
						$r['request_campaign'],
						$r['request_content'],
						$r['request_landing_page'],
						$r['owner_name'],
						CRPCRM_Labels::get_activity_label( $r['last_action'] ),
						$this->csv->format_datetime( $r['last_activity_at'] ),
						$this->csv->format_datetime( $r['next_follow_up_at'] ),
						$this->csv->format_datetime( $r['closed_at'] ),
						CRPCRM_Labels::get_close_reason_label( $r['close_reason'] ),
						CRPCRM_Labels::get_invalid_reason_label( $r['invalid_reason'] ),
					)
				);
				++$count;
			}

			$offset += $chunk_size;
		} while ( count( $rows ) === $chunk_size );

		CRPCRM_Logger::info( 'csv_export_requests', 'csv_export_requests', array( 'user_id' => get_current_user_id(), 'filters' => $f, 'count' => $count ) );
		$this->csv->finish_output( $handle );
	}

	public function export_customers() {
		global $wpdb;

		$this->verify_export( 'crpcrm_export_customers' );
		$f = $this->sanitize_filters(
			array(
				'date_from'         => 'date',
				'date_to'           => 'date',
				'province'          => 'text',
				'city'              => 'text',
				'first_source'      => 'key',
				'last_source'       => 'key',
				'profile_completed' => 'bool',
			)
		);

		$where = array( '1=1' );
		$vals  = array();
		$this->where_date_range( $where, $vals, 'c.created_at', $f['date_from'], $f['date_to'] );
		$this->where_equal( $where, $vals, 'c.province', $f['province'] );
		$this->where_equal( $where, $vals, 'c.city', $f['city'] );
		$this->where_equal( $where, $vals, 'c.first_source', $f['first_source'] );
		$this->where_equal( $where, $vals, 'c.last_source', $f['last_source'] );

		if ( '' !== $f['profile_completed'] ) {
			$where[] = 'c.profile_completed = %d';
			$vals[]  = $f['profile_completed'];
		}

		$requests   = CRPCRM_DB::table( 'requests' );
		$stream_sql = "SELECT c.*, (SELECT COUNT(*) FROM {$requests} r WHERE r.customer_id = c.id) AS total_requests, (SELECT COUNT(*) FROM {$requests} r WHERE r.customer_id = c.id AND r.status IN ('new','in_progress','no_answer','follow_up')) AS open_requests, (SELECT COUNT(*) FROM {$requests} r WHERE r.customer_id = c.id AND r.status IN ('won','lost','invalid','closed')) AS closed_requests FROM " . CRPCRM_DB::table( 'customers' ) . ' c WHERE ' . implode( ' AND ', $where ) . ' ORDER BY c.created_at DESC, c.id DESC';
		$handle     = $this->csv->start_output(
			'customers-export-' . CRPCRM_Helpers::current_date() . '.csv',
			array( 'نام و نام خانوادگی', 'شماره موبایل', 'استان', 'شهر', 'وضعیت تکمیل پروفایل', 'منبع اولین ورود', 'کمپین اولین ورود', 'تاریخ اولین ورود', 'منبع آخرین ورود', 'کمپین آخرین ورود', 'تاریخ آخرین ورود', 'تعداد کل درخواست‌ها', 'تعداد درخواست‌های باز', 'تعداد درخواست‌های بسته‌شده', 'تاریخ ایجاد پروفایل', 'تاریخ بروزرسانی پروفایل' )
		);
		$chunk_size = 500;
		$offset     = 0;
		$count      = 0;

		do {
			$query_vals = array_merge( $vals, array( $chunk_size, $offset ) );
			$rows       = $wpdb->get_results( $wpdb->prepare( $stream_sql . ' LIMIT %d OFFSET %d', $query_vals ), ARRAY_A );

			foreach ( $rows as $r ) {
				$this->csv->write_row(
					$handle,
					array(
						$r['full_name'],
						$r['phone'],
						$r['province'],
						$r['city'],
						$this->csv->format_boolean( $r['profile_completed'] ),
						CRPCRM_Labels::get_source_label( $r['first_source'] ),
						$r['first_campaign'],
						$this->csv->format_datetime( $r['first_seen_at'] ),
						CRPCRM_Labels::get_source_label( $r['last_source'] ),
						$r['last_campaign'],
						$this->csv->format_datetime( $r['last_seen_at'] ),
						$r['total_requests'],
						$r['open_requests'],
						$r['closed_requests'],
						$this->csv->format_datetime( $r['created_at'] ),
						$this->csv->format_datetime( $r['updated_at'] ),
					)
				);
				++$count;
			}

			$offset += $chunk_size;
		} while ( count( $rows ) === $chunk_size );

		CRPCRM_Logger::info( 'csv_export_customers', 'csv_export_customers', array( 'user_id' => get_current_user_id(), 'filters' => $f, 'count' => $count ) );
		$this->csv->finish_output( $handle );
	}

	public function export_daily_reports() {
		$this->export_staff_table( 'daily_reports' );
	}

	public function export_staff_requests() {
		$this->export_staff_table( 'staff_requests' );
	}

	public function export_staff_issues() {
		$this->export_staff_table( 'staff_issues' );
	}

	private function export_staff_table( $type ) {
		global $wpdb;

		$actions = array(
			'daily_reports'  => 'crpcrm_export_daily_reports',
			'staff_requests' => 'crpcrm_export_staff_requests',
			'staff_issues'   => 'crpcrm_export_staff_issues',
		);
		$this->verify_export( $actions[ $type ] );

		$table    = 'daily_reports' === $type ? CRPCRM_DB::table( 'staff_daily_reports' ) : CRPCRM_DB::table( $type );
		$where    = array( '1=1' );
		$vals     = array();
		$date_col = 'daily_reports' === $type ? 't.report_date' : 't.created_at';
		$f        = $this->sanitize_filters(
			array(
				'date_from'                => 'date',
				'date_to'                  => 'date',
				'user_id'                  => 'int',
				'status'                   => 'key',
				'needs_manager_attention'  => 'bool',
				'category'                 => 'key',
				'priority'                 => 'key',
				'severity'                 => 'key',
				'needs_manager_decision'   => 'bool',
			)
		);

		$this->where_date_range( $where, $vals, $date_col, $f['date_from'], $f['date_to'], 'daily_reports' !== $type );
		if ( $f['user_id'] ) {
			$where[] = 't.user_id = %d';
			$vals[]  = $f['user_id'];
		}
		$this->where_equal( $where, $vals, 't.status', $f['status'] );

		if ( 'daily_reports' === $type && '' !== $f['needs_manager_attention'] ) {
			$where[] = 't.needs_manager_attention = %d';
			$vals[]  = $f['needs_manager_attention'];
		}

		if ( 'staff_requests' === $type ) {
			$this->where_equal( $where, $vals, 't.category', $f['category'] );
			$this->where_equal( $where, $vals, 't.priority', $f['priority'] );
		}

		if ( 'staff_issues' === $type ) {
			$this->where_equal( $where, $vals, 't.severity', $f['severity'] );
			if ( '' !== $f['needs_manager_decision'] ) {
				$where[] = 't.needs_manager_decision = %d';
				$vals[]  = $f['needs_manager_decision'];
			}
		}

		$stream_sql = "SELECT t.*, u.display_name AS employee_name FROM {$table} t LEFT JOIN {$wpdb->users} u ON u.ID = t.user_id WHERE " . implode( ' AND ', $where ) . ' ORDER BY ' . ( 'daily_reports' === $type ? 't.report_date DESC, t.created_at DESC' : 't.created_at DESC' );
		$headers    = 'daily_reports' === $type
			? array( 'تاریخ گزارش', 'نام کارمند', 'کارهای انجام‌شده', 'کارهای نیمه‌تمام', 'مشکلات', 'برنامه فردا', 'نیازمند توجه مدیر', 'وضعیت', 'پاسخ مدیر', 'توضیح تکمیلی فروش', 'آمار CRM، به صورت خلاصه متنی', 'تاریخ ثبت', 'تاریخ بروزرسانی' )
			: ( 'staff_requests' === $type
				? array( 'تاریخ ثبت', 'نام کارمند', 'دسته‌بندی', 'عنوان', 'توضیحات', 'اولویت', 'وضعیت', 'پاسخ مدیر', 'تاریخ بروزرسانی' )
				: array( 'تاریخ ثبت', 'نام کارمند', 'عنوان', 'واحد مرتبط', 'شدت', 'توضیحات', 'پیشنهاد راه‌حل', 'نیازمند تصمیم مدیر', 'وضعیت', 'پاسخ مدیر', 'تاریخ بروزرسانی' ) );
		$filename   = 'daily_reports' === $type ? 'daily-reports-export-' : ( 'staff_requests' === $type ? 'staff-requests-export-' : 'staff-issues-export-' );
		$handle     = $this->csv->start_output( $filename . CRPCRM_Helpers::current_date() . '.csv', $headers );
		$chunk_size = 500;
		$offset     = 0;
		$count      = 0;

		do {
			$query_vals = array_merge( $vals, array( $chunk_size, $offset ) );
			$rows       = $wpdb->get_results( $wpdb->prepare( $stream_sql . ' LIMIT %d OFFSET %d', $query_vals ), ARRAY_A );

			foreach ( $rows as $r ) {
				if ( 'daily_reports' === $type ) {
					$this->csv->write_row( $handle, array( CRPCRM_Helpers::format_jalali_date( $r['report_date'] ), $r['employee_name'], $r['completed_work'], $r['unfinished_work'], $r['problems'], $r['tomorrow_plan'], $this->csv->format_boolean( $r['needs_manager_attention'] ), CRPCRM_Labels::get_staff_status_label( $r['status'] ), $r['manager_response'], $r['sales_comment'], $this->csv->flatten_snapshot( $r['sales_crm_snapshot'] ), $this->csv->format_datetime( $r['created_at'] ), $this->csv->format_datetime( $r['updated_at'] ) ) );
				} elseif ( 'staff_requests' === $type ) {
					$this->csv->write_row( $handle, array( $this->csv->format_datetime( $r['created_at'] ), $r['employee_name'], $r['category'], $r['title'], $r['description'], CRPCRM_Labels::get_priority_label( $r['priority'] ), CRPCRM_Labels::get_staff_status_label( $r['status'] ), $r['manager_response'], $this->csv->format_datetime( $r['updated_at'] ) ) );
				} else {
					$this->csv->write_row( $handle, array( $this->csv->format_datetime( $r['created_at'] ), $r['employee_name'], $r['title'], $r['related_department'], CRPCRM_Labels::get_severity_label( $r['severity'] ), $r['description'], $r['suggested_solution'], $this->csv->format_boolean( $r['needs_manager_decision'] ), CRPCRM_Labels::get_staff_status_label( $r['status'] ), $r['manager_response'], $this->csv->format_datetime( $r['updated_at'] ) ) );
				}

				++$count;
			}

			$offset += $chunk_size;
		} while ( count( $rows ) === $chunk_size );

		$log_map = array(
			'daily_reports'  => 'csv_export_daily_reports',
			'staff_requests' => 'csv_export_staff_requests',
			'staff_issues'   => 'csv_export_staff_issues',
		);
		CRPCRM_Logger::info( $log_map[ $type ], $log_map[ $type ], array( 'user_id' => get_current_user_id(), 'filters' => $f, 'count' => $count ) );
		$this->csv->finish_output( $handle );
	}

	private function where_equal( &$where, &$vals, $column, $value ) {
		if ( '' !== $value && null !== $value ) {
			$where[] = $column . ' = %s';
			$vals[]  = $value;
		}
	}

	private function where_date_range( &$where, &$vals, $column, $from, $to, $datetime = true ) {
		if ( $from ) {
			$where[] = $column . ' >= %s';
			$vals[]  = $datetime ? $from . ' 00:00:00' : $from;
		}

		if ( $to ) {
			$where[] = $column . ' <= %s';
			$vals[]  = $datetime ? $to . ' 23:59:59' : $to;
		}
	}

	public function cleanup_logs() {
		$this->verify_maintenance( 'crpcrm_cleanup_logs' );
		$deleted = $this->maintenance->cleanup_old_logs( CRPCRM_Settings::get( 'log_retention_days', 90 ) );
		CRPCRM_Logger::info( 'logs_cleanup_run', 'logs_cleanup_run', array( 'user_id' => get_current_user_id(), 'deleted' => $deleted, 'mode' => 'manual' ) );
		$this->redirect_tool( array( 'logs-cleaned' => $deleted ) );
	}

	public function repair_tables() {
		$this->verify_maintenance( 'crpcrm_repair_tables' );
		$this->maintenance->repair_tables();
		CRPCRM_Logger::info( 'tables_repair_run', 'tables_repair_run', array( 'user_id' => get_current_user_id() ) );
		$this->redirect_tool( array( 'tables-repaired' => 'true' ) );
	}

	public function rebuild_roles() {
		$this->verify_maintenance( 'crpcrm_tools_rebuild_roles' );
		$this->maintenance->rebuild_roles();
		CRPCRM_Logger::info( 'roles_rebuilt', 'roles_rebuilt', array( 'user_id' => get_current_user_id(), 'source' => 'tools' ) );
		$this->redirect_tool( array( 'roles-rebuilt' => 'true' ) );
	}

	public function fix_request_codes() {
		$this->verify_maintenance( 'crpcrm_fix_request_codes' );
		$count = $this->maintenance->fix_missing_request_codes();
		CRPCRM_Logger::info( 'missing_request_codes_fixed', 'missing_request_codes_fixed', array( 'user_id' => get_current_user_id(), 'count' => $count ) );
		$this->redirect_tool( array( 'request-codes-fixed' => $count ) );
	}

	private function redirect_tool( $args ) {
		wp_safe_redirect( add_query_arg( array_merge( array( 'page' => 'crpcrm-settings', 'tab' => 'tools' ), $args ), admin_url( 'admin.php' ) ) );
		exit;
	}
}

<?php
/**
 * Database schema management.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_DB {
	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'crpcrm_' . $name;
	}

	public static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$tables          = self::get_schema_sql( $charset_collate );

		foreach ( $tables as $sql ) {
			dbDelta( $sql );
		}

		update_option( 'crpcrm_db_version', CRPCRM_DB_VERSION );
	}

	public static function maybe_upgrade() {
		if ( get_option( 'crpcrm_db_version' ) !== CRPCRM_DB_VERSION ) {
			self::create_tables();
		}
	}

	private static function get_schema_sql( $charset_collate ) {
		$customers                  = self::table( 'customers' );
		$attribution_events         = self::table( 'customer_attribution_events' );
		$requests                   = self::table( 'requests' );
		$request_activities         = self::table( 'request_activities' );
		$otp_logs                   = self::table( 'otp_logs' );
		$staff_daily_reports        = self::table( 'staff_daily_reports' );
		$staff_requests             = self::table( 'staff_requests' );
		$staff_issues               = self::table( 'staff_issues' );
		$staff_tasks                = self::table( 'staff_tasks' );
		$manager_announcements      = self::table( 'manager_announcements' );
		$announcement_reads         = self::table( 'announcement_reads' );
		$plugin_logs                = self::table( 'plugin_logs' );

		return array(
			"CREATE TABLE $customers (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				user_id BIGINT UNSIGNED NOT NULL,
				full_name VARCHAR(190) NULL,
				phone VARCHAR(30) NOT NULL,
				phone_normalized VARCHAR(30) NOT NULL,
				province VARCHAR(100) NULL,
				city VARCHAR(100) NULL,
				profile_completed TINYINT(1) NOT NULL DEFAULT 0,
				first_source VARCHAR(100) NULL,
				first_medium VARCHAR(100) NULL,
				first_campaign VARCHAR(190) NULL,
				first_content VARCHAR(190) NULL,
				first_term VARCHAR(190) NULL,
				first_landing_page TEXT NULL,
				first_referrer TEXT NULL,
				first_seen_at DATETIME NULL,
				last_source VARCHAR(100) NULL,
				last_medium VARCHAR(100) NULL,
				last_campaign VARCHAR(190) NULL,
				last_content VARCHAR(190) NULL,
				last_term VARCHAR(190) NULL,
				last_landing_page TEXT NULL,
				last_referrer TEXT NULL,
				last_seen_at DATETIME NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY user_id (user_id),
				UNIQUE KEY phone_normalized (phone_normalized),
				KEY profile_completed (profile_completed),
				KEY first_source (first_source),
				KEY last_source (last_source),
				KEY created_at (created_at)
			) $charset_collate;",
			"CREATE TABLE $attribution_events (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				customer_id BIGINT UNSIGNED NULL,
				user_id BIGINT UNSIGNED NULL,
				source VARCHAR(100) NULL,
				medium VARCHAR(100) NULL,
				campaign VARCHAR(190) NULL,
				content VARCHAR(190) NULL,
				term VARCHAR(190) NULL,
				landing_page TEXT NULL,
				referrer TEXT NULL,
				detected_at DATETIME NOT NULL,
				is_logged_in TINYINT(1) NOT NULL DEFAULT 0,
				ip_hash VARCHAR(190) NULL,
				user_agent_hash VARCHAR(190) NULL,
				created_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				KEY customer_id (customer_id),
				KEY user_id (user_id),
				KEY source (source),
				KEY campaign (campaign),
				KEY detected_at (detected_at),
				KEY is_logged_in (is_logged_in)
			) $charset_collate;",
			"CREATE TABLE $requests (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				request_code VARCHAR(50) NOT NULL,
				customer_id BIGINT UNSIGNED NOT NULL,
				user_id BIGINT UNSIGNED NOT NULL,
				request_type VARCHAR(50) NOT NULL,
				status VARCHAR(50) NOT NULL DEFAULT 'new',
				owner_id BIGINT UNSIGNED NULL,
				first_assigned_at DATETIME NULL,
				request_title VARCHAR(255) NULL,
				request_summary TEXT NULL,
				request_data LONGTEXT NULL,
				request_source VARCHAR(100) NULL,
				request_medium VARCHAR(100) NULL,
				request_campaign VARCHAR(190) NULL,
				request_content VARCHAR(190) NULL,
				request_term VARCHAR(190) NULL,
				request_landing_page TEXT NULL,
				request_referrer TEXT NULL,
				last_action VARCHAR(100) NULL,
				last_activity_at DATETIME NULL,
				next_follow_up_at DATETIME NULL,
				closed_at DATETIME NULL,
				close_reason VARCHAR(100) NULL,
				invalid_reason VARCHAR(100) NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY request_code (request_code),
				KEY customer_id (customer_id),
				KEY user_id (user_id),
				KEY request_type (request_type),
				KEY status (status),
				KEY owner_id (owner_id),
				KEY request_source (request_source),
				KEY request_campaign (request_campaign),
				KEY request_content (request_content),
				KEY created_at (created_at),
				KEY next_follow_up_at (next_follow_up_at),
				KEY closed_at (closed_at)
			) $charset_collate;",
			"CREATE TABLE $request_activities (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				request_id BIGINT UNSIGNED NOT NULL,
				customer_id BIGINT UNSIGNED NULL,
				actor_user_id BIGINT UNSIGNED NULL,
				actor_type VARCHAR(50) NOT NULL DEFAULT 'system',
				activity_type VARCHAR(100) NOT NULL,
				old_status VARCHAR(50) NULL,
				new_status VARCHAR(50) NULL,
				note TEXT NULL,
				is_internal TINYINT(1) NOT NULL DEFAULT 1,
				meta LONGTEXT NULL,
				created_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				KEY request_id (request_id),
				KEY customer_id (customer_id),
				KEY actor_user_id (actor_user_id),
				KEY activity_type (activity_type),
				KEY created_at (created_at),
				KEY is_internal (is_internal)
			) $charset_collate;",
			"CREATE TABLE $otp_logs (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				phone_normalized VARCHAR(30) NOT NULL,
				otp_hash VARCHAR(255) NULL,
				verification_token_hash VARCHAR(255) NULL,
				status VARCHAR(50) NOT NULL DEFAULT 'created',
				provider VARCHAR(100) NULL,
				provider_response LONGTEXT NULL,
				ip_hash VARCHAR(190) NULL,
				user_agent_hash VARCHAR(190) NULL,
				attempts INT UNSIGNED NOT NULL DEFAULT 0,
				expires_at DATETIME NULL,
				verified_at DATETIME NULL,
				created_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				KEY phone_normalized (phone_normalized),
				KEY status (status),
				KEY expires_at (expires_at),
				KEY created_at (created_at),
				KEY ip_hash (ip_hash)
			) $charset_collate;",
			"CREATE TABLE $staff_daily_reports (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				user_id BIGINT UNSIGNED NOT NULL,
				report_date DATE NOT NULL,
				completed_work TEXT NULL,
				unfinished_work TEXT NULL,
				problems TEXT NULL,
				tomorrow_plan TEXT NULL,
				needs_manager_attention TINYINT(1) NOT NULL DEFAULT 0,
				manager_response TEXT NULL,
				status VARCHAR(50) NOT NULL DEFAULT 'submitted',
				sales_crm_snapshot LONGTEXT NULL,
				sales_comment TEXT NULL,
				seen_at DATETIME NULL,
				responded_at DATETIME NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY user_report_date (user_id,report_date),
				KEY user_id (user_id),
				KEY report_date (report_date),
				KEY status (status),
				KEY needs_manager_attention (needs_manager_attention),
				KEY created_at (created_at)
			) $charset_collate;",
			"CREATE TABLE $staff_requests (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				user_id BIGINT UNSIGNED NOT NULL,
				category VARCHAR(100) NOT NULL,
				title VARCHAR(255) NOT NULL,
				description TEXT NOT NULL,
				priority VARCHAR(50) NOT NULL DEFAULT 'normal',
				status VARCHAR(50) NOT NULL DEFAULT 'new',
				manager_response TEXT NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				KEY user_id (user_id),
				KEY category (category),
				KEY priority (priority),
				KEY status (status),
				KEY created_at (created_at)
			) $charset_collate;",
			"CREATE TABLE $staff_issues (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				user_id BIGINT UNSIGNED NOT NULL,
				title VARCHAR(255) NOT NULL,
				related_department VARCHAR(190) NULL,
				severity VARCHAR(50) NOT NULL DEFAULT 'medium',
				description TEXT NOT NULL,
				suggested_solution TEXT NULL,
				needs_manager_decision TINYINT(1) NOT NULL DEFAULT 0,
				status VARCHAR(50) NOT NULL DEFAULT 'new',
				manager_response TEXT NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				KEY user_id (user_id),
				KEY severity (severity),
				KEY status (status),
				KEY needs_manager_decision (needs_manager_decision),
				KEY created_at (created_at)
			) $charset_collate;",
			"CREATE TABLE $staff_tasks (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				title VARCHAR(255) NOT NULL,
				description TEXT NULL,
				assigned_to BIGINT UNSIGNED NOT NULL,
				due_date DATE NULL,
				priority VARCHAR(50) NOT NULL DEFAULT 'normal',
				status VARCHAR(50) NOT NULL DEFAULT 'new',
				employee_update TEXT NULL,
				manager_note TEXT NULL,
				created_by BIGINT UNSIGNED NOT NULL,
				completed_at DATETIME NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				KEY assigned_to (assigned_to),
				KEY created_by (created_by),
				KEY due_date (due_date),
				KEY priority (priority),
				KEY status (status),
				KEY created_at (created_at)
			) $charset_collate;",
			"CREATE TABLE $manager_announcements (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				title VARCHAR(255) NOT NULL,
				body TEXT NOT NULL,
				audience_type VARCHAR(50) NOT NULL DEFAULT 'all',
				audience_data LONGTEXT NULL,
				created_by BIGINT UNSIGNED NOT NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				KEY audience_type (audience_type),
				KEY created_by (created_by),
				KEY created_at (created_at)
			) $charset_collate;",
			"CREATE TABLE $announcement_reads (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				announcement_id BIGINT UNSIGNED NOT NULL,
				user_id BIGINT UNSIGNED NOT NULL,
				read_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY announcement_user (announcement_id,user_id),
				KEY announcement_id (announcement_id),
				KEY user_id (user_id),
				KEY read_at (read_at)
			) $charset_collate;",
			"CREATE TABLE $plugin_logs (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				level VARCHAR(50) NOT NULL DEFAULT 'info',
				context VARCHAR(100) NULL,
				message TEXT NOT NULL,
				meta LONGTEXT NULL,
				created_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				KEY level (level),
				KEY context (context),
				KEY created_at (created_at)
			) $charset_collate;",
		);
	}
}

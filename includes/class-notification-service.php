<?php
/**
 * Central plugin notification service.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_Notification_Service {
	private $table;

	public function __construct() {
		$this->table = CRPCRM_DB::table( 'notifications' );
	}

	public function register_hooks() {
		add_action( 'admin_post_crpcrm_notification_action', array( $this, 'handle_action' ) );
		add_action( 'wp_ajax_crpcrm_get_new_notifications', array( $this, 'ajax_get_new_notifications' ) );
	}

	public static function is_enabled() {
		return CRPCRM_Feature_Manager::is_enabled( 'notifications' );
	}

	public static function current_user_can_view_notifications() {
		return current_user_can( 'crpcrm_view_notifications' ) || current_user_can( 'crpcrm_use_staff_portal' ) || current_user_can( 'manage_options' );
	}

	public function unique_user_ids( $user_ids ) {
		$clean = array();
		foreach ( array_map( 'absint', (array) $user_ids ) as $user_id ) {
			if ( $user_id <= 0 || isset( $clean[ $user_id ] ) ) {
				continue;
			}
			$user = get_userdata( $user_id );
			if ( ! $user ) {
				continue;
			}
			$clean[ $user_id ] = $user_id;
		}

		return array_values( $clean );
	}

	public function get_user_recipients_by_capability( $capabilities ) {
		$capabilities = array_values( array_filter( array_map( 'sanitize_key', (array) $capabilities ) ) );
		if ( empty( $capabilities ) ) {
			return array();
		}

		$user_ids = array();
		foreach ( get_users( array( 'fields' => 'ID' ) ) as $user_id ) {
			$user_id = absint( $user_id );
			if ( $user_id <= 0 ) {
				continue;
			}

			foreach ( $capabilities as $capability ) {
				if ( user_can( $user_id, $capability ) ) {
					$user_ids[] = $user_id;
					break;
				}
			}
		}

		return $this->unique_user_ids( $user_ids );
	}

	public function get_request_manager_recipients() {
		return $this->get_user_recipients_by_capability( array( 'crpcrm_view_all_requests', 'crpcrm_manage_requests', 'crpcrm_assign_requests', 'manage_options' ) );
	}

	public function get_staff_manager_recipients() {
		return $this->get_user_recipients_by_capability( array( 'crpcrm_manage_staff_portal', 'crpcrm_view_reports', 'crpcrm_manage_plugin', 'manage_options' ) );
	}

	public function get_sales_agent_recipients() {
		return $this->get_user_recipients_by_capability( array( 'crpcrm_view_assigned_requests', 'crpcrm_claim_requests', 'crpcrm_create_requests' ) );
	}

	public function create_for_user( $user_id, $type, $title, $message = '', $target_url = '', $target_type = '', $target_id = 0 ) {
		global $wpdb;

		if ( ! self::is_enabled() ) {
			return 0;
		}

		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			return 0;
		}

		$type = sanitize_key( (string) $type );
		if ( '' === $type ) {
			return 0;
		}

		$title = sanitize_text_field( (string) $title );
		if ( '' === $title ) {
			return 0;
		}

		$message = null === $message || '' === (string) $message ? null : sanitize_textarea_field( (string) $message );
		if ( '' === $message ) {
			$message = null;
		}

		$target_url  = '' !== trim( (string) $target_url ) ? esc_url_raw( (string) $target_url ) : null;
		$target_type = '' !== trim( (string) $target_type ) ? sanitize_key( (string) $target_type ) : null;
		$target_id   = absint( $target_id );
		$target_id   = $target_id > 0 ? $target_id : null;

		$inserted = $wpdb->insert(
			$this->table,
			array(
				'user_id'     => $user_id,
				'type'        => $type,
				'title'       => $title,
				'message'     => $message,
				'target_url'  => $target_url,
				'target_type' => $target_type,
				'target_id'   => $target_id,
				'is_seen'     => 0,
				'is_read'     => 0,
				'seen_at'     => null,
				'read_at'     => null,
				'created_at'  => CRPCRM_Helpers::current_datetime(),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			$this->log_insert_failure(
				'create_for_user',
				array(
					'user_id'    => $user_id,
					'type'       => $type,
					'title'      => $title,
					'wpdb_error' => $wpdb->last_error,
				)
			);
			return 0;
		}

		return absint( $wpdb->insert_id );
	}

	public function create_for_users( $user_ids, $type, $title, $message = '', $target_url = '', $target_type = '', $target_id = 0 ) {
		$created_ids = array();
		$unique_ids  = $this->unique_user_ids( $user_ids );

		foreach ( $unique_ids as $user_id ) {
			$notification_id = $this->create_for_user( $user_id, $type, $title, $message, $target_url, $target_type, $target_id );
			if ( $notification_id ) {
				$created_ids[] = $notification_id;
			}
		}

		return $created_ids;
	}

	public function get_notifications_page_url() {
		return admin_url( 'admin.php?page=crpcrm-notifications' );
	}

	public function ajax_get_new_notifications() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'unauthorized' ), 401 );
		}

		check_ajax_referer( 'crpcrm_get_new_notifications', 'nonce' );

		if ( ! self::current_user_can_view_notifications() ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}

		if ( ! self::is_enabled() ) {
			wp_send_json_success(
				array(
					'notifications'          => array(),
					'notifications_page_url' => $this->get_notifications_page_url(),
				)
			);
		}

		$user_id = get_current_user_id();
		$notifications = $this->get_recent_unseen_for_user( $user_id, 5 );
		$ids = array();
		$payload = array();

		foreach ( $notifications as $notification ) {
			if ( ! is_array( $notification ) ) {
				continue;
			}

			$notification_id = absint( $notification['id'] ?? 0 );
			if ( $notification_id <= 0 ) {
				continue;
			}

			$ids[] = $notification_id;
			$payload[] = $this->sanitize_notification_payload( $notification );
		}

		if ( ! empty( $ids ) ) {
			$this->mark_seen( $ids, $user_id );
		}

		wp_send_json_success(
			array(
				'notifications'          => $payload,
				'notifications_page_url' => $this->get_notifications_page_url(),
			)
		);
	}

	public function get_recent_unseen_for_user( $user_id, $limit = 5 ) {
		global $wpdb;

		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			return array();
		}

		$limit  = max( 1, absint( $limit ) );
		$hours  = $this->get_notification_toast_max_age_hours();
		$cutoff = $this->get_notification_toast_cutoff_datetime( $hours );
		if ( '' === $cutoff ) {
			return $this->get_unseen_for_user( $user_id, $limit );
		}

		$sql = $wpdb->prepare(
			"SELECT * FROM {$this->table} WHERE user_id = %d AND is_seen = 0 AND created_at >= %s ORDER BY created_at DESC, id DESC LIMIT %d",
			$user_id,
			$cutoff,
			$limit
		);

		return $wpdb->get_results( $sql, ARRAY_A );
	}

	public function notify_new_request( $request_id, $request_title, $request_code = '', $actor_user_id = 0, $target_url = '' ) {
		if ( ! self::is_enabled() ) {
			return array();
		}

		$actor_user_id = absint( $actor_user_id );
		$request_id    = absint( $request_id );
		$request_code  = sanitize_text_field( (string) $request_code );
		$request_title = sanitize_text_field( (string) $request_title );
		$recipients    = $this->unique_user_ids(
			array_diff(
				$this->get_request_manager_recipients(),
				$actor_user_id ? array( $actor_user_id ) : array()
			)
		);

		if ( empty( $recipients ) ) {
			return array();
		}

		$message = $request_title ? sprintf( 'یک درخواست جدید با عنوان «%s» ثبت شد.', $request_title ) : 'یک درخواست جدید ثبت شد.';
		if ( '' !== $request_code ) {
			$message = sprintf( 'یک درخواست جدید با عنوان «%1$s» و کد %2$s ثبت شد.', $request_title ?: 'بدون عنوان', $request_code );
		}

		$target_url = $this->normalize_internal_url(
			$target_url,
			$request_id ? admin_url( 'admin.php?page=crpcrm-requests&request_id=' . $request_id ) : admin_url( 'admin.php?page=crpcrm-requests' )
		);

		return $this->create_for_users( $recipients, 'new_request', 'درخواست جدید ثبت شد', $message, $target_url, 'request', $request_id );
	}

	public function notify_request_assigned( $request_id, $request_title, $new_owner_id, $actor_user_id = 0, $previous_owner_id = 0, $request_code = '', $target_url = '' ) {
		if ( ! self::is_enabled() ) {
			return array();
		}

		$new_owner_id    = absint( $new_owner_id );
		$actor_user_id    = absint( $actor_user_id );
		$previous_owner_id = absint( $previous_owner_id );
		$request_id      = absint( $request_id );
		$request_title   = sanitize_text_field( (string) $request_title );
		$request_code    = sanitize_text_field( (string) $request_code );

		if ( $new_owner_id <= 0 || $new_owner_id === $previous_owner_id || $new_owner_id === $actor_user_id ) {
			return array();
		}

		$recipient = $this->unique_user_ids( array( $new_owner_id ) );
		if ( empty( $recipient ) ) {
			return array();
		}

		$message = $request_title ? sprintf( 'درخواست «%s» به شما اختصاص داده شد.', $request_title ) : 'یک درخواست جدید به شما اختصاص داده شد.';
		if ( '' !== $request_code ) {
			$message = sprintf( 'درخواست %1$s%s به شما اختصاص داده شد.', $request_code, $request_title ? ' - ' . $request_title : '' );
		}

		$target_url = $this->normalize_internal_url(
			$target_url,
			$request_id ? admin_url( 'admin.php?page=crpcrm-requests&request_id=' . $request_id ) : admin_url( 'admin.php?page=crpcrm-requests' )
		);

		return $this->create_for_users( $recipient, 'task_assigned', 'وظیفه جدید به شما اختصاص داده شد', $message, $target_url, 'request', $request_id );
	}

	public function notify_reply_added( $request, $actor_user_id, $message_text, $target_url = '' ) {
		if ( ! self::is_enabled() ) {
			return array();
		}

		$request       = is_array( $request ) ? $request : array();
		$request_id    = absint( $request['id'] ?? 0 );
		$request_code  = sanitize_text_field( (string) ( $request['request_code'] ?? '' ) );
		$customer_id   = absint( $request['user_id'] ?? 0 );
		$owner_id      = absint( $request['owner_id'] ?? 0 );
		$actor_user_id = absint( $actor_user_id );
		$message_text  = sanitize_textarea_field( (string) $message_text );
		$actor_type    = $this->get_reply_actor_type( $request, $actor_user_id );
		$title         = 'پاسخ جدید برای درخواست';

		if ( '' === $message_text ) {
			$message_text = 'یک پاسخ جدید برای این درخواست ثبت شد.';
		}

		$results = array();

		if ( 'customer' === $actor_type ) {
			$recipients = array();
			if ( $owner_id && $owner_id !== $actor_user_id ) {
				$recipients[] = $owner_id;
			} else {
				$recipients = $this->get_request_manager_recipients();
			}

			$recipients = array_diff( $this->unique_user_ids( $recipients ), array_filter( array( $actor_user_id, $customer_id ) ) );
			if ( ! empty( $recipients ) ) {
				$results = array_merge(
					$results,
					$this->create_for_users(
						$recipients,
						'reply_added',
						$title,
						$message_text,
						$this->normalize_internal_url( $target_url, $this->get_admin_request_url( $request_id ) ),
						'request',
						$request_id
					)
				);
			}

			return $results;
		}

		if ( $customer_id && $customer_id !== $actor_user_id ) {
			$results = array_merge(
				$results,
				$this->create_for_users(
					array( $customer_id ),
					'reply_added',
					$title,
					$message_text,
					$this->normalize_internal_url( '', $this->get_portal_request_url( $request_id, $request_code ) ),
					'request',
					$request_id
				)
			);
		}

		return $results;
	}

	public function notify_daily_report_created( $report_id, $report_user_id, $report_title = '' ) {
		if ( ! self::is_enabled() ) {
			return array();
		}

		$report_id      = absint( $report_id );
		$report_user_id = absint( $report_user_id );
		$recipients     = $this->unique_user_ids( array_diff( $this->get_staff_manager_recipients(), $report_user_id ? array( $report_user_id ) : array() ) );
		if ( empty( $recipients ) ) {
			return array();
		}

		$message = $report_title ? sprintf( 'گزارش روزانه %s ثبت شد.', sanitize_text_field( $report_title ) ) : 'یک گزارش روزانه جدید ثبت شد.';
		$target_url = $this->normalize_internal_url(
			'',
			$report_id ? admin_url( 'admin.php?page=crpcrm-staff&staff_tab=daily_reports&staff_item_id=' . $report_id ) : admin_url( 'admin.php?page=crpcrm-staff&staff_tab=daily_reports' )
		);

		return $this->create_for_users( $recipients, 'daily_report_created', 'گزارش روزانه جدید ثبت شد', $message, $target_url, 'daily_report', $report_id );
	}

	public function notify_staff_request_created( $request_id, $request_user_id, $request_title = '' ) {
		if ( ! self::is_enabled() ) {
			return array();
		}

		$request_id     = absint( $request_id );
		$request_user_id = absint( $request_user_id );
		$recipients     = $this->unique_user_ids( array_diff( $this->get_staff_manager_recipients(), $request_user_id ? array( $request_user_id ) : array() ) );
		if ( empty( $recipients ) ) {
			return array();
		}

		$message = $request_title ? sprintf( 'درخواست جدید از کارشناس برای «%s» ثبت شد.', sanitize_text_field( $request_title ) ) : 'درخواست جدید از کارشناس ثبت شد.';
		$target_url = $this->normalize_internal_url(
			'',
			$request_id ? admin_url( 'admin.php?page=crpcrm-staff&staff_tab=requests&staff_item_id=' . $request_id ) : admin_url( 'admin.php?page=crpcrm-staff&staff_tab=requests' )
		);

		return $this->create_for_users( $recipients, 'staff_request_created', 'درخواست جدید از کارشناس', $message, $target_url, 'staff_request', $request_id );
	}

	public function notify_issue_created( $issue_id, $issue_user_id, $issue_title = '' ) {
		if ( ! self::is_enabled() ) {
			return array();
		}

		$issue_id      = absint( $issue_id );
		$issue_user_id = absint( $issue_user_id );
		$recipients    = $this->unique_user_ids( array_diff( $this->get_staff_manager_recipients(), $issue_user_id ? array( $issue_user_id ) : array() ) );
		if ( empty( $recipients ) ) {
			return array();
		}

		$message = $issue_title ? sprintf( 'مشکل جدید «%s» ثبت شد.', sanitize_text_field( $issue_title ) ) : 'مشکل جدید توسط کارشناس ثبت شد.';
		$target_url = $this->normalize_internal_url(
			'',
			$issue_id ? admin_url( 'admin.php?page=crpcrm-staff&staff_tab=issues&staff_item_id=' . $issue_id ) : admin_url( 'admin.php?page=crpcrm-staff&staff_tab=issues' )
		);

		return $this->create_for_users( $recipients, 'issue_created', 'مشکل جدید توسط کارشناس ثبت شد', $message, $target_url, 'issue', $issue_id );
	}

	public function notify_announcement_created( $announcement_id, $recipient_user_ids, $announcement_title = '' ) {
		if ( ! self::is_enabled() ) {
			return array();
		}

		$announcement_id   = absint( $announcement_id );
		$recipient_user_ids = $this->unique_user_ids( $recipient_user_ids );
		if ( empty( $recipient_user_ids ) ) {
			return array();
		}

		$message = $announcement_title ? sanitize_text_field( $announcement_title ) : 'اطلاعیه جدید منتشر شد.';
		$target_url = $this->normalize_internal_url(
			'',
			$announcement_id ? admin_url( 'admin.php?page=crpcrm-staff&staff_tab=announcements&staff_item_id=' . $announcement_id ) : admin_url( 'admin.php?page=crpcrm-staff&staff_tab=announcements' )
		);

		return $this->create_for_users( $recipient_user_ids, 'announcement', 'اطلاعیه جدید', $message, $target_url, 'announcement', $announcement_id );
	}

	public function get_unseen_for_user( $user_id, $limit = 5 ) {
		return $this->get_for_user(
			$user_id,
			array(
				'limit'    => max( 1, absint( $limit ) ),
				'offset'   => 0,
				'is_seen'  => 0,
			)
		);
	}

	private function get_notification_toast_max_age_hours() {
		$hours = apply_filters( 'crpcrm_notification_toast_max_age_hours', 48 );
		$hours = absint( $hours );

		return max( 1, $hours );
	}

	private function get_notification_toast_cutoff_datetime( $hours ) {
		$hours = absint( $hours );
		if ( $hours <= 0 ) {
			return '';
		}

		return CRPCRM_Helpers::subtract_seconds_from_now( $hours * HOUR_IN_SECONDS );
	}

	public function mark_seen( $ids, $user_id ) {
		global $wpdb;

		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			return 0;
		}

		$ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $ids ) ) ) );
		if ( empty( $ids ) ) {
			return 0;
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$sql          = "UPDATE {$this->table} SET is_seen = 1, seen_at = %s WHERE user_id = %d AND is_seen = 0 AND id IN ($placeholders)";
		$params       = array_merge( array( CRPCRM_Helpers::current_datetime(), $user_id ), $ids );

		$result = $wpdb->query( $wpdb->prepare( $sql, $params ) );
		return false === $result ? false : (int) $result;
	}

	public function get_for_user( $user_id, $args = array() ) {
		global $wpdb;

		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			return array();
		}

		$args = wp_parse_args(
			$args,
			array(
				'limit'   => 50,
				'offset'  => 0,
				'order'   => 'DESC',
				'is_seen' => null,
				'is_read' => null,
			)
		);

		$order = strtoupper( sanitize_key( (string) $args['order'] ) );
		if ( ! in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
			$order = 'DESC';
		}

		$where  = array( 'user_id = %d' );
		$values = array( $user_id );

		if ( null !== $args['is_seen'] ) {
			$where[]  = 'is_seen = %d';
			$values[] = $args['is_seen'] ? 1 : 0;
		}

		if ( null !== $args['is_read'] ) {
			$where[]  = 'is_read = %d';
			$values[] = $args['is_read'] ? 1 : 0;
		}

		$values[] = max( 1, absint( $args['limit'] ) );
		$values[] = max( 0, absint( $args['offset'] ) );

		$sql = "SELECT * FROM {$this->table} WHERE " . implode( ' AND ', $where ) . " ORDER BY created_at {$order}, id {$order} LIMIT %d OFFSET %d";

		return $wpdb->get_results(
			$wpdb->prepare( $sql, $values ),
			ARRAY_A
		);
	}

	public function mark_read( $id, $user_id ) {
		global $wpdb;

		$user_id = absint( $user_id );
		$id      = absint( $id );
		if ( $user_id <= 0 || $id <= 0 ) {
			return 0;
		}

		$sql    = "UPDATE {$this->table} SET is_read = 1, read_at = %s WHERE user_id = %d AND id = %d AND is_read = 0";
		$result = $wpdb->query(
			$wpdb->prepare(
				$sql,
				CRPCRM_Helpers::current_datetime(),
				$user_id,
				$id
			)
		);

		return false === $result ? false : (int) $result;
	}

	public function mark_all_read( $user_id ) {
		global $wpdb;

		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			return 0;
		}

		$sql    = "UPDATE {$this->table} SET is_read = 1, read_at = %s WHERE user_id = %d AND is_read = 0";
		$result = $wpdb->query(
			$wpdb->prepare(
				$sql,
				CRPCRM_Helpers::current_datetime(),
				$user_id
			)
		);

		return false === $result ? false : (int) $result;
	}

	public function get_unread_count( $user_id ) {
		global $wpdb;

		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			return 0;
		}

		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->table} WHERE user_id = %d AND is_read = 0", $user_id ) );
	}

	public function count_for_user( $user_id, $args = array() ) {
		global $wpdb;

		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			return 0;
		}

		$args = wp_parse_args(
			$args,
			array(
				'is_seen' => null,
				'is_read' => null,
			)
		);

		$where  = array( 'user_id = %d' );
		$values = array( $user_id );

		if ( null !== $args['is_seen'] ) {
			$where[]  = 'is_seen = %d';
			$values[] = $args['is_seen'] ? 1 : 0;
		}

		if ( null !== $args['is_read'] ) {
			$where[]  = 'is_read = %d';
			$values[] = $args['is_read'] ? 1 : 0;
		}

		$sql = "SELECT COUNT(*) FROM {$this->table} WHERE " . implode( ' AND ', $where );

		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $values ) );
	}

	public function get_notification( $notification_id, $user_id ) {
		global $wpdb;

		$notification_id = absint( $notification_id );
		$user_id         = absint( $user_id );
		if ( $notification_id <= 0 || $user_id <= 0 ) {
			return null;
		}

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d AND user_id = %d", $notification_id, $user_id ),
			ARRAY_A
		);
	}

	public function get_safe_notification_target_url( $url, $fallback_url = '' ) {
		$url          = is_string( $url ) ? trim( $url ) : '';
		$fallback_url = is_string( $fallback_url ) && '' !== trim( $fallback_url ) ? trim( $fallback_url ) : $this->get_notifications_page_url();
		$fallback_url = esc_url_raw( $fallback_url );

		if ( '' === $url ) {
			return $fallback_url;
		}

		if ( 0 === strpos( $url, '/' ) ) {
			$url = home_url( $url );
		}

		$admin_prefix = admin_url();
		$home_prefix   = home_url( '/' );
		if ( 0 !== strpos( $url, $admin_prefix ) && 0 !== strpos( $url, $home_prefix ) ) {
			return $fallback_url;
		}

		$validated = wp_validate_redirect( $url, $fallback_url );
		return $validated ? $validated : $fallback_url;
	}

	private function sanitize_notification_payload( $notification ) {
		$notification = is_array( $notification ) ? $notification : array();
		return array(
			'id'                 => absint( $notification['id'] ?? 0 ),
			'type'               => sanitize_key( (string) ( $notification['type'] ?? '' ) ),
			'title'              => sanitize_text_field( (string) ( $notification['title'] ?? '' ) ),
			'message'            => sanitize_textarea_field( (string) ( $notification['message'] ?? '' ) ),
			'created_at'         => sanitize_text_field( (string) ( $notification['created_at'] ?? '' ) ),
			'target_url'         => ! empty( $notification['target_url'] ) ? esc_url_raw( (string) $notification['target_url'] ) : '',
			'notifications_page_url' => $this->get_notifications_page_url(),
		);
	}

	private function normalize_internal_url( $candidate_url, $fallback_url ) {
		$candidate_url = is_string( $candidate_url ) ? trim( $candidate_url ) : '';
		$fallback_url  = is_string( $fallback_url ) ? trim( $fallback_url ) : '';

		foreach ( array_filter( array( $candidate_url, $fallback_url ) ) as $url ) {
			if ( 0 === strpos( $url, admin_url() ) || 0 === strpos( $url, home_url( '/' ) ) ) {
				return $url;
			}
		}

		return $fallback_url ? $fallback_url : admin_url();
	}

	private function get_reply_actor_type( $request, $actor_user_id ) {
		$request       = is_array( $request ) ? $request : array();
		$actor_user_id = absint( $actor_user_id );
		$request_user  = absint( $request['user_id'] ?? 0 );
		$owner_id      = absint( $request['owner_id'] ?? 0 );

		if ( $request_user && $request_user === $actor_user_id ) {
			return 'customer';
		}

		if ( user_can( $actor_user_id, 'crpcrm_manage_requests' ) || user_can( $actor_user_id, 'manage_options' ) ) {
			return 'manager';
		}

		$user = get_userdata( $actor_user_id );
		if ( $user ) {
			$roles = (array) $user->roles;
			if ( in_array( 'sales_manager', $roles, true ) ) {
				return 'manager';
			}
		}

		if ( $owner_id && $owner_id === $actor_user_id ) {
			return 'staff';
		}

		return 'staff';
	}

	private function get_admin_request_url( $request_id ) {
		$request_id = absint( $request_id );
		return $request_id ? admin_url( 'admin.php?page=crpcrm-requests&request_id=' . $request_id ) : admin_url( 'admin.php?page=crpcrm-requests' );
	}

	private function get_portal_request_url( $request_id, $request_code = '' ) {
		$request_id   = absint( $request_id );
		$request_code = sanitize_text_field( (string) $request_code );
		$settings     = class_exists( 'CRPCRM_Settings' ) ? CRPCRM_Settings::get() : array();
		$portal_page  = absint( $settings['portal_page_id'] ?? 0 );

		if ( $portal_page ) {
			$portal_url = get_permalink( $portal_page );
			if ( $portal_url ) {
				return add_query_arg(
					array_filter(
						array(
							'crpcrm_page'  => 'request_detail',
							'request_id'   => $request_id,
							'request_code' => $request_code,
						)
					),
					$portal_url
				);
			}
		}

		return $this->get_admin_request_url( $request_id );
	}

	public function list_for_user( $user_id, $args = array() ) {
		return $this->get_for_user( $user_id, $args );
	}

	public function count_unread_for_user( $user_id ) {
		return $this->get_unread_count( $user_id );
	}

	public function get( $notification_id, $user_id ) {
		return $this->get_notification( $notification_id, $user_id );
	}

	public function handle_action() {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'دسترسی نامعتبر است.', 'customer-request-portal-crm' ) );
		}

		if ( ! self::is_enabled() ) {
			wp_safe_redirect( $this->get_notifications_page_url() );
			exit;
		}

		check_admin_referer( 'crpcrm_notification_action', 'crpcrm_notification_nonce' );

		$user_id         = get_current_user_id();
		$action_type     = isset( $_POST['notification_action_type'] ) ? sanitize_key( wp_unslash( $_POST['notification_action_type'] ) ) : '';
		$notification_id = isset( $_POST['notification_id'] ) ? absint( $_POST['notification_id'] ) : 0;
		$notifications_page_url = $this->get_notifications_page_url();

		if ( ! self::current_user_can_view_notifications() ) {
			wp_safe_redirect( $notifications_page_url );
			exit;
		}

		if ( 'mark_all_read' === $action_type ) {
			$this->mark_all_read( $user_id );
			wp_safe_redirect( $notifications_page_url );
			exit;
		}

		if ( 'mark_read' === $action_type ) {
			if ( $notification_id > 0 ) {
				$this->mark_read( $notification_id, $user_id );
			}
			wp_safe_redirect( $notifications_page_url );
			exit;
		}

		$notification = $this->get_notification( $notification_id, $user_id );
		if ( ! $notification ) {
			wp_safe_redirect( $notifications_page_url );
			exit;
		}

		$this->mark_seen( array( $notification_id ), $user_id );
		$this->mark_read( $notification_id, $user_id );
		$target = $this->get_safe_notification_target_url( $notification['target_url'] ?? '', $notifications_page_url );
		wp_safe_redirect( $target );
		exit;
	}

	private function log_insert_failure( $action, $meta = array() ) {
		$message = 'notification_insert_failed';
		$payload = array_merge(
			array(
				'action' => sanitize_key( $action ),
			),
			is_array( $meta ) ? $meta : array()
		);

		$logged = false;
		if ( class_exists( 'CRPCRM_Logger' ) && method_exists( 'CRPCRM_Logger', 'error' ) ) {
			$logged = false !== CRPCRM_Logger::error( $message, 'notification_service', $payload );
		}

		if ( ! $logged ) {
			$log_message = sprintf(
				'[CRPCRM] %s: %s',
				$message,
				wp_json_encode( $payload )
			);
			error_log( $log_message );
		}
	}
}

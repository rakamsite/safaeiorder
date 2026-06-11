<?php
/**
 * Sales daily CRM statistics service.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_Sales_Daily_Stats_Service {
	private $requests_table;
	private $activities_table;
	private $staff_repository;

	const OPEN_STATUSES   = array( 'new', 'in_progress', 'no_answer', 'follow_up' );
	const CLOSED_STATUSES = array( 'won', 'lost', 'invalid' );
	const SALES_ACTIONS   = array( 'call_answered', 'call_no_answer', 'whatsapp_sent', 'internal_note', 'follow_up_scheduled', 'request_won', 'request_lost', 'request_invalid' );

	public function __construct( $staff_repository = null ) {
		$this->requests_table   = CRPCRM_DB::table( 'requests' );
		$this->activities_table = CRPCRM_DB::table( 'request_activities' );
		$this->staff_repository = $staff_repository ? $staff_repository : new CRPCRM_Staff_Repository();
	}

	public function is_sales_user( $user_id ) {
		$user = get_userdata( absint( $user_id ) );
		if ( ! $user ) {
			return false;
		}

		$roles = (array) $user->roles;
		return (bool) array_intersect( array( 'sales_agent', 'sales_manager' ), $roles ) && ! in_array( 'customer', $roles, true );
	}

	public function get_sales_users() {
		if ( ! CRPCRM_Feature_Manager::is_enabled( 'staff' ) ) {
			return array();
		}

		$users = get_users(
			array(
				'role__in' => array( 'sales_agent', 'sales_manager' ),
				'orderby'  => 'display_name',
				'order'    => 'ASC',
				'fields'   => 'all',
			)
		);

		return array_values(
			array_filter(
				$users,
				function( $user ) {
					return ! in_array( 'customer', (array) $user->roles, true );
				}
			)
		);
	}

	public function get_stats_for_users( $user_ids, $date = null ) {
		if ( ! CRPCRM_Feature_Manager::is_enabled( 'staff' ) ) {
			return array();
		}

		$stats = array();
		foreach ( array_map( 'absint', (array) $user_ids ) as $user_id ) {
			if ( $user_id && $this->is_sales_user( $user_id ) ) {
				$stats[ $user_id ] = $this->get_stats_for_user( $user_id, $date );
			}
		}
		return $stats;
	}

	public function get_stats_for_user( $user_id, $date = null ) {
		global $wpdb;

		$user_id = absint( $user_id );
		if ( ! CRPCRM_Feature_Manager::is_enabled( 'staff' ) ) {
			return $this->empty_stats( $user_id, $date );
		}

		if ( ! $this->is_sales_user( $user_id ) ) {
			CRPCRM_Logger::warning( 'sales_daily_stats_access_denied', 'sales_daily_stats_access_denied', array( 'target_user_id' => $user_id, 'viewer_user_id' => get_current_user_id() ) );
			return $this->empty_stats( $user_id, $date );
		}

		$range = $this->get_day_range( $date );
		$start = $range['start'];
		$end   = $range['end'];
		$now   = CRPCRM_Helpers::current_datetime();

		$stats = $this->empty_stats( $user_id, $range['date'] );
		$stats['claimed_today'] = $this->count_activities_by_types( $user_id, array( 'request_claimed' ), $start, $end );
		$stats['current_owned_requests'] = $this->count_requests_by_statuses( $user_id, self::OPEN_STATUSES );
		$stats['actions_today'] = $this->count_activities_by_types( $user_id, self::SALES_ACTIONS, $start, $end );
		$stats['call_answered_today'] = $this->count_activities_by_types( $user_id, array( 'call_answered' ), $start, $end );
		$stats['call_no_answer_today'] = $this->count_activities_by_types( $user_id, array( 'call_no_answer' ), $start, $end );
		$stats['whatsapp_sent_today'] = $this->count_activities_by_types( $user_id, array( 'whatsapp_sent' ), $start, $end );
		$stats['internal_notes_today'] = $this->count_activities_by_types( $user_id, array( 'internal_note' ), $start, $end );
		$stats['followups_scheduled_today'] = $this->count_activities_by_types( $user_id, array( 'follow_up_scheduled' ), $start, $end );
		$stats['followups_due_today'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->requests_table} WHERE owner_id = %d AND status = %s AND next_follow_up_at BETWEEN %s AND %s", $user_id, 'follow_up', $start, $end ) );
		$stats['overdue_followups'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->requests_table} WHERE owner_id = %d AND status = %s AND next_follow_up_at IS NOT NULL AND next_follow_up_at < %s", $user_id, 'follow_up', $now ) );
		$stats['won_today'] = $this->count_activities_by_types( $user_id, array( 'request_won' ), $start, $end );
		$stats['lost_today'] = $this->count_activities_by_types( $user_id, array( 'request_lost' ), $start, $end );
		$stats['invalid_today'] = $this->count_activities_by_types( $user_id, array( 'request_invalid' ), $start, $end );
		$stats['open_requests'] = $stats['current_owned_requests'];
		$stats['closed_today'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->requests_table} WHERE owner_id = %d AND status IN ('won','lost','invalid') AND closed_at BETWEEN %s AND %s", $user_id, $start, $end ) );

		CRPCRM_Logger::info( 'sales_daily_stats_generated', 'sales_daily_stats_generated', array( 'user_id' => $user_id, 'date' => $range['date'] ) );
		return $stats;
	}

	public function build_snapshot( $user_id, $date = null ) {
		$stats = $this->get_stats_for_user( $user_id, $date );
		$stats['generated_at'] = CRPCRM_Helpers::current_datetime();
		return $stats;
	}

	public function snapshot_to_json( $snapshot ) {
		$normalized = $this->normalize_snapshot( $snapshot );
		return wp_json_encode( $normalized );
	}

	public function decode_snapshot( $snapshot_json ) {
		if ( empty( $snapshot_json ) || ! is_string( $snapshot_json ) ) {
			return array();
		}
		$decoded = json_decode( $snapshot_json, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
			return array();
		}
		return $this->normalize_snapshot( $decoded );
	}

	public function all_zero( $stats ) {
		foreach ( $this->stat_keys() as $key ) {
			if ( ! empty( $stats[ $key ] ) ) {
				return false;
			}
		}
		return true;
	}

	public function get_labels() {
		return array(
			'claimed_today' => 'درخواست‌هایی که امروز برداشته‌اید',
			'current_owned_requests' => 'درخواست‌های فعلی تحت مسئولیت شما',
			'actions_today' => 'اقدامات ثبت‌شده امروز',
			'call_answered_today' => 'تماس‌های پاسخ‌داده‌شده',
			'call_no_answer_today' => 'تماس‌های پاسخ‌داده‌نشده',
			'whatsapp_sent_today' => 'پیام‌های واتساپ',
			'internal_notes_today' => 'یادداشت‌های داخلی',
			'followups_scheduled_today' => 'پیگیری‌های تنظیم‌شده',
			'followups_due_today' => 'پیگیری‌های امروز',
			'overdue_followups' => 'پیگیری‌های عقب‌افتاده',
			'won_today' => 'موفق امروز',
			'lost_today' => 'ناموفق امروز',
			'invalid_today' => 'نامعتبر امروز',
			'open_requests' => 'درخواست‌های باز',
			'closed_today' => 'درخواست‌های بسته‌شده امروز',
			'generated_at' => 'زمان تولید آمار',
		);
	}

	private function get_day_range( $date = null ) {
		$timezone = wp_timezone();
		$date     = $date ? sanitize_text_field( $date ) : current_time( 'Y-m-d' );
		$day      = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $date . ' 00:00:00', $timezone );
		if ( ! $day ) {
			$day = new DateTimeImmutable( current_time( 'Y-m-d' ) . ' 00:00:00', $timezone );
		}

		return array(
			'date'  => $day->format( 'Y-m-d' ),
			'start' => $day->format( 'Y-m-d 00:00:00' ),
			'end'   => $day->format( 'Y-m-d 23:59:59' ),
		);
	}

	private function empty_stats( $user_id, $date = null ) {
		$range = $this->get_day_range( $date );
		$stats = array( 'date' => $range['date'], 'user_id' => absint( $user_id ) );
		foreach ( $this->stat_keys() as $key ) {
			$stats[ $key ] = 0;
		}
		return $stats;
	}

	private function stat_keys() {
		return array( 'claimed_today', 'current_owned_requests', 'actions_today', 'call_answered_today', 'call_no_answer_today', 'whatsapp_sent_today', 'internal_notes_today', 'followups_scheduled_today', 'followups_due_today', 'overdue_followups', 'won_today', 'lost_today', 'invalid_today', 'open_requests', 'closed_today' );
	}

	private function normalize_snapshot( $snapshot ) {
		$snapshot = is_array( $snapshot ) ? $snapshot : array();
		$normalized = $this->empty_stats( isset( $snapshot['user_id'] ) ? absint( $snapshot['user_id'] ) : 0, isset( $snapshot['date'] ) ? $snapshot['date'] : null );
		foreach ( $this->stat_keys() as $key ) {
			$normalized[ $key ] = isset( $snapshot[ $key ] ) ? absint( $snapshot[ $key ] ) : 0;
		}
		$normalized['generated_at'] = isset( $snapshot['generated_at'] ) ? sanitize_text_field( $snapshot['generated_at'] ) : '';
		return $normalized;
	}

	private function count_requests_by_statuses( $user_id, $statuses ) {
		global $wpdb;
		$statuses = array_values( array_map( 'sanitize_key', (array) $statuses ) );
		if ( empty( $statuses ) ) {
			return 0;
		}
		$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
		$sql = "SELECT COUNT(*) FROM {$this->requests_table} WHERE owner_id = %d AND status IN ($placeholders)";
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, array_merge( array( absint( $user_id ) ), $statuses ) ) );
	}

	private function count_activities_by_types( $user_id, $activity_types, $start, $end ) {
		global $wpdb;
		$activity_types = array_values( array_map( 'sanitize_key', (array) $activity_types ) );
		if ( empty( $activity_types ) ) {
			return 0;
		}
		$placeholders = implode( ',', array_fill( 0, count( $activity_types ), '%s' ) );
		$sql = "SELECT COUNT(*) FROM {$this->activities_table} a INNER JOIN {$this->requests_table} r ON r.id = a.request_id WHERE a.actor_user_id = %d AND a.activity_type IN ($placeholders) AND a.created_at BETWEEN %s AND %s";
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, array_merge( array( absint( $user_id ) ), $activity_types, array( $start, $end ) ) ) );
	}
}

<?php
/**
 * Staff portal repository.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_Staff_Repository {
	private $tables;

	public function __construct() {
		$this->tables = $this->tables();
	}

	public function tables() {
		return array(
			'daily_reports'      => CRPCRM_DB::table( 'staff_daily_reports' ),
			'staff_requests'     => CRPCRM_DB::table( 'staff_requests' ),
			'staff_issues'       => CRPCRM_DB::table( 'staff_issues' ),
			'staff_tasks'        => CRPCRM_DB::table( 'staff_tasks' ),
			'announcements'      => CRPCRM_DB::table( 'manager_announcements' ),
			'announcement_reads' => CRPCRM_DB::table( 'announcement_reads' ),
		);
	}

	public function get_today_report( $user_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->tables['daily_reports']} WHERE user_id = %d AND report_date = %s", absint( $user_id ), $this->today() ), ARRAY_A );
	}

	public function get_daily_report( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->tables['daily_reports']} WHERE id = %d", absint( $id ) ), ARRAY_A );
	}

	public function create_daily_report( $data ) {
		global $wpdb;
		$now  = CRPCRM_Helpers::current_datetime();
		$data = wp_parse_args( $data, array( 'status' => 'submitted', 'report_date' => $this->today(), 'created_at' => $now, 'updated_at' => $now ) );
		$wpdb->insert( $this->tables['daily_reports'], $data );
		return $wpdb->insert_id;
	}

	public function update_daily_report( $id, $data ) {
		global $wpdb;
		$data['updated_at'] = CRPCRM_Helpers::current_datetime();
		return false !== $wpdb->update( $this->tables['daily_reports'], $data, array( 'id' => absint( $id ) ) );
	}

	public function list_daily_reports( $args = array() ) {
		return $this->list_rows( 'daily_reports', $args, 'report_date DESC, created_at DESC' );
	}

	public function save_daily_report_snapshot( $report_id, $snapshot ) {
		$snapshot_json = is_string( $snapshot ) ? $snapshot : wp_json_encode( $snapshot );
		return $this->update_daily_report( absint( $report_id ), array( 'sales_crm_snapshot' => $snapshot_json ) );
	}

	public function get_daily_report_with_snapshot( $report_id ) {
		return $this->get_daily_report( $report_id );
	}

	public function list_daily_reports_with_snapshot( $args = array() ) {
		return $this->list_daily_reports( $args );
	}

	public function count_daily_reports( $args = array() ) {
		return $this->count_rows( 'daily_reports', $args );
	}

	public function mark_report_seen( $id, $manager_id ) {
		return $this->update_daily_report( $id, array( 'status' => 'seen', 'seen_at' => CRPCRM_Helpers::current_datetime() ) );
	}

	public function respond_to_report( $id, $manager_id, $response ) {
		return $this->update_daily_report( $id, array( 'status' => 'responded', 'manager_response' => sanitize_textarea_field( $response ), 'responded_at' => CRPCRM_Helpers::current_datetime(), 'seen_at' => CRPCRM_Helpers::current_datetime() ) );
	}

	public function close_report( $id, $manager_id ) {
		return $this->update_daily_report( $id, array( 'status' => 'closed' ) );
	}

	public function create_staff_request( $data ) {
		global $wpdb;
		$now = CRPCRM_Helpers::current_datetime();
		$data = wp_parse_args( $data, array( 'status' => 'new', 'created_at' => $now, 'updated_at' => $now ) );
		$wpdb->insert( $this->tables['staff_requests'], $data );
		return $wpdb->insert_id;
	}

	public function update_staff_request( $id, $data ) {
		global $wpdb;
		$data['updated_at'] = CRPCRM_Helpers::current_datetime();
		return false !== $wpdb->update( $this->tables['staff_requests'], $data, array( 'id' => absint( $id ) ) );
	}

	public function list_staff_requests( $args = array() ) {
		return $this->list_rows( 'staff_requests', $args, 'created_at DESC' );
	}

	public function count_staff_requests( $args = array() ) {
		return $this->count_rows( 'staff_requests', $args );
	}

	public function get_staff_request( $id ) {
		return $this->get_row( 'staff_requests', $id );
	}

	public function update_staff_request_status( $id, $status, $manager_response ) {
		return $this->update_staff_request( $id, array( 'status' => sanitize_key( $status ), 'manager_response' => sanitize_textarea_field( $manager_response ) ) );
	}

	public function create_issue( $data ) {
		global $wpdb;
		$now = CRPCRM_Helpers::current_datetime();
		$data = wp_parse_args( $data, array( 'status' => 'new', 'created_at' => $now, 'updated_at' => $now ) );
		$wpdb->insert( $this->tables['staff_issues'], $data );
		return $wpdb->insert_id;
	}

	public function update_issue( $id, $data ) {
		global $wpdb;
		$data['updated_at'] = CRPCRM_Helpers::current_datetime();
		return false !== $wpdb->update( $this->tables['staff_issues'], $data, array( 'id' => absint( $id ) ) );
	}

	public function list_issues( $args = array() ) {
		return $this->list_rows( 'staff_issues', $args, 'created_at DESC' );
	}

	public function count_issues( $args = array() ) {
		return $this->count_rows( 'staff_issues', $args );
	}

	public function get_issue( $id ) {
		return $this->get_row( 'staff_issues', $id );
	}

	public function update_issue_status( $id, $status, $manager_response ) {
		return $this->update_issue( $id, array( 'status' => sanitize_key( $status ), 'manager_response' => sanitize_textarea_field( $manager_response ) ) );
	}

	public function create_task( $data ) {
		global $wpdb;
		$now = CRPCRM_Helpers::current_datetime();
		$data = wp_parse_args( $data, array( 'status' => 'new', 'created_at' => $now, 'updated_at' => $now ) );
		$wpdb->insert( $this->tables['staff_tasks'], $data );
		return $wpdb->insert_id;
	}

	public function update_task( $id, $data ) {
		global $wpdb;
		$data['updated_at'] = CRPCRM_Helpers::current_datetime();
		return false !== $wpdb->update( $this->tables['staff_tasks'], $data, array( 'id' => absint( $id ) ) );
	}

	public function list_tasks( $args = array() ) {
		return $this->list_rows( 'staff_tasks', $args, 'due_date ASC, created_at DESC' );
	}

	public function count_tasks( $args = array() ) {
		return $this->count_rows( 'staff_tasks', $args );
	}

	public function get_task( $id ) {
		return $this->get_row( 'staff_tasks', $id );
	}

	public function update_task_status( $id, $status, $user_id, $note ) {
		$data = array( 'status' => sanitize_key( $status ) );
		if ( 'done' === $status ) {
			$data['completed_at'] = CRPCRM_Helpers::current_datetime();
		} elseif ( 'done' !== $status ) {
			$data['completed_at'] = null;
		}
		if ( current_user_can( 'crpcrm_manage_staff_portal' ) ) {
			$data['manager_note'] = sanitize_textarea_field( $note );
		} else {
			$data['employee_update'] = sanitize_textarea_field( $note );
		}
		return $this->update_task( $id, $data );
	}

	public function count_overdue_tasks( $user_id = null ) {
		$args = array( 'overdue' => 1 );
		if ( $user_id ) {
			$args['assigned_to'] = absint( $user_id );
		}
		return $this->count_rows( 'staff_tasks', $args );
	}

	public function create_announcement( $data ) {
		global $wpdb;
		$now = CRPCRM_Helpers::current_datetime();
		$data = wp_parse_args( $data, array( 'created_at' => $now, 'updated_at' => $now ) );
		$wpdb->insert( $this->tables['announcements'], $data );
		return $wpdb->insert_id;
	}

	public function list_announcements( $args = array() ) {
		return $this->list_rows( 'announcements', $args, 'created_at DESC' );
	}

	public function count_announcements( $args = array() ) {
		return $this->count_rows( 'announcements', $args );
	}

	public function get_announcement( $id ) {
		return $this->get_row( 'announcements', $id );
	}

	public function update_announcement( $id, $data ) {
		global $wpdb;
		$data['updated_at'] = CRPCRM_Helpers::current_datetime();
		return false !== $wpdb->update( $this->tables['announcements'], $data, array( 'id' => absint( $id ) ) );
	}

	public function delete_announcement( $id ) {
		global $wpdb;
		$announcement_id = absint( $id );
		$wpdb->delete( $this->tables['announcement_reads'], array( 'announcement_id' => $announcement_id ), array( '%d' ) );
		return false !== $wpdb->delete( $this->tables['announcements'], array( 'id' => $announcement_id ), array( '%d' ) );
	}

	public function mark_announcement_read( $announcement_id, $user_id ) {
		global $wpdb;
		return false !== $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$this->tables['announcement_reads']} (announcement_id,user_id,read_at) VALUES (%d,%d,%s)", absint( $announcement_id ), absint( $user_id ), CRPCRM_Helpers::current_datetime() ) );
	}

	public function get_announcement_read_stats( $announcement_id ) {
		global $wpdb;
		$announcement = $this->get_announcement( $announcement_id );
		$audience     = $announcement ? $this->get_audience_user_ids( $announcement ) : array();
		$seen_ids     = $wpdb->get_col( $wpdb->prepare( "SELECT user_id FROM {$this->tables['announcement_reads']} WHERE announcement_id = %d", absint( $announcement_id ) ) );
		$seen_ids     = array_map( 'absint', $seen_ids );
		return array(
			'seen'      => count( array_intersect( $audience, $seen_ids ) ),
			'unseen'    => count( array_diff( $audience, $seen_ids ) ),
			'seen_ids'  => array_values( array_intersect( $audience, $seen_ids ) ),
			'unseen_ids'=> array_values( array_diff( $audience, $seen_ids ) ),
		);
	}

	public function get_announcements_for_user( $user_id ) {
		$announcements = $this->list_announcements( array( 'limit' => 200 ) );
		$result        = array();
		foreach ( $announcements as $announcement ) {
			if ( $this->announcement_matches_user( $announcement, $user_id ) ) {
				$result[] = $announcement;
			}
		}
		return $result;
	}

	public function user_has_read_announcement( $announcement_id, $user_id ) {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$this->tables['announcement_reads']} WHERE announcement_id = %d AND user_id = %d", absint( $announcement_id ), absint( $user_id ) ) );
	}

	public function count_unread_announcements_for_user( $user_id ) {
		$count = 0;
		foreach ( $this->get_announcements_for_user( $user_id ) as $announcement ) {
			if ( ! $this->user_has_read_announcement( $announcement['id'], $user_id ) ) {
				$count++;
			}
		}
		return $count;
	}

	public function get_staff_users() {
		return get_users(
			array(
				'role__in' => array( 'sales_agent', 'sales_manager', 'internal_employee', 'crm_admin', 'administrator' ),
				'orderby'  => 'display_name',
				'order'    => 'ASC',
				'fields'   => 'all',
			)
		);
	}

	public function today() {
		return current_time( 'Y-m-d' );
	}

	public function get_audience_user_ids( $announcement ) {
		$users = $this->get_staff_users();
		$data  = CRPCRM_Helpers::maybe_json_decode( isset( $announcement['audience_data'] ) ? $announcement['audience_data'] : '', true );
		$ids   = array();
		foreach ( $users as $user ) {
			if ( 'all' === $announcement['audience_type'] ) {
				$ids[] = absint( $user->ID );
			} elseif ( 'selected_roles' === $announcement['audience_type'] && is_array( $data ) && array_intersect( (array) $user->roles, array_map( 'sanitize_key', $data ) ) ) {
				$ids[] = absint( $user->ID );
			} elseif ( 'selected_users' === $announcement['audience_type'] && is_array( $data ) && in_array( absint( $user->ID ), array_map( 'absint', $data ), true ) ) {
				$ids[] = absint( $user->ID );
			}
		}
		return array_values( array_unique( $ids ) );
	}

	private function announcement_matches_user( $announcement, $user_id ) {
		return in_array( absint( $user_id ), $this->get_audience_user_ids( $announcement ), true );
	}

	private function get_row( $table_key, $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->tables[$table_key]} WHERE id = %d", absint( $id ) ), ARRAY_A );
	}

	private function list_rows( $table_key, $args, $order_by ) {
		global $wpdb;
		$where = $this->build_where( $table_key, $args );
		$limit = isset( $args['limit'] ) ? max( 1, absint( $args['limit'] ) ) : 50;
		$offset = isset( $args['offset'] ) ? max( 0, absint( $args['offset'] ) ) : 0;
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->tables[$table_key]} {$where['sql']} ORDER BY {$order_by} LIMIT %d OFFSET %d", array_merge( $where['values'], array( $limit, $offset ) ) ), ARRAY_A );
	}

	private function count_rows( $table_key, $args ) {
		global $wpdb;
		$where = $this->build_where( $table_key, $args );
		if ( $where['values'] ) {
			return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->tables[$table_key]} {$where['sql']}", $where['values'] ) );
		}
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->tables[$table_key]}" );
	}

	private function build_where( $table_key, $args ) {
		$where = array();
		$vals  = array();
		$map   = array(
			'user_id' => '%d', 'assigned_to' => '%d', 'created_by' => '%d', 'status' => '%s', 'category' => '%s', 'priority' => '%s', 'severity' => '%s', 'audience_type' => '%s', 'report_date' => '%s', 'needs_manager_attention' => '%d', 'needs_manager_decision' => '%d',
		);
		foreach ( $map as $key => $format ) {
			if ( isset( $args[ $key ] ) && '' !== $args[ $key ] && null !== $args[ $key ] ) {
				$where[] = "$key = $format";
				$vals[]  = '%d' === $format ? absint( $args[ $key ] ) : sanitize_text_field( $args[ $key ] );
			}
		}
		if ( ! empty( $args['open'] ) ) {
			if ( 'staff_tasks' === $table_key ) {
				$where[] = "status NOT IN ('done','cancelled')";
			} else {
				$where[] = "status NOT IN ('done','rejected','resolved','closed')";
			}
		}
		if ( ! empty( $args['overdue'] ) ) {
			$where[] = 'due_date < %s';
			$vals[]  = $this->today();
			$where[] = "status NOT IN ('done','cancelled')";
		}
		return array( 'sql' => $where ? 'WHERE ' . implode( ' AND ', $where ) : '', 'values' => $vals );
	}
}

<?php
/**
 * Customer repository.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_Customer_Repository {
	private $table;

	public function __construct() {
		$this->table = CRPCRM_DB::table( 'customers' );
	}

	public function find_by_user_id( $user_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE user_id = %d LIMIT 1", absint( $user_id ) ), ARRAY_A );
	}

	public function find_by_phone_normalized( $phone_normalized ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE phone_normalized = %s LIMIT 1", sanitize_text_field( $phone_normalized ) ), ARRAY_A );
	}

	public function ensure_for_user( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return null;
		}

		$existing = $this->find_by_user_id( $user_id );
		if ( $existing ) {
			return $existing;
		}

		$phone_normalized = get_user_meta( $user_id, 'crpcrm_phone_normalized', true );
		$phone_normalized = CRPCRM_Helpers::normalize_iran_phone( $phone_normalized );
		if ( ! CRPCRM_Helpers::is_valid_iran_phone_normalized( $phone_normalized ) ) {
			return null;
		}

		$by_phone = $this->find_by_phone_normalized( $phone_normalized );
		if ( $by_phone ) {
			if ( absint( $by_phone['user_id'] ) === $user_id ) {
				return $by_phone;
			}

			return new WP_Error( 'customer_phone_conflict', 'اطلاعات حساب شما کامل نیست. لطفاً دوباره وارد شوید.' );
		}

		$customer_id = $this->create(
			array(
				'user_id'           => $user_id,
				'phone'             => $phone_normalized,
				'phone_normalized'  => $phone_normalized,
				'profile_completed' => 0,
			)
		);

		return $customer_id ? $this->get( $customer_id ) : null;
	}

	public function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d LIMIT 1", absint( $id ) ), ARRAY_A );
	}

	public function create( $data ) {
		global $wpdb;

		$now  = CRPCRM_Helpers::current_datetime();
		$data = $this->sanitize_data( $data );
		$data = wp_parse_args(
			$data,
			array(
				'profile_completed' => 0,
				'created_at'        => $now,
				'updated_at'        => $now,
			)
		);

		$result = $wpdb->insert( $this->table, $data );
		return false === $result ? false : (int) $wpdb->insert_id;
	}

	public function update( $id, $data ) {
		global $wpdb;

		$data               = $this->sanitize_data( $data );
		$data['updated_at'] = CRPCRM_Helpers::current_datetime();

		return false !== $wpdb->update( $this->table, $data, array( 'id' => absint( $id ) ) );
	}


	public function get_with_user( $customer_id ) {
		global $wpdb;
		$users = $wpdb->users;
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT c.*, u.user_registered, u.display_name AS user_display_name, u.user_email
				FROM {$this->table} c
				LEFT JOIN {$users} u ON u.ID = c.user_id
				WHERE c.id = %d LIMIT 1",
				absint( $customer_id )
			),
			ARRAY_A
		);
	}

	public function list_for_admin( $args = array() ) {
		global $wpdb;
		$args = wp_parse_args(
			$args,
			array(
				'limit'        => 20,
				'offset'       => 0,
				'user_id'      => get_current_user_id(),
				'first_source' => '',
				'last_source'  => '',
				'province'     => '',
				'city'         => '',
				'open_filter'  => '',
				'search'       => '',
			)
		);

		$where  = $this->build_admin_where( $args );
		$limit  = max( 1, min( 100, absint( $args['limit'] ) ) );
		$offset = absint( $args['offset'] );
		$requests = CRPCRM_DB::table( 'requests' );

		$sql = "SELECT c.*,
			COUNT(r.id) AS requests_count,
			SUM(CASE WHEN r.status IN ('new','in_progress','no_answer','follow_up') THEN 1 ELSE 0 END) AS open_requests_count,
			MAX(r.created_at) AS last_request_at,
			(SELECT rr.request_code FROM {$requests} rr WHERE rr.customer_id = c.id ORDER BY rr.created_at DESC, rr.id DESC LIMIT 1) AS last_request_code,
			(SELECT rr.id FROM {$requests} rr WHERE rr.customer_id = c.id ORDER BY rr.created_at DESC, rr.id DESC LIMIT 1) AS last_request_id
			FROM {$this->table} c
			LEFT JOIN {$requests} r ON r.customer_id = c.id
			{$where['sql']}
			GROUP BY c.id
			ORDER BY c.created_at DESC, c.id DESC
			LIMIT %d OFFSET %d";
		$where['values'][] = $limit;
		$where['values'][] = $offset;

		return $wpdb->get_results( $wpdb->prepare( $sql, $where['values'] ), ARRAY_A );
	}

	public function count_for_admin( $args = array() ) {
		global $wpdb;
		$args  = wp_parse_args( $args, array( 'user_id' => get_current_user_id(), 'first_source' => '', 'last_source' => '', 'province' => '', 'city' => '', 'open_filter' => '', 'search' => '' ) );
		$where = $this->build_admin_where( $args );
		$sql   = "SELECT COUNT(DISTINCT c.id) FROM {$this->table} c LEFT JOIN " . CRPCRM_DB::table( 'requests' ) . " r ON r.customer_id = c.id {$where['sql']}";
		return (int) $wpdb->get_var( empty( $where['values'] ) ? $sql : $wpdb->prepare( $sql, $where['values'] ) );
	}

	public function get_customer_stats( $customer_id ) {
		global $wpdb;
		$requests   = CRPCRM_DB::table( 'requests' );
		$activities = CRPCRM_DB::table( 'request_activities' );
		$customer_id = absint( $customer_id );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
				COUNT(*) AS total_requests,
				SUM(CASE WHEN status IN ('new','in_progress','no_answer','follow_up') THEN 1 ELSE 0 END) AS open_requests,
				SUM(CASE WHEN status IN ('won','lost','invalid') THEN 1 ELSE 0 END) AS closed_requests,
				SUM(CASE WHEN request_type = 'car_registration' THEN 1 ELSE 0 END) AS car_registration,
				SUM(CASE WHEN request_type = 'parts_request' THEN 1 ELSE 0 END) AS parts_request,
				SUM(CASE WHEN request_type = 'repair_booking' THEN 1 ELSE 0 END) AS repair_booking,
				SUM(CASE WHEN status = 'won' THEN 1 ELSE 0 END) AS won,
				SUM(CASE WHEN status = 'lost' THEN 1 ELSE 0 END) AS lost,
				SUM(CASE WHEN status = 'invalid' THEN 1 ELSE 0 END) AS invalid,
				SUM(CASE WHEN status = 'follow_up' THEN 1 ELSE 0 END) AS open_followups,
				MAX(created_at) AS last_request_at,
				MAX(last_activity_at) AS last_activity_at
				FROM {$requests} WHERE customer_id = %d",
				$customer_id
			),
			ARRAY_A
		);
		$activity_last = $wpdb->get_var( $wpdb->prepare( "SELECT MAX(created_at) FROM {$activities} WHERE customer_id = %d", $customer_id ) );
		if ( $activity_last && ( empty( $row['last_activity_at'] ) || strtotime( $activity_last ) > strtotime( $row['last_activity_at'] ) ) ) {
			$row['last_activity_at'] = $activity_last;
		}
		return array_map( function( $value ) { return null === $value ? 0 : $value; }, $row ? $row : array() );
	}

	public function get_customer_requests( $customer_id, $args = array() ) {
		global $wpdb;
		$args = wp_parse_args( $args, array( 'limit' => 100, 'offset' => 0, 'user_id' => get_current_user_id() ) );
		$limit = max( 1, min( 100, absint( $args['limit'] ) ) );
		$offset = absint( $args['offset'] );
		$user_id = absint( $args['user_id'] );
		$requests = CRPCRM_DB::table( 'requests' );
		$where = array( 'r.customer_id = %d' );
		$values = array( absint( $customer_id ) );
		if ( ! CRPCRM_Request_Access_Service::can_view_all( $user_id ) ) {
			$where[] = '(r.owner_id = %d OR r.owner_id IS NULL)';
			$values[] = $user_id;
		}
		$values[] = $limit;
		$values[] = $offset;
		$sql = "SELECT r.* FROM {$requests} r WHERE " . implode( ' AND ', $where ) . ' ORDER BY r.created_at DESC, r.id DESC LIMIT %d OFFSET %d';
		return $wpdb->get_results( $wpdb->prepare( $sql, $values ), ARRAY_A );
	}

	public function get_customer_agents( $customer_id ) {
		global $wpdb;
		$requests   = CRPCRM_DB::table( 'requests' );
		$activities = CRPCRM_DB::table( 'request_activities' );
		$customer_id = absint( $customer_id );
		$owner_rows = $wpdb->get_results( $wpdb->prepare( "SELECT owner_id AS user_id, COUNT(*) AS owner_requests FROM {$requests} WHERE customer_id = %d AND owner_id IS NOT NULL GROUP BY owner_id", $customer_id ), ARRAY_A );
		$activity_rows = $wpdb->get_results( $wpdb->prepare( "SELECT actor_user_id AS user_id, COUNT(*) AS activity_count, MAX(created_at) AS last_activity_at FROM {$activities} WHERE customer_id = %d AND actor_user_id IS NOT NULL AND actor_type IN ('sales_agent','sales_manager','admin') GROUP BY actor_user_id", $customer_id ), ARRAY_A );
		$agents = array();
		foreach ( $owner_rows as $row ) {
			$user_id = absint( $row['user_id'] );
			$agents[ $user_id ] = array( 'user_id' => $user_id, 'owner_requests' => absint( $row['owner_requests'] ), 'activity_count' => 0, 'last_activity_at' => '' );
		}
		foreach ( $activity_rows as $row ) {
			$user_id = absint( $row['user_id'] );
			if ( ! isset( $agents[ $user_id ] ) ) {
				$agents[ $user_id ] = array( 'user_id' => $user_id, 'owner_requests' => 0, 'activity_count' => 0, 'last_activity_at' => '' );
			}
			$agents[ $user_id ]['activity_count'] = absint( $row['activity_count'] );
			$agents[ $user_id ]['last_activity_at'] = $row['last_activity_at'];
		}
		return array_values( $agents );
	}

	public function get_customer_recent_activities( $customer_id, $limit = 20 ) {
		global $wpdb;
		$activities = CRPCRM_DB::table( 'request_activities' );
		$requests = CRPCRM_DB::table( 'requests' );
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.*, r.request_code FROM {$activities} a LEFT JOIN {$requests} r ON r.id = a.request_id WHERE a.customer_id = %d AND a.is_internal = 1 ORDER BY a.created_at DESC, a.id DESC LIMIT %d",
				absint( $customer_id ),
				max( 1, min( 100, absint( $limit ) ) )
			),
			ARRAY_A
		);
	}

	public function user_can_view_customer( $customer_id, $user_id ) {
		global $wpdb;
		$customer_id = absint( $customer_id );
		$user_id     = absint( $user_id );
		if ( ! $customer_id || ! CRPCRM_Request_Access_Service::can_access_admin_requests( $user_id ) ) {
			return false;
		}
		if ( CRPCRM_Request_Access_Service::can_view_all( $user_id ) ) {
			return true;
		}
		$requests = CRPCRM_DB::table( 'requests' );
		$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$requests} WHERE customer_id = %d AND (owner_id = %d OR owner_id IS NULL)", $customer_id, $user_id ) );
		return $count > 0;
	}

	private function build_admin_where( $args ) {
		global $wpdb;
		$user_id = isset( $args['user_id'] ) ? absint( $args['user_id'] ) : get_current_user_id();
		$where = array( '1=1' );
		$values = array();

		if ( ! CRPCRM_Request_Access_Service::can_view_all( $user_id ) ) {
			$where[] = '(r.owner_id = %d OR r.owner_id IS NULL)';
			$values[] = $user_id;
		}
		if ( ! empty( $args['first_source'] ) ) {
			$where[] = 'c.first_source = %s';
			$values[] = sanitize_key( $args['first_source'] );
		}
		if ( ! empty( $args['last_source'] ) ) {
			$where[] = 'c.last_source = %s';
			$values[] = sanitize_key( $args['last_source'] );
		}
		if ( ! empty( $args['province'] ) ) {
			$where[] = 'c.province LIKE %s';
			$values[] = '%' . $wpdb->esc_like( sanitize_text_field( $args['province'] ) ) . '%';
		}
		if ( ! empty( $args['city'] ) ) {
			$where[] = 'c.city LIKE %s';
			$values[] = '%' . $wpdb->esc_like( sanitize_text_field( $args['city'] ) ) . '%';
		}
		if ( ! empty( $args['open_filter'] ) ) {
			if ( 'has_open' === $args['open_filter'] ) {
				$where[] = "EXISTS (SELECT 1 FROM " . CRPCRM_DB::table( 'requests' ) . " ro WHERE ro.customer_id = c.id AND ro.status IN ('new','in_progress','no_answer','follow_up'))";
			} elseif ( 'no_open' === $args['open_filter'] ) {
				$where[] = "NOT EXISTS (SELECT 1 FROM " . CRPCRM_DB::table( 'requests' ) . " ro WHERE ro.customer_id = c.id AND ro.status IN ('new','in_progress','no_answer','follow_up'))";
			}
		}
		if ( ! empty( $args['search'] ) ) {
			$like = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
			$where[] = '(c.full_name LIKE %s OR c.phone LIKE %s OR c.phone_normalized LIKE %s)';
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
		}

		return array( 'sql' => 'WHERE ' . implode( ' AND ', $where ), 'values' => $values );
	}

	private function sanitize_data( $data ) {
		$clean = array();
		$text_fields = array( 'full_name', 'phone', 'phone_normalized', 'province', 'city', 'first_source', 'first_medium', 'first_campaign', 'first_content', 'first_term', 'last_source', 'last_medium', 'last_campaign', 'last_content', 'last_term' );
		$textarea_fields = array( 'first_landing_page', 'first_referrer', 'last_landing_page', 'last_referrer' );
		$date_fields = array( 'first_seen_at', 'last_seen_at', 'created_at', 'updated_at' );

		if ( isset( $data['user_id'] ) ) {
			$clean['user_id'] = absint( $data['user_id'] );
		}
		if ( isset( $data['profile_completed'] ) ) {
			$clean['profile_completed'] = absint( $data['profile_completed'] );
		}

		foreach ( $text_fields as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$clean[ $field ] = sanitize_text_field( $data[ $field ] );
			}
		}
		foreach ( $textarea_fields as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$clean[ $field ] = sanitize_textarea_field( $data[ $field ] );
			}
		}
		foreach ( $date_fields as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$clean[ $field ] = sanitize_text_field( $data[ $field ] );
			}
		}

		return $clean;
	}
}

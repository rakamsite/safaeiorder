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
				'created_source'    => 'site_customer',
				'profile_completed' => 0,
			)
		);

		return $customer_id ? $this->get( $customer_id ) : null;
	}

	public function find_customers_without_requests( $registered_before, $limit = 100 ) {
		global $wpdb;
		$requests = CRPCRM_DB::table( 'requests' );
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.* FROM {$this->table} c LEFT JOIN {$requests} r ON r.customer_id = c.id WHERE c.created_at <= %s AND r.id IS NULL ORDER BY c.created_at ASC LIMIT %d",
				sanitize_text_field( $registered_before ),
				max( 1, min( 500, absint( $limit ) ) )
			),
			ARRAY_A
		);
	}

	public function search_for_manual_request( $search, $limit = 20 ) {
		global $wpdb;
		$search = trim( sanitize_text_field( $search ) );
		if ( '' === $search ) {
			return array();
		}
		$like  = '%' . $wpdb->esc_like( $search ) . '%';
		$users = $wpdb->users;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.*, u.display_name, u.user_email FROM {$this->table} c LEFT JOIN {$users} u ON u.ID = c.user_id WHERE c.full_name LIKE %s OR c.phone LIKE %s OR c.phone_normalized LIKE %s OR u.display_name LIKE %s ORDER BY c.updated_at DESC LIMIT %d",
				$like, $like, $like, $like, max( 1, min( 50, absint( $limit ) ) )
			),
			ARRAY_A
		);
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

	/**
	 * Permanently delete a customer and all CRM records owned by that customer.
	 *
	 * The linked WordPress user is intentionally left untouched. This allows the
	 * method to be used both after WordPress deletes a user and from the manual
	 * CRM cleanup action.
	 *
	 * @param int $customer_id Customer ID.
	 * @return true|WP_Error
	 */
	public function delete_permanently( $customer_id ) {
		global $wpdb;

		$customer_id = absint( $customer_id );
		$customer    = $this->get( $customer_id );
		if ( ! $customer ) {
			return new WP_Error( 'customer_not_found', 'مشتری موردنظر یافت نشد.' );
		}

		$requests     = CRPCRM_DB::table( 'requests' );
		$activities   = CRPCRM_DB::table( 'request_activities' );
		$attributions = CRPCRM_DB::table( 'customer_attribution_events' );
		$user_id      = absint( $customer['user_id'] );
		$request_rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, request_data FROM {$requests} WHERE customer_id = %d", $customer_id ), ARRAY_A );

		$wpdb->query( 'START TRANSACTION' );

		$activities_deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$activities} WHERE customer_id = %d OR request_id IN (SELECT id FROM {$requests} WHERE customer_id = %d)",
				$customer_id,
				$customer_id
			)
		);
		$requests_deleted = $wpdb->delete( $requests, array( 'customer_id' => $customer_id ), array( '%d' ) );

		if ( $user_id ) {
			$attributions_deleted = $wpdb->query(
				$wpdb->prepare( "DELETE FROM {$attributions} WHERE customer_id = %d OR user_id = %d", $customer_id, $user_id )
			);
		} else {
			$attributions_deleted = $wpdb->delete( $attributions, array( 'customer_id' => $customer_id ), array( '%d' ) );
		}

		$customer_deleted = $wpdb->delete( $this->table, array( 'id' => $customer_id ), array( '%d' ) );
		if ( false === $activities_deleted || false === $requests_deleted || false === $attributions_deleted || 1 !== $customer_deleted ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'customer_delete_failed', 'حذف کامل مشتری انجام نشد. لطفاً دوباره تلاش کنید.' );
		}

		$wpdb->query( 'COMMIT' );

		$cleanup = new CRPCRM_Request_File_Cleanup_Service();
		$files   = $cleanup->cleanup_request_rows( is_array( $request_rows ) ? $request_rows : array() );
		if ( ! empty( $files['failed'] ) ) {
			CRPCRM_Logger::warning(
				'customer_file_cleanup_partial',
				'customer',
				array(
					'customer_id' => $customer_id,
					'deleted'     => absint( $files['deleted'] ?? 0 ),
					'missing'     => absint( $files['missing'] ?? 0 ),
					'failed'      => absint( $files['failed'] ?? 0 ),
				)
			);
		}

		return true;
	}

	public function update( $id, $data ) {
		global $wpdb;

		$data               = $this->sanitize_data( $data );
		$data['updated_at'] = CRPCRM_Helpers::current_datetime();

		return false !== $wpdb->update( $this->table, $data, array( 'id' => absint( $id ) ) );
	}

	public function save_landing_attribution( $customer_id, array $landing_attribution ) {
		$customer_id = absint( $customer_id );
		if ( ! $customer_id || ! $this->get( $customer_id ) ) {
			return false;
		}

		$normalized = self::normalize_landing_attribution( $landing_attribution );
		return $this->update(
			$customer_id,
			array(
				'visitor_id'          => $normalized['visitor_id'],
				'landing_attribution' => $normalized,
			)
		);
	}

	public static function get_landing_attribution( $customer ) {
		$customer = is_array( $customer ) ? $customer : array();
		$data     = CRPCRM_Helpers::maybe_json_decode( $customer['landing_attribution'] ?? '', true );

		if ( ! is_array( $data ) ) {
			$data = array();
		}

		if ( empty( $data['visitor_id'] ) && ! empty( $customer['visitor_id'] ) ) {
			$data['visitor_id'] = sanitize_text_field( $customer['visitor_id'] );
		}

		return self::normalize_landing_attribution( $data );
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
		$where['values'] = array_merge( $where['values'], array( $limit, $offset ) );

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
		$activity_last = $wpdb->get_var( $wpdb->prepare( "SELECT MAX(a.created_at) FROM {$activities} a INNER JOIN {$requests} r ON r.id = a.request_id WHERE a.customer_id = %d", $customer_id ) );
		if ( $activity_last && ( empty( $row['last_activity_at'] ) || CRPCRM_Helpers::datetime_to_timestamp( $activity_last ) > CRPCRM_Helpers::datetime_to_timestamp( $row['last_activity_at'] ) ) ) {
			$row['last_activity_at'] = $activity_last;
		}
		$row                        = array_map( function( $value ) { return null === $value ? 0 : $value; }, $row ? $row : array() );
		$row['request_type_counts'] = array();
		$type_rows                  = $wpdb->get_results( $wpdb->prepare( "SELECT request_type, COUNT(*) AS total FROM {$requests} WHERE customer_id = %d GROUP BY request_type ORDER BY total DESC", $customer_id ), ARRAY_A );
		foreach ( $type_rows as $type_row ) {
			$request_type = sanitize_key( $type_row['request_type'] );
			if ( $request_type ) {
				$row['request_type_counts'][ $request_type ] = absint( $type_row['total'] );
			}
		}
		return $row;
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
			$where[] = 'r.owner_id = %d';
			$values[] = $user_id;
		}
		$values[] = $limit;
		$values[] = $offset;
		$sql = "SELECT r.* FROM {$requests} r WHERE " . implode( ' AND ', $where ) . ' ORDER BY r.created_at DESC, r.id DESC LIMIT %d OFFSET %d';
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $values ), ARRAY_A );
		foreach ( $rows as &$row ) {
			$row['request_data'] = CRPCRM_Helpers::maybe_json_encode( CRPCRM_Request_Repository::get_merged_request_data( $row ) );
		}
		unset( $row );
		return $rows;
	}

	public function get_customer_agents( $customer_id ) {
		global $wpdb;
		$requests   = CRPCRM_DB::table( 'requests' );
		$activities = CRPCRM_DB::table( 'request_activities' );
		$customer_id = absint( $customer_id );
		$owner_rows = $wpdb->get_results( $wpdb->prepare( "SELECT owner_id AS user_id, COUNT(*) AS owner_requests FROM {$requests} WHERE customer_id = %d AND owner_id IS NOT NULL GROUP BY owner_id", $customer_id ), ARRAY_A );
		$activity_rows = $wpdb->get_results( $wpdb->prepare( "SELECT a.actor_user_id AS user_id, COUNT(*) AS activity_count, MAX(a.created_at) AS last_activity_at FROM {$activities} a INNER JOIN {$requests} r ON r.id = a.request_id WHERE a.customer_id = %d AND a.actor_user_id IS NOT NULL AND a.actor_type IN ('sales_agent','sales_manager','admin') GROUP BY a.actor_user_id", $customer_id ), ARRAY_A );
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
				"SELECT a.*, r.request_code FROM {$activities} a INNER JOIN {$requests} r ON r.id = a.request_id WHERE a.customer_id = %d AND a.is_internal = 1 ORDER BY a.created_at DESC, a.id DESC LIMIT %d",
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
		$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$requests} WHERE customer_id = %d AND owner_id = %d", $customer_id, $user_id ) );
		return $count > 0;
	}

	private function build_admin_where( $args ) {
		global $wpdb;
		$user_id = isset( $args['user_id'] ) ? absint( $args['user_id'] ) : get_current_user_id();
		$where = array( '1=1' );
		$values = array();
		if ( ! CRPCRM_Request_Access_Service::can_view_all( $user_id ) ) {
			$where[] = 'r.owner_id = %d';
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
		$date_fields = array( 'first_seen_at', 'last_seen_at', 'created_by_staff_at', 'created_at', 'updated_at' );

		if ( isset( $data['user_id'] ) ) {
			$clean['user_id'] = absint( $data['user_id'] );
		}
		if ( isset( $data['profile_completed'] ) ) {
			$clean['profile_completed'] = absint( $data['profile_completed'] );
		}
		if ( array_key_exists( 'created_by_staff_user_id', $data ) ) {
			$clean['created_by_staff_user_id'] = null === $data['created_by_staff_user_id'] || '' === $data['created_by_staff_user_id'] ? null : absint( $data['created_by_staff_user_id'] );
		}
		if ( array_key_exists( 'visitor_id', $data ) ) {
			$clean['visitor_id'] = sanitize_text_field( $data['visitor_id'] );
		}
		if ( array_key_exists( 'created_source', $data ) ) {
			$clean['created_source'] = sanitize_key( $data['created_source'] );
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
		if ( array_key_exists( 'landing_attribution', $data ) ) {
			$clean['landing_attribution'] = is_string( $data['landing_attribution'] ) ? wp_kses_post( $data['landing_attribution'] ) : CRPCRM_Helpers::maybe_json_encode( self::normalize_landing_attribution( $data['landing_attribution'] ) );
		}

		return $clean;
	}

	private static function normalize_landing_attribution( $attribution ) {
		$attribution = is_array( $attribution ) ? $attribution : array();
		$touch_keys  = array( 'landing_id', 'landing_slug', 'landing_title', 'destination_url', 'source_code', 'source_label', 'medium_code', 'medium_label', 'campaign_code', 'campaign_label', 'content_code', 'content_label', 'term_code', 'term_label', 'clicked_at', 'click_id' );
		$touch       = static function ( $value ) use ( $touch_keys ) {
			$clean = array();
			$value = is_array( $value ) ? $value : array();

			foreach ( $touch_keys as $key ) {
				if ( ! array_key_exists( $key, $value ) ) {
					continue;
				}

				if ( 'landing_id' === $key || 'click_id' === $key ) {
					$clean[ $key ] = absint( $value[ $key ] );
					continue;
				}

				if ( 'destination_url' === $key ) {
					$clean[ $key ] = esc_url_raw( $value[ $key ] );
					continue;
				}

				$clean[ $key ] = sanitize_text_field( $value[ $key ] );
			}

			return $clean;
		};

		return array(
			'visitor_id'      => isset( $attribution['visitor_id'] ) ? sanitize_text_field( $attribution['visitor_id'] ) : '',
			'conversion_page' => isset( $attribution['conversion_page'] ) ? esc_url_raw( $attribution['conversion_page'] ) : '',
			'converted_at'    => isset( $attribution['converted_at'] ) ? sanitize_text_field( $attribution['converted_at'] ) : '',
			'referrer'        => isset( $attribution['referrer'] ) ? esc_url_raw( $attribution['referrer'] ) : '',
			'first_touch'     => isset( $attribution['first_touch'] ) ? $touch( $attribution['first_touch'] ) : array(),
			'last_touch'      => isset( $attribution['last_touch'] ) ? $touch( $attribution['last_touch'] ) : array(),
		);
	}
}

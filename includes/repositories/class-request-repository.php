<?php
/**
 * Request repository.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_Request_Repository {
	private $table;

	public function __construct() {
		$this->table = CRPCRM_DB::table( 'requests' );
	}

	public function create( $data ) {
		global $wpdb;

		$now  = CRPCRM_Helpers::current_datetime();
		$data = $this->with_form_metadata( $data );
		$data = $this->sanitize_data( $data );
		$data = wp_parse_args(
			$data,
			array(
				'request_code'     => 'TEMP-' . wp_generate_uuid4(),
				'status'           => 'new',
				'owner_id'         => null,
				'last_activity_at' => $now,
				'created_at'       => $now,
				'updated_at'       => $now,
			)
		);

		$result = $wpdb->insert( $this->table, $data );
		if ( false === $result ) {
			return false;
		}

		$id           = (int) $wpdb->insert_id;
		$request_code = $this->generate_request_code( $id );
		$this->update( $id, array( 'request_code' => $request_code ) );

		if ( class_exists( 'CRPCRM_Dynamic_Form_Renderer' ) ) {
			$request_data = isset( $data['request_data'] ) ? CRPCRM_Helpers::maybe_json_decode( $data['request_data'], true ) : array();
			$request_data = is_array( $request_data ) ? $request_data : array();
			$finalized    = CRPCRM_Dynamic_Form_Renderer::finalize_request_data_uploads( $request_data, $id );
			if ( $finalized !== $request_data ) {
				$this->update( $id, array( 'request_data' => $finalized ) );
			}
		}

		return $id;
	}

	public function update( $id, $data ) {
		global $wpdb;

		$data               = $this->sanitize_data( $data );
		$data['updated_at'] = CRPCRM_Helpers::current_datetime();

		return false !== $wpdb->update( $this->table, $data, array( 'id' => absint( $id ) ) );
	}

	public function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d LIMIT 1", absint( $id ) ), ARRAY_A );
	}

	public function get_by_code( $request_code ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE request_code = %s LIMIT 1", sanitize_text_field( $request_code ) ), ARRAY_A );
	}

	public function save_landing_attribution( $request_id, array $landing_attribution ) {
		$request_id = absint( $request_id );
		$request    = $request_id ? $this->get( $request_id ) : null;
		if ( ! $request ) {
			return false;
		}

		$request_data                           = self::get_merged_request_data( $request );
		$request_data['_landing_attribution']    = self::normalize_landing_attribution( $landing_attribution );
		return $this->update( $request_id, array( 'request_data' => $request_data ) );
	}

	public function save_request_attribution( $request_id, array $request_attribution ) {
		return $this->save_landing_attribution( $request_id, $request_attribution );
	}

	public static function get_landing_attribution( $request ) {
		$request = is_array( $request ) ? $request : array();
		$data    = self::get_merged_request_data( $request );
		$landing = isset( $data['_landing_attribution'] ) && is_array( $data['_landing_attribution'] ) ? $data['_landing_attribution'] : array();

		return self::normalize_landing_attribution( $landing );
	}

	public static function get_request_attribution( $request ) {
		return self::get_landing_attribution( $request );
	}

	public function delete_permanently( $request_id ) {
		global $wpdb;

		$request_id       = absint( $request_id );
		$activities_table = CRPCRM_DB::table( 'request_activities' );

		if ( ! $request_id || ! $this->get( $request_id ) ) {
			return new WP_Error( 'request_not_found', 'درخواست موردنظر یافت نشد.' );
		}

		$wpdb->query( 'START TRANSACTION' );
		$activities_deleted = $wpdb->delete( $activities_table, array( 'request_id' => $request_id ), array( '%d' ) );
		$request_deleted    = $wpdb->delete( $this->table, array( 'id' => $request_id ), array( '%d' ) );

		if ( false === $activities_deleted || 1 !== $request_deleted ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'request_delete_failed', 'حذف کامل درخواست انجام نشد.' );
		}

		$wpdb->query( 'COMMIT' );
		return true;
	}

	public function get_by_customer( $customer_id, $args = array() ) {
		return $this->list_for_customer( $customer_id, $args );
	}

	public function list_for_customer( $customer_id, $args = array() ) {
		global $wpdb;

		$defaults = array(
			'limit'   => 20,
			'offset'  => 0,
			'user_id' => 0,
		);
		$args     = wp_parse_args( $args, $defaults );
		$limit    = max( 1, min( 100, absint( $args['limit'] ) ) );
		$offset   = absint( $args['offset'] );
		$user_id  = absint( $args['user_id'] );
		$system_types = CRPCRM_System_Request_Types::get_type_ids();
		$system_sql   = $system_types ? ' AND request_type NOT IN (' . implode( ', ', array_fill( 0, count( $system_types ), '%s' ) ) . ')' : '';

		if ( $user_id ) {
			$values = array_merge( array( absint( $customer_id ), $user_id ), $system_types, array( $limit, $offset ) );
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$this->table} WHERE (customer_id = %d OR user_id = %d){$system_sql} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d",
					$values
				),
				ARRAY_A
			);
		}

		$values = array_merge( array( absint( $customer_id ) ), $system_types, array( $limit, $offset ) );
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE customer_id = %d{$system_sql} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d",
				$values
			),
			ARRAY_A
		);
	}

	public function count_recent_for_customer_user( $customer_id, $user_id, $since_datetime ) {
		global $wpdb;
		$system_types = CRPCRM_System_Request_Types::get_type_ids();
		$system_sql   = $system_types ? ' AND request_type NOT IN (' . implode( ', ', array_fill( 0, count( $system_types ), '%s' ) ) . ')' : '';
		$values       = array_merge( array( absint( $customer_id ), absint( $user_id ) ), $system_types, array( sanitize_text_field( $since_datetime ) ) );

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table} WHERE customer_id = %d AND user_id = %d{$system_sql} AND created_at >= %s",
				$values
			)
		);
	}

	public function generate_request_code( $id ) {
		return 'REQ-' . str_pad( absint( $id ), 6, '0', STR_PAD_LEFT );
	}


	public function list_for_admin( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'limit'          => 20,
			'offset'         => 0,
			'user_id'        => get_current_user_id(),
			'request_type'   => '',
			'status'         => '',
			'owner_filter'   => 'all',
			'source'         => '',
			'campaign'       => '',
			'date_from'      => '',
			'date_to'        => '',
			'search'         => '',
			'workflow_filter' => '',
			'status_group'    => '',
			'stale_hours'     => absint( CRPCRM_Settings::get( 'stale_request_hours', 48 ) ),
		);
		$args = wp_parse_args( $args, $defaults );

		$where  = $this->build_admin_where( $args );
		$limit  = max( 1, min( 100, absint( $args['limit'] ) ) );
		$offset = absint( $args['offset'] );

		$sql = "SELECT r.*, c.full_name AS customer_name, c.phone AS customer_phone, c.phone_normalized AS customer_phone_normalized, c.province AS customer_province, c.city AS customer_city
			FROM {$this->table} r
			LEFT JOIN " . CRPCRM_DB::table( 'customers' ) . " c ON c.id = r.customer_id
			{$where['sql']}
			ORDER BY r.created_at DESC, r.id DESC
			LIMIT %d OFFSET %d";
		$where['values'][] = $limit;
		$where['values'][] = $offset;

		return $wpdb->get_results( $wpdb->prepare( $sql, $where['values'] ), ARRAY_A );
	}

	public function count_for_admin( $args = array() ) {
		global $wpdb;

		$args  = wp_parse_args( $args, array( 'user_id' => get_current_user_id(), 'request_type' => '', 'status' => '', 'owner_filter' => 'all', 'source' => '', 'campaign' => '', 'date_from' => '', 'date_to' => '', 'search' => '', 'status_group' => '', 'workflow_filter' => '', 'stale_hours' => absint( CRPCRM_Settings::get( 'stale_request_hours', 48 ) ) ) );
		$where = $this->build_admin_where( $args );
		$sql   = "SELECT COUNT(*) FROM {$this->table} r LEFT JOIN " . CRPCRM_DB::table( 'customers' ) . " c ON c.id = r.customer_id {$where['sql']}";

		return (int) $wpdb->get_var( empty( $where['values'] ) ? $sql : $wpdb->prepare( $sql, $where['values'] ) );
	}

	public function get_with_customer( $request_id ) {
		global $wpdb;
		$customers = CRPCRM_DB::table( 'customers' );
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT r.*, c.full_name AS customer_name, c.phone AS customer_phone, c.phone_normalized AS customer_phone_normalized, c.province AS customer_province, c.city AS customer_city, c.user_id AS customer_user_id
				FROM {$this->table} r
				LEFT JOIN {$customers} c ON c.id = r.customer_id
				WHERE r.id = %d LIMIT 1",
				absint( $request_id )
			),
			ARRAY_A
		);
	}

	public function claim_request( $request_id, $user_id ) {
		global $wpdb;

		$request_id = absint( $request_id );
		$user_id    = absint( $user_id );
		$request    = $this->get( $request_id );
		if ( ! $request ) {
			return new WP_Error( 'request_not_found', 'درخواست موردنظر یافت نشد.' );
		}
		if ( ! CRPCRM_Request_Access_Service::can_claim_request( $request, $user_id ) ) {
			if ( ! empty( $request['owner_id'] ) ) {
				return new WP_Error( 'request_already_owned', 'این درخواست قبلاً توسط کارشناس دیگری در حال پیگیری است.' );
			}
			return new WP_Error( 'request_claim_denied', 'شما اجازه دسترسی به این بخش را ندارید.' );
		}

		$now        = CRPCRM_Helpers::current_datetime();
		$old_status = $request['status'];
		$new_status = 'new' === $old_status ? 'in_progress' : $old_status;
		$data       = array(
			'owner_id'         => $user_id,
			'status'           => $new_status,
			'last_action'      => 'request_claimed',
			'last_activity_at' => $now,
			'updated_at'       => $now,
		);
		$formats    = array( '%d', '%s', '%s', '%s', '%s' );
		if ( empty( $request['first_assigned_at'] ) ) {
			$data['first_assigned_at'] = $now;
			$formats[]                 = '%s';
		}

		$set_parts = array();
		$values    = array();
		foreach ( $data as $field => $value ) {
			$set_parts[] = sanitize_key( $field ) . ' = ' . ( is_int( $value ) ? '%d' : '%s' );
			$values[]    = $value;
		}
		$values[] = $request_id;
		$result   = $wpdb->query( $wpdb->prepare( "UPDATE {$this->table} SET " . implode( ', ', $set_parts ) . ' WHERE id = %d AND owner_id IS NULL', $values ) );
		if ( false === $result || 0 === $result ) {
			return new WP_Error( 'request_already_owned', 'این درخواست قبلاً توسط کارشناس دیگری در حال پیگیری است.' );
		}

		CRPCRM_Activity::add(
			$request_id,
			'request_claimed',
			array(
				'customer_id'   => $request['customer_id'],
				'actor_user_id' => $user_id,
				'actor_type'    => 'sales_agent',
				'old_status'    => $old_status,
				'new_status'    => $new_status,
				'note'          => 'درخواست توسط کارشناس شروع به پیگیری شد.',
				'is_internal'   => 1,
			)
		);

		return true;
	}

	public function change_owner( $request_id, $new_owner_id, $actor_user_id ) {
		$request_id    = absint( $request_id );
		$new_owner_id  = absint( $new_owner_id );
		$actor_user_id = absint( $actor_user_id );
		$request       = $this->get( $request_id );
		if ( ! $request ) {
			return new WP_Error( 'request_not_found', 'درخواست موردنظر یافت نشد.' );
		}
		if ( ! CRPCRM_Request_Access_Service::can_manage_request( $request, $actor_user_id ) || ! $this->is_assignable_owner( $new_owner_id ) ) {
			return new WP_Error( 'request_owner_change_denied', 'شما اجازه دسترسی به این بخش را ندارید.' );
		}

		$old_status = $request['status'];
		$new_status = 'new' === $old_status ? 'in_progress' : $old_status;
		$now        = CRPCRM_Helpers::current_datetime();
		$data       = array(
			'owner_id'         => $new_owner_id,
			'status'           => $new_status,
			'last_action'      => 'request_owner_changed',
			'last_activity_at' => $now,
		);
		if ( empty( $request['first_assigned_at'] ) ) {
			$data['first_assigned_at'] = $now;
		}
		if ( ! $this->update( $request_id, $data ) ) {
			return new WP_Error( 'request_owner_change_failed', 'تغییر مسئول درخواست انجام نشد.' );
		}

		CRPCRM_Activity::add(
			$request_id,
			'request_owner_changed',
			array(
				'customer_id'   => $request['customer_id'],
				'actor_user_id' => $actor_user_id,
				'actor_type'    => 'sales_manager',
				'old_status'    => $old_status,
				'new_status'    => $new_status,
				'note'          => 'مسئول درخواست تغییر کرد.',
				'is_internal'   => 1,
				'meta'          => array( 'old_owner_id' => absint( $request['owner_id'] ), 'new_owner_id' => $new_owner_id ),
			)
		);

		return true;
	}

	public function release_owner( $request_id, $actor_user_id ) {
		$request_id    = absint( $request_id );
		$actor_user_id = absint( $actor_user_id );
		$request       = $this->get( $request_id );
		if ( ! $request ) {
			return new WP_Error( 'request_not_found', 'درخواست موردنظر یافت نشد.' );
		}
		if ( ! CRPCRM_Request_Access_Service::can_manage_request( $request, $actor_user_id ) ) {
			return new WP_Error( 'request_owner_release_denied', 'شما اجازه دسترسی به این بخش را ندارید.' );
		}

		$old_status = $request['status'];
		$new_status = ( 'in_progress' === $old_status && empty( $request['closed_at'] ) ) ? 'new' : $old_status;
		if ( ! $this->update( $request_id, array( 'owner_id' => null, 'status' => $new_status, 'last_action' => 'request_owner_released', 'last_activity_at' => CRPCRM_Helpers::current_datetime() ) ) ) {
			return new WP_Error( 'request_owner_release_failed', 'آزادسازی درخواست انجام نشد.' );
		}

		CRPCRM_Activity::add(
			$request_id,
			'request_owner_released',
			array(
				'customer_id'   => $request['customer_id'],
				'actor_user_id' => $actor_user_id,
				'actor_type'    => 'sales_manager',
				'old_status'    => $old_status,
				'new_status'    => $new_status,
				'note'          => 'مسئول درخواست آزاد شد.',
				'is_internal'   => 1,
				'meta'          => array( 'old_owner_id' => absint( $request['owner_id'] ) ),
			)
		);

		return true;
	}


	public function update_status( $request_id, $status, $last_action = '' ) {
		$data = array( 'status' => sanitize_key( $status ), 'last_activity_at' => CRPCRM_Helpers::current_datetime() );
		if ( '' !== $last_action ) {
			$data['last_action'] = sanitize_key( $last_action );
		}
		return $this->update( $request_id, $data );
	}

	public function update_follow_up( $request_id, $next_follow_up_at, $last_action = 'schedule_follow_up' ) {
		return $this->update(
			$request_id,
			array(
				'status'             => 'follow_up',
				'next_follow_up_at'  => sanitize_text_field( $next_follow_up_at ),
				'last_action'        => sanitize_key( $last_action ),
				'last_activity_at'   => CRPCRM_Helpers::current_datetime(),
			)
		);
	}

	public function close_request( $request_id, $status, $reason_key = '', $last_action = '' ) {
		$status = sanitize_key( $status );
		$data   = array(
			'status'             => $status,
			'closed_at'          => CRPCRM_Helpers::current_datetime(),
			'next_follow_up_at'  => null,
			'last_activity_at'   => CRPCRM_Helpers::current_datetime(),
		);
		if ( '' !== $last_action ) {
			$data['last_action'] = sanitize_key( $last_action );
		}
		if ( 'lost' === $status ) {
			$data['close_reason'] = sanitize_key( $reason_key );
		} elseif ( 'invalid' === $status ) {
			$data['invalid_reason'] = sanitize_key( $reason_key );
		}
		return $this->update( $request_id, $data );
	}

	public function list_followups_today( $user_id = 0 ) {
		return $this->list_for_admin( array( 'user_id' => $user_id ? absint( $user_id ) : get_current_user_id(), 'workflow_filter' => 'followups_today', 'limit' => 100 ) );
	}

	public function list_overdue_followups( $user_id = 0 ) {
		return $this->list_for_admin( array( 'user_id' => $user_id ? absint( $user_id ) : get_current_user_id(), 'workflow_filter' => 'overdue_followups', 'limit' => 100 ) );
	}

	public function count_summary_for_user( $user_id ) {
		$user_id = absint( $user_id );
		return array(
			'unassigned'        => $this->count_for_admin( array( 'user_id' => $user_id, 'owner_filter' => 'unassigned', 'status_group' => 'open' ) ),
			'mine'              => $this->count_for_admin( array( 'user_id' => $user_id, 'owner_filter' => 'me' ) ),
			'followups_today'   => $this->count_for_admin( array( 'user_id' => $user_id, 'owner_filter' => 'me', 'workflow_filter' => 'followups_today' ) ),
			'overdue_followups' => $this->count_for_admin( array( 'user_id' => $user_id, 'owner_filter' => 'me', 'workflow_filter' => 'overdue_followups' ) ),
		);
	}

	public function count_summary_for_manager( $user_id, $stale_hours = 48 ) {
		$user_id = absint( $user_id );
		return array(
			'unassigned'        => $this->count_for_admin( array( 'user_id' => $user_id, 'owner_filter' => 'unassigned', 'status_group' => 'open' ) ),
			'open'              => $this->count_for_admin( array( 'user_id' => $user_id, 'status_group' => 'open' ) ),
			'followups_today'   => $this->count_for_admin( array( 'user_id' => $user_id, 'workflow_filter' => 'followups_today' ) ),
			'overdue_followups' => $this->count_for_admin( array( 'user_id' => $user_id, 'workflow_filter' => 'overdue_followups' ) ),
			'stale'             => $this->count_for_admin( array( 'user_id' => $user_id, 'workflow_filter' => 'stale', 'stale_hours' => absint( $stale_hours ) ) ),
		);
	}


	public function count_by_customer_grouped_by_type( $customer_id ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT request_type, COUNT(*) AS total FROM {$this->table} WHERE customer_id = %d GROUP BY request_type", absint( $customer_id ) ), ARRAY_A );
		$counts = array();
		foreach ( $rows as $row ) {
			$counts[ $row['request_type'] ] = absint( $row['total'] );
		}
		return $counts;
	}

	public function count_by_customer_grouped_by_status( $customer_id ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT status, COUNT(*) AS total FROM {$this->table} WHERE customer_id = %d GROUP BY status", absint( $customer_id ) ), ARRAY_A );
		$counts = array();
		foreach ( $rows as $row ) {
			$counts[ $row['status'] ] = absint( $row['total'] );
		}
		return $counts;
	}

	public function get_last_request_for_customer( $customer_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE customer_id = %d ORDER BY created_at DESC, id DESC LIMIT 1", absint( $customer_id ) ), ARRAY_A );
	}

	public function get_last_activity_for_customer( $customer_id ) {
		global $wpdb;
		$activities = CRPCRM_DB::table( 'request_activities' );
		$requests   = CRPCRM_DB::table( 'requests' );
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT a.*, r.request_code FROM {$activities} a INNER JOIN {$requests} r ON r.id = a.request_id WHERE a.customer_id = %d ORDER BY a.created_at DESC, a.id DESC LIMIT 1",
				absint( $customer_id )
			),
			ARRAY_A
		);
	}

	public function user_can_access_request( $request_id, $user_id ) {
		$request = $this->get( $request_id );
		return CRPCRM_Request_Access_Service::can_view_request( $request, $user_id );
	}

	private function build_admin_where( $args ) {
		$user_id = isset( $args['user_id'] ) ? absint( $args['user_id'] ) : get_current_user_id();
		$where   = array( '1=1' );
		$values  = array();
		if ( ! CRPCRM_Request_Access_Service::can_view_all( $user_id ) ) {
			$where[]  = '(r.owner_id = %d OR r.owner_id IS NULL)';
			$values[] = $user_id;
		}

		if ( ! empty( $args['request_type'] ) ) {
			$where[]  = 'r.request_type = %s';
			$values[] = sanitize_key( $args['request_type'] );
		}
		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'r.status = %s';
			$values[] = sanitize_key( $args['status'] );
		}
		if ( empty( $args['status'] ) && ! empty( $args['status_group'] ) ) {
			if ( 'open' === $args['status_group'] ) {
				$where[] = "r.status IN ('new','in_progress','no_answer','follow_up')";
			} elseif ( 'closed' === $args['status_group'] ) {
				$where[] = "r.status IN ('won','lost','invalid')";
			}
		}
		if ( ! empty( $args['workflow_filter'] ) ) {
			$workflow_filter = sanitize_key( $args['workflow_filter'] );
			if ( 'followups_today' === $workflow_filter ) {
				$where[]  = 'r.status = %s AND r.next_follow_up_at >= %s AND r.next_follow_up_at <= %s';
				$values[] = 'follow_up';
				$values[] = wp_date( 'Y-m-d 00:00:00', current_time( 'timestamp' ) );
				$values[] = wp_date( 'Y-m-d 23:59:59', current_time( 'timestamp' ) );
			} elseif ( 'overdue_followups' === $workflow_filter ) {
				$where[]  = 'r.status = %s AND r.next_follow_up_at < %s';
				$values[] = 'follow_up';
				$values[] = CRPCRM_Helpers::current_datetime();
			} elseif ( 'stale' === $workflow_filter ) {
				if ( CRPCRM_Request_Access_Service::can_view_all( $user_id ) ) {
					$stale_hours = max( 1, absint( $args['stale_hours'] ) );
					$where[]     = "r.status IN ('new','in_progress','no_answer','follow_up') AND r.owner_id IS NOT NULL AND (r.last_activity_at IS NULL OR r.last_activity_at < %s)";
					$values[]    = wp_date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - ( HOUR_IN_SECONDS * $stale_hours ) );
				} else {
					$where[] = '1=0';
				}
			}
		}
		if ( isset( $args['owner_filter'] ) && '' !== $args['owner_filter'] && 'all' !== $args['owner_filter'] ) {
			$owner_filter = sanitize_text_field( $args['owner_filter'] );
			if ( 'unassigned' === $owner_filter ) {
				$where[] = 'r.owner_id IS NULL';
			} elseif ( 'me' === $owner_filter ) {
				$where[]  = 'r.owner_id = %d';
				$values[] = $user_id;
			} elseif ( CRPCRM_Request_Access_Service::can_view_all( $user_id ) && absint( $owner_filter ) ) {
				$where[]  = 'r.owner_id = %d';
				$values[] = absint( $owner_filter );
			}
		}
		if ( ! empty( $args['source'] ) ) {
			$source = sanitize_key( $args['source'] );
			if ( 'other' === $source ) {
				$where[] = "(r.request_source IS NOT NULL AND r.request_source <> '' AND r.request_source NOT IN ('direct','instagram','whatsapp','telegram','google','bing'))";
			} else {
				$where[]  = 'r.request_source = %s';
				$values[] = $source;
			}
		}
		if ( ! empty( $args['campaign'] ) ) {
			$where[]  = 'r.request_campaign LIKE %s';
			$values[] = '%' . $GLOBALS['wpdb']->esc_like( sanitize_text_field( $args['campaign'] ) ) . '%';
		}
		if ( ! empty( $args['date_from'] ) ) {
			$where[]  = 'r.created_at >= %s';
			$values[] = sanitize_text_field( $args['date_from'] ) . ' 00:00:00';
		}
		if ( ! empty( $args['date_to'] ) ) {
			$where[]  = 'r.created_at <= %s';
			$values[] = sanitize_text_field( $args['date_to'] ) . ' 23:59:59';
		}
		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $GLOBALS['wpdb']->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
			$where[]  = '(r.request_code LIKE %s OR c.full_name LIKE %s OR c.phone LIKE %s OR c.phone_normalized LIKE %s OR r.request_summary LIKE %s)';
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
		}

		return array( 'sql' => 'WHERE ' . implode( ' AND ', $where ), 'values' => $values );
	}

	private function is_assignable_owner( $user_id ) {
		$user = get_userdata( absint( $user_id ) );
		if ( ! $user ) {
			return false;
		}
		$roles = (array) $user->roles;
		return in_array( 'sales_agent', $roles, true ) || in_array( 'sales_manager', $roles, true );
	}

	private function sanitize_data( $data ) {
		$clean = array();
		$int_fields = array( 'customer_id', 'user_id', 'owner_id' );
		$key_fields = array( 'request_type', 'form_id' );
		$text_fields = array( 'request_code', 'form_version', 'status', 'request_title', 'request_source', 'request_medium', 'request_campaign', 'request_content', 'request_term', 'last_action', 'close_reason', 'invalid_reason' );
		$textarea_fields = array( 'request_summary', 'request_landing_page', 'request_referrer' );
		$date_fields = array( 'first_assigned_at', 'last_activity_at', 'next_follow_up_at', 'closed_at', 'created_at', 'updated_at' );

		foreach ( $int_fields as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$clean[ $field ] = null === $data[ $field ] || '' === $data[ $field ] ? null : absint( $data[ $field ] );
			}
		}
		foreach ( $text_fields as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$clean[ $field ] = sanitize_text_field( $data[ $field ] );
			}
		}
		foreach ( $key_fields as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$clean[ $field ] = sanitize_key( $data[ $field ] );
			}
		}
		foreach ( $textarea_fields as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$clean[ $field ] = sanitize_textarea_field( $data[ $field ] );
			}
		}
		foreach ( $date_fields as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$clean[ $field ] = null === $data[ $field ] || '' === $data[ $field ] ? null : sanitize_text_field( $data[ $field ] );
			}
		}
		if ( array_key_exists( 'request_data', $data ) ) {
			$clean['request_data'] = is_string( $data['request_data'] ) ? wp_kses_post( $data['request_data'] ) : CRPCRM_Helpers::maybe_json_encode( CRPCRM_Helpers::sanitize_array( $data['request_data'] ) );
		}

		return $clean;
	}

	private function with_form_metadata( $data ) {
		$data         = is_array( $data ) ? $data : array();
		$request_data = isset( $data['request_data'] ) ? $data['request_data'] : array();
		$request_data = is_string( $request_data ) ? CRPCRM_Helpers::maybe_json_decode( $request_data, true ) : $request_data;
		$request_data = is_array( $request_data ) ? $request_data : array();

		if ( empty( $data['form_id'] ) && ! empty( $request_data['form_id'] ) ) {
			$data['form_id'] = $request_data['form_id'];
		}
		if ( empty( $data['form_version'] ) && ! empty( $request_data['form_version'] ) ) {
			$data['form_version'] = $request_data['form_version'];
		}
		$data['request_data'] = $request_data;

		return $data;
	}

	public static function get_merged_request_data( $request ) {
		$request = is_array( $request ) ? $request : array();
		$data    = CRPCRM_Helpers::maybe_json_decode( $request['request_data'] ?? '', true );
		$data    = is_array( $data ) ? $data : array();
		foreach ( array( 'request_type', 'form_id', 'form_version' ) as $field ) {
			if ( isset( $request[ $field ] ) && '' !== (string) $request[ $field ] ) {
				$data[ $field ] = $request[ $field ];
			}
		}
		return $data;
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
				if ( in_array( $key, array( 'destination_url' ), true ) ) {
					$clean[ $key ] = esc_url_raw( $value[ $key ] );
					continue;
				}
				if ( 'clicked_at' === $key ) {
					$clean[ $key ] = sanitize_text_field( $value[ $key ] );
					continue;
				}
				$clean[ $key ] = sanitize_text_field( $value[ $key ] );
			}

			return $clean;
		};

		$first_touch = isset( $attribution['first_touch'] ) ? $touch( $attribution['first_touch'] ) : array();
		$last_touch   = isset( $attribution['last_touch'] ) ? $touch( $attribution['last_touch'] ) : array();

		return array(
			'visitor_id'      => isset( $attribution['visitor_id'] ) ? sanitize_text_field( $attribution['visitor_id'] ) : '',
			'conversion_page' => isset( $attribution['conversion_page'] ) ? esc_url_raw( $attribution['conversion_page'] ) : '',
			'converted_at'    => isset( $attribution['converted_at'] ) ? sanitize_text_field( $attribution['converted_at'] ) : '',
			'first_touch'     => $first_touch,
			'last_touch'      => $last_touch,
		);
	}
}

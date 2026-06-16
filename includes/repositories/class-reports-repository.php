<?php
/**
 * Management reports repository.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_Reports_Repository {
	private $requests_table;
	private $customers_table;
	private $activities_table;

	private $open_statuses   = array( 'new', 'in_progress', 'no_answer', 'follow_up' );
	private $closed_statuses = array( 'won', 'lost', 'invalid' );
	private $statuses        = array( 'new', 'in_progress', 'no_answer', 'follow_up', 'won', 'lost', 'invalid' );
	private $known_sources   = array( 'direct', 'instagram', 'whatsapp', 'google', 'telegram', 'bing' );

	public function __construct() {
		$this->requests_table   = CRPCRM_DB::table( 'requests' );
		$this->customers_table  = CRPCRM_DB::table( 'customers' );
		$this->activities_table = CRPCRM_DB::table( 'request_activities' );
	}

	private function get_request_types() {
		return CRPCRM_Request_Type_Registry::get_request_type_ids();
	}

	public function normalize_filters( $input ) {
		$filters = array(
			'date_range'      => isset( $input['date_range'] ) ? sanitize_key( wp_unslash( $input['date_range'] ) ) : 'last_30_days',
			'date_from'       => isset( $input['date_from'] ) ? CRPCRM_Helpers::normalize_date_input( wp_unslash( $input['date_from'] ) ) : '',
			'date_to'         => isset( $input['date_to'] ) ? CRPCRM_Helpers::normalize_date_input( wp_unslash( $input['date_to'] ) ) : '',
			'request_type'    => isset( $input['request_type'] ) ? sanitize_key( wp_unslash( $input['request_type'] ) ) : '',
			'source'          => isset( $input['source'] ) ? sanitize_key( wp_unslash( $input['source'] ) ) : '',
			'campaign'        => isset( $input['campaign'] ) ? sanitize_text_field( wp_unslash( $input['campaign'] ) ) : '',
			'content'         => isset( $input['content'] ) ? sanitize_text_field( wp_unslash( $input['content'] ) ) : '',
			'landing'         => isset( $input['landing'] ) ? sanitize_text_field( wp_unslash( $input['landing'] ) ) : '',
			'status'          => isset( $input['status'] ) ? sanitize_key( wp_unslash( $input['status'] ) ) : '',
			'owner_filter'    => isset( $input['owner_filter'] ) ? sanitize_text_field( wp_unslash( $input['owner_filter'] ) ) : 'all',
			'workflow_filter' => isset( $input['workflow_filter'] ) ? sanitize_key( wp_unslash( $input['workflow_filter'] ) ) : '',
		);

		$allowed_ranges = array( 'today', 'yesterday', 'last_7_days', 'last_30_days', 'current_month', 'last_month', 'custom' );
		if ( ! in_array( $filters['date_range'], $allowed_ranges, true ) ) {
			$filters['date_range'] = 'last_30_days';
		}
		if ( $filters['request_type'] && ! in_array( $filters['request_type'], $this->get_request_types(), true ) ) {
			$filters['request_type'] = '';
		}
		if ( $filters['status'] && ! in_array( $filters['status'], $this->statuses, true ) ) {
			$filters['status'] = '';
		}
		$allowed_sources = array_merge( $this->known_sources, array( 'other' ) );
		if ( $filters['source'] && ! in_array( $filters['source'], $allowed_sources, true ) ) {
			$filters['source'] = '';
		}
		if ( ! in_array( $filters['workflow_filter'], array( '', 'followups_today', 'overdue_followups', 'stale', 'unassigned' ), true ) ) {
			$filters['workflow_filter'] = '';
		}
		if ( '' !== $filters['landing'] ) {
			$filters['landing'] = sanitize_text_field( $filters['landing'] );
		}

		$filters = array_merge( $filters, $this->resolve_date_range( $filters ) );
		return $filters;
	}

	public function get_kpis( $filters ) {
		$where = $this->build_request_filters_where( $filters );
		$row   = $this->get_row(
			"SELECT COUNT(*) AS total,
				SUM(CASE WHEN r.status IN ('new','in_progress','no_answer','follow_up') THEN 1 ELSE 0 END) AS open_total,
				SUM(CASE WHEN r.status IN ('won','lost','invalid') THEN 1 ELSE 0 END) AS closed_total,
				SUM(CASE WHEN r.owner_id IS NULL THEN 1 ELSE 0 END) AS unassigned,
				SUM(CASE WHEN r.status = 'won' THEN 1 ELSE 0 END) AS won,
				SUM(CASE WHEN r.status = 'lost' THEN 1 ELSE 0 END) AS lost,
				SUM(CASE WHEN r.status = 'invalid' THEN 1 ELSE 0 END) AS invalid,
				AVG(CASE WHEN r.first_assigned_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, r.created_at, r.first_assigned_at) ELSE NULL END) AS avg_first_action_seconds
			FROM {$this->requests_table} r {$where['sql']}",
			$where['values']
		);

		$today_filters                = $filters;
		$today_filters['start_date']  = wp_date( 'Y-m-d 00:00:00', current_time( 'timestamp' ) );
		$today_filters['end_date']    = wp_date( 'Y-m-d 23:59:59', current_time( 'timestamp' ) );
		$today_where                  = $this->build_request_filters_where( $today_filters );
		$timezone                     = wp_timezone();
		$now                          = new DateTimeImmutable( 'now', $timezone );
		$week_filters                 = $filters;
		$week_filters['start_date']   = $now->modify( 'monday this week' )->setTime( 0, 0, 0 )->format( 'Y-m-d H:i:s' );
		$week_filters['end_date']     = $now->setTime( 23, 59, 59 )->format( 'Y-m-d H:i:s' );
		$week_where                   = $this->build_request_filters_where( $week_filters );
		$month_filters                = $filters;
		$jalali_month                = CRPCRM_Helpers::get_jalali_month_range();
		$month_filters['start_date']  = $jalali_month['start'];
		$month_filters['end_date']    = $now->setTime( 23, 59, 59 )->format( 'Y-m-d H:i:s' );
		$month_where                  = $this->build_request_filters_where( $month_filters );
		$followups_today_filters      = $filters;
		$followups_today_filters['workflow_filter'] = 'followups_today';
		$followups_today_where        = $this->build_request_filters_where( $followups_today_filters );
		$overdue_filters              = $filters;
		$overdue_filters['workflow_filter'] = 'overdue_followups';
		$overdue_where                = $this->build_request_filters_where( $overdue_filters );

		$total        = absint( $row['total'] ?? 0 );
		$closed_total = absint( $row['closed_total'] ?? 0 );
		$won          = absint( $row['won'] ?? 0 );

		return array(
			'total'                    => $total,
			'today'                    => $this->get_var( "SELECT COUNT(*) FROM {$this->requests_table} r {$today_where['sql']}", $today_where['values'] ),
			'this_week'                => $this->get_var( "SELECT COUNT(*) FROM {$this->requests_table} r {$week_where['sql']}", $week_where['values'] ),
			'this_month'               => $this->get_var( "SELECT COUNT(*) FROM {$this->requests_table} r {$month_where['sql']}", $month_where['values'] ),
			'open_total'               => absint( $row['open_total'] ?? 0 ),
			'closed_total'             => $closed_total,
			'unassigned'               => absint( $row['unassigned'] ?? 0 ),
			'followups_today'          => $this->get_var( "SELECT COUNT(*) FROM {$this->requests_table} r {$followups_today_where['sql']}", $followups_today_where['values'] ),
			'overdue_followups'        => $this->get_var( "SELECT COUNT(*) FROM {$this->requests_table} r {$overdue_where['sql']}", $overdue_where['values'] ),
			'won'                      => $won,
			'lost'                     => absint( $row['lost'] ?? 0 ),
			'invalid'                  => absint( $row['invalid'] ?? 0 ),
			'success_rate'             => $closed_total > 0 ? round( ( $won / $closed_total ) * 100, 1 ) : null,
			'avg_first_action_seconds' => null === $row['avg_first_action_seconds'] ? null : (int) $row['avg_first_action_seconds'],
		);
	}

	public function get_source_report( $filters ) {
		$where = $this->build_request_filters_where( $filters );
		return $this->get_results(
			"SELECT COALESCE(NULLIF(r.request_source, ''), 'direct') AS request_source,
				COUNT(*) AS total,
				SUM(CASE WHEN r.status = 'won' THEN 1 ELSE 0 END) AS won,
				SUM(CASE WHEN r.status = 'lost' THEN 1 ELSE 0 END) AS lost,
				SUM(CASE WHEN r.status = 'invalid' THEN 1 ELSE 0 END) AS invalid,
				SUM(CASE WHEN r.status IN ('new','in_progress','no_answer','follow_up') THEN 1 ELSE 0 END) AS open_total,
				AVG(CASE WHEN r.first_assigned_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, r.created_at, r.first_assigned_at) ELSE NULL END) AS avg_first_action_seconds
			FROM {$this->requests_table} r {$where['sql']}
			GROUP BY COALESCE(NULLIF(r.request_source, ''), 'direct')
			ORDER BY total DESC",
			$where['values']
		);
	}

	public function get_campaign_report( $filters ) {
		$where        = $this->build_request_filters_where( $filters );
		$type_columns = $this->build_request_type_count_columns();
		return $this->get_results(
			"SELECT COALESCE(NULLIF(r.request_campaign, ''), '') AS request_campaign,
				COUNT(*) AS total{$type_columns['sql']},
				SUM(CASE WHEN r.status = 'won' THEN 1 ELSE 0 END) AS won,
				SUM(CASE WHEN r.status = 'lost' THEN 1 ELSE 0 END) AS lost,
				SUM(CASE WHEN r.status = 'invalid' THEN 1 ELSE 0 END) AS invalid,
				SUM(CASE WHEN r.status IN ('new','in_progress','no_answer','follow_up') THEN 1 ELSE 0 END) AS open_total
			FROM {$this->requests_table} r {$where['sql']}
			GROUP BY COALESCE(NULLIF(r.request_campaign, ''), '')
			ORDER BY total DESC
			LIMIT 50",
			array_merge( $type_columns['values'], $where['values'] )
		);
	}

	public function get_content_report( $filters ) {
		$where = $this->build_request_filters_where( $filters );
		$rows  = $this->get_results(
			"SELECT COALESCE(NULLIF(r.request_content, ''), '') AS request_content,
				COUNT(*) AS total,
				SUM(CASE WHEN r.status = 'won' THEN 1 ELSE 0 END) AS won,
				SUM(CASE WHEN r.status = 'lost' THEN 1 ELSE 0 END) AS lost,
				SUM(CASE WHEN r.status IN ('new','in_progress','no_answer','follow_up') THEN 1 ELSE 0 END) AS open_total
			FROM {$this->requests_table} r {$where['sql']}
			GROUP BY COALESCE(NULLIF(r.request_content, ''), '')
			ORDER BY total DESC
			LIMIT 50",
			$where['values']
		);

		foreach ( $rows as &$row ) {
			$row['top_source'] = $this->get_top_group_value( 'request_source', $filters, 'request_content', $row['request_content'] );
			$row['top_type']   = $this->get_top_group_value( 'request_type', $filters, 'request_content', $row['request_content'] );
		}
		unset( $row );
		return $rows;
	}

	public function get_request_type_report( $filters ) {
		$where = $this->build_request_filters_where( $filters );
		$rows  = $this->get_results(
			"SELECT r.request_type,
				COUNT(*) AS total,
				SUM(CASE WHEN r.status IN ('new','in_progress','no_answer','follow_up') THEN 1 ELSE 0 END) AS open_total,
				SUM(CASE WHEN r.status = 'won' THEN 1 ELSE 0 END) AS won,
				SUM(CASE WHEN r.status = 'lost' THEN 1 ELSE 0 END) AS lost,
				SUM(CASE WHEN r.status = 'invalid' THEN 1 ELSE 0 END) AS invalid
			FROM {$this->requests_table} r {$where['sql']}
			GROUP BY r.request_type
			ORDER BY total DESC",
			$where['values']
		);
		foreach ( $rows as &$row ) {
			$row['top_source']   = $this->get_top_group_value( 'request_source', $filters, 'request_type', $row['request_type'] );
			$row['top_campaign'] = $this->get_top_group_value( 'request_campaign', $filters, 'request_type', $row['request_type'] );
		}
		unset( $row );
		return $rows;
	}

	public function get_source_type_matrix( $filters ) {
		$where        = $this->build_request_filters_where( $filters );
		$type_columns = $this->build_request_type_count_columns();
		return $this->get_results(
			"SELECT COALESCE(NULLIF(r.request_source, ''), 'direct') AS request_source{$type_columns['sql']},
				COUNT(*) AS total
			FROM {$this->requests_table} r {$where['sql']}
			GROUP BY COALESCE(NULLIF(r.request_source, ''), 'direct')
			ORDER BY total DESC",
			array_merge( $type_columns['values'], $where['values'] )
		);
	}

	public function get_status_funnel( $filters ) {
		$where = $this->build_request_filters_where( $filters );
		$rows  = $this->get_results( "SELECT r.status, COUNT(*) AS total FROM {$this->requests_table} r {$where['sql']} GROUP BY r.status", $where['values'] );
		$map   = array_fill_keys( $this->statuses, 0 );
		foreach ( $rows as $row ) {
			if ( isset( $map[ $row['status'] ] ) ) {
				$map[ $row['status'] ] = absint( $row['total'] );
			}
		}
		$result = array();
		foreach ( $map as $status => $total ) {
			$result[] = array( 'status' => $status, 'total' => $total );
		}
		return $result;
	}

	public function get_agent_performance( $filters ) {
		$users = get_users(
			array(
				'role__in' => array( 'sales_agent', 'sales_manager' ),
				'orderby'  => 'display_name',
				'order'    => 'ASC',
				'fields'   => array( 'ID', 'display_name', 'user_login' ),
			)
		);
		$performance = array();
		foreach ( $users as $user ) {
			$performance[ absint( $user->ID ) ] = array(
				'user_id'                  => absint( $user->ID ),
				'display_name'             => $user->display_name,
				'current_owned'            => 0,
				'claimed'                  => 0,
				'activities'               => 0,
				'call_answered'            => 0,
				'call_no_answer'           => 0,
				'whatsapp_sent'            => 0,
				'follow_up_scheduled'      => 0,
				'won'                      => 0,
				'lost'                     => 0,
				'invalid'                  => 0,
				'overdue_followups'        => 0,
				'avg_first_action_seconds' => null,
				'success_rate'             => null,
			);
		}

		$where = $this->build_request_filters_where( $filters );
		foreach ( $this->get_results( "SELECT r.owner_id, COUNT(*) AS current_owned, SUM(CASE WHEN r.status = 'won' THEN 1 ELSE 0 END) AS won, SUM(CASE WHEN r.status = 'lost' THEN 1 ELSE 0 END) AS lost, SUM(CASE WHEN r.status = 'invalid' THEN 1 ELSE 0 END) AS invalid, AVG(CASE WHEN r.first_assigned_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, r.created_at, r.first_assigned_at) ELSE NULL END) AS avg_first_action_seconds FROM {$this->requests_table} r {$where['sql']} AND r.owner_id IS NOT NULL GROUP BY r.owner_id", $where['values'] ) as $row ) {
			$id = absint( $row['owner_id'] );
			if ( ! isset( $performance[ $id ] ) ) {
				$performance[ $id ] = array( 'user_id' => $id, 'display_name' => 'کاربر #' . $id, 'current_owned' => 0, 'claimed' => 0, 'activities' => 0, 'call_answered' => 0, 'call_no_answer' => 0, 'whatsapp_sent' => 0, 'follow_up_scheduled' => 0, 'won' => 0, 'lost' => 0, 'invalid' => 0, 'overdue_followups' => 0, 'avg_first_action_seconds' => null, 'success_rate' => null );
			}
			$performance[ $id ]['current_owned']            = absint( $row['current_owned'] );
			$performance[ $id ]['won']                      = absint( $row['won'] );
			$performance[ $id ]['lost']                     = absint( $row['lost'] );
			$performance[ $id ]['invalid']                  = absint( $row['invalid'] );
			$performance[ $id ]['avg_first_action_seconds'] = null === $row['avg_first_action_seconds'] ? null : (int) $row['avg_first_action_seconds'];
		}

		$claimed_filters = $filters;
		unset( $claimed_filters['workflow_filter'] );
		$claimed_where = $this->build_request_filters_where( $claimed_filters, 'r', 'first_assigned_at' );
		foreach ( $this->get_results( "SELECT r.owner_id, COUNT(*) AS total FROM {$this->requests_table} r {$claimed_where['sql']} AND r.owner_id IS NOT NULL AND r.first_assigned_at IS NOT NULL GROUP BY r.owner_id", $claimed_where['values'] ) as $row ) {
			$id = absint( $row['owner_id'] );
			if ( isset( $performance[ $id ] ) ) {
				$performance[ $id ]['claimed'] = absint( $row['total'] );
			}
		}

		$activity_where = $this->build_request_filters_where( $filters );
		$activity_sql   = "SELECT a.actor_user_id,
			COUNT(*) AS activities,
			SUM(CASE WHEN a.activity_type = 'call_answered' THEN 1 ELSE 0 END) AS call_answered,
			SUM(CASE WHEN a.activity_type = 'call_no_answer' THEN 1 ELSE 0 END) AS call_no_answer,
			SUM(CASE WHEN a.activity_type = 'whatsapp_sent' THEN 1 ELSE 0 END) AS whatsapp_sent,
			SUM(CASE WHEN a.activity_type = 'follow_up_scheduled' THEN 1 ELSE 0 END) AS follow_up_scheduled
			FROM {$this->activities_table} a
			INNER JOIN {$this->requests_table} r ON r.id = a.request_id
			{$activity_where['sql']} AND a.actor_user_id IS NOT NULL";
		$activity_values = $activity_where['values'];
		if ( ! empty( $filters['start_date'] ) ) {
			$activity_sql     .= ' AND a.created_at >= %s';
			$activity_values[] = $filters['start_date'];
		}
		if ( ! empty( $filters['end_date'] ) ) {
			$activity_sql     .= ' AND a.created_at <= %s';
			$activity_values[] = $filters['end_date'];
		}
		$activity_sql .= ' GROUP BY a.actor_user_id';
		foreach ( $this->get_results( $activity_sql, $activity_values ) as $row ) {
			$id = absint( $row['actor_user_id'] );
			if ( isset( $performance[ $id ] ) ) {
				$performance[ $id ]['activities']          = absint( $row['activities'] );
				$performance[ $id ]['call_answered']       = absint( $row['call_answered'] );
				$performance[ $id ]['call_no_answer']      = absint( $row['call_no_answer'] );
				$performance[ $id ]['whatsapp_sent']       = absint( $row['whatsapp_sent'] );
				$performance[ $id ]['follow_up_scheduled'] = absint( $row['follow_up_scheduled'] );
			}
		}

		$overdue_filters = $filters;
		$overdue_filters['workflow_filter'] = 'overdue_followups';
		$overdue_where = $this->build_request_filters_where( $overdue_filters );
		foreach ( $this->get_results( "SELECT r.owner_id, COUNT(*) AS total FROM {$this->requests_table} r {$overdue_where['sql']} AND r.owner_id IS NOT NULL GROUP BY r.owner_id", $overdue_where['values'] ) as $row ) {
			$id = absint( $row['owner_id'] );
			if ( isset( $performance[ $id ] ) ) {
				$performance[ $id ]['overdue_followups'] = absint( $row['total'] );
			}
		}

		foreach ( $performance as &$row ) {
			$closed = $row['won'] + $row['lost'] + $row['invalid'];
			$row['success_rate'] = $closed > 0 ? round( ( $row['won'] / $closed ) * 100, 1 ) : null;
		}
		unset( $row );
		return array_values( $performance );
	}

	public function get_followup_report( $filters ) {
		$items       = array();
		$stale_hours = absint( CRPCRM_Settings::get( 'stale_request_hours', 48 ) );
		foreach ( array( 'followups_today' => 'پیگیری‌های امروز', 'overdue_followups' => 'پیگیری‌های عقب‌افتاده', 'stale' => 'درخواست‌های بدون فعالیت در ' . $stale_hours . ' ساعت اخیر', 'unassigned' => 'درخواست‌های بدون مسئول' ) as $key => $label ) {
			$item_filters = $filters;
			$item_filters['workflow_filter'] = $key;
			$where = $this->build_request_filters_where( $item_filters );
			$items[] = array( 'key' => $key, 'label' => $label, 'total' => $this->get_var( "SELECT COUNT(*) FROM {$this->requests_table} r {$where['sql']}", $where['values'] ) );
		}
		return $items;
	}

	public function get_close_reason_report( $filters ) {
		$item_filters = $filters;
		$item_filters['status'] = 'lost';
		$where = $this->build_request_filters_where( $item_filters );
		return $this->get_results( "SELECT COALESCE(NULLIF(r.close_reason, ''), 'other') AS reason, COUNT(*) AS total FROM {$this->requests_table} r {$where['sql']} GROUP BY COALESCE(NULLIF(r.close_reason, ''), 'other') ORDER BY total DESC", $where['values'] );
	}

	public function get_invalid_reason_report( $filters ) {
		$item_filters = $filters;
		$item_filters['status'] = 'invalid';
		$where = $this->build_request_filters_where( $item_filters );
		return $this->get_results( "SELECT COALESCE(NULLIF(r.invalid_reason, ''), 'other') AS reason, COUNT(*) AS total FROM {$this->requests_table} r {$where['sql']} GROUP BY COALESCE(NULLIF(r.invalid_reason, ''), 'other') ORDER BY total DESC", $where['values'] );
	}

	public function get_request_details( $filters, $pagination ) {
		$where  = $this->build_request_filters_where( $filters );
		$limit  = isset( $pagination['limit'] ) ? max( 1, min( 1000, absint( $pagination['limit'] ) ) ) : 20;
		$offset = isset( $pagination['offset'] ) ? absint( $pagination['offset'] ) : 0;
		$sql    = "SELECT r.id, r.request_code, r.customer_id, r.request_type, r.form_id, r.form_version, r.request_data, r.request_summary, r.request_source, r.request_campaign, r.request_content, r.status, r.owner_id, r.last_activity_at, r.created_at, c.full_name AS customer_name, c.phone AS customer_phone
			FROM {$this->requests_table} r
			LEFT JOIN {$this->customers_table} c ON c.id = r.customer_id
			{$where['sql']}
			ORDER BY r.created_at DESC, r.id DESC
			LIMIT %d OFFSET %d";
		$values = $where['values'];
		$values[] = $limit;
		$values[] = $offset;
		return $this->get_results( $sql, $values );
	}

	public function count_request_details( $filters ) {
		$where = $this->build_request_filters_where( $filters );
		return $this->get_var( "SELECT COUNT(*) FROM {$this->requests_table} r {$where['sql']}", $where['values'] );
	}

	public function build_request_filters_where( $filters, $alias = 'r', $date_column = 'created_at' ) {
		global $wpdb;
		$where  = array( '1=1' );
		$values = array();
		$p      = preg_replace( '/[^a-zA-Z0-9_]/', '', $alias );

		if ( ! empty( $filters['start_date'] ) ) {
			$where[]  = "$p.$date_column >= %s";
			$values[] = sanitize_text_field( $filters['start_date'] );
		}
		if ( ! empty( $filters['end_date'] ) ) {
			$where[]  = "$p.$date_column <= %s";
			$values[] = sanitize_text_field( $filters['end_date'] );
		}
		if ( ! empty( $filters['request_type'] ) && in_array( $filters['request_type'], $this->get_request_types(), true ) ) {
			$where[]  = "$p.request_type = %s";
			$values[] = $filters['request_type'];
		}
		if ( ! empty( $filters['source'] ) ) {
			if ( 'other' === $filters['source'] ) {
				$where[] = "$p.request_source IS NOT NULL AND $p.request_source <> '' AND $p.request_source NOT IN ('direct','instagram','whatsapp','google','telegram','bing')";
			} elseif ( 'direct' === $filters['source'] ) {
				$where[] = "($p.request_source = 'direct' OR $p.request_source IS NULL OR $p.request_source = '')";
			} elseif ( in_array( $filters['source'], $this->known_sources, true ) ) {
				$where[]  = "$p.request_source = %s";
				$values[] = $filters['source'];
			}
		}
		if ( ! empty( $filters['campaign'] ) ) {
			$where[]  = "$p.request_campaign LIKE %s";
			$values[] = '%' . $wpdb->esc_like( sanitize_text_field( $filters['campaign'] ) ) . '%';
		}
		if ( ! empty( $filters['content'] ) ) {
			$where[]  = "$p.request_content LIKE %s";
			$values[] = '%' . $wpdb->esc_like( sanitize_text_field( $filters['content'] ) ) . '%';
		}
		if ( ! empty( $filters['landing'] ) ) {
			$landing = sanitize_text_field( $filters['landing'] );
			if ( preg_match( '/^\d+$/', $landing ) ) {
				$where[]  = "$p.request_landing_id = %d";
				$values[] = absint( $landing );
			} else {
				$where[]  = "$p.request_landing_slug = %s";
				$values[] = sanitize_key( $landing );
			}
		}
		if ( ! empty( $filters['status'] ) && in_array( $filters['status'], $this->statuses, true ) ) {
			$where[]  = "$p.status = %s";
			$values[] = $filters['status'];
		}
		if ( isset( $filters['owner_filter'] ) && '' !== $filters['owner_filter'] && 'all' !== $filters['owner_filter'] ) {
			if ( 'unassigned' === $filters['owner_filter'] ) {
				$where[] = "$p.owner_id IS NULL";
			} elseif ( absint( $filters['owner_filter'] ) ) {
				$where[]  = "$p.owner_id = %d";
				$values[] = absint( $filters['owner_filter'] );
			}
		}
		if ( ! empty( $filters['workflow_filter'] ) ) {
			$workflow_filter = sanitize_key( $filters['workflow_filter'] );
			if ( 'followups_today' === $workflow_filter ) {
				$where[]  = "$p.status = 'follow_up' AND $p.next_follow_up_at >= %s AND $p.next_follow_up_at <= %s";
				$values[] = wp_date( 'Y-m-d 00:00:00', current_time( 'timestamp' ) );
				$values[] = wp_date( 'Y-m-d 23:59:59', current_time( 'timestamp' ) );
			} elseif ( 'overdue_followups' === $workflow_filter ) {
				$where[]  = "$p.status = 'follow_up' AND $p.next_follow_up_at < %s";
				$values[] = CRPCRM_Helpers::current_datetime();
			} elseif ( 'stale' === $workflow_filter ) {
				$stale_hours = absint( CRPCRM_Settings::get( 'stale_request_hours', 48 ) );
				$where[]     = "$p.status IN ('new','in_progress','no_answer','follow_up') AND ($p.last_activity_at IS NULL OR $p.last_activity_at < %s)";
				$values[]    = wp_date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - ( HOUR_IN_SECONDS * max( 1, $stale_hours ) ) );
			} elseif ( 'unassigned' === $workflow_filter ) {
				$where[] = "$p.owner_id IS NULL";
			}
		}

		return array( 'sql' => 'WHERE ' . implode( ' AND ', $where ), 'values' => $values );
	}

	private function build_request_type_count_columns() {
		$columns = array();
		$values  = array();
		foreach ( $this->get_request_types() as $request_type ) {
			$alias = sanitize_key( $request_type );
			if ( '' === $alias ) {
				continue;
			}
			$columns[] = "SUM(CASE WHEN r.request_type = %s THEN 1 ELSE 0 END) AS `{$alias}`";
			$values[]  = $alias;
		}
		return array(
			'sql'    => $columns ? ",
				" . implode( ",
				", $columns ) : '',
			'values' => $values,
		);
	}

	private function get_top_group_value( $field, $filters, $match_field, $match_value ) {
		$allowed_fields = array( 'request_source', 'request_type', 'request_campaign', 'request_content' );
		if ( ! in_array( $field, $allowed_fields, true ) || ! in_array( $match_field, $allowed_fields, true ) ) {
			return '';
		}
		$item_filters = $filters;
		if ( '' === $match_value || null === $match_value ) {
			$extra_sql    = " AND (r.$match_field IS NULL OR r.$match_field = '')";
			$extra_values = array();
		} else {
			$extra_sql    = " AND r.$match_field = %s";
			$extra_values = array( sanitize_text_field( $match_value ) );
		}
		$where = $this->build_request_filters_where( $item_filters );
		$sql   = "SELECT COALESCE(NULLIF(r.$field, ''), '') AS item_value, COUNT(*) AS total FROM {$this->requests_table} r {$where['sql']} {$extra_sql} GROUP BY COALESCE(NULLIF(r.$field, ''), '') ORDER BY total DESC LIMIT 1";
		$row   = $this->get_row( $sql, array_merge( $where['values'], $extra_values ) );
		return $row ? $row['item_value'] : '';
	}

	private function resolve_date_range( $filters ) {
		$timezone = wp_timezone();
		$now      = new DateTimeImmutable( 'now', $timezone );
		$start    = null;
		$end      = null;

		switch ( $filters['date_range'] ) {
			case 'yesterday':
				$start = $now->modify( 'yesterday' )->setTime( 0, 0, 0 );
				$end   = $now->modify( 'yesterday' )->setTime( 23, 59, 59 );
				break;
			case 'last_7_days':
				$start = $now->modify( '-6 days' )->setTime( 0, 0, 0 );
				$end   = $now->setTime( 23, 59, 59 );
				break;
			case 'last_30_days':
				$start = $now->modify( '-29 days' )->setTime( 0, 0, 0 );
				$end   = $now->setTime( 23, 59, 59 );
				break;
			case 'current_month':
				$range = CRPCRM_Helpers::get_jalali_month_range();
				$start = new DateTimeImmutable( $range['start'], $timezone );
				$end   = new DateTimeImmutable( $range['end'], $timezone );
				break;
			case 'last_month':
				$range = CRPCRM_Helpers::get_jalali_month_range( -1 );
				$start = new DateTimeImmutable( $range['start'], $timezone );
				$end   = new DateTimeImmutable( $range['end'], $timezone );
				break;
			case 'custom':
				if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $filters['date_from'] ) ) {
					$start = new DateTimeImmutable( $filters['date_from'] . ' 00:00:00', $timezone );
				}
				if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $filters['date_to'] ) ) {
					$end = new DateTimeImmutable( $filters['date_to'] . ' 23:59:59', $timezone );
				}
				break;
			case 'today':
			default:
				$start = $now->setTime( 0, 0, 0 );
				$end   = $now->setTime( 23, 59, 59 );
				break;
		}

		return array(
			'start_date' => $start ? $start->format( 'Y-m-d H:i:s' ) : '',
			'end_date'   => $end ? $end->format( 'Y-m-d H:i:s' ) : '',
		);
	}

	private function get_results( $sql, $values = array() ) {
		global $wpdb;
		return $wpdb->get_results( empty( $values ) ? $sql : $wpdb->prepare( $sql, $values ), ARRAY_A );
	}

	private function get_row( $sql, $values = array() ) {
		global $wpdb;
		return $wpdb->get_row( empty( $values ) ? $sql : $wpdb->prepare( $sql, $values ), ARRAY_A );
	}

	private function get_var( $sql, $values = array() ) {
		global $wpdb;
		return absint( $wpdb->get_var( empty( $values ) ? $sql : $wpdb->prepare( $sql, $values ) ) );
	}
}

<?php
/**
 * Reports dashboard service.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_Reports_Service {
	private $reports_repository;
	private $request_repository;
	private $landing_manager;
	private $staff_repository;

	public function __construct( CRPCRM_Reports_Repository $reports_repository = null, CRPCRM_Request_Repository $request_repository = null, CRPCRM_Landing_Manager $landing_manager = null, CRPCRM_Staff_Repository $staff_repository = null ) {
		$this->reports_repository = $reports_repository ? $reports_repository : new CRPCRM_Reports_Repository();
		$this->request_repository  = $request_repository ? $request_repository : new CRPCRM_Request_Repository();
		$this->landing_manager     = $landing_manager ? $landing_manager : ( class_exists( 'CRPCRM_Landing_Manager' ) ? new CRPCRM_Landing_Manager() : null );
		$this->staff_repository    = $staff_repository ? $staff_repository : ( class_exists( 'CRPCRM_Staff_Repository' ) ? new CRPCRM_Staff_Repository() : null );
	}

	public function normalize_filters( $input ) {
		return $this->reports_repository->normalize_filters( $input );
	}

	public function get_dashboard_data( $filters, $pagination = array(), $options = array() ) {
		$filters    = $this->normalize_filters( $filters );
		$pagination = wp_parse_args(
			$pagination,
			array(
				'limit'  => 20,
				'offset' => 0,
			)
		);
		$options = wp_parse_args(
			$options,
			array(
				'include_staff'    => false,
				'include_landings' => false,
			)
		);

		$overview          = $this->get_overview_stats( $filters );
		$requests_trend    = $this->get_requests_trend( $filters );
		$source_breakdown   = $this->get_source_breakdown( $filters );
		$status_breakdown   = $this->get_status_breakdown( $filters );
		$staff_performance  = ! empty( $options['include_staff'] ) ? $this->get_staff_performance( $filters ) : array();
		$landing_performance = ! empty( $options['include_landings'] ) ? $this->get_landing_performance( $filters ) : array();
		$campaign_summary   = $this->get_campaign_summary( $filters, ! empty( $options['include_landings'] ) );
		$request_details    = $this->reports_repository->get_request_details( $filters, $pagination );
		$request_total      = $this->reports_repository->count_request_details( $filters );

		return array(
			'filters'            => $filters,
			'overview'           => $overview,
			'charts'             => array(
				'requestsTrend'    => $requests_trend,
				'sourceBreakdown'  => $source_breakdown,
				'statusBreakdown'  => $status_breakdown,
				'staffPerformance' => $this->build_staff_chart_data( $staff_performance ),
				'landingPerformance'=> $this->build_landing_chart_data( $landing_performance ),
			),
			'staff_performance'  => $staff_performance,
			'landing_performance'=> $landing_performance,
			'campaign_summary'   => $campaign_summary,
			'request_details'    => $request_details,
			'request_total'      => $request_total,
		);
	}

	public function get_overview_stats( $filters ) {
		$filters = $this->normalize_filters( $filters );
		$kpis    = $this->reports_repository->get_kpis( $filters );
		$status  = $this->get_status_breakdown( $filters );
		$status_map = array();
		foreach ( $status['rows'] as $row ) {
			$status_map[ $row['status'] ] = absint( $row['total'] );
		}

		return array(
			'total_requests'     => absint( $kpis['total'] ?? 0 ),
			'new_requests'       => absint( $status_map['new'] ?? 0 ),
			'in_progress'        => absint( $status_map['in_progress'] ?? 0 ),
			'no_answer'          => absint( $status_map['no_answer'] ?? 0 ),
			'follow_up'          => absint( $status_map['follow_up'] ?? 0 ),
			'won_requests'       => absint( $kpis['won'] ?? 0 ),
			'lost_requests'      => absint( $kpis['lost'] ?? 0 ),
			'invalid_requests'   => absint( $kpis['invalid'] ?? 0 ),
			'open_requests'      => absint( $kpis['open_total'] ?? 0 ),
			'closed_requests'    => absint( $kpis['closed_total'] ?? 0 ),
			'unassigned'         => absint( $kpis['unassigned'] ?? 0 ),
			'followups_today'    => absint( $kpis['followups_today'] ?? 0 ),
			'overdue_followups'  => absint( $kpis['overdue_followups'] ?? 0 ),
			'success_rate'       => isset( $kpis['success_rate'] ) ? $kpis['success_rate'] : null,
			'avg_first_action'   => isset( $kpis['avg_first_action_seconds'] ) ? $kpis['avg_first_action_seconds'] : null,
			'opened_total'       => absint( $status_map['new'] ?? 0 ) + absint( $status_map['in_progress'] ?? 0 ) + absint( $status_map['no_answer'] ?? 0 ) + absint( $status_map['follow_up'] ?? 0 ),
		);
	}

	public function get_requests_trend( $filters ) {
		global $wpdb;

		$filters = $this->normalize_filters( $filters );
		$where   = $this->reports_repository->build_request_filters_where( $filters );
		$requests_table = CRPCRM_DB::table( 'requests' );
		$sql     = "SELECT DATE(r.created_at) AS day, COUNT(*) AS total FROM {$requests_table} r {$where['sql']} GROUP BY DATE(r.created_at) ORDER BY day ASC";
		$rows    = $wpdb->get_results( empty( $where['values'] ) ? $sql : $wpdb->prepare( $sql, $where['values'] ), ARRAY_A );

		$series   = array();
		$start    = $this->parse_datetime( $filters['start_date'] ?? '' );
		$end      = $this->parse_datetime( $filters['end_date'] ?? '' );
		$map      = array();
		foreach ( $rows as $row ) {
			$map[ sanitize_text_field( $row['day'] ) ] = absint( $row['total'] );
		}

		if ( ! $start ) {
			$start = new DateTimeImmutable( 'today', wp_timezone() );
		}
		if ( ! $end ) {
			$end = $start;
		}
		$end = $end->setTime( 23, 59, 59 );
		$current = $start->setTime( 0, 0, 0 );
		$guard = 0;
		while ( $current <= $end && $guard < 120 ) {
			$key = $current->format( 'Y-m-d' );
			$series[] = array(
				'date'  => $key,
				'label' => CRPCRM_Helpers::format_jalali_date( $key ),
				'total' => absint( $map[ $key ] ?? 0 ),
			);
			$current = $current->modify( '+1 day' );
			$guard++;
		}

		return array(
			'type'   => 'line',
			'labels' => wp_list_pluck( $series, 'label' ),
			'datasets' => array(
				array(
					'label' => 'درخواست‌ها',
					'data'  => wp_list_pluck( $series, 'total' ),
					'borderColor' => '#2d5bff',
					'backgroundColor' => 'rgba(45, 91, 255, 0.12)',
					'fill'  => true,
				),
			),
			'rows'   => $series,
		);
	}

	public function get_source_breakdown( $filters ) {
		$filters = $this->normalize_filters( $filters );
		$rows    = $this->reports_repository->get_source_report( $filters );
		$labels  = array();
		$values  = array();
		$rows    = is_array( $rows ) ? $rows : array();
		$limit   = 8;
		$other   = 0;
		$colors  = array( '#2d5bff', '#5aa9fa', '#7bc6ff', '#6d7cff', '#52c7b8', '#8b5cf6', '#f59e0b', '#ef4444' );

		foreach ( $rows as $index => $row ) {
			$total = absint( $row['total'] ?? 0 );
			$label = CRPCRM_Helpers::get_source_label( $row['request_source'] ?? '' );
			if ( $index < $limit ) {
				$labels[] = $label;
				$values[] = $total;
			} else {
				$other += $total;
			}
		}
		if ( $other > 0 ) {
			$labels[] = 'سایر';
			$values[] = $other;
		}

		return array(
			'type'   => 'bar',
			'labels' => $labels,
			'datasets' => array(
				array(
					'label' => 'درخواست‌ها',
					'data'  => $values,
					'backgroundColor' => $colors,
					'borderColor' => $colors,
				),
			),
			'rows'   => $rows,
		);
	}

	public function get_status_breakdown( $filters ) {
		$filters = $this->normalize_filters( $filters );
		$rows    = $this->reports_repository->get_status_funnel( $filters );
		$rows    = is_array( $rows ) ? $rows : array();
		$map     = array(
			'new'         => array( 'label' => 'جدید', 'color' => '#2d5bff' ),
			'in_progress' => array( 'label' => 'در حال پیگیری', 'color' => '#5aa9fa' ),
			'no_answer'   => array( 'label' => 'پاسخ داده نشده', 'color' => '#f59e0b' ),
			'follow_up'   => array( 'label' => 'پیگیری بعدی', 'color' => '#8b5cf6' ),
			'won'         => array( 'label' => 'موفق', 'color' => '#10b981' ),
			'lost'        => array( 'label' => 'ناموفق', 'color' => '#ef4444' ),
			'invalid'     => array( 'label' => 'نامعتبر', 'color' => '#64748b' ),
		);
		$labels = array();
		$values = array();
		$colors = array();
		$normalized = array();
		foreach ( $rows as $row ) {
			$status = sanitize_key( $row['status'] ?? '' );
			$total  = absint( $row['total'] ?? 0 );
			if ( ! isset( $map[ $status ] ) ) {
				continue;
			}
			$labels[] = $map[ $status ]['label'];
			$values[] = $total;
			$colors[] = $map[ $status ]['color'];
			$normalized[] = array(
				'status' => $status,
				'label'  => $map[ $status ]['label'],
				'total'  => $total,
				'color'  => $map[ $status ]['color'],
			);
		}

		return array(
			'type'   => 'doughnut',
			'labels' => $labels,
			'datasets' => array(
				array(
					'label' => 'وضعیت درخواست‌ها',
					'data'  => $values,
					'backgroundColor' => $colors,
					'borderColor' => $colors,
				),
			),
			'rows'   => $normalized,
		);
	}

	public function get_staff_performance( $filters ) {
		$filters = $this->normalize_filters( $filters );
		if ( ! $this->staff_repository || ! CRPCRM_Feature_Manager::is_enabled( 'staff' ) ) {
			return array();
		}

		$rows = $this->reports_repository->get_agent_performance( $filters );
		usort(
			$rows,
			static function ( $left, $right ) {
				return absint( $right['current_owned'] ?? 0 ) <=> absint( $left['current_owned'] ?? 0 );
			}
		);

		return array_slice( $rows, 0, 8 );
	}

	public function get_landing_performance( $filters ) {
		global $wpdb;

		$filters = $this->normalize_filters( $filters );
		if ( ! $this->landing_manager || ! CRPCRM_Feature_Manager::is_enabled( 'landing_manager' ) ) {
			return array();
		}

		$where          = $this->reports_repository->build_request_filters_where( $filters );
		$requests_table = CRPCRM_DB::table( 'requests' );
		$sql            = "SELECT
				COALESCE(NULLIF(r.request_landing_id, 0), 0) AS landing_id,
				COALESCE(NULLIF(r.request_landing_slug, ''), '') AS landing_slug,
				COUNT(*) AS request_count,
				SUM(CASE WHEN r.status = 'won' THEN 1 ELSE 0 END) AS won_total,
				SUM(CASE WHEN r.status = 'lost' THEN 1 ELSE 0 END) AS lost_total,
				SUM(CASE WHEN r.status = 'invalid' THEN 1 ELSE 0 END) AS invalid_total,
				MAX(r.created_at) AS last_request_at
			FROM {$requests_table} r {$where['sql']}
			AND ((r.request_landing_id IS NOT NULL AND r.request_landing_id > 0) OR (r.request_landing_slug IS NOT NULL AND r.request_landing_slug <> ''))
			GROUP BY COALESCE(NULLIF(r.request_landing_id, 0), 0), COALESCE(NULLIF(r.request_landing_slug, ''), '')
			ORDER BY request_count DESC, last_request_at DESC
			LIMIT 8";
		$rows           = $wpdb->get_results( empty( $where['values'] ) ? $sql : $wpdb->prepare( $sql, $where['values'] ), ARRAY_A );
		$rows           = is_array( $rows ) ? $rows : array();

		$list        = $this->landing_manager->list_landings(
			array(
				'status' => '',
				'limit'  => 200,
				'offset' => 0,
			)
		);
		$items       = isset( $list['items'] ) && is_array( $list['items'] ) ? $list['items'] : array();
		$stats_map   = $this->landing_manager->get_landing_stats_for_items( $items );
		$items_by_id = array();
		$items_by_slug = array();
		foreach ( $items as $item ) {
			$landing_id = absint( $item['id'] ?? 0 );
			$landing_slug = sanitize_key( $item['slug'] ?? '' );
			if ( $landing_id ) {
				$items_by_id[ $landing_id ] = $item;
			}
			if ( '' !== $landing_slug ) {
				$items_by_slug[ $landing_slug ] = $item;
			}
		}

		$items_map = array();
		foreach ( $rows as $row ) {
			$landing_id   = absint( $row['landing_id'] ?? 0 );
			$landing_slug = sanitize_key( $row['landing_slug'] ?? '' );
			$item         = $landing_id && isset( $items_by_id[ $landing_id ] ) ? $items_by_id[ $landing_id ] : ( $landing_slug && isset( $items_by_slug[ $landing_slug ] ) ? $items_by_slug[ $landing_slug ] : array() );
			$mapped_id    = absint( $item['id'] ?? $landing_id );
			$stats        = $mapped_id && isset( $stats_map[ $mapped_id ] ) ? $stats_map[ $mapped_id ] : array();
			$clicks       = absint( $stats['valid_clicks'] ?? 0 );
			$request_count = absint( $row['request_count'] ?? 0 );
			$items_map[]  = array(
				'landing_id'      => $mapped_id,
				'title'           => sanitize_text_field( $item['title'] ?? $landing_slug ),
				'slug'            => sanitize_key( $item['slug'] ?? $landing_slug ),
				'status'          => sanitize_key( $item['status'] ?? '' ),
				'valid_clicks'    => $clicks,
				'request_count'   => $request_count,
				'conversion_rate' => $clicks > 0 ? round( ( $request_count / $clicks ) * 100, 1 ) : null,
				'last_click_at'   => ! empty( $stats['last_click_at'] ) ? sanitize_text_field( $stats['last_click_at'] ) : '',
				'last_request_at' => ! empty( $row['last_request_at'] ) ? sanitize_text_field( $row['last_request_at'] ) : '',
			);
		}

		return $items_map;
	}

	public function get_campaign_summary( $filters, $include_landing = false ) {
		global $wpdb;

		$filters = $this->normalize_filters( $filters );
		$where   = $this->reports_repository->build_request_filters_where( $filters );
		$requests_table = CRPCRM_DB::table( 'requests' );
		$sql     = "SELECT COALESCE(NULLIF(r.request_source, ''), 'direct') AS request_source,
			COALESCE(NULLIF(r.request_campaign, ''), '') AS request_campaign,
			COALESCE(NULLIF(r.request_landing_slug, ''), '') AS request_landing_slug,
			COALESCE(NULLIF(r.request_landing_id, 0), 0) AS request_landing_id,
			COUNT(*) AS request_count,
			SUM(CASE WHEN r.status = 'won' THEN 1 ELSE 0 END) AS won_total,
			SUM(CASE WHEN r.status = 'lost' THEN 1 ELSE 0 END) AS lost_total,
			SUM(CASE WHEN r.status = 'invalid' THEN 1 ELSE 0 END) AS invalid_total,
			MAX(r.created_at) AS last_request_at
		FROM {$requests_table} r {$where['sql']}
		GROUP BY COALESCE(NULLIF(r.request_source, ''), 'direct'),
			COALESCE(NULLIF(r.request_campaign, ''), ''),
			COALESCE(NULLIF(r.request_landing_slug, ''), ''),
			COALESCE(NULLIF(r.request_landing_id, 0), 0)
		ORDER BY request_count DESC, last_request_at DESC
		LIMIT 12";
		$rows  = $wpdb->get_results( empty( $where['values'] ) ? $sql : $wpdb->prepare( $sql, $where['values'] ), ARRAY_A );
		$items = array();

		$landing_map = array();
		if ( $include_landing && $this->landing_manager ) {
			$landing_ids = array();
			foreach ( $rows as $row ) {
				if ( ! empty( $row['request_landing_id'] ) ) {
					$landing_ids[] = absint( $row['request_landing_id'] );
				}
			}
			if ( $landing_ids ) {
				$landing_list = $this->landing_manager->list_landings(
					array(
						'status' => '',
						'limit'  => 200,
						'offset' => 0,
					)
				);
				foreach ( (array) $landing_list['items'] as $landing ) {
					$landing_id = absint( $landing['id'] ?? 0 );
					if ( ! $landing_id ) {
						continue;
					}
					$landing_map[ $landing_id ] = $landing;
				}
			}
		}

		$landing_stats = $include_landing && $this->landing_manager ? $this->landing_manager->get_landing_stats_for_ids( array_map( 'absint', wp_list_pluck( $rows, 'request_landing_id' ) ) ) : array();

		foreach ( $rows as $row ) {
			$landing_id    = absint( $row['request_landing_id'] ?? 0 );
			$landing_slug  = sanitize_key( $row['request_landing_slug'] ?? '' );
			$landing_title = '';
			if ( $landing_id && isset( $landing_map[ $landing_id ] ) ) {
				$landing_title = sanitize_text_field( $landing_map[ $landing_id ]['title'] ?? '' );
			} elseif ( $landing_slug ) {
				$landing_title = $landing_slug;
			}

			$stats = isset( $landing_stats[ $landing_id ] ) ? $landing_stats[ $landing_id ] : array();
			$clicks = $include_landing ? absint( $stats['valid_clicks'] ?? 0 ) : 0;
			$requests = absint( $row['request_count'] ?? 0 );
			$items[] = array(
				'request_source'    => sanitize_key( $row['request_source'] ?? 'direct' ),
				'request_campaign'  => sanitize_text_field( $row['request_campaign'] ?? '' ),
				'landing_id'       => $landing_id,
				'landing_slug'     => $landing_slug,
				'landing_title'    => $landing_title,
				'clicks'           => $clicks,
				'request_count'    => $requests,
				'won_total'        => absint( $row['won_total'] ?? 0 ),
				'lost_total'       => absint( $row['lost_total'] ?? 0 ),
				'invalid_total'    => absint( $row['invalid_total'] ?? 0 ),
				'conversion_rate'  => $requests > 0 ? round( ( absint( $row['won_total'] ?? 0 ) / $requests ) * 100, 1 ) : null,
				'last_request_at'  => ! empty( $row['last_request_at'] ) ? sanitize_text_field( $row['last_request_at'] ) : '',
			);
		}

		return $items;
	}

	private function build_staff_chart_data( array $staff_performance ) {
		if ( empty( $staff_performance ) ) {
			return array(
				'type' => 'bar',
				'labels' => array(),
				'datasets' => array(),
				'rows' => array(),
			);
		}

		$labels = wp_list_pluck( $staff_performance, 'display_name' );
		$current_owned = array_map( 'absint', wp_list_pluck( $staff_performance, 'current_owned' ) );
		$won = array_map( 'absint', wp_list_pluck( $staff_performance, 'won' ) );

		return array(
			'type' => 'bar',
			'labels' => $labels,
			'datasets' => array(
				array(
					'label' => 'در جریان',
					'data'  => $current_owned,
					'backgroundColor' => '#2d5bff',
				),
				array(
					'label' => 'موفق',
					'data'  => $won,
					'backgroundColor' => '#10b981',
				),
			),
			'rows' => $staff_performance,
		);
	}

	private function build_landing_chart_data( array $landing_performance ) {
		if ( empty( $landing_performance ) ) {
			return array(
				'type' => 'bar',
				'labels' => array(),
				'datasets' => array(),
				'rows' => array(),
			);
		}

		$labels = array();
		$requests = array();
		$clicks = array();

		foreach ( $landing_performance as $row ) {
			$labels[] = ! empty( $row['title'] ) ? $row['title'] : ( $row['slug'] ?? '' );
			$requests[] = absint( $row['request_count'] ?? 0 );
			$clicks[] = absint( $row['valid_clicks'] ?? 0 );
		}

		return array(
			'type' => 'bar',
			'labels' => $labels,
			'datasets' => array(
				array(
					'label' => 'درخواست',
					'data'  => $requests,
					'backgroundColor' => '#2d5bff',
				),
				array(
					'label' => 'کلیک معتبر',
					'data'  => $clicks,
					'backgroundColor' => '#8b5cf6',
				),
			),
			'rows' => $landing_performance,
		);
	}

	private function parse_datetime( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return null;
		}

		try {
			return new DateTimeImmutable( $value, wp_timezone() );
		} catch ( Exception $exception ) {
			return null;
		}
	}
}

<?php
/**
 * Admin page renderer.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_Admin_Pages {
	private $request_repository;
	private $customer_repository;
	private $workflow_service;
	private $reports_repository;
	private $staff_repository;
	private $sales_daily_stats_service;

	public function __construct() {
		$this->request_repository = new CRPCRM_Request_Repository();
		$this->customer_repository = new CRPCRM_Customer_Repository();
		$this->workflow_service    = new CRPCRM_Request_Workflow_Service( $this->request_repository );
		$this->reports_repository = new CRPCRM_Reports_Repository();
		$this->staff_repository = new CRPCRM_Staff_Repository();
		$this->sales_daily_stats_service = new CRPCRM_Sales_Daily_Stats_Service( $this->staff_repository );
		add_action( 'admin_post_crpcrm_claim_request', array( $this, 'handle_claim_request' ) );
		add_action( 'admin_post_crpcrm_change_owner', array( $this, 'handle_change_owner' ) );
		add_action( 'admin_post_crpcrm_release_owner', array( $this, 'handle_release_owner' ) );
		add_action( 'admin_post_crpcrm_delete_request', array( $this, 'handle_delete_request' ) );
		add_action( 'admin_post_crpcrm_delete_customer', array( $this, 'handle_delete_customer' ) );
		add_action( 'admin_post_crpcrm_add_sales_action', array( $this, 'handle_add_sales_action' ) );
		add_action( 'admin_post_crpcrm_create_manual_request', array( $this, 'handle_create_manual_request' ) );
		add_action( 'admin_post_crpcrm_reports_csv', array( $this, 'handle_reports_csv' ) );
		add_action( 'admin_post_crpcrm_staff_action', array( $this, 'handle_staff_action' ) );
	}

	public function dashboard() {
		CRPCRM_Logger::info( 'admin_dashboard_viewed', 'admin_dashboard_viewed', array( 'user_id' => get_current_user_id() ) );
		$this->render( 'dashboard.php', array( 'cards' => $this->get_admin_dashboard_cards() ) );
	}

	public function requests() {
		if ( ! CRPCRM_Request_Access_Service::can_access_admin_requests() ) {
			CRPCRM_Logger::warning( 'request_access_denied', 'request_access_denied', array( 'user_id' => get_current_user_id(), 'screen' => 'admin_requests' ) );
			$this->render_message( 'شما اجازه دسترسی به این بخش را ندارید.' );
			return;
		}

		if ( isset( $_GET['action'] ) && 'new' === sanitize_key( wp_unslash( $_GET['action'] ) ) ) {
			$this->render_manual_request_form();
			return;
		}

		$request_id = isset( $_GET['request_id'] ) ? absint( $_GET['request_id'] ) : 0;
		if ( $request_id ) {
			$this->render_request_detail( $request_id );
			return;
		}

		$this->render_request_list();
	}

	public function customers() {
		if ( ! CRPCRM_Request_Access_Service::can_view_all( get_current_user_id() ) ) {
			$this->render_message( 'شما اجازه دسترسی به این بخش را ندارید.' );
			return;
		}

		$page     = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$per_page = max( 5, absint( CRPCRM_Settings::get( 'requests_per_page', 20 ) ) );
		$filters  = $this->get_customer_filters();
		$args     = array_merge( $filters, array( 'limit' => $per_page, 'offset' => ( $page - 1 ) * $per_page, 'user_id' => get_current_user_id() ) );
		$customers = $this->customer_repository->list_for_admin( $args );
		$total     = $this->customer_repository->count_for_admin( $args );

		CRPCRM_Logger::info( 'admin_customer_list_viewed', 'admin_customer_list_viewed', array( 'user_id' => get_current_user_id(), 'filters' => $filters ) );

		$this->render(
			'customers.php',
			array(
				'mode'        => 'list',
				'customers'   => $customers,
				'filters'     => $filters,
				'page'        => $page,
				'per_page'    => $per_page,
				'total'       => $total,
				'total_pages' => max( 1, (int) ceil( $total / $per_page ) ),
			)
		);
	}

	public function customer_profile() {
		if ( ! CRPCRM_Request_Access_Service::can_access_admin_requests() ) {
			$this->render_message( 'شما اجازه دسترسی به این بخش را ندارید.' );
			return;
		}

		$customer_id = isset( $_GET['customer_id'] ) ? absint( $_GET['customer_id'] ) : 0;
		if ( ! $customer_id ) {
			CRPCRM_Logger::warning( 'admin_customer_profile_not_found', 'admin_customer_profile_not_found', array( 'user_id' => get_current_user_id() ) );
			$this->render_message( 'مشتری موردنظر یافت نشد.' );
			return;
		}

		$customer = $this->customer_repository->get_with_user( $customer_id );
		if ( ! $customer ) {
			CRPCRM_Logger::warning( 'admin_customer_profile_not_found', 'admin_customer_profile_not_found', array( 'customer_id' => $customer_id, 'user_id' => get_current_user_id() ) );
			$this->render_message( 'مشتری موردنظر یافت نشد.' );
			return;
		}

		if ( ! $this->customer_repository->user_can_view_customer( $customer_id, get_current_user_id() ) ) {
			CRPCRM_Logger::warning( 'admin_customer_profile_access_denied', 'admin_customer_profile_access_denied', array( 'customer_id' => $customer_id, 'user_id' => get_current_user_id() ) );
			$this->render_message( 'شما اجازه مشاهده پروفایل این مشتری را ندارید.' );
			return;
		}

		$attribution_repository = new CRPCRM_Customer_Attribution_Repository();
		CRPCRM_Logger::info( 'admin_customer_profile_viewed', 'admin_customer_profile_viewed', array( 'customer_id' => $customer_id, 'user_id' => get_current_user_id() ) );

		$this->render(
			'customers.php',
			array(
				'mode'               => 'profile',
				'customer'           => $customer,
				'stats'              => $this->customer_repository->get_customer_stats( $customer_id ),
				'requests'           => $this->customer_repository->get_customer_requests( $customer_id, array( 'limit' => 100, 'user_id' => get_current_user_id() ) ),
				'agents'             => $this->customer_repository->get_customer_agents( $customer_id ),
				'activities'         => $this->customer_repository->get_customer_recent_activities( $customer_id, 20 ),
				'attribution_events' => $attribution_repository->get_events_by_customer( $customer_id, 20 ),
				'return_request_id'  => isset( $_GET['return_request_id'] ) ? absint( $_GET['return_request_id'] ) : 0,
			)
		);
	}

	public function reports() {
		if ( ! current_user_can( 'crpcrm_view_reports' ) ) {
			CRPCRM_Logger::warning( 'reports_access_denied', 'reports_access_denied', array( 'user_id' => get_current_user_id(), 'screen' => 'admin_reports' ) );
			$this->render_message( 'شما اجازه دسترسی به گزارش‌ها را ندارید.' );
			return;
		}

		$page       = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$per_page   = max( 5, absint( CRPCRM_Settings::get( 'staff_items_per_page', 20 ) ) );
		$filters    = $this->reports_repository->normalize_filters( $_GET );
		$pagination = array( 'limit' => $per_page, 'offset' => ( $page - 1 ) * $per_page );

		if ( ! empty( $_GET ) && count( array_intersect( array_keys( $_GET ), array( 'date_range', 'request_type', 'source', 'campaign', 'content', 'status', 'owner_filter', 'workflow_filter' ) ) ) ) {
			CRPCRM_Logger::info( 'reports_filters_applied', 'reports_filters_applied', array( 'user_id' => get_current_user_id(), 'filters' => $filters ) );
		} else {
			CRPCRM_Logger::info( 'reports_viewed', 'reports_viewed', array( 'user_id' => get_current_user_id(), 'filters' => $filters ) );
		}

		$total = $this->reports_repository->count_request_details( $filters );
		$this->render(
			'reports.php',
			array(
				'filters'               => $filters,
				'kpis'                  => $this->reports_repository->get_kpis( $filters ),
				'source_report'         => $this->reports_repository->get_source_report( $filters ),
				'campaign_report'       => $this->reports_repository->get_campaign_report( $filters ),
				'content_report'        => $this->reports_repository->get_content_report( $filters ),
				'request_type_report'   => $this->reports_repository->get_request_type_report( $filters ),
				'source_type_matrix'    => $this->reports_repository->get_source_type_matrix( $filters ),
				'status_funnel'         => $this->reports_repository->get_status_funnel( $filters ),
				'agent_performance'     => $this->reports_repository->get_agent_performance( $filters ),
				'followup_report'       => $this->reports_repository->get_followup_report( $filters ),
				'close_reason_report'   => $this->reports_repository->get_close_reason_report( $filters ),
				'invalid_reason_report' => $this->reports_repository->get_invalid_reason_report( $filters ),
				'request_details'       => $this->reports_repository->get_request_details( $filters, $pagination ),
				'assignable_users'      => $this->get_assignable_users(),
				'page'                  => $page,
				'per_page'              => $per_page,
				'total'                 => $total,
				'total_pages'           => max( 1, (int) ceil( $total / $per_page ) ),
			)
		);
	}

	public function staff() {
		if ( 'yes' !== CRPCRM_Settings::get( 'staff_portal_enabled', 'yes' ) && ! current_user_can( 'manage_options' ) ) {
			CRPCRM_Logger::warning( 'staff_access_denied', 'staff_access_denied', array( 'user_id' => get_current_user_id(), 'reason' => 'disabled' ) );
			$this->render_message( 'پنل داخلی کارکنان در حال حاضر غیرفعال است.' );
			return;
		}

		if ( ! current_user_can( 'crpcrm_use_staff_portal' ) ) {
			CRPCRM_Logger::warning( 'staff_access_denied', 'staff_access_denied', array( 'user_id' => get_current_user_id(), 'screen' => 'staff_portal' ) );
			$this->render_message( 'شما اجازه دسترسی به پنل کارکنان را ندارید.' );
			return;
		}

		$tab        = isset( $_GET['staff_tab'] ) ? sanitize_key( wp_unslash( $_GET['staff_tab'] ) ) : 'dashboard';
		$valid_tabs = array( 'dashboard', 'daily_reports', 'requests', 'issues', 'tasks', 'announcements' );
		if ( ! in_array( $tab, $valid_tabs, true ) ) {
			$tab = 'dashboard';
		}

		CRPCRM_Logger::info( 'staff_dashboard_viewed', 'staff_dashboard_viewed', array( 'user_id' => get_current_user_id(), 'tab' => $tab ) );

		$user_id    = get_current_user_id();
		$can_manage = current_user_can( 'crpcrm_manage_staff_portal' );
		$today      = $this->staff_repository->today();
		$page       = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$per_page   = max( 5, absint( CRPCRM_Settings::get( 'staff_items_per_page', 20 ) ) );
		$notice     = isset( $_GET['crpcrm_notice'] ) ? sanitize_key( wp_unslash( $_GET['crpcrm_notice'] ) ) : '';
		$staff_users = $this->staff_repository->get_staff_users();

		$filters = $this->get_staff_filters( $tab, $can_manage, $user_id );
		$list_args = array_merge( $filters, array( 'limit' => $per_page, 'offset' => ( $page - 1 ) * $per_page ) );
		if ( ! $can_manage ) {
			if ( in_array( $tab, array( 'daily_reports', 'requests', 'issues' ), true ) ) {
				$list_args['user_id'] = $user_id;
			} elseif ( 'tasks' === $tab ) {
				$list_args['assigned_to'] = $user_id;
			}
		}

		$items = array();
		$total = 0;
		if ( 'daily_reports' === $tab ) {
			$items = $this->staff_repository->list_daily_reports_with_snapshot( $list_args );
			$total = $this->staff_repository->count_daily_reports( $list_args );
			if ( $can_manage ) {
				CRPCRM_Logger::info( 'sales_daily_snapshot_viewed', 'sales_daily_snapshot_viewed', array( 'viewer_user_id' => $user_id, 'report_count' => count( $items ) ) );
			}
		} elseif ( 'requests' === $tab ) {
			$items = $this->staff_repository->list_staff_requests( $list_args );
			$total = $this->staff_repository->count_staff_requests( $list_args );
		} elseif ( 'issues' === $tab ) {
			$items = $this->staff_repository->list_issues( $list_args );
			$total = $this->staff_repository->count_issues( $list_args );
		} elseif ( 'tasks' === $tab ) {
			$items = $this->staff_repository->list_tasks( $list_args );
			$total = $this->staff_repository->count_tasks( $list_args );
		} elseif ( 'announcements' === $tab ) {
			$items = $can_manage ? $this->staff_repository->list_announcements( $list_args ) : $this->staff_repository->get_announcements_for_user( $user_id );
			$total = count( $items );
		}

		$detail_item = $this->get_staff_detail_item( $tab, isset( $_GET['staff_item_id'] ) ? absint( $_GET['staff_item_id'] ) : 0, $user_id, $can_manage );

		$this->render(
			'staff.php',
			array(
				'tab'              => $tab,
				'can_manage'       => $can_manage,
				'today'            => $today,
				'notice'           => $notice,
				'filters'          => $filters,
				'items'            => $items,
				'page'             => $page,
				'per_page'         => $per_page,
				'total'            => $total,
				'total_pages'      => max( 1, (int) ceil( $total / $per_page ) ),
				'staff_users'      => $staff_users,
				'repository'       => $this->staff_repository,
				'today_report'     => $this->staff_repository->get_today_report( $user_id ),
				'sales_stats_service' => $this->sales_daily_stats_service,
				'current_sales_stats' => $this->sales_daily_stats_service->is_sales_user( $user_id ) ? $this->sales_daily_stats_service->get_stats_for_user( $user_id ) : array(),
				'is_sales_user'    => $this->sales_daily_stats_service->is_sales_user( $user_id ),
				'dashboard_counts' => $this->get_staff_dashboard_counts( $user_id, $can_manage, $staff_users ),
				'detail_item'      => $detail_item,
			)
		);
	}

	public function settings() {
		$settings            = CRPCRM_Settings::get();
		$active_tab          = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'portal';
		$tabs                = CRPCRM_Settings::tabs();
		if ( ! CRPCRM_Admin_Tools::can_maintain() && CRPCRM_Admin_Tools::can_export() ) {
			$tabs       = array( 'tools' => isset( $tabs['tools'] ) ? $tabs['tools'] : 'ابزارها و نگهداری' );
			$active_tab = 'tools';
		}
		if ( ! isset( $tabs[ $active_tab ] ) ) {
			$active_tab = 'portal';
		}
		if ( 'tools' === $active_tab && ! CRPCRM_Admin_Tools::can_export() && ! CRPCRM_Admin_Tools::can_maintain() ) {
			CRPCRM_Logger::warning( 'maintenance_access_denied', 'maintenance_access_denied', array( 'user_id' => get_current_user_id(), 'context' => 'tools_tab' ) );
			$this->render_message( 'شما اجازه دسترسی به این بخش را ندارید.' );
			return;
		}
		CRPCRM_Logger::info( 'settings_viewed', 'settings_viewed', array( 'user_id' => get_current_user_id(), 'tab' => $active_tab ) );
		if ( 'tools' === $active_tab && CRPCRM_Admin_Tools::can_maintain() ) {
			CRPCRM_Logger::info( 'health_check_viewed', 'health_check_viewed', array( 'user_id' => get_current_user_id() ) );
		}
		$attribution_service = class_exists( 'CRPCRM_Attribution_Service' ) ? new CRPCRM_Attribution_Service() : null;
		$current_attribution = $attribution_service ? $attribution_service->get_current_attribution() : null;
		$health_status       = 'tools' === $active_tab && CRPCRM_Admin_Tools::can_maintain() ? ( new CRPCRM_Health_Check_Service() )->get_status() : array();
		$staff_users         = 'tools' === $active_tab ? ( new CRPCRM_Staff_Repository() )->get_staff_users() : array();

		$this->render(
			'settings.php',
			array(
				'settings'            => $settings,
				'current_attribution' => $current_attribution,
				'tabs'                => $tabs,
				'active_tab'          => $active_tab,
				'role_configs'        => CRPCRM_Roles::get_roles(),
				'health_status'       => $health_status,
				'staff_users'         => $staff_users,
			)
		);
	}



	private function get_admin_dashboard_cards() {
		$user_id = get_current_user_id();
		$today   = wp_date( 'Y-m-d', current_time( 'timestamp' ) );
		$jalali_month = CRPCRM_Helpers::get_jalali_month_range();
		$month_start = substr( $jalali_month['start'], 0, 10 );
		$stale_hours = absint( CRPCRM_Settings::get( 'stale_request_hours', 48 ) );
		return array(
			array( 'title' => 'درخواست‌های امروز', 'count' => $this->request_repository->count_for_admin( array( 'user_id' => $user_id, 'date_from' => $today, 'date_to' => $today ) ), 'url' => admin_url( 'admin.php?page=crpcrm-requests&date_from=' . rawurlencode( $today ) . '&date_to=' . rawurlencode( $today ) ) ),
			array( 'title' => 'درخواست‌های بدون مسئول', 'count' => $this->request_repository->count_for_admin( array( 'user_id' => $user_id, 'owner_filter' => 'unassigned', 'status_group' => 'open' ) ), 'url' => admin_url( 'admin.php?page=crpcrm-requests&owner_filter=unassigned&status_group=open' ) ),
			array( 'title' => 'پیگیری‌های عقب‌افتاده', 'count' => $this->request_repository->count_for_admin( array( 'user_id' => $user_id, 'workflow_filter' => 'overdue_followups' ) ), 'url' => admin_url( 'admin.php?page=crpcrm-requests&workflow_filter=overdue_followups' ) ),
			array( 'title' => 'درخواست‌های موفق این ماه', 'count' => $this->request_repository->count_for_admin( array( 'user_id' => $user_id, 'status' => 'won', 'date_from' => $month_start ) ), 'url' => admin_url( 'admin.php?page=crpcrm-requests&status=won&date_from=' . rawurlencode( $month_start ) ) ),
			array( 'title' => 'درخواست‌های ناموفق این ماه', 'count' => $this->request_repository->count_for_admin( array( 'user_id' => $user_id, 'status' => 'lost', 'date_from' => $month_start ) ), 'url' => admin_url( 'admin.php?page=crpcrm-requests&status=lost&date_from=' . rawurlencode( $month_start ) ) ),
			array( 'title' => 'گزارش‌های روزانه نیازمند توجه', 'count' => $this->staff_repository->count_daily_reports( array( 'needs_manager_attention' => 1 ) ), 'url' => admin_url( 'admin.php?page=crpcrm-staff&staff_tab=daily_reports&needs_manager_attention=1' ) ),
			array( 'title' => 'درخواست‌های جدید از مدیریت', 'count' => $this->staff_repository->count_staff_requests( array( 'status' => 'new' ) ), 'url' => admin_url( 'admin.php?page=crpcrm-staff&staff_tab=requests&status=new' ) ),
			array( 'title' => 'وظایف عقب‌افتاده', 'count' => $this->staff_repository->count_overdue_tasks(), 'url' => admin_url( 'admin.php?page=crpcrm-staff&staff_tab=tasks&overdue=1' ) ),
		);
	}


	private function get_staff_filters( $tab, $can_manage, $user_id ) {
		$filters = array();
		if ( 'daily_reports' === $tab ) {
			$filters['report_date'] = isset( $_GET['report_date'] ) ? CRPCRM_Helpers::normalize_date_input( wp_unslash( $_GET['report_date'] ) ) : '';
			$filters['status'] = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
			$filters['needs_manager_attention'] = isset( $_GET['needs_manager_attention'] ) && '' !== $_GET['needs_manager_attention'] ? absint( $_GET['needs_manager_attention'] ) : '';
			if ( $can_manage ) {
				$filters['user_id'] = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : '';
			}
		} elseif ( 'requests' === $tab ) {
			$filters['category'] = isset( $_GET['category'] ) ? sanitize_key( wp_unslash( $_GET['category'] ) ) : '';
			$filters['priority'] = isset( $_GET['priority'] ) ? sanitize_key( wp_unslash( $_GET['priority'] ) ) : '';
			$filters['status'] = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
			if ( $can_manage ) { $filters['user_id'] = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : ''; }
		} elseif ( 'issues' === $tab ) {
			$filters['severity'] = isset( $_GET['severity'] ) ? sanitize_key( wp_unslash( $_GET['severity'] ) ) : '';
			$filters['status'] = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
			$filters['needs_manager_decision'] = isset( $_GET['needs_manager_decision'] ) && '' !== $_GET['needs_manager_decision'] ? absint( $_GET['needs_manager_decision'] ) : '';
			if ( $can_manage ) { $filters['user_id'] = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : ''; }
		} elseif ( 'tasks' === $tab ) {
			$filters['status'] = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
			$filters['priority'] = isset( $_GET['priority'] ) ? sanitize_key( wp_unslash( $_GET['priority'] ) ) : '';
			$filters['overdue'] = isset( $_GET['overdue'] ) ? absint( $_GET['overdue'] ) : '';
			if ( $can_manage ) { $filters['assigned_to'] = isset( $_GET['assigned_to'] ) ? absint( $_GET['assigned_to'] ) : ''; }
		} elseif ( 'announcements' === $tab ) {
			$filters['audience_type'] = isset( $_GET['audience_type'] ) ? sanitize_key( wp_unslash( $_GET['audience_type'] ) ) : '';
		}
		return $filters;
	}

	private function get_staff_dashboard_counts( $user_id, $can_manage, $staff_users ) {
		$today = $this->staff_repository->today();
		$reported = array();
		foreach ( $this->staff_repository->list_daily_reports( array( 'report_date' => $today, 'limit' => 500 ) ) as $report ) {
			$reported[] = absint( $report['user_id'] );
		}
		$staff_ids = array_map( 'absint', wp_list_pluck( $staff_users, 'ID' ) );
		$counts = array(
			'today_report' => $this->staff_repository->get_today_report( $user_id ) ? 'ثبت شده' : 'ثبت نشده',
			'open_requests' => $this->staff_repository->count_staff_requests( array( 'user_id' => $user_id, 'open' => 1 ) ),
			'open_issues' => $this->staff_repository->count_issues( array( 'user_id' => $user_id, 'open' => 1 ) ),
			'open_tasks' => $this->staff_repository->count_tasks( array( 'assigned_to' => $user_id, 'open' => 1 ) ),
			'overdue_tasks' => $this->staff_repository->count_overdue_tasks( $user_id ),
			'unread_announcements' => $this->staff_repository->count_unread_announcements_for_user( $user_id ),
			'reported_users' => array_values( array_intersect( $staff_ids, $reported ) ),
			'not_reported_users' => array_values( array_diff( $staff_ids, $reported ) ),
			'attention_reports' => $this->staff_repository->count_daily_reports( array( 'needs_manager_attention' => 1 ) ),
			'new_requests' => $this->staff_repository->count_staff_requests( array( 'status' => 'new' ) ),
			'all_open_issues' => $this->staff_repository->count_issues( array( 'open' => 1 ) ),
			'all_overdue_tasks' => $this->staff_repository->count_overdue_tasks(),
			'all_unread_announcements' => 0,
		);
		if ( $can_manage ) {
			foreach ( $staff_ids as $id ) { $counts['all_unread_announcements'] += $this->staff_repository->count_unread_announcements_for_user( $id ); }
			$counts['sales_today_summary'] = $this->get_sales_today_summary( $reported );
		} else {
			$counts['sales_today_summary'] = array();
		}
		return $counts;
	}

	private function get_sales_today_summary( $reported_user_ids ) {
		$summary = array();
		$reported_user_ids = array_map( 'absint', (array) $reported_user_ids );
		foreach ( $this->sales_daily_stats_service->get_sales_users() as $user ) {
			if ( in_array( 'customer', (array) $user->roles, true ) ) {
				continue;
			}
			$user_id = absint( $user->ID );
			$summary[] = array(
				'user_id' => $user_id,
				'display_name' => $user->display_name,
				'reported_today' => in_array( $user_id, $reported_user_ids, true ),
				'stats' => $this->sales_daily_stats_service->get_stats_for_user( $user_id ),
			);
		}
		return $summary;
	}

	public function handle_staff_action() {
		if ( ! current_user_can( 'crpcrm_use_staff_portal' ) ) {
			CRPCRM_Logger::warning( 'staff_access_denied', 'staff_access_denied', array( 'user_id' => get_current_user_id(), 'action' => 'post' ) );
			wp_die( esc_html( 'شما اجازه دسترسی به پنل کارکنان را ندارید.' ) );
		}
		$action = isset( $_POST['staff_action_type'] ) ? sanitize_key( wp_unslash( $_POST['staff_action_type'] ) ) : '';
		check_admin_referer( 'crpcrm_staff_' . $action );
		$user_id = get_current_user_id();
		$can_manage = current_user_can( 'crpcrm_manage_staff_portal' );
		$tab = 'dashboard';
		$notice = 'saved';

		if ( 'save_daily_report' === $action && ! $can_manage ) {
			$tab = 'daily_reports';
			$is_sales_user = $this->sales_daily_stats_service->is_sales_user( $user_id );
			$sales_comment = sanitize_textarea_field( wp_unslash( $_POST['sales_comment'] ?? '' ) );
			if ( $is_sales_user && '' === trim( $sales_comment ) ) {
				$this->staff_redirect( $tab, 'validation_error' );
			}

			$data = array(
				'user_id' => $user_id,
				'report_date' => $this->staff_repository->today(),
				'completed_work' => sanitize_textarea_field( wp_unslash( $_POST['completed_work'] ?? '' ) ),
				'unfinished_work' => sanitize_textarea_field( wp_unslash( $_POST['unfinished_work'] ?? '' ) ),
				'problems' => sanitize_textarea_field( wp_unslash( $_POST['problems'] ?? '' ) ),
				'tomorrow_plan' => sanitize_textarea_field( wp_unslash( $_POST['tomorrow_plan'] ?? '' ) ),
				'needs_manager_attention' => isset( $_POST['needs_manager_attention'] ) ? 1 : 0,
			);
			if ( $is_sales_user ) {
				$snapshot = $this->sales_daily_stats_service->build_snapshot( $user_id );
				$data['sales_comment'] = $sales_comment;
				$data['sales_crm_snapshot'] = $this->sales_daily_stats_service->snapshot_to_json( $snapshot );
			}

			$existing = $this->staff_repository->get_today_report( $user_id );
			if ( $existing ) {
				$this->staff_repository->update_daily_report( $existing['id'], $data );
				$report_id = absint( $existing['id'] );
				CRPCRM_Logger::info( 'staff_daily_report_updated', 'staff_daily_report_updated', array( 'user_id' => $user_id, 'report_id' => $report_id ) );
			} else {
				$report_id = $this->staff_repository->create_daily_report( $data );
				CRPCRM_Logger::info( 'staff_daily_report_created', 'staff_daily_report_created', array( 'user_id' => $user_id, 'report_id' => $report_id ) );
			}
			if ( $is_sales_user ) {
				CRPCRM_Logger::info( 'sales_daily_snapshot_saved', 'sales_daily_snapshot_saved', array( 'user_id' => $user_id, 'report_id' => $report_id, 'date' => $this->staff_repository->today() ) );
			}
		} elseif ( 'manage_daily_report' === $action && $can_manage ) {
			$tab = 'daily_reports';
			$report_id = absint( $_POST['report_id'] ?? 0 );
			$manager_action = sanitize_key( wp_unslash( $_POST['manager_action'] ?? '' ) );
			if ( 'seen' === $manager_action ) { $this->staff_repository->mark_report_seen( $report_id, $user_id ); }
			elseif ( 'closed' === $manager_action ) { $this->staff_repository->close_report( $report_id, $user_id ); }
			elseif ( 'responded' === $manager_action ) { $this->staff_repository->respond_to_report( $report_id, $user_id, wp_unslash( $_POST['manager_response'] ?? '' ) ); }
			CRPCRM_Logger::info( 'staff_daily_report_responded', 'staff_daily_report_responded', array( 'user_id' => $user_id, 'report_id' => $report_id, 'manager_action' => $manager_action ) );
		} elseif ( 'save_staff_request' === $action && ! $can_manage ) {
			$tab = 'requests';
			$id = absint( $_POST['request_id'] ?? 0 );
			$row = $id ? $this->staff_repository->get_staff_request( $id ) : null;
			if ( $row && ( absint( $row['user_id'] ) !== $user_id || 'new' !== $row['status'] ) ) { $this->staff_redirect( $tab, 'access_denied' ); }
			$data = array( 'user_id' => $user_id, 'category' => sanitize_key( wp_unslash( $_POST['category'] ?? '' ) ), 'title' => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ), 'description' => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ), 'priority' => sanitize_key( wp_unslash( $_POST['priority'] ?? 'normal' ) ) );
			if ( $id ) { $this->staff_repository->update_staff_request( $id, $data ); CRPCRM_Logger::info( 'staff_request_updated', 'staff_request_updated', array( 'user_id' => $user_id, 'request_id' => $id ) ); }
			else { $id = $this->staff_repository->create_staff_request( $data ); CRPCRM_Logger::info( 'staff_request_created', 'staff_request_created', array( 'user_id' => $user_id, 'request_id' => $id ) ); }
		} elseif ( 'manage_staff_request' === $action && $can_manage ) {
			$tab = 'requests'; $id = absint( $_POST['request_id'] ?? 0 );
			$this->staff_repository->update_staff_request_status( $id, sanitize_key( wp_unslash( $_POST['status'] ?? 'seen' ) ), wp_unslash( $_POST['manager_response'] ?? '' ) );
			CRPCRM_Logger::info( 'staff_request_status_changed', 'staff_request_status_changed', array( 'user_id' => $user_id, 'request_id' => $id ) );
		} elseif ( 'save_issue' === $action && ! $can_manage ) {
			$tab = 'issues'; $id = absint( $_POST['issue_id'] ?? 0 ); $row = $id ? $this->staff_repository->get_issue( $id ) : null;
			if ( $row && ( absint( $row['user_id'] ) !== $user_id || 'new' !== $row['status'] ) ) { $this->staff_redirect( $tab, 'access_denied' ); }
			$data = array( 'user_id' => $user_id, 'title' => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ), 'related_department' => sanitize_text_field( wp_unslash( $_POST['related_department'] ?? '' ) ), 'severity' => sanitize_key( wp_unslash( $_POST['severity'] ?? 'medium' ) ), 'description' => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ), 'suggested_solution' => sanitize_textarea_field( wp_unslash( $_POST['suggested_solution'] ?? '' ) ), 'needs_manager_decision' => isset( $_POST['needs_manager_decision'] ) ? 1 : 0 );
			if ( $id ) { $this->staff_repository->update_issue( $id, $data ); CRPCRM_Logger::info( 'staff_issue_updated', 'staff_issue_updated', array( 'user_id' => $user_id, 'issue_id' => $id ) ); }
			else { $id = $this->staff_repository->create_issue( $data ); CRPCRM_Logger::info( 'staff_issue_created', 'staff_issue_created', array( 'user_id' => $user_id, 'issue_id' => $id ) ); }
		} elseif ( 'manage_issue' === $action && $can_manage ) {
			$tab = 'issues'; $id = absint( $_POST['issue_id'] ?? 0 );
			$this->staff_repository->update_issue_status( $id, sanitize_key( wp_unslash( $_POST['status'] ?? 'seen' ) ), wp_unslash( $_POST['manager_response'] ?? '' ) );
			CRPCRM_Logger::info( 'staff_issue_status_changed', 'staff_issue_status_changed', array( 'user_id' => $user_id, 'issue_id' => $id ) );
		} elseif ( 'save_task' === $action && $can_manage ) {
			$tab = 'tasks'; $id = absint( $_POST['task_id'] ?? 0 );
			$assigned_to = absint( $_POST['assigned_to'] ?? 0 );
			if ( ! $this->is_staff_user_id( $assigned_to ) ) { $this->staff_redirect( $tab, 'access_denied' ); }
			$data = array( 'title' => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ), 'description' => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ), 'assigned_to' => $assigned_to, 'due_date' => CRPCRM_Helpers::normalize_date_input( wp_unslash( $_POST['due_date'] ?? '' ) ), 'priority' => sanitize_key( wp_unslash( $_POST['priority'] ?? 'normal' ) ), 'status' => sanitize_key( wp_unslash( $_POST['status'] ?? 'new' ) ), 'manager_note' => sanitize_textarea_field( wp_unslash( $_POST['manager_note'] ?? '' ) ), 'created_by' => $user_id );
			if ( $id ) { unset( $data['created_by'] ); $this->staff_repository->update_task( $id, $data ); CRPCRM_Logger::info( 'staff_task_updated', 'staff_task_updated', array( 'user_id' => $user_id, 'task_id' => $id ) ); }
			else { $id = $this->staff_repository->create_task( $data ); CRPCRM_Logger::info( 'staff_task_created', 'staff_task_created', array( 'user_id' => $user_id, 'task_id' => $id ) ); }
		} elseif ( 'update_task_status' === $action ) {
			$tab = 'tasks'; $id = absint( $_POST['task_id'] ?? 0 ); $task = $this->staff_repository->get_task( $id );
			if ( ! $task || ( ! $can_manage && absint( $task['assigned_to'] ) !== $user_id ) ) { $this->staff_redirect( $tab, 'access_denied' ); }
			$status = sanitize_key( wp_unslash( $_POST['status'] ?? 'new' ) );
			if ( ! $can_manage && 'cancelled' === $status ) { $this->staff_redirect( $tab, 'access_denied' ); }
			$this->staff_repository->update_task_status( $id, $status, $user_id, wp_unslash( $_POST['note'] ?? '' ) );
			CRPCRM_Logger::info( 'staff_task_status_changed', 'staff_task_status_changed', array( 'user_id' => $user_id, 'task_id' => $id, 'status' => $status ) );
		} elseif ( 'save_announcement' === $action && $can_manage ) {
			$tab = 'announcements';
			$audience_type = sanitize_key( wp_unslash( $_POST['audience_type'] ?? 'all' ) );
			$audience_data = array();
			if ( 'selected_roles' === $audience_type ) {
				$audience_data = array_intersect( array_map( 'sanitize_key', (array) ( $_POST['audience_roles'] ?? array() ) ), array( 'sales_agent', 'sales_manager', 'internal_employee', 'crm_admin', 'administrator' ) );
			} elseif ( 'selected_users' === $audience_type ) {
				foreach ( array_map( 'absint', (array) ( $_POST['audience_users'] ?? array() ) ) as $candidate_user_id ) {
					if ( $this->is_staff_user_id( $candidate_user_id ) ) { $audience_data[] = $candidate_user_id; }
				}
				$audience_data = array_values( array_unique( $audience_data ) );
			}
			$data = array( 'title' => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ), 'body' => sanitize_textarea_field( wp_unslash( $_POST['body'] ?? '' ) ), 'audience_type' => $audience_type, 'audience_data' => wp_json_encode( $audience_data ) );
			$id = absint( $_POST['announcement_id'] ?? 0 );
			if ( $id ) {
				$this->staff_repository->update_announcement( $id, $data );
				CRPCRM_Logger::info( 'staff_announcement_updated', 'staff_announcement_updated', array( 'user_id' => $user_id, 'announcement_id' => $id ) );
			} else {
				$data['created_by'] = $user_id;
				$id = $this->staff_repository->create_announcement( $data );
				CRPCRM_Logger::info( 'staff_announcement_created', 'staff_announcement_created', array( 'user_id' => $user_id, 'announcement_id' => $id ) );
			}
		} elseif ( 'delete_announcement' === $action && $can_manage ) {
			$tab = 'announcements';
			$id = absint( $_POST['announcement_id'] ?? 0 );
			$this->staff_repository->delete_announcement( $id );
			CRPCRM_Logger::info( 'staff_announcement_deleted', 'staff_announcement_deleted', array( 'user_id' => $user_id, 'announcement_id' => $id ) );
		} elseif ( 'mark_announcement_read' === $action ) {
			$tab = 'announcements'; $id = absint( $_POST['announcement_id'] ?? 0 ); $announcement = $this->staff_repository->get_announcement( $id );
			if ( ! $announcement || ! in_array( $user_id, $this->staff_repository->get_audience_user_ids( $announcement ), true ) ) { $this->staff_redirect( $tab, 'access_denied' ); }
			$this->staff_repository->mark_announcement_read( $id, $user_id );
			CRPCRM_Logger::info( 'staff_announcement_read', 'staff_announcement_read', array( 'user_id' => $user_id, 'announcement_id' => $id ) );
		} else {
			$notice = 'access_denied';
		}
		$this->staff_redirect( $tab, $notice );
	}

	private function get_staff_detail_item( $tab, $item_id, $user_id, $can_manage ) {
		if ( ! $item_id ) { return null; }
		$getters = array( 'daily_reports' => 'get_daily_report_with_snapshot', 'requests' => 'get_staff_request', 'issues' => 'get_issue', 'tasks' => 'get_task', 'announcements' => 'get_announcement' );
		if ( ! isset( $getters[ $tab ] ) ) { return null; }
		$item = $this->staff_repository->{$getters[ $tab ]}( $item_id );
		if ( ! $item || $can_manage ) { return $item; }
		if ( in_array( $tab, array( 'daily_reports', 'requests', 'issues' ), true ) && absint( $item['user_id'] ) === $user_id ) { return $item; }
		if ( 'tasks' === $tab && absint( $item['assigned_to'] ) === $user_id ) { return $item; }
		if ( 'announcements' === $tab && in_array( $user_id, $this->staff_repository->get_audience_user_ids( $item ), true ) ) { return $item; }
		return null;
	}

	private function is_staff_user_id( $user_id ) {
		foreach ( $this->staff_repository->get_staff_users() as $user ) {
			if ( absint( $user->ID ) === absint( $user_id ) ) { return true; }
		}
		return false;
	}

	private function staff_redirect( $tab, $notice ) {
		if ( 'access_denied' === $notice ) {
			CRPCRM_Logger::warning( 'staff_access_denied', 'staff_access_denied', array( 'user_id' => get_current_user_id(), 'tab' => $tab ) );
		}
		wp_safe_redirect( add_query_arg( array( 'page' => 'crpcrm-staff', 'staff_tab' => sanitize_key( $tab ), 'crpcrm_notice' => sanitize_key( $notice ) ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_reports_csv() {
		if ( ! current_user_can( 'crpcrm_view_reports' ) ) {
			CRPCRM_Logger::warning( 'reports_access_denied', 'reports_access_denied', array( 'user_id' => get_current_user_id(), 'screen' => 'reports_csv' ) );
			wp_die( esc_html( 'شما اجازه دسترسی به گزارش‌ها را ندارید.' ) );
		}
		check_admin_referer( 'crpcrm_reports_csv' );

		$filters  = $this->reports_repository->normalize_filters( $_GET );
		$requests = $this->reports_repository->get_request_details( $filters, array( 'limit' => 1000, 'offset' => 0 ) );

		$rows = array();
		foreach ( $requests as $request ) {
			$owner = ! empty( $request['owner_id'] ) ? get_userdata( absint( $request['owner_id'] ) ) : null;
			$rows[] = array(
				$request['request_code'],
				$request['created_at'],
				$request['customer_name'],
				$request['customer_phone'],
				CRPCRM_Helpers::get_request_type_label( $request['request_type'] ),
				$request['request_summary'],
				CRPCRM_Helpers::get_source_label( $request['request_source'] ),
				$request['request_campaign'],
				$request['request_content'],
				CRPCRM_Helpers::get_persian_status_label( $request['status'] ),
				$owner ? $owner->display_name : 'بدون مسئول',
				$request['last_activity_at'],
			);
		}

		CRPCRM_Logger::info( 'reports_csv_exported', 'reports_csv_exported', array( 'user_id' => get_current_user_id(), 'filters' => $filters, 'count' => count( $rows ) ) );

		$csv = new CRPCRM_CSV_Exporter();
		$csv->output_csv(
			'crpcrm-report-' . gmdate( 'Ymd-His' ) . '.csv',
			array( 'کد پیگیری', 'تاریخ ثبت', 'نام مشتری', 'موبایل', 'نوع درخواست', 'خلاصه درخواست', 'منبع', 'کمپین', 'محتوا', 'وضعیت', 'مسئول', 'آخرین فعالیت' ),
			$rows
		);
	}

	public function handle_claim_request() {
		$request_id = isset( $_POST['request_id'] ) ? absint( $_POST['request_id'] ) : 0;
		check_admin_referer( 'crpcrm_claim_request_' . $request_id );

		if ( ! current_user_can( 'crpcrm_claim_requests' ) ) {
			CRPCRM_Logger::warning( 'request_claim_failed', 'request_claim_failed', array( 'request_id' => $request_id, 'user_id' => get_current_user_id(), 'reason' => 'capability' ) );
			$this->redirect_to_request( $request_id, 'access_denied' );
		}

		$result = $this->request_repository->claim_request( $request_id, get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			CRPCRM_Logger::warning( 'request_claim_failed', 'request_claim_failed', array( 'request_id' => $request_id, 'user_id' => get_current_user_id(), 'reason' => $result->get_error_code() ) );
			$this->redirect_to_request( $request_id, 'already_owned' === $result->get_error_code() || 'request_already_owned' === $result->get_error_code() ? 'already_owned' : 'access_denied' );
		}

		CRPCRM_Logger::info( 'request_claimed', 'request_claimed', array( 'request_id' => $request_id, 'user_id' => get_current_user_id() ) );
		$this->redirect_to_request( $request_id, 'claimed' );
	}

	public function handle_change_owner() {
		$request_id = isset( $_POST['request_id'] ) ? absint( $_POST['request_id'] ) : 0;
		check_admin_referer( 'crpcrm_change_owner_' . $request_id );

		$new_owner_id = isset( $_POST['owner_id'] ) ? absint( $_POST['owner_id'] ) : 0;
		if ( ! CRPCRM_Request_Access_Service::can_manage_request( null, get_current_user_id() ) ) {
			CRPCRM_Logger::warning( 'request_owner_change_failed', 'request_owner_change_failed', array( 'request_id' => $request_id, 'user_id' => get_current_user_id(), 'reason' => 'capability' ) );
			$this->redirect_to_request( $request_id, 'access_denied' );
		}

		$result = $this->request_repository->change_owner( $request_id, $new_owner_id, get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			CRPCRM_Logger::warning( 'request_owner_change_failed', 'request_owner_change_failed', array( 'request_id' => $request_id, 'user_id' => get_current_user_id(), 'reason' => $result->get_error_code() ) );
			$this->redirect_to_request( $request_id, 'owner_change_failed' );
		}

		CRPCRM_Logger::info( 'request_owner_changed', 'request_owner_changed', array( 'request_id' => $request_id, 'user_id' => get_current_user_id(), 'owner_id' => $new_owner_id ) );
		$this->redirect_to_request( $request_id, 'owner_changed' );
	}

	public function handle_release_owner() {
		$request_id = isset( $_POST['request_id'] ) ? absint( $_POST['request_id'] ) : 0;
		check_admin_referer( 'crpcrm_release_owner_' . $request_id );

		if ( ! CRPCRM_Request_Access_Service::can_manage_request( null, get_current_user_id() ) ) {
			CRPCRM_Logger::warning( 'request_access_denied', 'request_access_denied', array( 'request_id' => $request_id, 'user_id' => get_current_user_id(), 'action' => 'release_owner' ) );
			$this->redirect_to_request( $request_id, 'access_denied' );
		}

		$result = $this->request_repository->release_owner( $request_id, get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			CRPCRM_Logger::warning( 'request_owner_change_failed', 'request_owner_change_failed', array( 'request_id' => $request_id, 'user_id' => get_current_user_id(), 'reason' => $result->get_error_code() ) );
			$this->redirect_to_request( $request_id, 'owner_release_failed' );
		}

		CRPCRM_Logger::info( 'request_owner_released', 'request_owner_released', array( 'request_id' => $request_id, 'user_id' => get_current_user_id() ) );
		$this->redirect_to_request( $request_id, 'owner_released' );
	}


	public function handle_delete_request() {
		$request_id = isset( $_POST['request_id'] ) ? absint( $_POST['request_id'] ) : 0;
		check_admin_referer( 'crpcrm_delete_request_' . $request_id );

		if ( ! CRPCRM_Request_Access_Service::can_delete_request( get_current_user_id() ) ) {
			CRPCRM_Logger::warning( 'request_delete_denied', 'request_delete_denied', array( 'request_id' => $request_id, 'user_id' => get_current_user_id() ) );
			$this->redirect_to_request_list( 'request_delete_denied' );
		}

		$request = $this->request_repository->get( $request_id );
		$result  = $this->request_repository->delete_permanently( $request_id );
		if ( is_wp_error( $result ) ) {
			CRPCRM_Logger::warning( 'request_delete_failed', 'request_delete_failed', array( 'request_id' => $request_id, 'user_id' => get_current_user_id(), 'reason' => $result->get_error_code() ) );
			$this->redirect_to_request_list( $result->get_error_code() );
		}

		CRPCRM_Logger::info( 'request_deleted_permanently', 'request_deleted_permanently', array( 'request_id' => $request_id, 'request_code' => isset( $request['request_code'] ) ? $request['request_code'] : '', 'user_id' => get_current_user_id() ) );
		$this->redirect_to_request_list( 'request_deleted' );
	}

	public function handle_delete_customer() {
		$customer_id = isset( $_POST['customer_id'] ) ? absint( $_POST['customer_id'] ) : 0;
		check_admin_referer( 'crpcrm_delete_customer_' . $customer_id );

		if ( ! current_user_can( 'manage_options' ) ) {
			CRPCRM_Logger::warning( 'customer_delete_denied', 'customer_delete_denied', array( 'customer_id' => $customer_id, 'user_id' => get_current_user_id() ) );
			$this->redirect_to_customer_list( 'customer_delete_denied' );
		}

		$customer = $this->customer_repository->get( $customer_id );
		$result   = $this->customer_repository->delete_permanently( $customer_id );
		if ( is_wp_error( $result ) ) {
			CRPCRM_Logger::warning( 'customer_delete_failed', 'customer_delete_failed', array( 'customer_id' => $customer_id, 'user_id' => get_current_user_id(), 'reason' => $result->get_error_code() ) );
			$this->redirect_to_customer_list( $result->get_error_code() );
		}

		CRPCRM_Logger::info( 'customer_deleted_permanently', 'customer_deleted_permanently', array( 'customer_id' => $customer_id, 'linked_user_id' => isset( $customer['user_id'] ) ? absint( $customer['user_id'] ) : 0, 'user_id' => get_current_user_id() ) );
		$this->redirect_to_customer_list( 'customer_deleted' );
	}

	public function handle_add_sales_action() {
		$request_id = isset( $_POST['request_id'] ) ? absint( $_POST['request_id'] ) : 0;
		check_admin_referer( 'crpcrm_add_sales_action_' . $request_id );

		$action_type = isset( $_POST['action_type'] ) ? sanitize_key( wp_unslash( $_POST['action_type'] ) ) : '';
		$data        = array(
			'note'              => isset( $_POST['action_note'] ) ? wp_unslash( $_POST['action_note'] ) : '',
			'next_follow_up_at' => isset( $_POST['next_follow_up_at'] ) ? wp_unslash( $_POST['next_follow_up_at'] ) : '',
			'close_reason'      => isset( $_POST['close_reason'] ) ? wp_unslash( $_POST['close_reason'] ) : '',
			'invalid_reason'    => isset( $_POST['invalid_reason'] ) ? wp_unslash( $_POST['invalid_reason'] ) : '',
		);

		$result = $this->workflow_service->add_sales_action( $request_id, $action_type, $data, get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			$this->redirect_to_request( $request_id, $result->get_error_code() );
		}

		$this->redirect_to_request( $request_id, 'sales_action_' . $action_type );
	}

	public function handle_create_manual_request() {
		check_admin_referer( 'crpcrm_create_manual_request' );
		if ( ! CRPCRM_Request_Access_Service::can_create_request() ) {
			$this->redirect_to_request_list( 'access_denied' );
		}

		$customer_mode = isset( $_POST['customer_mode'] ) ? sanitize_key( wp_unslash( $_POST['customer_mode'] ) ) : 'existing';
		$customer      = null;
		if ( 'new' === $customer_mode ) {
			$customer = $this->create_customer_from_manual_request( $_POST );
			if ( is_wp_error( $customer ) ) {
				$this->redirect_to_manual_request_form( $customer->get_error_code() );
			}
		} else {
			$customer_id = isset( $_POST['customer_id'] ) ? absint( $_POST['customer_id'] ) : 0;
			$customer    = $this->customer_repository->get( $customer_id );
			if ( ! $customer ) {
				$this->redirect_to_manual_request_form( 'manual_customer_required' );
			}
		}

		$allowed_types    = array_keys( CRPCRM_Request_Type_Registry::get_request_types() );
		$allowed_statuses = array( 'new', 'in_progress', 'no_answer', 'follow_up', 'won', 'lost', 'invalid' );
		$request_type     = isset( $_POST['request_type'] ) ? sanitize_key( wp_unslash( $_POST['request_type'] ) ) : '';
		$status           = isset( $_POST['request_status'] ) ? sanitize_key( wp_unslash( $_POST['request_status'] ) ) : 'new';
		if ( ! in_array( $request_type, $allowed_types, true ) ) {
			$this->redirect_to_manual_request_form( 'manual_request_type_invalid' );
		}
		if ( ! in_array( $status, $allowed_statuses, true ) ) {
			$status = 'new';
		}

		$owner_id = get_current_user_id();
		if ( CRPCRM_Request_Access_Service::can_manage_request() && isset( $_POST['owner_id'] ) ) {
			$requested_owner_id = absint( $_POST['owner_id'] );
			$assignable_ids     = wp_list_pluck( $this->get_assignable_users(), 'ID' );
			$owner_id           = $requested_owner_id && in_array( $requested_owner_id, array_map( 'absint', $assignable_ids ), true ) ? $requested_owner_id : 0;
		}
		$request_data = array();
		foreach ( array( 'desired_vehicle', 'part_name', 'vehicle_model', 'description', 'service_type', 'problem_description', 'manual_details' ) as $field ) {
			if ( ! empty( $_POST[ $field ] ) ) {
				$request_data[ $field ] = sanitize_textarea_field( wp_unslash( $_POST[ $field ] ) );
			}
		}
		$now          = CRPCRM_Helpers::current_datetime();
		$request_data = array_filter( $request_data, 'strlen' );
		$request_args = array(
				'customer_id'      => absint( $customer['id'] ),
				'user_id'          => absint( $customer['user_id'] ),
				'request_type'     => $request_type,
				'status'           => $status,
				'owner_id'         => $owner_id ? $owner_id : null,
				'first_assigned_at' => $owner_id ? $now : null,
				'request_title'    => ! empty( $_POST['request_title'] ) ? sanitize_text_field( wp_unslash( $_POST['request_title'] ) ) : CRPCRM_Helpers::get_request_type_label( $request_type ),
				'request_summary'  => isset( $_POST['request_summary'] ) ? sanitize_textarea_field( wp_unslash( $_POST['request_summary'] ) ) : '',
				'request_data'     => $request_data,
				'request_source'   => isset( $_POST['request_source'] ) ? sanitize_key( wp_unslash( $_POST['request_source'] ) ) : 'direct',
				'request_medium'   => isset( $_POST['request_medium'] ) ? sanitize_key( wp_unslash( $_POST['request_medium'] ) ) : '',
				'request_campaign' => isset( $_POST['request_campaign'] ) ? sanitize_text_field( wp_unslash( $_POST['request_campaign'] ) ) : '',
				'request_content'  => isset( $_POST['request_content'] ) ? sanitize_text_field( wp_unslash( $_POST['request_content'] ) ) : '',
				'request_term'     => isset( $_POST['request_term'] ) ? sanitize_text_field( wp_unslash( $_POST['request_term'] ) ) : '',
				'request_landing_page' => isset( $_POST['request_landing_page'] ) ? esc_url_raw( wp_unslash( $_POST['request_landing_page'] ) ) : '',
				'request_referrer' => isset( $_POST['request_referrer'] ) ? esc_url_raw( wp_unslash( $_POST['request_referrer'] ) ) : '',
				'next_follow_up_at' => isset( $_POST['next_follow_up_at'] ) ? CRPCRM_Helpers::normalize_datetime_input( wp_unslash( $_POST['next_follow_up_at'] ) ) : null,
				'last_action'      => 'manual_request_created',
			);
		if ( in_array( $status, array( 'won', 'lost', 'invalid' ), true ) ) {
			$request_args['closed_at'] = $now;
		}
		$request_id = $this->request_repository->create( $request_args );
		if ( ! $request_id ) {
			$this->redirect_to_manual_request_form( 'manual_request_failed' );
		}

		CRPCRM_Activity::add( $request_id, 'manual_request_created', array( 'customer_id' => absint( $customer['id'] ), 'actor_user_id' => get_current_user_id(), 'actor_type' => CRPCRM_Request_Access_Service::can_manage_request() ? 'sales_manager' : 'sales_agent', 'new_status' => $status, 'note' => 'درخواست به صورت دستی توسط کارشناس فروش ثبت شد.', 'is_internal' => 1 ) );
		CRPCRM_Logger::info( 'manual_request_created', 'request', array( 'request_id' => $request_id, 'customer_id' => absint( $customer['id'] ), 'actor_user_id' => get_current_user_id() ) );
		$this->redirect_to_request( $request_id, 'manual_request_created' );
	}

	private function create_customer_from_manual_request( $posted ) {
		$phone = isset( $posted['new_customer_phone'] ) ? CRPCRM_Helpers::normalize_iran_phone( wp_unslash( $posted['new_customer_phone'] ) ) : '';
		$name     = isset( $posted['new_customer_name'] ) ? trim( sanitize_text_field( wp_unslash( $posted['new_customer_name'] ) ) ) : '';
		$province = isset( $posted['new_customer_province'] ) ? trim( sanitize_text_field( wp_unslash( $posted['new_customer_province'] ) ) ) : '';
		$city     = isset( $posted['new_customer_city'] ) ? trim( sanitize_text_field( wp_unslash( $posted['new_customer_city'] ) ) ) : '';
		if ( '' === $name || '' === $province || '' === $city || ! CRPCRM_Helpers::is_valid_iran_phone_normalized( $phone ) ) {
			return new WP_Error( 'manual_customer_invalid', 'اطلاعات مشتری جدید معتبر نیست.' );
		}
		if ( $this->customer_repository->find_by_phone_normalized( $phone ) ) {
			return new WP_Error( 'manual_customer_exists', 'این شماره موبایل قبلاً ثبت شده است.' );
		}
		$user_id = wp_insert_user( array( 'user_login' => $phone, 'user_pass' => wp_generate_password( 32, true, true ), 'role' => 'customer', 'display_name' => $name ) );
		if ( is_wp_error( $user_id ) ) {
			return new WP_Error( 'manual_customer_failed', $user_id->get_error_message() );
		}
		update_user_meta( $user_id, 'crpcrm_phone_normalized', $phone );
		$source      = isset( $posted['new_customer_source'] ) ? sanitize_key( wp_unslash( $posted['new_customer_source'] ) ) : 'direct';
		$customer_id = $this->customer_repository->create( array( 'user_id' => $user_id, 'full_name' => $name, 'phone' => $phone, 'phone_normalized' => $phone, 'province' => $province, 'city' => $city, 'profile_completed' => 1, 'first_source' => $source, 'last_source' => $source, 'first_seen_at' => CRPCRM_Helpers::current_datetime(), 'last_seen_at' => CRPCRM_Helpers::current_datetime() ) );
		if ( ! $customer_id ) {
			wp_delete_user( $user_id );
			return new WP_Error( 'manual_customer_failed', 'ساخت مشتری انجام نشد.' );
		}
		return $this->customer_repository->get( $customer_id );
	}

	private function render_manual_request_form() {
		if ( ! CRPCRM_Request_Access_Service::can_create_request() ) {
			$this->render_message( 'شما اجازه ایجاد درخواست را ندارید.' );
			return;
		}
		$search = isset( $_GET['customer_search'] ) ? sanitize_text_field( wp_unslash( $_GET['customer_search'] ) ) : '';
		$this->render( 'request-create.php', array( 'customer_search' => $search, 'customer_results' => $this->customer_repository->search_for_manual_request( $search ), 'assignable_users' => CRPCRM_Request_Access_Service::can_manage_request() ? $this->get_assignable_users() : array( wp_get_current_user() ), 'can_manage' => CRPCRM_Request_Access_Service::can_manage_request() ) );
	}

	private function redirect_to_manual_request_form( $notice ) {
		wp_safe_redirect( crpcrm_admin_requests_url( array( 'action' => 'new', 'crpcrm_notice' => sanitize_key( $notice ) ) ) );
		exit;
	}

	private function render_request_list() {
		$page     = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$per_page = max( 5, absint( CRPCRM_Settings::get( 'requests_per_page', 20 ) ) );
		$filters  = $this->get_request_filters();
		$args     = array_merge( $filters, array( 'limit' => $per_page, 'offset' => ( $page - 1 ) * $per_page, 'user_id' => get_current_user_id() ) );
		$requests    = $this->request_repository->list_for_admin( $args );
		$total       = $this->request_repository->count_for_admin( $args );
		$stale_hours = absint( CRPCRM_Settings::get( 'stale_request_hours', 48 ) );
		$summary     = CRPCRM_Request_Access_Service::can_view_all( get_current_user_id() ) ? $this->request_repository->count_summary_for_manager( get_current_user_id(), $stale_hours ) : $this->request_repository->count_summary_for_user( get_current_user_id() );

		CRPCRM_Logger::info( 'admin_requests_list_viewed', 'admin_requests_list_viewed', array( 'user_id' => get_current_user_id(), 'filters' => $filters ) );

		$this->render(
			'requests.php',
			array(
				'mode'          => 'list',
				'requests'      => $requests,
				'filters'       => $filters,
				'page'          => $page,
				'per_page'      => $per_page,
				'total'         => $total,
				'total_pages'   => max( 1, (int) ceil( $total / $per_page ) ),
				'assignable_users' => $this->get_assignable_users(),
				'can_manage'    => CRPCRM_Request_Access_Service::can_manage_request(),
				'can_delete'    => CRPCRM_Request_Access_Service::can_delete_request(),
				'summary'       => $summary,
				'stale_hours'   => $stale_hours,
			)
		);
	}

	private function render_request_detail( $request_id ) {
		$request = $this->request_repository->get_with_customer( $request_id );
		if ( ! $request ) {
			$this->render_message( 'درخواست موردنظر یافت نشد.' );
			return;
		}
		if ( ! CRPCRM_Request_Access_Service::can_view_request( $request, get_current_user_id() ) ) {
			CRPCRM_Logger::warning( 'request_access_denied', 'request_access_denied', array( 'request_id' => $request_id, 'user_id' => get_current_user_id(), 'screen' => 'admin_request_detail' ) );
			$this->render_message( 'شما اجازه مشاهده این درخواست را ندارید.' );
			return;
		}

		$activity_repository = new CRPCRM_Activity_Repository();
		$activities          = $activity_repository->get_by_request_id( $request_id, 100, 0 );
		CRPCRM_Logger::info( 'admin_request_detail_viewed', 'admin_request_detail_viewed', array( 'request_id' => $request_id, 'user_id' => get_current_user_id() ) );

		$this->render(
			'requests.php',
			array(
				'mode'          => 'detail',
				'request'       => $request,
				'activities'    => $activities,
				'assignable_users' => $this->get_assignable_users(),
				'can_manage'    => CRPCRM_Request_Access_Service::can_manage_request( $request ),
				'can_delete'    => CRPCRM_Request_Access_Service::can_delete_request(),
				'can_claim'     => CRPCRM_Request_Access_Service::can_claim_request( $request ),
				'can_add_action'=> $this->workflow_service->can_add_action( $request, get_current_user_id(), 'call_answered' ) || $this->workflow_service->can_add_action( $request, get_current_user_id(), 'internal_note' ),
				'workflow'      => $this->workflow_service,
			)
		);
	}

	private function get_customer_filters() {
		return array(
			'first_source' => isset( $_GET['first_source'] ) ? sanitize_key( wp_unslash( $_GET['first_source'] ) ) : '',
			'last_source'  => isset( $_GET['last_source'] ) ? sanitize_key( wp_unslash( $_GET['last_source'] ) ) : '',
			'province'     => isset( $_GET['province'] ) ? sanitize_text_field( wp_unslash( $_GET['province'] ) ) : '',
			'city'         => isset( $_GET['city'] ) ? sanitize_text_field( wp_unslash( $_GET['city'] ) ) : '',
			'open_filter'  => isset( $_GET['open_filter'] ) ? sanitize_key( wp_unslash( $_GET['open_filter'] ) ) : '',
			'search'       => isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '',
		);
	}

	private function get_request_filters() {
		return array(
			'request_type' => isset( $_GET['request_type'] ) ? sanitize_key( wp_unslash( $_GET['request_type'] ) ) : '',
			'status'       => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '',
			'owner_filter' => isset( $_GET['owner_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['owner_filter'] ) ) : 'all',
			'source'       => isset( $_GET['source'] ) ? sanitize_key( wp_unslash( $_GET['source'] ) ) : '',
			'campaign'     => isset( $_GET['campaign'] ) ? sanitize_text_field( wp_unslash( $_GET['campaign'] ) ) : '',
			'date_from'    => isset( $_GET['date_from'] ) ? CRPCRM_Helpers::normalize_date_input( wp_unslash( $_GET['date_from'] ) ) : '',
			'date_to'      => isset( $_GET['date_to'] ) ? CRPCRM_Helpers::normalize_date_input( wp_unslash( $_GET['date_to'] ) ) : '',
			'search'       => isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '',
			'workflow_filter' => isset( $_GET['workflow_filter'] ) ? sanitize_key( wp_unslash( $_GET['workflow_filter'] ) ) : '',
			'status_group' => isset( $_GET['status_group'] ) ? sanitize_key( wp_unslash( $_GET['status_group'] ) ) : '',
		);
	}

	private function get_assignable_users() {
		return get_users(
			array(
				'role__in' => array( 'sales_agent', 'sales_manager' ),
				'orderby'  => 'display_name',
				'order'    => 'ASC',
				'fields'   => array( 'ID', 'display_name', 'user_login' ),
			)
		);
	}

	private function redirect_to_customer_list( $notice ) {
		wp_safe_redirect( add_query_arg( array( 'page' => 'crpcrm-customers', 'crpcrm_notice' => sanitize_key( $notice ) ), admin_url( 'admin.php' ) ) );
		exit;
	}

	private function redirect_to_request( $request_id, $notice ) {
		wp_safe_redirect( add_query_arg( array( 'page' => 'crpcrm-requests', 'request_id' => absint( $request_id ), 'crpcrm_notice' => sanitize_key( $notice ) ), admin_url( 'admin.php' ) ) );
		exit;
	}

	private function redirect_to_request_list( $notice ) {
		wp_safe_redirect( add_query_arg( array( 'page' => 'crpcrm-requests', 'crpcrm_notice' => sanitize_key( $notice ) ), admin_url( 'admin.php' ) ) );
		exit;
	}

	private function render_message( $message ) {
		echo '<div class="wrap crpcrm-admin-wrap" dir="rtl"><h1>' . esc_html( 'پرتال و CRM' ) . '</h1><div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div></div>';
	}

	private function render( $view, $vars = array() ) {
		$view_path = CRPCRM_PLUGIN_DIR . 'admin/views/' . $view;

		if ( ! file_exists( $view_path ) ) {
			wp_die( esc_html__( 'فایل نمایشی پیدا نشد.', 'customer-request-portal-crm' ) );
		}

		extract( $vars, EXTR_SKIP );
		include $view_path;
	}
}

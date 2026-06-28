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
	private $reports_service;
	private $staff_repository;
	private $sales_daily_stats_service;
	private $notification_service;

	public function __construct() {
		$this->request_repository = new CRPCRM_Request_Repository();
		$this->customer_repository = new CRPCRM_Customer_Repository();
		$this->workflow_service    = new CRPCRM_Request_Workflow_Service( $this->request_repository );
		$this->reports_repository = new CRPCRM_Reports_Repository();
		$this->reports_service = new CRPCRM_Reports_Service( $this->reports_repository, $this->request_repository, new CRPCRM_Landing_Manager(), new CRPCRM_Staff_Repository() );
		$this->staff_repository = new CRPCRM_Staff_Repository();
		$this->sales_daily_stats_service = new CRPCRM_Sales_Daily_Stats_Service( $this->staff_repository );
		$this->notification_service = new CRPCRM_Notification_Service();
		add_action( 'admin_post_crpcrm_claim_request', array( $this, 'handle_claim_request' ) );
		add_action( 'admin_post_crpcrm_change_owner', array( $this, 'handle_change_owner' ) );
		add_action( 'admin_post_crpcrm_transfer_request', array( $this, 'handle_transfer_request' ) );
		add_action( 'admin_post_crpcrm_release_owner', array( $this, 'handle_release_owner' ) );
		add_action( 'admin_post_crpcrm_delete_request', array( $this, 'handle_delete_request' ) );
		add_action( 'admin_post_crpcrm_delete_customer', array( $this, 'handle_delete_customer' ) );
		add_action( 'admin_post_crpcrm_add_sales_action', array( $this, 'handle_add_sales_action' ) );
		add_action( 'admin_post_crpcrm_create_manual_request', array( $this, 'handle_create_manual_request' ) );
		add_action( 'admin_post_crpcrm_reports_csv', array( $this, 'handle_reports_csv' ) );
		add_action( 'admin_post_crpcrm_staff_action', array( $this, 'handle_staff_action' ) );
		add_action( 'admin_post_crpcrm_staff_request_reply', array( $this, 'handle_request_reply' ) );
		add_action( 'wp_ajax_crpcrm_manual_request_customer_lookup', array( $this, 'handle_manual_request_customer_lookup' ) );
		add_action( 'wp_ajax_crpcrm_manual_request_customer_save', array( $this, 'handle_manual_request_customer_save' ) );
	}

	public function dashboard() {
		if ( ! CRPCRM_Feature_Manager::is_crm_ui_enabled() ) {
			$this->render_message( CRPCRM_Feature_Manager::disabled_message() );
			return;
		}

		CRPCRM_Logger::info( 'admin_dashboard_viewed', 'admin_dashboard_viewed', array( 'user_id' => get_current_user_id() ) );
		$this->render( 'dashboard.php', array( 'cards' => $this->get_admin_dashboard_cards() ) );
	}

	public function requests() {
		if ( ! CRPCRM_Feature_Manager::is_crm_ui_enabled() ) {
			$this->render_message( CRPCRM_Feature_Manager::disabled_message() );
			return;
		}

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
		if ( ! CRPCRM_Feature_Manager::is_crm_ui_enabled() ) {
			$this->render_message( CRPCRM_Feature_Manager::disabled_message() );
			return;
		}

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
		if ( ! CRPCRM_Feature_Manager::is_crm_ui_enabled() ) {
			$this->render_message( CRPCRM_Feature_Manager::disabled_message() );
			return;
		}

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
				'registration_fields'=> CRPCRM_Customer_Registration_Fields::get_ordered_fields(),
				'registration_values'=> CRPCRM_Customer_Registration_Fields::get_customer_values( $customer ),
				'stats'              => $this->customer_repository->get_customer_stats( $customer_id ),
				'requests'           => $this->customer_repository->get_customer_requests( $customer_id, array( 'limit' => 100, 'user_id' => get_current_user_id() ) ),
				'agents'             => CRPCRM_Feature_Manager::is_enabled( 'staff' ) ? $this->customer_repository->get_customer_agents( $customer_id ) : array(),
				'activities'         => $this->customer_repository->get_customer_recent_activities( $customer_id, 20 ),
				'attribution_events' => CRPCRM_Feature_Manager::is_enabled( 'tracking' ) ? $attribution_repository->get_events_by_customer( $customer_id, 20 ) : array(),
				'staff_customer_audit' => $this->get_manual_request_customer_audit( $customer ),
				'return_request_id'  => isset( $_GET['return_request_id'] ) ? absint( $_GET['return_request_id'] ) : 0,
			)
		);
	}

	public function reports() {
		if ( ! CRPCRM_Feature_Manager::is_enabled( 'reports' ) ) {
			$this->render_message( CRPCRM_Feature_Manager::disabled_message() );
			return;
		}
		if ( ! current_user_can( 'crpcrm_view_reports' ) ) {
			CRPCRM_Logger::warning( 'reports_access_denied', 'reports_access_denied', array( 'user_id' => get_current_user_id(), 'screen' => 'admin_reports' ) );
			$this->render_message( 'شما اجازه دسترسی به گزارش‌ها را ندارید.' );
			return;
		}

		$page       = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$per_page   = max( 5, absint( CRPCRM_Settings::get( 'staff_items_per_page', 20 ) ) );
		$filters    = $this->reports_repository->normalize_filters( $_GET );
		if ( ! CRPCRM_Feature_Manager::is_enabled( 'staff' ) ) {
			$filters['owner_filter'] = 'all';
		}
		$pagination = array( 'limit' => $per_page, 'offset' => ( $page - 1 ) * $per_page );

		if ( ! empty( $_GET ) && count( array_intersect( array_keys( $_GET ), array( 'date_range', 'date_from', 'date_to', 'request_type', 'source', 'campaign', 'content', 'landing', 'status', 'owner_filter', 'workflow_filter' ) ) ) ) {
			CRPCRM_Logger::info( 'reports_filters_applied', 'reports_filters_applied', array( 'user_id' => get_current_user_id(), 'filters' => $filters ) );
		} else {
			CRPCRM_Logger::info( 'reports_viewed', 'reports_viewed', array( 'user_id' => get_current_user_id(), 'filters' => $filters ) );
		}

		$dashboard = $this->reports_service->get_dashboard_data(
			$filters,
			$pagination,
			array(
				'include_staff'   => CRPCRM_Feature_Manager::is_enabled( 'staff' ),
				'include_landings'=> CRPCRM_Feature_Manager::is_enabled( 'landing_manager' ) && CRPCRM_Feature_Manager::is_enabled( 'tracking' ),
			)
		);
		$total = absint( $dashboard['request_total'] ?? 0 );
		wp_add_inline_script(
			'crpcrm-admin-reports',
			'window.crpcrmReportsData = ' . wp_json_encode(
				array(
					'charts' => isset( $dashboard['charts'] ) ? $dashboard['charts'] : array(),
				)
			) . ';',
			'before'
		);
		$this->render(
			'reports.php',
			array(
				'filters'        => $filters,
				'dashboard'      => $dashboard,
				'page'           => $page,
				'per_page'       => $per_page,
				'total'          => $total,
				'total_pages'    => max( 1, (int) ceil( $total / $per_page ) ),
				'landing_enabled'=> CRPCRM_Feature_Manager::is_enabled( 'landing_manager' ) && CRPCRM_Feature_Manager::is_enabled( 'tracking' ),
				'staff_enabled'  => CRPCRM_Feature_Manager::is_enabled( 'staff' ),
				'assignable_users' => $this->get_assignable_users(),
			)
		);
	}

	public function staff() {
		if ( ! CRPCRM_Feature_Manager::is_enabled( 'staff' ) || ( 'yes' !== CRPCRM_Settings::get( 'staff_portal_enabled', 'yes' ) && ! current_user_can( 'manage_options' ) ) ) {
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
		$valid_tabs = array( 'dashboard', 'daily_reports', 'requests', 'tasks', 'announcements' );
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

	public function notifications() {
		if ( ! CRPCRM_Notification_Service::is_enabled() ) {
			$this->render_message( CRPCRM_Feature_Manager::disabled_message() );
			return;
		}

		if ( ! CRPCRM_Notification_Service::current_user_can_view_notifications() ) {
			$this->render_message( 'شما اجازه دسترسی به این بخش را ندارید.' );
			return;
		}

		$user_id       = get_current_user_id();
		$page          = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$per_page      = 20;
		$total         = $this->notification_service->count_for_user( $user_id );
		$total_pages   = max( 1, (int) ceil( $total / $per_page ) );
		$page          = min( $page, $total_pages );
		$notifications = $this->notification_service->get_for_user(
			$user_id,
			array(
				'limit'  => $per_page,
				'offset' => ( $page - 1 ) * $per_page,
			)
		);
		if ( ! empty( $notifications ) ) {
			$notification_ids = array_values(
				array_filter(
					array_map(
						static function ( $notification ) {
							return absint( $notification['id'] ?? 0 );
						},
						(array) $notifications
					)
				)
			);
			if ( ! empty( $notification_ids ) ) {
				$this->notification_service->mark_seen( $notification_ids, $user_id );
			}
		}

		$this->render(
			'notifications.php',
			array(
				'notifications'  => $notifications,
				'unread_count'   => $this->notification_service->get_unread_count( $user_id ),
				'page'           => $page,
				'per_page'       => $per_page,
				'total'          => $total,
				'total_pages'    => $total_pages,
				'base_url'       => admin_url( 'admin.php?page=crpcrm-notifications' ),
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
			$active_tab = sanitize_key( array_key_first( $tabs ) );
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
		$today   = CRPCRM_Helpers::current_date();
		$jalali_month = CRPCRM_Helpers::get_jalali_month_range();
		$month_start = substr( $jalali_month['start'], 0, 10 );
		$stale_hours = absint( CRPCRM_Settings::get( 'stale_request_hours', 48 ) );
		$cards = array(
			array( 'title' => 'درخواست‌های امروز', 'count' => $this->request_repository->count_for_admin( array( 'user_id' => $user_id, 'date_from' => $today, 'date_to' => $today ) ), 'url' => admin_url( 'admin.php?page=crpcrm-requests&date_from=' . rawurlencode( $today ) . '&date_to=' . rawurlencode( $today ) ) ),
			array( 'title' => 'پیگیری‌های عقب‌افتاده', 'count' => $this->request_repository->count_for_admin( array( 'user_id' => $user_id, 'workflow_filter' => 'overdue_followups' ) ), 'url' => admin_url( 'admin.php?page=crpcrm-requests&workflow_filter=overdue_followups' ) ),
			array( 'title' => 'درخواست‌های موفق این ماه', 'count' => $this->request_repository->count_for_admin( array( 'user_id' => $user_id, 'status' => 'won', 'date_from' => $month_start ) ), 'url' => admin_url( 'admin.php?page=crpcrm-requests&status=won&date_from=' . rawurlencode( $month_start ) ) ),
			array( 'title' => 'درخواست‌های ناموفق این ماه', 'count' => $this->request_repository->count_for_admin( array( 'user_id' => $user_id, 'status' => 'lost', 'date_from' => $month_start ) ), 'url' => admin_url( 'admin.php?page=crpcrm-requests&status=lost&date_from=' . rawurlencode( $month_start ) ) ),
		);
		if ( CRPCRM_Feature_Manager::is_enabled( 'staff' ) ) {
			$cards[] = array( 'title' => 'درخواست‌های بدون مسئول', 'count' => $this->request_repository->count_for_admin( array( 'user_id' => $user_id, 'owner_filter' => 'unassigned', 'status_group' => 'open' ) ), 'url' => admin_url( 'admin.php?page=crpcrm-requests&owner_filter=unassigned&status_group=open' ) );
			$cards[] = array( 'title' => 'گزارش‌های روزانه نیازمند توجه', 'count' => $this->staff_repository->count_daily_reports( array( 'needs_manager_attention' => 1 ) ), 'url' => admin_url( 'admin.php?page=crpcrm-staff&staff_tab=daily_reports&needs_manager_attention=1' ) );
			$cards[] = array( 'title' => 'درخواست‌های جدید از مدیریت', 'count' => $this->staff_repository->count_staff_requests( array( 'status' => 'new' ) ), 'url' => admin_url( 'admin.php?page=crpcrm-staff&staff_tab=requests&status=new' ) );
			$cards[] = array( 'title' => 'وظایف عقب‌افتاده', 'count' => $this->staff_repository->count_overdue_tasks(), 'url' => admin_url( 'admin.php?page=crpcrm-staff&staff_tab=tasks&overdue=1' ) );
		}
		return $cards;
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
			'my_open_requests' => $this->request_repository->count_for_admin( array( 'user_id' => $user_id, 'owner_filter' => 'me', 'status_group' => 'open' ) ),
			'unassigned_requests' => $this->request_repository->count_for_admin( array( 'user_id' => $user_id, 'owner_filter' => 'unassigned', 'status_group' => 'open' ) ),
			'customer_replies' => $this->request_repository->count_for_admin( array( 'user_id' => $user_id, 'workflow_filter' => 'customer_replies', 'status_group' => 'open' ) ),
			'lead_follow_ups' => $this->request_repository->count_for_admin( array( 'user_id' => $user_id, 'request_type' => CRPCRM_System_Request_Types::LEAD_FOLLOW_UP, 'status_group' => 'open' ) ),
			'all_open_issues' => $this->staff_repository->count_issues( array( 'open' => 1 ) ),
			'all_overdue_tasks' => $this->staff_repository->count_overdue_tasks(),
			'all_unread_announcements' => 0,
		);
		if ( $can_manage ) {
			foreach ( $staff_ids as $id ) { $counts['all_unread_announcements'] += $this->staff_repository->count_unread_announcements_for_user( $id ); }
			$counts['sales_today_summary'] = $this->get_sales_today_summary( $reported );
		} else {
			$counts['unassigned_requests'] = 0;
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
		if ( ! CRPCRM_Feature_Manager::is_enabled( 'staff' ) ) {
			wp_die( esc_html( CRPCRM_Feature_Manager::disabled_message() ) );
		}
		if ( ! current_user_can( 'crpcrm_use_staff_portal' ) && ! current_user_can( 'manage_options' ) ) {
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
				$update_result = $this->staff_repository->update_daily_report( $existing['id'], $data );
				if ( is_wp_error( $update_result ) ) {
					$this->staff_redirect( $tab, 'db_error' );
				}
				$report_id = absint( $existing['id'] );
				CRPCRM_Logger::info( 'staff_daily_report_updated', 'staff_daily_report_updated', array( 'user_id' => $user_id, 'report_id' => $report_id ) );
			} else {
				$report_id = $this->staff_repository->create_daily_report( $data );
				if ( is_wp_error( $report_id ) || $report_id <= 0 ) {
					$this->staff_redirect( $tab, 'db_error' );
				}
				CRPCRM_Logger::info( 'staff_daily_report_created', 'staff_daily_report_created', array( 'user_id' => $user_id, 'report_id' => $report_id ) );
				$this->notification_service->notify_daily_report_created( $report_id, $user_id, $this->staff_repository->today() );
			}
			if ( $is_sales_user ) {
				CRPCRM_Logger::info( 'sales_daily_snapshot_saved', 'sales_daily_snapshot_saved', array( 'user_id' => $user_id, 'report_id' => $report_id, 'date' => $this->staff_repository->today() ) );
			}
		} elseif ( 'manage_daily_report' === $action && $can_manage ) {
			$tab = 'daily_reports';
			$report_id = absint( $_POST['report_id'] ?? 0 );
			if ( ! $this->staff_repository->get_daily_report( $report_id ) ) {
				$this->staff_redirect( $tab, 'access_denied' );
			}
			$manager_action = sanitize_key( wp_unslash( $_POST['manager_action'] ?? '' ) );
			if ( ! $this->is_valid_staff_choice( $manager_action, $this->get_staff_daily_report_actions() ) ) {
				$this->staff_redirect( $tab, 'invalid_staff_field' );
			}
			if ( 'seen' === $manager_action ) { $result = $this->staff_repository->mark_report_seen( $report_id, $user_id ); }
			elseif ( 'closed' === $manager_action ) { $result = $this->staff_repository->close_report( $report_id, $user_id ); }
			elseif ( 'responded' === $manager_action ) { $result = $this->staff_repository->respond_to_report( $report_id, $user_id, wp_unslash( $_POST['manager_response'] ?? '' ) ); }
			else { $result = true; }
			if ( empty( $result ) || is_wp_error( $result ) ) {
				$this->staff_redirect( $tab, 'db_error' );
			}
			$this->notify_about_daily_report_update( $report_id, $manager_action );
			CRPCRM_Logger::info( 'staff_daily_report_responded', 'staff_daily_report_responded', array( 'user_id' => $user_id, 'report_id' => $report_id, 'manager_action' => $manager_action ) );
		} elseif ( 'save_staff_request' === $action && ! $can_manage ) {
			$tab = 'requests';
			$id = absint( $_POST['request_id'] ?? 0 );
			$row = $id ? $this->staff_repository->get_staff_request( $id ) : null;
			if ( $id && ! $row ) { $this->staff_redirect( $tab, 'access_denied' ); }
			if ( $row && ( absint( $row['user_id'] ) !== $user_id || 'new' !== $row['status'] ) ) { $this->staff_redirect( $tab, 'access_denied' ); }
			$data = array( 'user_id' => $user_id, 'category' => sanitize_key( wp_unslash( $_POST['category'] ?? '' ) ), 'title' => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ), 'description' => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ), 'priority' => sanitize_key( wp_unslash( $_POST['priority'] ?? 'normal' ) ) );
			if ( ! $this->is_valid_required_staff_text( $data['title'] ) || ! $this->is_valid_required_staff_text( $data['description'] ) ) {
				$this->staff_redirect( $tab, 'validation_error' );
			}
			if ( ! $this->is_valid_staff_choice( $data['category'], $this->get_staff_request_categories() ) || ! $this->is_valid_staff_choice( $data['priority'], $this->get_staff_priorities() ) ) {
				$this->staff_redirect( $tab, 'invalid_staff_field' );
			}
			$request_attachments = $this->handle_staff_attachment_upload( 'request_attachment', 'staff_request' );
			if ( is_wp_error( $request_attachments ) ) {
				$this->staff_redirect( $tab, 'attachment_upload_failed' );
			}
			$existing_request_attachments = $row ? CRPCRM_Dynamic_Form_Renderer::get_uploaded_file_payload( $row['request_attachment'] ?? '' ) : array();
			$merged_request_attachments   = array_merge( $existing_request_attachments, $request_attachments );
			if ( ! empty( $request_attachments ) ) {
				$attachment_validation = $this->validate_staff_uploaded_files( $merged_request_attachments );
				if ( is_wp_error( $attachment_validation ) ) {
					$this->staff_redirect( $tab, 'attachment_limit_exceeded' );
				}
			}
			if ( $id ) {
				$update_result = $this->staff_repository->update_staff_request( $id, $data );
				if ( is_wp_error( $update_result ) ) {
					$this->staff_redirect( $tab, 'db_error' );
				}
				if ( ! empty( $request_attachments ) ) {
					$finalized_new_request_attachments = CRPCRM_Dynamic_Form_Renderer::finalize_record_uploaded_files_strict( $request_attachments, 'staff_request', $id, 'request_attachment', $user_id );
					if ( is_wp_error( $finalized_new_request_attachments ) ) {
						$this->staff_redirect( $tab, 'attachment_finalize_failed' );
					}
					$finalized_request_attachments = array_merge( $existing_request_attachments, $finalized_new_request_attachments );
					$attachment_result = $this->staff_repository->update_staff_request(
						$id,
						array(
							'request_attachment' => $finalized_request_attachments,
						)
					);
					if ( is_wp_error( $attachment_result ) ) {
						( new CRPCRM_Request_File_Cleanup_Service() )->cleanup_files( $finalized_new_request_attachments );
						$this->staff_redirect( $tab, 'attachment_save_partial' );
					}
				}
				CRPCRM_Logger::info( 'staff_request_updated', 'staff_request_updated', array( 'user_id' => $user_id, 'request_id' => $id ) );
			} else {
				$id = $this->staff_repository->create_staff_request( $data );
				if ( is_wp_error( $id ) || $id <= 0 ) {
					$this->staff_redirect( $tab, 'db_error' );
				}
				if ( ! empty( $request_attachments ) ) {
					$finalized_new_request_attachments = CRPCRM_Dynamic_Form_Renderer::finalize_record_uploaded_files_strict( $request_attachments, 'staff_request', $id, 'request_attachment', $user_id );
					if ( is_wp_error( $finalized_new_request_attachments ) ) {
						$this->staff_redirect( $tab, 'attachment_finalize_failed' );
					}
					$attachment_result = $this->staff_repository->update_staff_request(
						$id,
						array(
							'request_attachment' => $finalized_new_request_attachments,
						)
					);
					if ( is_wp_error( $attachment_result ) ) {
						( new CRPCRM_Request_File_Cleanup_Service() )->cleanup_files( $finalized_new_request_attachments );
						$this->staff_redirect( $tab, 'attachment_save_partial' );
					}
				}
				CRPCRM_Logger::info( 'staff_request_created', 'staff_request_created', array( 'user_id' => $user_id, 'request_id' => $id ) );
				$this->notification_service->notify_staff_request_created( $id, $user_id, $data['title'] );
			}
		} elseif ( 'manage_staff_request' === $action && $can_manage ) {
			$tab = 'requests'; $id = absint( $_POST['request_id'] ?? 0 );
			$manager_response = sanitize_textarea_field( wp_unslash( $_POST['manager_response'] ?? '' ) );
			$status = sanitize_key( wp_unslash( $_POST['status'] ?? 'seen' ) );
			if ( ! $this->is_valid_staff_choice( $status, $this->get_staff_request_statuses() ) ) {
				$this->staff_redirect( $tab, 'invalid_staff_field' );
			}
			$current_request = $this->staff_repository->get_staff_request( $id );
			if ( ! $current_request ) {
				$this->staff_redirect( $tab, 'access_denied' );
			}
			$manager_response_attachments = $this->handle_staff_attachment_upload( 'manager_response_attachment', 'staff_request_reply' );
			if ( is_wp_error( $manager_response_attachments ) ) {
				$this->staff_redirect( $tab, 'attachment_upload_failed' );
			}
			$existing_manager_attachments = $current_request ? CRPCRM_Dynamic_Form_Renderer::get_uploaded_file_payload( $current_request['manager_response_attachment'] ?? '' ) : array();
			$merged_manager_attachments   = array_merge( $existing_manager_attachments, $manager_response_attachments );
			if ( ! empty( $manager_response_attachments ) ) {
				$attachment_validation = $this->validate_staff_uploaded_files( $merged_manager_attachments );
				if ( is_wp_error( $attachment_validation ) ) {
					$this->staff_redirect( $tab, 'attachment_limit_exceeded' );
				}
			}
			$update_data = array(
				'status'           => $status,
				'manager_response' => $manager_response,
			);
			$update_result = $this->staff_repository->update_staff_request( $id, $update_data );
			if ( is_wp_error( $update_result ) ) {
				$this->staff_redirect( $tab, 'db_error' );
			}
			if ( ! empty( $manager_response_attachments ) ) {
				$finalized_new_manager_attachments = CRPCRM_Dynamic_Form_Renderer::finalize_record_uploaded_files_strict( $manager_response_attachments, 'staff_request', $id, 'manager_response_attachment', $user_id );
				if ( is_wp_error( $finalized_new_manager_attachments ) ) {
					$this->staff_redirect( $tab, 'attachment_finalize_failed' );
				}
				$finalized_manager_attachments = array_merge( $existing_manager_attachments, $finalized_new_manager_attachments );
				$attachment_result = $this->staff_repository->update_staff_request(
					$id,
					array(
						'manager_response_attachment' => $finalized_manager_attachments,
					)
				);
				if ( is_wp_error( $attachment_result ) ) {
					( new CRPCRM_Request_File_Cleanup_Service() )->cleanup_files( $finalized_new_manager_attachments );
					$this->staff_redirect( $tab, 'attachment_save_partial' );
				}
			}
			$this->notify_about_staff_request_update( $id, $manager_response, $manager_response_attachments );
			CRPCRM_Logger::info( 'staff_request_status_changed', 'staff_request_status_changed', array( 'user_id' => $user_id, 'request_id' => $id ) );
		} elseif ( 'save_issue' === $action && ! $can_manage ) {
			$tab = 'issues'; $id = absint( $_POST['issue_id'] ?? 0 ); $row = $id ? $this->staff_repository->get_issue( $id ) : null;
			if ( $id && ! $row ) { $this->staff_redirect( $tab, 'access_denied' ); }
			if ( $row && ( absint( $row['user_id'] ) !== $user_id || 'new' !== $row['status'] ) ) { $this->staff_redirect( $tab, 'access_denied' ); }
			$data = array( 'user_id' => $user_id, 'title' => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ), 'related_department' => sanitize_text_field( wp_unslash( $_POST['related_department'] ?? '' ) ), 'severity' => sanitize_key( wp_unslash( $_POST['severity'] ?? 'medium' ) ), 'description' => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ), 'suggested_solution' => sanitize_textarea_field( wp_unslash( $_POST['suggested_solution'] ?? '' ) ), 'needs_manager_decision' => isset( $_POST['needs_manager_decision'] ) ? 1 : 0 );
			if ( ! $this->is_valid_required_staff_text( $data['title'] ) || ! $this->is_valid_required_staff_text( $data['related_department'] ) || ! $this->is_valid_required_staff_text( $data['description'] ) ) {
				$this->staff_redirect( $tab, 'validation_error' );
			}
			if ( ! $this->is_valid_staff_choice( $data['severity'], $this->get_staff_issue_severities() ) ) {
				$this->staff_redirect( $tab, 'invalid_staff_field' );
			}
			if ( $id ) {
				$update_result = $this->staff_repository->update_issue( $id, $data );
				if ( is_wp_error( $update_result ) ) {
					$this->staff_redirect( $tab, 'db_error' );
				}
				CRPCRM_Logger::info( 'staff_issue_updated', 'staff_issue_updated', array( 'user_id' => $user_id, 'issue_id' => $id ) );
			} else {
				$id = $this->staff_repository->create_issue( $data );
				if ( is_wp_error( $id ) || $id <= 0 ) {
					$this->staff_redirect( $tab, 'db_error' );
				}
				CRPCRM_Logger::info( 'staff_issue_created', 'staff_issue_created', array( 'user_id' => $user_id, 'issue_id' => $id ) );
				$this->notification_service->notify_issue_created( $id, $user_id, $data['title'] );
			}
		} elseif ( 'manage_issue' === $action && $can_manage ) {
			$tab = 'issues'; $id = absint( $_POST['issue_id'] ?? 0 );
			if ( ! $this->staff_repository->get_issue( $id ) ) {
				$this->staff_redirect( $tab, 'access_denied' );
			}
			$status = sanitize_key( wp_unslash( $_POST['status'] ?? 'seen' ) );
			if ( ! $this->is_valid_staff_choice( $status, $this->get_staff_issue_statuses() ) ) {
				$this->staff_redirect( $tab, 'invalid_staff_field' );
			}
			$update_result = $this->staff_repository->update_issue_status( $id, $status, wp_unslash( $_POST['manager_response'] ?? '' ) );
			if ( is_wp_error( $update_result ) ) {
				$this->staff_redirect( $tab, 'db_error' );
			}
			$this->notify_about_issue_update( $id );
			CRPCRM_Logger::info( 'staff_issue_status_changed', 'staff_issue_status_changed', array( 'user_id' => $user_id, 'issue_id' => $id ) );
		} elseif ( 'save_task' === $action && $can_manage ) {
			$tab = 'tasks'; $id = absint( $_POST['task_id'] ?? 0 );
			if ( $id && ! $this->staff_repository->get_task( $id ) ) { $this->staff_redirect( $tab, 'access_denied' ); }
			$assigned_to = absint( $_POST['assigned_to'] ?? 0 );
			if ( ! $this->is_staff_user_id( $assigned_to ) ) { $this->staff_redirect( $tab, 'access_denied' ); }
			$data = array( 'title' => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ), 'description' => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ), 'assigned_to' => $assigned_to, 'due_date' => CRPCRM_Helpers::normalize_date_input( wp_unslash( $_POST['due_date'] ?? '' ) ), 'priority' => sanitize_key( wp_unslash( $_POST['priority'] ?? 'normal' ) ), 'status' => sanitize_key( wp_unslash( $_POST['status'] ?? 'new' ) ), 'manager_note' => sanitize_textarea_field( wp_unslash( $_POST['manager_note'] ?? '' ) ), 'created_by' => $user_id );
			if ( ! $this->is_valid_required_staff_text( $data['title'] ) || ! $this->is_valid_required_staff_text( $data['description'] ) ) {
				$this->staff_redirect( $tab, 'validation_error' );
			}
			if ( ! $this->is_valid_staff_choice( $data['priority'], $this->get_staff_priorities() ) || ! $this->is_valid_staff_choice( $data['status'], $this->get_staff_task_statuses() ) ) {
				$this->staff_redirect( $tab, 'invalid_staff_field' );
			}
			if ( $id ) {
				unset( $data['created_by'] );
				$update_result = $this->staff_repository->update_task( $id, $data );
				if ( is_wp_error( $update_result ) ) {
					$this->staff_redirect( $tab, 'db_error' );
				}
				CRPCRM_Logger::info( 'staff_task_updated', 'staff_task_updated', array( 'user_id' => $user_id, 'task_id' => $id ) );
			} else {
				$id = $this->staff_repository->create_task( $data );
				if ( is_wp_error( $id ) || $id <= 0 ) {
					$this->staff_redirect( $tab, 'db_error' );
				}
				CRPCRM_Logger::info( 'staff_task_created', 'staff_task_created', array( 'user_id' => $user_id, 'task_id' => $id ) );
			}
			$this->notify_about_task_assignment( $id, 0 === absint( $_POST['task_id'] ?? 0 ) );
		} elseif ( 'update_task_status' === $action ) {
			$tab = 'tasks'; $id = absint( $_POST['task_id'] ?? 0 ); $task = $this->staff_repository->get_task( $id );
			if ( ! $task || ( ! $can_manage && absint( $task['assigned_to'] ) !== $user_id ) ) { $this->staff_redirect( $tab, 'access_denied' ); }
			$status = sanitize_key( wp_unslash( $_POST['status'] ?? 'new' ) );
			if ( ! $can_manage && 'cancelled' === $status ) { $this->staff_redirect( $tab, 'access_denied' ); }
			$allowed_task_statuses = $can_manage ? $this->get_staff_task_statuses() : $this->get_staff_employee_task_statuses();
			if ( ! $this->is_valid_staff_choice( $status, $allowed_task_statuses ) ) {
				$this->staff_redirect( $tab, 'invalid_staff_field' );
			}
			$update_result = $this->staff_repository->update_task_status( $id, $status, $user_id, wp_unslash( $_POST['note'] ?? '' ) );
			if ( is_wp_error( $update_result ) ) {
				$this->staff_redirect( $tab, 'db_error' );
			}
			CRPCRM_Logger::info( 'staff_task_status_changed', 'staff_task_status_changed', array( 'user_id' => $user_id, 'task_id' => $id, 'status' => $status ) );
		} elseif ( 'save_announcement' === $action && $can_manage ) {
			$tab = 'announcements';
			$audience_type = sanitize_key( wp_unslash( $_POST['audience_type'] ?? 'all' ) );
			if ( ! $this->is_valid_staff_choice( $audience_type, $this->get_staff_audience_types() ) ) {
				$this->staff_redirect( $tab, 'invalid_staff_field' );
			}
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
			if ( ! $this->is_valid_required_staff_text( $data['title'] ) || ! $this->is_valid_required_staff_text( $data['body'] ) ) {
				$this->staff_redirect( $tab, 'validation_error' );
			}
			$id = absint( $_POST['announcement_id'] ?? 0 );
			if ( $id && ! $this->staff_repository->get_announcement( $id ) ) {
				$this->staff_redirect( $tab, 'access_denied' );
			}
			if ( $id ) {
				$update_result = $this->staff_repository->update_announcement( $id, $data );
				if ( is_wp_error( $update_result ) ) {
					$this->staff_redirect( $tab, 'db_error' );
				}
				CRPCRM_Logger::info( 'staff_announcement_updated', 'staff_announcement_updated', array( 'user_id' => $user_id, 'announcement_id' => $id ) );
			} else {
				$data['created_by'] = $user_id;
				$id = $this->staff_repository->create_announcement( $data );
				if ( is_wp_error( $id ) || $id <= 0 ) {
					$this->staff_redirect( $tab, 'db_error' );
				}
				CRPCRM_Logger::info( 'staff_announcement_created', 'staff_announcement_created', array( 'user_id' => $user_id, 'announcement_id' => $id ) );
				$this->notify_about_announcement( $id, $user_id );
			}
		} elseif ( 'delete_announcement' === $action && $can_manage ) {
			$tab = 'announcements';
			$id = absint( $_POST['announcement_id'] ?? 0 );
			$delete_result = $this->staff_repository->delete_announcement( $id );
			if ( is_wp_error( $delete_result ) ) {
				$this->staff_redirect( $tab, 'db_error' );
			}
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

	private function is_valid_staff_choice( $value, $allowed_values, $allow_empty = false ) {
		$value = sanitize_key( (string) $value );
		if ( '' === $value ) {
			return $allow_empty;
		}
		return in_array( $value, array_map( 'sanitize_key', $allowed_values ), true );
	}

	private function is_valid_required_staff_text( $value ) {
		return '' !== trim( (string) $value );
	}

	private function validate_staff_uploaded_files( $files ) {
		return CRPCRM_Dynamic_Form_Renderer::validate_uploaded_file_payload( $files );
	}

	private function get_staff_daily_report_actions() {
		return array( 'seen', 'responded', 'closed' );
	}

	private function get_staff_request_categories() {
		return array( 'manager_decision', 'purchase_or_supply', 'customer_problem', 'internal_process_problem', 'improvement_suggestion', 'error_or_bug_report', 'other' );
	}

	private function get_staff_priorities() {
		return array( 'low', 'normal', 'high' );
	}

	private function get_staff_request_statuses() {
		return array( 'new', 'seen', 'in_review', 'done', 'rejected' );
	}

	private function get_staff_issue_severities() {
		return array( 'low', 'medium', 'high' );
	}

	private function get_staff_issue_statuses() {
		return array( 'new', 'seen', 'in_review', 'resolved', 'rejected' );
	}

	private function get_staff_task_statuses() {
		return array( 'new', 'in_progress', 'done', 'blocked', 'cancelled' );
	}

	private function get_staff_employee_task_statuses() {
		return array( 'new', 'in_progress', 'done', 'blocked' );
	}

	private function get_staff_audience_types() {
		return array( 'all', 'selected_roles', 'selected_users' );
	}

	private function staff_redirect( $tab, $notice ) {
		if ( 'access_denied' === $notice ) {
			CRPCRM_Logger::warning( 'staff_access_denied', 'staff_access_denied', array( 'user_id' => get_current_user_id(), 'tab' => $tab ) );
		}
		wp_safe_redirect( add_query_arg( array( 'page' => 'crpcrm-staff', 'staff_tab' => sanitize_key( $tab ), 'crpcrm_notice' => sanitize_key( $notice ) ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_reports_csv() {
		if ( ! CRPCRM_Feature_Manager::is_enabled( 'reports' ) ) {
			wp_die( esc_html( CRPCRM_Feature_Manager::disabled_message() ) );
		}
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
				CRPCRM_Request_Type_Registry::get_label( $request['request_type'], $request ),
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
			'crpcrm-report-' . CRPCRM_Helpers::now()->format( 'Ymd-His' ) . '.csv',
			array( 'کد پیگیری', 'تاریخ ثبت', 'نام مشتری', 'موبایل', 'نوع درخواست', 'خلاصه درخواست', 'منبع', 'کمپین', 'محتوا', 'وضعیت', 'مسئول', 'آخرین فعالیت' ),
			$rows
		);
	}

	public function handle_claim_request() {
		$request_id = isset( $_POST['request_id'] ) ? absint( $_POST['request_id'] ) : 0;
		check_admin_referer( 'crpcrm_claim_request_' . $request_id );

		if ( ! CRPCRM_Feature_Manager::is_enabled( 'staff' ) ) {
			CRPCRM_Logger::warning( 'request_claim_failed', 'request_claim_failed', array( 'request_id' => $request_id, 'user_id' => get_current_user_id(), 'reason' => 'feature_disabled' ) );
			$this->redirect_to_request( $request_id, 'access_denied' );
		}

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

		if ( ! CRPCRM_Feature_Manager::is_enabled( 'staff' ) ) {
			CRPCRM_Logger::warning( 'request_owner_change_failed', 'request_owner_change_failed', array( 'request_id' => $request_id, 'user_id' => get_current_user_id(), 'reason' => 'feature_disabled' ) );
			$this->redirect_to_request( $request_id, 'access_denied' );
		}

		$request = $this->request_repository->get( $request_id );
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

		$updated_request = $this->request_repository->get( $request_id );
		$old_owner_id    = absint( $request['owner_id'] ?? 0 );
		$new_owner_id    = absint( $updated_request['owner_id'] ?? $new_owner_id );
		if ( $updated_request && $new_owner_id && $new_owner_id !== $old_owner_id ) {
			$this->notification_service->notify_request_assigned(
				$request_id,
				$updated_request['request_title'] ?? '',
				$new_owner_id,
				get_current_user_id(),
				$old_owner_id,
				$updated_request['request_code'] ?? '',
				admin_url( 'admin.php?page=crpcrm-requests&request_id=' . $request_id )
			);
		}

		CRPCRM_Logger::info( 'request_owner_changed', 'request_owner_changed', array( 'request_id' => $request_id, 'user_id' => get_current_user_id(), 'owner_id' => $new_owner_id ) );
		$this->redirect_to_request( $request_id, 'owner_changed' );
	}

	public function handle_transfer_request() {
		$request_id = isset( $_POST['request_id'] ) ? absint( $_POST['request_id'] ) : 0;
		check_admin_referer( 'crpcrm_transfer_request_' . $request_id );

		if ( ! CRPCRM_Feature_Manager::is_enabled( 'staff' ) ) {
			$this->redirect_to_request( $request_id, 'access_denied' );
		}

		$new_owner_id     = isset( $_POST['new_owner_id'] ) ? absint( $_POST['new_owner_id'] ) : 0;
		$transfer_reason  = isset( $_POST['transfer_reason'] ) ? wp_unslash( $_POST['transfer_reason'] ) : '';
		$current_user_id  = get_current_user_id();
		$original_request = $this->request_repository->get( $request_id );
		$old_owner_id     = absint( $original_request['owner_id'] ?? 0 );
		$result           = $this->request_repository->transfer_request( $request_id, $new_owner_id, $current_user_id, $transfer_reason );

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'                  => 'crpcrm-requests',
						'request_id'            => $request_id,
						'crpcrm_notice'         => 'owner_change_failed',
						'crpcrm_notice_type'    => 'error',
						'crpcrm_notice_message' => $result->get_error_message(),
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		$updated_request = $this->request_repository->get( $request_id );
		if ( $updated_request && $new_owner_id && $new_owner_id !== $old_owner_id ) {
			$this->notification_service->notify_request_assigned(
				$request_id,
				$updated_request['request_title'] ?? '',
				$new_owner_id,
				$current_user_id,
				$old_owner_id,
				$updated_request['request_code'] ?? '',
				admin_url( 'admin.php?page=crpcrm-requests&request_id=' . $request_id )
			);
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                  => 'crpcrm-requests',
					'request_id'            => $request_id,
					'crpcrm_notice'         => 'owner_changed',
					'crpcrm_notice_type'    => 'success',
					'crpcrm_notice_message' => 'درخواست با موفقیت منتقل شد.',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public function handle_release_owner() {
		$request_id = isset( $_POST['request_id'] ) ? absint( $_POST['request_id'] ) : 0;
		check_admin_referer( 'crpcrm_release_owner_' . $request_id );

		if ( ! CRPCRM_Feature_Manager::is_enabled( 'staff' ) ) {
			CRPCRM_Logger::warning( 'request_owner_change_failed', 'request_owner_change_failed', array( 'request_id' => $request_id, 'user_id' => get_current_user_id(), 'reason' => 'feature_disabled', 'action' => 'release_owner' ) );
			$this->redirect_to_request( $request_id, 'access_denied' );
		}

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
		);

		$result = $this->workflow_service->add_sales_action( $request_id, $action_type, $data, get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'                  => 'crpcrm-requests',
						'request_id'            => $request_id,
						'crpcrm_notice'         => 'sales_action_update_failed',
						'crpcrm_notice_type'    => 'error',
						'crpcrm_notice_message' => $result->get_error_message(),
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                  => 'crpcrm-requests',
					'request_id'            => $request_id,
					'crpcrm_notice'         => 'sales_action_added',
					'crpcrm_notice_type'    => 'success',
					'crpcrm_notice_message' => $this->workflow_service->get_success_message( $action_type ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public function handle_request_reply() {
		$request_id = isset( $_POST['request_id'] ) ? absint( $_POST['request_id'] ) : 0;
		check_admin_referer( 'crpcrm_staff_request_reply_' . $request_id, 'crpcrm_request_reply_nonce' );

		$request = $this->request_repository->get( $request_id );
		if ( ! $request ) {
			$this->redirect_to_request_list( 'request_not_found' );
		}

		$current_user_id = get_current_user_id();
		$can_reply       = CRPCRM_Request_Access_Service::can_manage_request( $request, $current_user_id ) || absint( $request['owner_id'] ) === $current_user_id;
		if ( ! $can_reply ) {
			CRPCRM_Logger::warning( 'request_reply_denied', 'request_reply_denied', array( 'request_id' => $request_id, 'user_id' => $current_user_id ) );
			$this->redirect_to_request( $request_id, 'request_reply_denied' );
		}

		$message = isset( $_POST['reply_message'] ) ? trim( sanitize_textarea_field( wp_unslash( $_POST['reply_message'] ) ) ) : '';
		if ( '' === $message ) {
			$this->redirect_to_request( $request_id, 'request_reply_empty' );
		}

		$actor_type = CRPCRM_Request_Access_Service::can_manage_request( $request, $current_user_id ) ? 'manager' : 'staff';
		$now        = CRPCRM_Helpers::current_datetime();

		$activity_repository = new CRPCRM_Activity_Repository();
		$activity_id         = $activity_repository->add_activity(
			$request_id,
			'manager' === $actor_type ? 'manager_reply' : 'staff_reply',
			array(
				'customer_id'   => absint( $request['customer_id'] ?? 0 ),
				'actor_user_id' => $current_user_id,
				'actor_type'    => $actor_type,
				'note'          => $message,
				'is_internal'   => 0,
			)
		);
		if ( false === $activity_id ) {
			CRPCRM_Logger::error( 'staff_request_reply_failed', 'request', array( 'request_id' => $request_id, 'user_id' => $current_user_id, 'reason' => 'activity_insert_failed' ) );
			$this->redirect_to_request( $request_id, 'request_reply_failed' );
		}

		$activity_update = $this->request_repository->update(
			$request_id,
			array(
				'last_action'      => 'manager' === $actor_type ? 'manager_reply' : 'staff_reply',
				'last_activity_at' => $now,
			)
		);
		if ( ! $activity_update ) {
			CRPCRM_Logger::error( 'staff_request_reply_last_activity_update_failed', 'request', array( 'request_id' => $request_id, 'user_id' => $current_user_id, 'actor_type' => $actor_type ) );
		}

		$this->notification_service->notify_reply_added(
			$request,
			$current_user_id,
			$message,
			admin_url( 'admin.php?page=crpcrm-requests&request_id=' . $request_id )
		);

		$this->redirect_to_request( $request_id, 'request_reply_added' );
	}

	public function handle_create_manual_request() {
		check_admin_referer( 'crpcrm_create_manual_request' );
		if ( ! CRPCRM_Request_Access_Service::can_create_request() ) {
			$this->redirect_to_request_list( 'access_denied' );
		}

		$form_id          = isset( $_POST['form_id'] ) ? sanitize_key( wp_unslash( $_POST['form_id'] ) ) : '';
		$form             = CRPCRM_Form_Registry::get_enabled_form( $form_id );
		if ( ! $form && empty( $form_id ) && ! empty( $_POST['request_type'] ) ) {
			$legacy_form = CRPCRM_Form_Registry::get_form_by_request_type( sanitize_key( wp_unslash( $_POST['request_type'] ) ) );
			$form        = $legacy_form ? CRPCRM_Form_Registry::get_enabled_form( $legacy_form['id'] ?? '' ) : null;
		}
		$allowed_statuses = array(
			CRPCRM_Request_Workflow_Service::STATUS_IN_PROGRESS,
			CRPCRM_Request_Workflow_Service::STATUS_FOLLOWED_UP,
			CRPCRM_Request_Workflow_Service::STATUS_FUTURE_FOLLOWUP,
		);
		$status           = isset( $_POST['request_status'] ) ? sanitize_key( wp_unslash( $_POST['request_status'] ) ) : CRPCRM_Request_Workflow_Service::get_default_status();
		if ( ! $form ) {
			$this->redirect_to_manual_request_form( 'manual_form_invalid', $this->build_manual_request_redirect_args( $_POST ) );
		}
		$request_type = sanitize_key( $form['request_type'] ?? '' );
		$request_type = $request_type ? $request_type : sanitize_key( $form['id'] ?? '' );
		if ( ! CRPCRM_Request_Type_Registry::is_registered( $request_type ) ) {
			$this->redirect_to_manual_request_form( 'manual_request_type_invalid', $this->build_manual_request_redirect_args( $_POST ) );
		}
		$validated = CRPCRM_Request_Forms::sanitize_and_validate( $form, $_POST, $_FILES );
		if ( ! empty( $validated['errors'] ) ) {
			$this->redirect_to_manual_request_form( 'manual_form_invalid', $this->build_manual_request_redirect_args( $_POST ) );
		}
		if ( ! in_array( $status, $allowed_statuses, true ) ) {
			$status = CRPCRM_Request_Workflow_Service::get_default_status();
		}

		$customer_phone = isset( $_POST['customer_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_phone'] ) ) : '';
		if ( ! CRPCRM_Helpers::is_valid_iran_phone_normalized( CRPCRM_Helpers::normalize_iran_phone( $customer_phone ) ) ) {
			$this->redirect_to_manual_request_form( 'manual_customer_invalid', $this->build_manual_request_redirect_args( $_POST, array( 'form_id' => $form_id ) ) );
		}

		$landing_value = isset( $_POST['request_source'] ) ? sanitize_text_field( wp_unslash( $_POST['request_source'] ) ) : '';
		$landing       = $this->get_manual_request_selected_landing( $landing_value );
		if ( is_wp_error( $landing ) ) {
			$this->redirect_to_manual_request_form( $landing->get_error_code(), $this->build_manual_request_redirect_args( $_POST, array( 'form_id' => $form_id ) ) );
		}

		if ( empty( $landing ) || empty( $landing['id'] ) ) {
			$this->redirect_to_manual_request_form( 'manual_landing_required', $this->build_manual_request_redirect_args( $_POST, array( 'form_id' => $form_id ) ) );
		}

		$customer_mode       = isset( $_POST['customer_mode'] ) ? sanitize_key( wp_unslash( $_POST['customer_mode'] ) ) : 'existing';
		$selected_customer_id = isset( $_POST['customer_id'] ) ? absint( $_POST['customer_id'] ) : 0;
		$customer            = null;
		$created_customer = false;
		$created_user     = false;
		if ( $selected_customer_id ) {
			$customer = $this->customer_repository->get( $selected_customer_id );
			if ( ! $customer ) {
				$this->redirect_to_manual_request_form( 'manual_customer_required', $this->build_manual_request_redirect_args( $_POST, array( 'form_id' => $form_id ) ) );
			}
		} elseif ( 'new' === $customer_mode ) {
			$this->redirect_to_manual_request_form( 'manual_customer_required', $this->build_manual_request_redirect_args( $_POST, array( 'form_id' => $form_id ) ) );
		} else {
			$customer_id = isset( $_POST['customer_id'] ) ? absint( $_POST['customer_id'] ) : 0;
			$customer    = $this->customer_repository->get( $customer_id );
			if ( ! $customer ) {
				$this->redirect_to_manual_request_form( 'manual_customer_required', $this->build_manual_request_redirect_args( $_POST, array( 'form_id' => $form_id ) ) );
			}
		}

		if ( empty( $customer ) || empty( $customer['id'] ) ) {
			$this->redirect_to_manual_request_form( 'manual_customer_failed', $this->build_manual_request_redirect_args( $_POST, array( 'form_id' => $form_id ) ) );
		}

		$owner_id           = get_current_user_id();
		$submitted_fields   = $validated['data'];
		$request_data       = $submitted_fields;
		$form_id            = sanitize_key( $form['id'] );
		$form_version       = sanitize_text_field( $form['version'] ?? '1' );
		$landing_attribution = $this->build_manual_request_landing_attribution( $landing );
		$request_data       = array_merge( $request_data, CRPCRM_Dynamic_Form_Renderer::get_submission_snapshot( $form ) );
		$request_data['_landing_attribution'] = $landing_attribution;
		$request_data['created_by_staff']     = 1;
		$request_data['staff_user_id']        = $owner_id;
		$request_data['created_via']          = 'staff_panel';

		$now          = CRPCRM_Helpers::current_datetime();
		$request_args = array(
				'customer_id'      => absint( $customer['id'] ),
				'user_id'          => absint( $customer['user_id'] ),
				'request_type'     => $request_type,
				'form_id'          => $form_id,
				'form_version'     => $form_version ? $form_version : '1',
				'status'           => $status,
				'owner_id'         => $owner_id ? $owner_id : null,
				'first_assigned_at' => $owner_id ? $now : null,
				'request_title'    => ! empty( $_POST['request_title'] ) ? sanitize_text_field( wp_unslash( $_POST['request_title'] ) ) : CRPCRM_Helpers::get_request_type_label( $request_type ),
				'request_summary'  => CRPCRM_Request_Forms::build_summary( $form, $submitted_fields ),
				'request_data'     => $request_data,
				'request_source'   => sanitize_key( $landing['source_code'] ?? $landing['slug'] ?? '' ),
				'request_medium'   => sanitize_text_field( $landing['medium_code'] ?? '' ),
				'request_campaign' => sanitize_text_field( $landing['campaign_code'] ?? '' ),
				'request_content'  => sanitize_text_field( $landing['content_code'] ?? '' ),
				'request_term'     => sanitize_text_field( $landing['term_code'] ?? '' ),
				'request_landing_id'   => absint( $landing['id'] ),
				'request_landing_slug' => sanitize_key( $landing['slug'] ?? '' ),
				'request_landing_page' => esc_url_raw( $landing['destination_url'] ?? '' ),
				'request_referrer'     => '',
				'last_action'      => 'manual_request_created',
			);
		if ( CRPCRM_Request_Workflow_Service::STATUS_FOLLOWED_UP === $status ) {
			$request_args['closed_at'] = $now;
		}
		$request_id = $this->request_repository->create(
			$request_args,
			array(
				'type' => 'manual_request_created',
				'args' => array(
					'customer_id'   => absint( $customer['id'] ),
					'actor_user_id' => get_current_user_id(),
					'actor_type'    => CRPCRM_Request_Access_Service::can_manage_request() ? 'sales_manager' : 'sales_agent',
					'new_status'    => $status,
					'note'          => 'درخواست به صورت دستی توسط کارشناس فروش ثبت شد.',
					'is_internal'   => 1,
				),
			)
		);
		if ( is_wp_error( $request_id ) || ! $request_id ) {
			$this->rollback_manual_request_customer( $customer, $created_customer, $created_user );
			$this->redirect_to_manual_request_form( 'manual_request_failed', $this->build_manual_request_redirect_args( $_POST, array( 'form_id' => $form_id ) ) );
		}

		$request = $this->request_repository->get( $request_id );
		$this->sync_manual_request_customer_attribution( $customer, $landing, $request_id );
		$this->maybe_record_manual_customer_created_activity( $request_id, $customer, get_current_user_id() );
		CRPCRM_Logger::info( 'manual_request_created', 'request', array( 'request_id' => $request_id, 'customer_id' => absint( $customer['id'] ), 'actor_user_id' => get_current_user_id() ) );
		$this->notify_sales_team_about_request( $request_id, $request_args['request_title'] );
		if ( $owner_id && $owner_id !== get_current_user_id() && $request ) {
			$this->notification_service->notify_request_assigned(
				$request_id,
				$request['request_title'] ?? $request_args['request_title'],
				$owner_id,
				get_current_user_id(),
				0,
				$request['request_code'] ?? '',
				admin_url( 'admin.php?page=crpcrm-requests&request_id=' . $request_id )
			);
		}
		$this->redirect_to_request( $request_id, 'manual_request_created' );
	}

	private function render_manual_request_form() {
		if ( ! CRPCRM_Request_Access_Service::can_create_request() ) {
			$this->render_message( 'شما اجازه ایجاد درخواست را ندارید.' );
			return;
		}
		$forms                  = CRPCRM_Form_Registry::get_enabled_forms();
		$selected_id            = isset( $_GET['form_id'] ) ? sanitize_key( wp_unslash( $_GET['form_id'] ) ) : ( $forms ? sanitize_key( array_key_first( $forms ) ) : '' );
		$selected_form          = CRPCRM_Form_Registry::get_enabled_form( $selected_id );
		$current_customer_phone = isset( $_GET['customer_phone'] ) ? sanitize_text_field( wp_unslash( $_GET['customer_phone'] ) ) : '';
		$current_customer_id    = isset( $_GET['customer_id'] ) ? absint( $_GET['customer_id'] ) : 0;
		$current_request_status = isset( $_GET['request_status'] ) ? sanitize_key( wp_unslash( $_GET['request_status'] ) ) : CRPCRM_Request_Workflow_Service::get_default_status();
		$current_request_source = isset( $_GET['request_source'] ) ? sanitize_text_field( wp_unslash( $_GET['request_source'] ) ) : '';
		$customer_context       = $this->build_manual_request_customer_context( $current_customer_phone, $current_customer_id );
		$active_landings        = $this->get_manual_request_active_landings();
		$this->render(
			'request-create.php',
			array(
				'forms'                  => $forms,
				'selected_form'          => $selected_form,
				'current_customer_phone' => $current_customer_phone,
				'current_customer_id'    => $current_customer_id,
				'current_request_status' => $current_request_status,
				'current_request_source' => $current_request_source,
				'customer_context'       => $customer_context,
				'active_landings'        => $active_landings,
			)
		);
	}

	private function redirect_to_manual_request_form( $notice, $args = array() ) {
		wp_safe_redirect( crpcrm_admin_requests_url( array_merge( array( 'action' => 'new', 'crpcrm_notice' => sanitize_key( $notice ) ), $args ) ) );
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
		$request_conversation = $activity_repository->get_conversation_by_request_id( $request_id, 100, 0 );
		CRPCRM_Logger::info( 'admin_request_detail_viewed', 'admin_request_detail_viewed', array( 'request_id' => $request_id, 'user_id' => get_current_user_id() ) );

		$this->render(
			'requests.php',
			array(
				'mode'          => 'detail',
				'request'       => $request,
				'activities'    => $activities,
				'request_conversation' => $request_conversation,
				'assignable_users' => $this->get_assignable_users(),
				'can_manage'    => CRPCRM_Request_Access_Service::can_manage_request( $request ),
				'can_delete'    => CRPCRM_Request_Access_Service::can_delete_request(),
				'can_claim'     => CRPCRM_Request_Access_Service::can_claim_request( $request ),
				'can_add_action'=> $this->workflow_service->can_add_action( $request, get_current_user_id(), CRPCRM_Request_Workflow_Service::STATUS_IN_PROGRESS ),
				'can_reply'     => CRPCRM_Request_Access_Service::can_manage_request( $request, get_current_user_id() ) || absint( $request['owner_id'] ) === get_current_user_id(),
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

	private function notify_about_daily_report_update( $report_id, $manager_action ) {
		$report = $this->staff_repository->get_daily_report( $report_id );
		if ( ! $report || empty( $report['user_id'] ) ) {
			return;
		}

		$this->notification_service->create_for_user(
			absint( $report['user_id'] ),
			'reply_added',
			'پاسخ جدید ثبت شد',
			'مدیر برای گزارش روزانه شما پاسخ یا تغییر جدید ثبت کرد.',
			admin_url( 'admin.php?page=crpcrm-staff&staff_tab=daily_reports&staff_item_id=' . absint( $report_id ) ),
			'daily_report',
			$report_id
		);
	}

	private function handle_staff_attachment_upload( $file_key, $context ) {
		$payload = wp_unslash( $_POST[ $file_key . '__uploaded' ] ?? '' );
		$file_group = isset( $_FILES[ $file_key ] ) && is_array( $_FILES[ $file_key ] ) ? $_FILES[ $file_key ] : null;
		$uploaded = CRPCRM_Dynamic_Form_Renderer::collect_submitted_uploaded_files( $payload, $file_group, $file_key );
		if ( is_wp_error( $uploaded ) ) {
			CRPCRM_Logger::error(
				'staff_attachment_upload_failed',
				'staff_attachment_upload_failed',
				array(
					'context'   => sanitize_key( $context ),
					'user_id'   => get_current_user_id(),
					'file_key'  => sanitize_key( $file_key ),
					'error'     => $uploaded->get_error_code(),
					'message'   => $uploaded->get_error_message(),
				)
			);
			return $uploaded;
		}

		return $uploaded;
	}

	private function notify_about_staff_request_update( $request_id, $manager_response = '', $manager_response_attachment = array() ) {
		$request = $this->staff_repository->get_staff_request( $request_id );
		if ( ! $request || empty( $request['user_id'] ) ) {
			return;
		}

		$title = 'پاسخ جدید ثبت شد';
		$request_title = ! empty( $request['title'] ) ? sanitize_text_field( $request['title'] ) : 'درخواست شما';
		$message = 'مدیر برای درخواست «' . $request_title . '» پاسخ یا تغییر جدید ثبت کرد.';
		$has_attachment = is_array( $manager_response_attachment ) && ! empty( $manager_response_attachment );
		if ( '' === trim( (string) $manager_response ) && $has_attachment ) {
			$title = 'فایل جدید برای درخواست شما ارسال شد';
			$message = 'مدیر یک فایل جدید برای درخواست «' . $request_title . '» ارسال کرد.';
		}

		$this->notification_service->create_for_user(
			absint( $request['user_id'] ),
			'reply_added',
			$title,
			$message,
			admin_url( 'admin.php?page=crpcrm-staff&staff_tab=requests&staff_item_id=' . absint( $request_id ) ),
			'staff_request',
			$request_id
		);
	}

	private function notify_about_issue_update( $issue_id ) {
		$issue = $this->staff_repository->get_issue( $issue_id );
		if ( ! $issue || empty( $issue['user_id'] ) ) {
			return;
		}

		$this->notification_service->create_for_user(
			absint( $issue['user_id'] ),
			'reply_added',
			'پاسخ جدید ثبت شد',
			'مدیر برای مورد ثبت‌شده شما پاسخ یا تغییر جدید ثبت کرد.',
			admin_url( 'admin.php?page=crpcrm-staff&staff_tab=issues&staff_item_id=' . absint( $issue_id ) ),
			'issue',
			$issue_id
		);
	}

	private function notify_about_task_assignment( $task_id, $is_new ) {
		$task = $this->staff_repository->get_task( $task_id );
		if ( ! $task || empty( $task['assigned_to'] ) ) {
			return;
		}

		$this->notification_service->create_for_user(
			absint( $task['assigned_to'] ),
			$is_new ? 'task_created' : 'task_updated',
			$is_new ? 'وظیفه جدید برای شما ثبت شد' : 'وظیفه شما به‌روزرسانی شد',
			sanitize_text_field( $task['title'] ),
			admin_url( 'admin.php?page=crpcrm-staff&staff_tab=tasks&staff_item_id=' . absint( $task_id ) ),
			'task',
			$task_id
		);
	}

	private function notify_about_announcement( $announcement_id, $actor_user_id = 0 ) {
		$announcement = $this->staff_repository->get_announcement( $announcement_id );
		if ( ! $announcement ) {
			return;
		}

		$recipients = array_diff( $this->staff_repository->get_audience_user_ids( $announcement ), $actor_user_id ? array( absint( $actor_user_id ) ) : array() );
		$this->notification_service->notify_announcement_created(
			$announcement_id,
			$recipients,
			$announcement['title'] ?? ''
		);
	}

	private function notify_sales_team_about_request( $request_id, $request_title = '' ) {
		$request = $this->request_repository->get( $request_id );
		$this->notification_service->notify_new_request(
			$request_id,
			$request_title,
			$request && ! empty( $request['request_code'] ) ? $request['request_code'] : '',
			get_current_user_id(),
			admin_url( 'admin.php?page=crpcrm-requests&request_id=' . absint( $request_id ) )
		);
	}

	private function get_assignable_users() {
		if ( ! CRPCRM_Feature_Manager::is_enabled( 'staff' ) ) {
			return array();
		}

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

	private function build_manual_request_redirect_args( $posted = array(), $args = array() ) {
		$redirect_args = array();
		$customer_mode = isset( $args['customer_mode'] ) ? sanitize_key( $args['customer_mode'] ) : ( isset( $posted['customer_mode'] ) ? sanitize_key( wp_unslash( $posted['customer_mode'] ) ) : '' );
		if ( '' !== $customer_mode ) {
			$redirect_args['customer_mode'] = $customer_mode;
		}

		$form_id       = isset( $args['form_id'] ) ? sanitize_key( $args['form_id'] ) : ( isset( $posted['form_id'] ) ? sanitize_key( wp_unslash( $posted['form_id'] ) ) : '' );
		if ( '' !== $form_id ) {
			$redirect_args['form_id'] = $form_id;
		}

		$search_term = isset( $args['customer_phone'] ) ? sanitize_text_field( $args['customer_phone'] ) : '';
		if ( '' === $search_term ) {
			if ( ! empty( $posted['customer_phone'] ) ) {
				$search_term = sanitize_text_field( wp_unslash( $posted['customer_phone'] ) );
			} elseif ( ! empty( $posted['new_customer_phone'] ) ) {
				$search_term = sanitize_text_field( wp_unslash( $posted['new_customer_phone'] ) );
			}
		}
		if ( '' !== $search_term ) {
			$redirect_args['customer_phone'] = $search_term;
		}

		$customer_id = isset( $args['customer_id'] ) ? absint( $args['customer_id'] ) : ( isset( $posted['customer_id'] ) ? absint( $posted['customer_id'] ) : 0 );
		if ( $customer_id ) {
			$redirect_args['customer_id'] = $customer_id;
		}

		$request_status = isset( $args['request_status'] ) ? sanitize_key( $args['request_status'] ) : ( isset( $posted['request_status'] ) ? sanitize_key( wp_unslash( $posted['request_status'] ) ) : '' );
		if ( '' !== $request_status ) {
			$redirect_args['request_status'] = $request_status;
		}

		$request_source = isset( $args['request_source'] ) ? sanitize_text_field( $args['request_source'] ) : ( isset( $posted['request_source'] ) ? sanitize_text_field( wp_unslash( $posted['request_source'] ) ) : '' );
		if ( '' !== $request_source ) {
			$redirect_args['request_source'] = $request_source;
		}

		return $redirect_args;
	}

	private function get_manual_request_active_landings() {
		if ( ! class_exists( 'CRPCRM_Landing_Manager' ) || ! CRPCRM_Feature_Manager::is_enabled( 'landing_manager' ) ) {
			return array();
		}

		$manager = new CRPCRM_Landing_Manager();
		$result  = $manager->list_landings(
			array(
				'status' => 'active',
				'limit'  => 500,
				'offset' => 0,
			)
		);

		return ! empty( $result['items'] ) && is_array( $result['items'] ) ? $result['items'] : array();
	}

	private function get_manual_request_selected_landing( $value ) {
		$landings = $this->get_manual_request_active_landings();
		if ( empty( $landings ) ) {
			return new WP_Error( 'manual_landing_required', 'برای ثبت درخواست، ابتدا باید حداقل یک لندینگ فعال در مدیریت لندینگ‌ها تعریف شود.' );
		}

		$value = sanitize_text_field( (string) $value );
		if ( '' === $value ) {
			return new WP_Error( 'manual_landing_required', 'برای ثبت درخواست، انتخاب منبع الزامی است.' );
		}

		foreach ( $landings as $landing ) {
			$landing_id   = absint( $landing['id'] ?? 0 );
			$landing_slug = sanitize_key( $landing['slug'] ?? '' );

			if ( (string) $landing_id === $value || $landing_slug === sanitize_key( $value ) ) {
				return $landing;
			}
		}

		return new WP_Error( 'manual_landing_invalid', 'منبع انتخاب‌شده معتبر نیست.' );
	}

	private function build_manual_request_landing_attribution( $landing ) {
		$landing = is_array( $landing ) ? $landing : array();
		$touch   = array(
			'landing_id'      => absint( $landing['id'] ?? 0 ),
			'landing_slug'    => sanitize_key( $landing['slug'] ?? '' ),
			'landing_title'   => sanitize_text_field( $landing['title'] ?? '' ),
			'destination_url' => esc_url_raw( $landing['destination_url'] ?? '' ),
			'source_code'     => sanitize_text_field( $landing['source_code'] ?? '' ),
			'source_label'    => sanitize_text_field( $landing['source_label'] ?? '' ),
			'medium_code'     => sanitize_text_field( $landing['medium_code'] ?? '' ),
			'medium_label'    => sanitize_text_field( $landing['medium_label'] ?? '' ),
			'campaign_code'   => sanitize_text_field( $landing['campaign_code'] ?? '' ),
			'campaign_label'  => sanitize_text_field( $landing['campaign_label'] ?? '' ),
			'content_code'    => sanitize_text_field( $landing['content_code'] ?? '' ),
			'content_label'   => sanitize_text_field( $landing['content_label'] ?? '' ),
			'term_code'       => sanitize_text_field( $landing['term_code'] ?? '' ),
			'term_label'      => sanitize_text_field( $landing['term_label'] ?? '' ),
			'clicked_at'      => CRPCRM_Helpers::current_datetime(),
			'click_id'        => 0,
		);

		return array(
			'visitor_id'      => '',
			'conversion_page' => admin_url( 'admin.php?page=crpcrm-requests&action=new' ),
			'converted_at'    => CRPCRM_Helpers::current_datetime(),
			'referrer'        => '',
			'first_touch'     => $touch,
			'last_touch'      => $touch,
		);
	}

	private function sync_manual_request_customer_attribution( $customer, $landing, $request_id = 0 ) {
		$customer_id = absint( $customer['id'] ?? 0 );
		if ( ! $customer_id ) {
			return false;
		}

		$customer            = $this->customer_repository->get( $customer_id );
		$landing_attribution = $this->build_manual_request_landing_attribution( $landing );
		$touch               = $landing_attribution['last_touch'];
		$stored              = CRPCRM_Customer_Repository::get_landing_attribution( $customer );
		$first_touch         = ! empty( $stored['first_touch'] ) ? $stored['first_touch'] : array();
		$last_touch          = $touch;

		if ( empty( $first_touch ) ) {
			$first_touch = $touch;
		}

		$normalized = array(
			'visitor_id'      => sanitize_text_field( $stored['visitor_id'] ?? '' ),
			'conversion_page' => ! empty( $touch['destination_url'] ) ? esc_url_raw( $touch['destination_url'] ) : '',
			'converted_at'    => CRPCRM_Helpers::current_datetime(),
			'referrer'        => '',
			'first_touch'     => $first_touch,
			'last_touch'      => $last_touch,
		);

		$update = array(
			'last_source'       => sanitize_text_field( $touch['source_code'] ?? '' ),
			'last_medium'       => sanitize_text_field( $touch['medium_code'] ?? '' ),
			'last_campaign'     => sanitize_text_field( $touch['campaign_code'] ?? '' ),
			'last_content'      => sanitize_text_field( $touch['content_code'] ?? '' ),
			'last_term'         => sanitize_text_field( $touch['term_code'] ?? '' ),
			'last_landing_page' => esc_url_raw( $touch['destination_url'] ?? '' ),
			'last_referrer'     => '',
			'last_seen_at'      => CRPCRM_Helpers::current_datetime(),
		);

		if ( empty( $customer['first_source'] ) ) {
			$update['first_source']       = sanitize_text_field( $first_touch['source_code'] ?? '' );
			$update['first_medium']       = sanitize_text_field( $first_touch['medium_code'] ?? '' );
			$update['first_campaign']     = sanitize_text_field( $first_touch['campaign_code'] ?? '' );
			$update['first_content']      = sanitize_text_field( $first_touch['content_code'] ?? '' );
			$update['first_term']         = sanitize_text_field( $first_touch['term_code'] ?? '' );
			$update['first_landing_page'] = esc_url_raw( $first_touch['destination_url'] ?? '' );
			$update['first_referrer']     = '';
			$update['first_seen_at']      = CRPCRM_Helpers::current_datetime();
		}

		$result  = $this->customer_repository->save_landing_attribution( $customer_id, $normalized );
		$updated = $this->customer_repository->update( $customer_id, $update );

		if ( ! $result || false === $updated ) {
			CRPCRM_Logger::warning(
				'manual_request_customer_attribution_failed',
				'customer',
				array(
					'customer_id' => $customer_id,
					'request_id'  => absint( $request_id ),
					'landing_id'  => absint( $landing['id'] ?? 0 ),
					'stored'      => $result ? 1 : 0,
					'updated'     => false === $updated ? 0 : 1,
				)
			);
		}

		return $result;
	}

	private function record_manual_request_customer_audit( $customer, $action ) {
		$user_id  = absint( $customer['user_id'] ?? 0 );
		$staff_id = get_current_user_id();
		$now      = CRPCRM_Helpers::current_datetime();

		if ( ! $user_id || ! $staff_id ) {
			return;
		}

		if ( 'created' === $action ) {
			update_user_meta( $user_id, 'crpcrm_customer_created_by_staff_user_id', $staff_id );
			update_user_meta( $user_id, 'crpcrm_customer_created_by_staff_at', $now );
			$this->customer_repository->update(
				absint( $customer['id'] ?? 0 ),
				array(
					'created_source'           => 'staff',
					'created_by_staff_user_id' => $staff_id,
					'created_by_staff_at'      => $now,
				)
			);
		}

		if ( in_array( $action, array( 'created', 'updated' ), true ) ) {
			update_user_meta( $user_id, 'crpcrm_customer_updated_by_staff_user_id', $staff_id );
			update_user_meta( $user_id, 'crpcrm_customer_updated_by_staff_at', $now );
		}
	}

	private function get_manual_request_customer_audit( $customer ) {
		$customer    = is_array( $customer ) ? $customer : array();
		$user_id     = absint( $customer['user_id'] ?? 0 );
		$created_by  = absint( $customer['created_by_staff_user_id'] ?? 0 );
		$created_at  = sanitize_text_field( (string) ( $customer['created_by_staff_at'] ?? '' ) );
		$source      = sanitize_key( (string) ( $customer['created_source'] ?? '' ) );
		$updated_by  = $user_id ? absint( get_user_meta( $user_id, 'crpcrm_customer_updated_by_staff_user_id', true ) ) : 0;
		$updated_at  = $user_id ? sanitize_text_field( (string) get_user_meta( $user_id, 'crpcrm_customer_updated_by_staff_at', true ) ) : '';

		if ( ! $created_by && $user_id ) {
			$created_by = absint( get_user_meta( $user_id, 'crpcrm_customer_created_by_staff_user_id', true ) );
		}
		if ( '' === $created_at && $user_id ) {
			$created_at = sanitize_text_field( (string) get_user_meta( $user_id, 'crpcrm_customer_created_by_staff_at', true ) );
		}
		if ( '' === $source ) {
			$source = $created_by ? 'staff' : '';
		}

		return array(
			'created_by'   => $created_by,
			'created_name' => $created_by ? $this->get_user_display_name( $created_by ) : '',
			'created_at'   => $created_at,
			'created_source' => $source,
			'updated_by'   => $updated_by,
			'updated_name' => $updated_by ? $this->get_user_display_name( $updated_by ) : '',
			'updated_at'   => $updated_at,
		);
	}

	private function get_user_display_name( $user_id, $fallback = '' ) {
		$user = get_userdata( absint( $user_id ) );
		if ( $user && ! empty( $user->display_name ) ) {
			return $user->display_name;
		}

		return '' !== $fallback ? $fallback : 'کاربر #' . absint( $user_id );
	}

	private function get_customer_creator_display_text( $customer ) {
		$audit      = $this->get_manual_request_customer_audit( $customer );
		$source     = sanitize_key( (string) ( $audit['created_source'] ?? '' ) );
		$created_by = absint( $audit['created_by'] ?? 0 );

		if ( $created_by ) {
			return 'ثبت اولیه توسط ' . $this->get_user_display_name( $created_by );
		}
		if ( 'site_customer' === $source ) {
			return 'ثبت توسط سایت / مشتری';
		}

		return 'ثبت‌کننده نامشخص';
	}

	private function maybe_record_manual_customer_created_activity( $request_id, $customer, $actor_user_id ) {
		$request_id     = absint( $request_id );
		$actor_user_id  = absint( $actor_user_id );
		$customer       = is_array( $customer ) ? $customer : array();
		$customer_id    = absint( $customer['id'] ?? 0 );
		$created_source = sanitize_key( (string) ( $customer['created_source'] ?? '' ) );
		$created_by     = absint( $customer['created_by_staff_user_id'] ?? 0 );
		$created_at     = sanitize_text_field( (string) ( $customer['created_by_staff_at'] ?? '' ) );

		if ( 'staff' !== $created_source || ! $request_id || ! $customer_id || $created_by !== $actor_user_id ) {
			return;
		}

		$created_timestamp = CRPCRM_Helpers::datetime_to_timestamp( $created_at );
		if ( false === $created_timestamp || ( CRPCRM_Helpers::current_timestamp() - $created_timestamp ) > 900 ) {
			return;
		}

		CRPCRM_Activity::add(
			$request_id,
			'manual_customer_created',
			array(
				'customer_id'   => $customer_id,
				'actor_user_id' => $actor_user_id,
				'actor_type'    => CRPCRM_Request_Access_Service::can_manage_request() ? 'sales_manager' : 'sales_agent',
				'note'          => 'مشتری در همین فرایند توسط کارشناس فروش ایجاد شد.',
				'is_internal'   => 1,
			)
		);
	}

	public function handle_manual_request_customer_lookup() {
		$this->verify_manual_request_ajax_request( 'crpcrm_manual_request_customer_lookup' );

		$phone       = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$customer_id = isset( $_POST['customer_id'] ) ? absint( $_POST['customer_id'] ) : 0;

		wp_send_json_success( $this->build_manual_request_customer_context( $phone, $customer_id ) );
	}

	public function handle_manual_request_customer_save() {
		$this->verify_manual_request_ajax_request( 'crpcrm_manual_request_customer_save' );

		$mode               = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'create';
		$customer_id        = isset( $_POST['customer_id'] ) ? absint( $_POST['customer_id'] ) : 0;
		$phone              = isset( $_POST['customer_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_phone'] ) ) : '';
		$registration_input = isset( $_POST['registration'] ) && is_array( $_POST['registration'] ) ? wp_unslash( $_POST['registration'] ) : array();
		$result             = $this->save_manual_request_customer( $mode, $phone, $registration_input, $customer_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
				),
				400
			);
		}

		wp_send_json_success( $result );
	}

	private function verify_manual_request_ajax_request( $action ) {
		check_ajax_referer( $action, 'nonce' );

		if ( ! CRPCRM_Request_Access_Service::can_create_request() ) {
			wp_send_json_error(
				array(
					'message' => 'شما اجازه انجام این عملیات را ندارید.',
				),
				403
			);
		}
	}

	private function build_manual_request_customer_context( $phone, $customer_id = 0 ) {
		$customer         = null;
		$phone_input      = sanitize_text_field( (string) $phone );
		$phone_normalized = CRPCRM_Helpers::normalize_iran_phone( $phone_input );
		$phone_is_valid   = CRPCRM_Helpers::is_valid_iran_phone_normalized( $phone_normalized );

		if ( $customer_id ) {
			$customer = $this->customer_repository->get( $customer_id );
		}

		if ( ! $customer && $phone_is_valid ) {
			$customer = $this->customer_repository->find_by_phone_normalized( $phone_normalized );
		}

		if ( $customer ) {
			$is_complete = $this->is_manual_request_customer_complete( $customer );
			$creator_text = $this->get_customer_creator_display_text( $customer );

			return array(
				'state'            => $is_complete ? 'complete' : 'incomplete',
				'customer_id'      => absint( $customer['id'] ),
				'phone'            => $phone_input,
				'phone_normalized' => ! empty( $customer['phone_normalized'] ) ? $customer['phone_normalized'] : $phone_normalized,
				'summary'          => 'مشتری پیدا شد — ' . $creator_text,
				'message'          => $is_complete ? $this->build_manual_request_customer_summary( $customer ) : 'اطلاعات مشتری ناقص است. ' . $this->build_manual_request_customer_summary( $customer ),
				'action_label'     => $is_complete ? '' : 'تکمیل اطلاعات',
				'form_html'        => $this->render_manual_request_customer_form_html( 'update', $customer, '' !== $phone_input ? $phone_input : $phone_normalized ),
			);
		}

		if ( $phone_is_valid ) {
			return array(
				'state'            => 'not_found',
				'customer_id'      => 0,
				'phone'            => $phone_input,
				'phone_normalized' => $phone_normalized,
				'summary'          => '',
				'message'          => 'مشتری یافت نشد.',
				'action_label'     => 'ساخت مشتری',
				'form_html'        => $this->render_manual_request_customer_form_html( 'create', null, '' !== $phone_input ? $phone_input : $phone_normalized ),
			);
		}

		return array(
			'state'            => 'idle',
			'customer_id'      => 0,
			'phone'            => $phone_input,
			'phone_normalized' => $phone_normalized,
			'summary'          => '',
			'message'          => '',
			'action_label'     => '',
			'form_html'        => '',
		);
	}

	private function save_manual_request_customer( $mode, $phone, $registration_input, $customer_id = 0 ) {
		$mode             = 'update' === $mode ? 'update' : 'create';
		$phone_normalized = CRPCRM_Helpers::normalize_iran_phone( $phone );

		if ( ! CRPCRM_Helpers::is_valid_iran_phone_normalized( $phone_normalized ) ) {
			return new WP_Error( 'manual_customer_invalid', 'شماره موبایل مشتری معتبر نیست.' );
		}

		$registration_data = $this->sanitize_manual_request_customer_input( $registration_input );
		$errors            = $this->validate_manual_request_customer_input( $registration_input, $registration_data, $customer_id );

		if ( ! empty( $errors ) ) {
			return new WP_Error( 'manual_customer_invalid', implode( ' ', $errors ) );
		}

		if ( 'update' === $mode ) {
			$customer = $this->customer_repository->get( $customer_id );
			if ( ! $customer ) {
				return new WP_Error( 'manual_customer_required', 'مشتری انتخاب‌شده معتبر نیست.' );
			}

			$update_data = array(
				'profile_completed' => $this->is_manual_request_registration_complete( $registration_data ) ? 1 : 0,
			);
			foreach ( array( 'full_name', 'province', 'city' ) as $column ) {
				if ( array_key_exists( $column, $registration_data ) ) {
					$update_data[ $column ] = $registration_data[ $column ];
				}
			}

			if ( ! $this->customer_repository->update( absint( $customer['id'] ), $update_data ) ) {
				return new WP_Error( 'manual_customer_failed', 'ذخیره اطلاعات مشتری انجام نشد.' );
			}

			CRPCRM_Customer_Registration_Fields::save_customer_values( $customer, $registration_data );
			if ( ! empty( $registration_data['full_name'] ) && ! empty( $customer['user_id'] ) ) {
				$this->maybe_update_customer_display_name( absint( $customer['user_id'] ), $registration_data['full_name'], $customer );
			}
			$this->record_manual_request_customer_audit( $customer, 'updated' );

			$context              = $this->build_manual_request_customer_context( $phone_normalized, absint( $customer['id'] ) );
			$context['message']   = 'اطلاعات مشتری با موفقیت ذخیره شد.';
			return $context;
		}

		$existing_customer = $this->customer_repository->find_by_phone_normalized( $phone_normalized );
		if ( $existing_customer ) {
			$context            = $this->build_manual_request_customer_context( $phone_normalized, absint( $existing_customer['id'] ) );
			$context['message'] = 'مشتری با این شماره موبایل قبلاً ثبت شده است و برای همین درخواست انتخاب شد.';
			return $context;
		}

		$user         = $this->find_existing_user_by_phone( $phone_normalized );
		$created_user = false;
		if ( ! $user ) {
			$user_id = wp_insert_user(
				array(
					'user_login'   => $phone_normalized,
					'user_pass'    => wp_generate_password( 20 ),
					'display_name' => ! empty( $registration_data['full_name'] ) ? $registration_data['full_name'] : $phone_normalized,
					'role'         => 'customer',
				)
			);

			if ( is_wp_error( $user_id ) || ! $user_id ) {
				return new WP_Error( 'manual_customer_failed', 'ساخت مشتری انجام نشد.' );
			}

			$user         = get_user_by( 'id', $user_id );
			$created_user = true;
			update_user_meta( $user_id, 'crpcrm_phone_normalized', $phone_normalized );
		}

		$new_customer_id = $this->customer_repository->create(
			array(
				'user_id'           => absint( $user->ID ),
				'phone'             => $phone_normalized,
				'phone_normalized'  => $phone_normalized,
				'full_name'         => $registration_data['full_name'] ?? '',
				'province'          => $registration_data['province'] ?? '',
				'city'              => $registration_data['city'] ?? '',
				'created_source'    => 'staff',
				'created_by_staff_user_id' => get_current_user_id(),
				'created_by_staff_at' => CRPCRM_Helpers::current_datetime(),
				'profile_completed' => $this->is_manual_request_registration_complete( $registration_data ) ? 1 : 0,
				'first_source'      => '',
				'last_source'       => '',
			)
		);

		if ( ! $new_customer_id ) {
			if ( $created_user ) {
				$this->delete_user_safely( $user->ID );
			}
			return new WP_Error( 'manual_customer_failed', 'ساخت مشتری انجام نشد.' );
		}

		$customer = $this->customer_repository->get( $new_customer_id );
		if ( ! $customer ) {
			$this->rollback_manual_request_customer(
				array(
					'id'      => $new_customer_id,
					'user_id' => $user->ID,
				),
				true,
				$created_user
			);
			return new WP_Error( 'manual_customer_failed', 'ساخت مشتری انجام نشد.' );
		}

			CRPCRM_Customer_Registration_Fields::save_customer_values( $customer, $registration_data );
			if ( ! empty( $registration_data['full_name'] ) ) {
				$this->maybe_update_customer_display_name( absint( $user->ID ), $registration_data['full_name'], $customer );
			}
			$this->record_manual_request_customer_audit( $customer, 'created' );

		$context            = $this->build_manual_request_customer_context( $phone_normalized, absint( $new_customer_id ) );
		$context['message'] = 'مشتری با موفقیت ایجاد شد.';
		return $context;
	}

	private function get_manual_request_registration_fields() {
		$fields = CRPCRM_Customer_Registration_Fields::get_enabled_fields();
		if ( ! empty( $fields ) ) {
			return $fields;
		}

		$all_fields = CRPCRM_Customer_Registration_Fields::get_fields();
		$fallback   = array();
		foreach ( array( 'full_name', 'province', 'city' ) as $field_key ) {
			if ( ! isset( $all_fields[ $field_key ] ) ) {
				continue;
			}
			$fallback[ $field_key ]             = $all_fields[ $field_key ];
			$fallback[ $field_key ]['required'] = 'full_name' === $field_key;
		}

		return $fallback;
	}

	private function sanitize_manual_request_customer_input( $input ) {
		$input       = is_array( $input ) ? $input : array();
		$definitions = CRPCRM_Customer_Registration_Fields::get_fields();
		$clean       = array();

		foreach ( $this->get_manual_request_registration_fields() as $field_key => $field ) {
			$value = isset( $input[ $field_key ] ) ? wp_unslash( $input[ $field_key ] ) : '';
			$type  = $definitions[ $field_key ]['type'] ?? ( $field['type'] ?? 'text' );

			if ( 'email' === $type ) {
				$clean[ $field_key ] = sanitize_email( $value );
			} elseif ( 'textarea' === $type ) {
				$clean[ $field_key ] = sanitize_textarea_field( $value );
			} else {
				$clean[ $field_key ] = sanitize_text_field( $value );
			}
		}

		return $clean;
	}

	private function validate_manual_request_customer_input( $raw_input, $registration_data, $customer_id = 0 ) {
		$raw_input    = is_array( $raw_input ) ? $raw_input : array();
		$errors       = array();
		$definitions  = CRPCRM_Customer_Registration_Fields::get_fields();
		$current_user = 0;

		if ( $customer_id ) {
			$current_customer = $this->customer_repository->get( $customer_id );
			$current_user     = $current_customer ? absint( $current_customer['user_id'] ) : 0;
		}

		foreach ( $this->get_manual_request_registration_fields() as $field_key => $field ) {
			$value = isset( $registration_data[ $field_key ] ) ? $registration_data[ $field_key ] : '';
			$type  = $definitions[ $field_key ]['type'] ?? ( $field['type'] ?? 'text' );

			if ( 'email' === $type && '' !== $value ) {
				if ( ! is_email( $value ) ) {
					$errors[] = 'ایمیل واردشده معتبر نیست.';
					continue;
				}

				$existing_user_id = email_exists( $value );
				if ( $existing_user_id && absint( $existing_user_id ) !== $current_user ) {
					$errors[] = 'این ایمیل قبلاً برای حساب دیگری ثبت شده است.';
				}
			}

			if ( 'select' === $type && '' !== $value && ! empty( $field['options'] ) && ! in_array( $value, $field['options'], true ) ) {
				$errors[] = sprintf( '%s معتبر نیست.', $field['label'] );
			}
		}

		if ( isset( $registration_data['province'] ) && '' !== $registration_data['province'] && ! in_array( $registration_data['province'], $this->get_iran_provinces(), true ) ) {
			$errors[] = 'استان انتخاب‌شده معتبر نیست.';
		}

		return $errors;
	}

	private function is_manual_request_registration_complete( $registration_data ) {
		foreach ( $this->get_manual_request_registration_fields() as $field_key => $field ) {
			if ( empty( $field['required'] ) ) {
				continue;
			}

			$value = isset( $registration_data[ $field_key ] ) ? trim( (string) $registration_data[ $field_key ] ) : '';
			if ( '' === $value ) {
				return false;
			}
		}

		return true;
	}

	private function is_manual_request_customer_complete( $customer ) {
		$fields = $this->get_manual_request_registration_fields();
		if ( empty( $fields ) ) {
			return true;
		}

		$values = CRPCRM_Customer_Registration_Fields::get_customer_values( $customer );
		foreach ( $fields as $field_key => $field ) {
			if ( empty( $field['required'] ) ) {
				continue;
			}

			$value = isset( $values[ $field_key ] ) ? trim( (string) $values[ $field_key ] ) : '';
			if ( '' === $value ) {
				return false;
			}
		}

		return true;
	}

	private function build_manual_request_customer_summary( $customer ) {
		$values = CRPCRM_Customer_Registration_Fields::get_customer_values( $customer );
		$stats  = $this->customer_repository->get_customer_stats( absint( $customer['id'] ) );
		$parts  = array();

		$name = trim( (string) ( $values['full_name'] ?? '' ) );
		if ( '' === $name ) {
			$name = trim( (string) ( $values['company_name'] ?? '' ) );
		}
		if ( '' === $name ) {
			$name = trim( (string) ( $customer['full_name'] ?? '' ) );
		}
		if ( '' !== $name ) {
			$parts[] = $name;
		}

		foreach ( array( 'province', 'city' ) as $field_key ) {
			$value = trim( (string) ( $values[ $field_key ] ?? '' ) );
			if ( '' !== $value ) {
				$parts[] = $value;
			}
		}

		$parts[] = sprintf( 'دارای %s درخواست', number_format_i18n( absint( $stats['total_requests'] ?? 0 ) ) );
		return implode( '، ', array_filter( $parts ) );
	}

	private function render_manual_request_customer_form_html( $mode, $customer = null, $phone_normalized = '' ) {
		$mode        = 'update' === $mode ? 'update' : 'create';
		$customer    = is_array( $customer ) ? $customer : null;
		$values      = $customer ? CRPCRM_Customer_Registration_Fields::get_customer_values( $customer ) : array();
		$fields      = $this->get_manual_request_registration_fields();
		$customer_id = $customer ? absint( $customer['id'] ) : 0;

		ob_start();
		?>
		<div class="crpcrm-manual-customer-editor-form" data-mode="<?php echo esc_attr( $mode ); ?>" data-customer-id="<?php echo esc_attr( $customer_id ); ?>">
			<div class="crpcrm-form-grid">
				<label class="crpcrm-width-50">
					<?php echo esc_html( 'شماره موبایل مشتری' ); ?>
					<input type="tel" name="customer_phone" value="<?php echo esc_attr( $phone_normalized ); ?>" readonly>
				</label>
				<?php foreach ( $fields as $field_key => $field ) : ?>
					<?php $field_value = isset( $values[ $field_key ] ) ? $values[ $field_key ] : ''; ?>
					<label class="<?php echo esc_attr( 'textarea' === $field['type'] ? 'crpcrm-full-field' : 'crpcrm-width-50' ); ?>">
						<?php echo esc_html( $field['label'] ); ?>
						<?php if ( 'province' === $field['type'] ) : ?>
							<select name="registration[<?php echo esc_attr( $field_key ); ?>]">
								<option value=""><?php echo esc_html( 'استان را انتخاب کنید' ); ?></option>
								<?php foreach ( $this->get_iran_provinces() as $province ) : ?>
									<option value="<?php echo esc_attr( $province ); ?>" <?php selected( $field_value, $province ); ?>><?php echo esc_html( $province ); ?></option>
								<?php endforeach; ?>
							</select>
						<?php elseif ( 'select' === $field['type'] ) : ?>
							<select name="registration[<?php echo esc_attr( $field_key ); ?>]">
								<option value=""><?php echo esc_html( 'انتخاب کنید' ); ?></option>
								<?php foreach ( $field['options'] ?? array() as $option ) : ?>
									<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $field_value, $option ); ?>><?php echo esc_html( $option ); ?></option>
								<?php endforeach; ?>
							</select>
						<?php elseif ( 'textarea' === $field['type'] ) : ?>
							<textarea name="registration[<?php echo esc_attr( $field_key ); ?>]" rows="4"><?php echo esc_textarea( $field_value ); ?></textarea>
						<?php elseif ( 'date' === $field['type'] ) : ?>
							<?php echo CRPCRM_Helpers::jalali_date_input( 'registration[' . $field_key . ']', $field_value, array() ); ?>
						<?php else : ?>
							<input type="<?php echo esc_attr( 'email' === $field['type'] ? 'email' : 'text' ); ?>" name="registration[<?php echo esc_attr( $field_key ); ?>]" value="<?php echo esc_attr( $field_value ); ?>">
						<?php endif; ?>
					</label>
				<?php endforeach; ?>
			</div>
			<div class="crpcrm-manual-customer-editor-actions">
				<button type="button" class="button button-secondary crpcrm-manual-customer-save">
					<?php echo esc_html( 'update' === $mode ? 'ذخیره اطلاعات مشتری' : 'ساخت مشتری' ); ?>
				</button>
				<span class="crpcrm-manual-customer-editor-status" aria-live="polite"></span>
			</div>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	private function get_iran_provinces() {
		return array(
			'آذربایجان شرقی',
			'آذربایجان غربی',
			'اردبیل',
			'اصفهان',
			'البرز',
			'ایلام',
			'بوشهر',
			'تهران',
			'چهارمحال و بختیاری',
			'خراسان جنوبی',
			'خراسان رضوی',
			'خراسان شمالی',
			'خوزستان',
			'زنجان',
			'سمنان',
			'سیستان و بلوچستان',
			'فارس',
			'قزوین',
			'قم',
			'کردستان',
			'کرمان',
			'کرمانشاه',
			'کهگیلویه و بویراحمد',
			'گلستان',
			'گیلان',
			'لرستان',
			'مازندران',
			'مرکزی',
			'هرمزگان',
			'همدان',
			'یزد',
		);
	}

	private function maybe_update_customer_display_name( $user_id, $full_name, $customer ) {
		$user = get_user_by( 'id', absint( $user_id ) );
		if ( ! $user ) {
			return;
		}

		$phone_normalized = ! empty( $customer['phone_normalized'] ) ? $customer['phone_normalized'] : get_user_meta( $user_id, 'crpcrm_phone_normalized', true );
		if ( $user->display_name === $phone_normalized || $user->display_name === $user->user_login || '' === trim( (string) $user->display_name ) ) {
			wp_update_user(
				array(
					'ID'           => absint( $user_id ),
					'display_name' => sanitize_text_field( $full_name ),
				)
			);
		}
	}

	private function find_existing_user_by_phone( $phone_normalized ) {
		$phone_normalized = CRPCRM_Helpers::normalize_iran_phone( $phone_normalized );
		$user             = get_user_by( 'login', $phone_normalized );
		if ( $user ) {
			return $user;
		}

		$users = get_users(
			array(
				'meta_key'   => 'crpcrm_phone_normalized',
				'meta_value' => $phone_normalized,
				'number'     => 1,
				'fields'     => 'all',
			)
		);

		return ! empty( $users ) ? $users[0] : null;
	}

	private function delete_user_safely( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return false;
		}

		if ( ! function_exists( 'wp_delete_user' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}

		if ( ! function_exists( 'wp_delete_user' ) ) {
			return false;
		}

		return (bool) wp_delete_user( $user_id );
	}

	private function rollback_manual_request_customer( $customer, $created_customer, $created_user ) {
		$customer_id = is_array( $customer ) ? absint( $customer['id'] ?? ( $customer['customer_id'] ?? 0 ) ) : 0;
		$user_id     = is_array( $customer ) ? absint( $customer['user_id'] ?? 0 ) : 0;

		if ( $created_customer && $customer_id ) {
			$deleted = $this->customer_repository->delete_permanently( $customer_id );
			if ( is_wp_error( $deleted ) ) {
				CRPCRM_Logger::warning( 'manual_request_customer_rollback_failed', 'manual_request_customer_rollback_failed', array( 'customer_id' => $customer_id, 'user_id' => get_current_user_id(), 'error' => $deleted->get_error_code() ) );
				return;
			}
		}

		if ( $created_user && $user_id ) {
			$this->delete_user_safely( $user_id );
		}
	}

	private function render_message( $message ) {
		echo '<div class="wrap crpcrm-admin-wrap" dir="rtl"><h1>' . esc_html( 'دیجیتال مارکتینگ' ) . '</h1><div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div></div>';
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

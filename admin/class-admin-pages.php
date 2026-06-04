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

	public function __construct() {
		$this->request_repository = new CRPCRM_Request_Repository();
		$this->customer_repository = new CRPCRM_Customer_Repository();
		$this->workflow_service    = new CRPCRM_Request_Workflow_Service( $this->request_repository );
		add_action( 'admin_post_crpcrm_claim_request', array( $this, 'handle_claim_request' ) );
		add_action( 'admin_post_crpcrm_change_owner', array( $this, 'handle_change_owner' ) );
		add_action( 'admin_post_crpcrm_release_owner', array( $this, 'handle_release_owner' ) );
		add_action( 'admin_post_crpcrm_add_sales_action', array( $this, 'handle_add_sales_action' ) );
	}

	public function dashboard() {
		$this->render( 'dashboard.php' );
	}

	public function requests() {
		if ( ! CRPCRM_Request_Access_Service::can_access_admin_requests() ) {
			CRPCRM_Logger::warning( 'request_access_denied', 'request_access_denied', array( 'user_id' => get_current_user_id(), 'screen' => 'admin_requests' ) );
			$this->render_message( 'شما اجازه دسترسی به این بخش را ندارید.' );
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
		$per_page = 20;
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
		$this->render( 'reports-placeholder.php' );
	}

	public function staff() {
		$this->render( 'staff-placeholder.php' );
	}

	public function settings() {
		$settings            = CRPCRM_Settings::get();
		$attribution_service = class_exists( 'CRPCRM_Attribution_Service' ) ? new CRPCRM_Attribution_Service() : null;
		$current_attribution = $attribution_service ? $attribution_service->get_current_attribution() : null;

		$this->render(
			'settings.php',
			array(
				'settings'            => $settings,
				'current_attribution' => $current_attribution,
			)
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

	private function render_request_list() {
		$page     = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$per_page = 20;
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
			'date_from'    => isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '',
			'date_to'      => isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '',
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

	private function redirect_to_request( $request_id, $notice ) {
		wp_safe_redirect( add_query_arg( array( 'page' => 'crpcrm-requests', 'request_id' => absint( $request_id ), 'crpcrm_notice' => sanitize_key( $notice ) ), admin_url( 'admin.php' ) ) );
		exit;
	}

	private function render_message( $message ) {
		echo '<div class="wrap crpcrm-admin-wrap" dir="rtl"><h1>' . esc_html( 'درخواست‌های مشتریان' ) . '</h1><div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div></div>';
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

<?php
/**
 * Admin menu registration.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_Admin_Menu {
	private $admin_pages;
	private $notification_service;

	public function __construct( CRPCRM_Admin_Pages $admin_pages ) {
		$this->admin_pages          = $admin_pages;
		$this->notification_service = new CRPCRM_Notification_Service();
	}

	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_init', array( $this, 'guard_internal_pages' ) );
	}

	public function guard_internal_pages() {
		if ( ! is_admin() || wp_doing_ajax() || ! isset( $_GET['page'] ) ) {
			return;
		}

		$page = sanitize_key( wp_unslash( $_GET['page'] ) );
		if ( 0 !== strpos( $page, 'crpcrm-' ) ) {
			return;
		}

		if ( in_array( $page, array( 'crpcrm-dashboard', 'crpcrm-requests', 'crpcrm-customers', 'crpcrm-customer-profile' ), true ) && ! CRPCRM_Feature_Manager::is_crm_ui_enabled() ) {
			wp_die( esc_html( CRPCRM_Feature_Manager::disabled_message() ) );
		}

		$page_features = array(
			'crpcrm-reports'       => 'reports',
			'crpcrm-staff'         => 'staff',
			'crpcrm-notifications' => 'notifications',
			'crpcrm-landings'      => 'landing_manager',
		);
		if ( isset( $page_features[ $page ] ) && ! CRPCRM_Feature_Manager::is_enabled( $page_features[ $page ] ) ) {
			wp_die( esc_html( CRPCRM_Feature_Manager::disabled_message() ) );
		}

		if ( 'crpcrm-settings' === $page && isset( $_GET['tab'] ) ) {
			$tab_features = array(
				'portal'              => 'portal',
				'registration_fields' => 'customer_registration',
				'attribution'         => 'tracking',
				'staff'               => 'staff',
				'notifications'       => 'notifications',
				'tools'               => 'admin_tools',
			);
			$tab          = sanitize_key( wp_unslash( $_GET['tab'] ) );
			if ( 'crm' === $tab && ! CRPCRM_Feature_Manager::is_crm_ui_enabled() ) {
				wp_die( esc_html( CRPCRM_Feature_Manager::disabled_message() ) );
			}
			if ( 'otp' === $tab && ! CRPCRM_Feature_Manager::is_enabled( 'otp' ) && ! CRPCRM_Feature_Manager::is_enabled( 'sms' ) ) {
				wp_die( esc_html( CRPCRM_Feature_Manager::disabled_message() ) );
			}
			if ( 'otp' === $tab ) {
				return;
			}
			if ( isset( $tab_features[ $tab ] ) && ! CRPCRM_Feature_Manager::is_enabled( $tab_features[ $tab ] ) ) {
				wp_die( esc_html( CRPCRM_Feature_Manager::disabled_message() ) );
			}
		}
	}

	public function register_menu() {
		$requests_capability = current_user_can( 'crpcrm_view_all_requests' ) ? 'crpcrm_view_all_requests' : 'crpcrm_view_assigned_requests';
		$crm_ui_enabled      = CRPCRM_Feature_Manager::is_crm_ui_enabled();
		$settings_capability = current_user_can( 'crpcrm_manage_settings' ) ? 'crpcrm_manage_settings' : ( current_user_can( 'crpcrm_manage_plugin' ) ? 'crpcrm_manage_plugin' : 'manage_options' );
		$parent_slug         = $crm_ui_enabled ? 'crpcrm-dashboard' : 'crpcrm-settings';
		$parent_capability   = $crm_ui_enabled ? 'crpcrm_use_staff_portal' : $settings_capability;
		$parent_callback     = $crm_ui_enabled ? array( $this->admin_pages, 'dashboard' ) : array( $this->admin_pages, 'settings' );
		if ( CRPCRM_Feature_Manager::is_enabled( 'landing_manager' ) && ! current_user_can( 'crpcrm_use_staff_portal' ) && current_user_can( 'crpcrm_manage_landings' ) ) {
			$parent_slug       = 'crpcrm-settings';
			$parent_capability = 'crpcrm_manage_landings';
			$parent_callback   = array( $this->admin_pages, 'settings' );
		}
		$unread_count        = CRPCRM_Notification_Service::is_enabled() && is_user_logged_in() ? $this->notification_service->get_unread_count( get_current_user_id() ) : 0;
		$can_view_notifications = CRPCRM_Notification_Service::current_user_can_view_notifications();
		$notifications_title = 'اعلانات';
		if ( $unread_count > 0 ) {
			$notifications_title .= ' <span class="update-plugins count-' . absint( $unread_count ) . '"><span class="plugin-count">' . number_format_i18n( $unread_count ) . '</span></span>';
		}

		add_menu_page(
			'دیجیتال مارکتینگ',
			'دیجیتال مارکتینگ',
			$parent_capability,
			$parent_slug,
			$parent_callback,
			'dashicons-groups',
			26
		);

		if ( $crm_ui_enabled ) {
			add_submenu_page( $parent_slug, 'داشبورد', 'داشبورد', 'crpcrm_use_staff_portal', 'crpcrm-dashboard', array( $this->admin_pages, 'dashboard' ) );
			add_submenu_page( $parent_slug, 'درخواست‌ها', 'درخواست‌ها', $requests_capability, 'crpcrm-requests', array( $this->admin_pages, 'requests' ) );
			add_submenu_page( $parent_slug, 'مشتریان', 'مشتریان', 'crpcrm_view_all_requests', 'crpcrm-customers', array( $this->admin_pages, 'customers' ) );
			add_submenu_page( null, 'پروفایل مشتری', 'پروفایل مشتری', 'crpcrm_use_staff_portal', 'crpcrm-customer-profile', array( $this->admin_pages, 'customer_profile' ) );
		}
		if ( CRPCRM_Feature_Manager::is_enabled( 'reports' ) ) {
			add_submenu_page( $parent_slug, 'گزارش‌ها', 'گزارش‌ها', 'crpcrm_view_reports', 'crpcrm-reports', array( $this->admin_pages, 'reports' ) );
		}
		if ( CRPCRM_Feature_Manager::is_enabled( 'staff' ) && ( 'yes' === CRPCRM_Settings::get( 'staff_portal_enabled', 'yes' ) || current_user_can( 'manage_options' ) ) ) {
			add_submenu_page( $parent_slug, 'کارکنان', 'کارکنان', 'crpcrm_use_staff_portal', 'crpcrm-staff', array( $this->admin_pages, 'staff' ) );
		}
		if ( CRPCRM_Notification_Service::is_enabled() && $can_view_notifications ) {
			add_submenu_page( $parent_slug, 'اعلانات', $notifications_title, current_user_can( 'crpcrm_view_notifications' ) ? 'crpcrm_view_notifications' : $parent_capability, 'crpcrm-notifications', array( $this->admin_pages, 'notifications' ) );
		}
		if ( CRPCRM_Settings::current_user_can_manage() ) {
			add_submenu_page( $parent_slug, 'تنظیمات', 'تنظیمات', $settings_capability, 'crpcrm-settings', array( $this->admin_pages, 'settings' ) );
		}
	}

	public function enqueue_assets( $hook_suffix ) {
		if ( false === strpos( $hook_suffix, 'crpcrm' ) ) {
			return;
		}

		$admin_css_path = CRPCRM_PLUGIN_DIR . 'assets/css/admin.css';
		$admin_ui_css_path = CRPCRM_PLUGIN_DIR . 'assets/css/admin-ui.css';
		$admin_js_path  = CRPCRM_PLUGIN_DIR . 'assets/js/admin.js';
		$request_preview_css_path = CRPCRM_PLUGIN_DIR . 'assets/css/request-file-preview.css';
		$request_preview_js_path  = CRPCRM_PLUGIN_DIR . 'assets/js/request-file-preview.js';
		$reports_css_path         = CRPCRM_PLUGIN_DIR . 'assets/css/reports.css';
		$reports_js_path          = CRPCRM_PLUGIN_DIR . 'assets/js/admin-reports.js';
		$chartjs_path             = CRPCRM_PLUGIN_DIR . 'assets/vendor/chartjs/chart.umd.min.js';
		wp_enqueue_style( 'crpcrm-admin', CRPCRM_PLUGIN_URL . 'assets/css/admin.css', array(), file_exists( $admin_css_path ) ? filemtime( $admin_css_path ) : CRPCRM_VERSION );
		wp_enqueue_style( 'crpcrm-admin-ui', CRPCRM_PLUGIN_URL . 'assets/css/admin-ui.css', array( 'crpcrm-admin' ), file_exists( $admin_ui_css_path ) ? filemtime( $admin_ui_css_path ) : CRPCRM_VERSION );
		wp_enqueue_script( 'crpcrm-admin', CRPCRM_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery', 'jquery-ui-sortable' ), file_exists( $admin_js_path ) ? filemtime( $admin_js_path ) : CRPCRM_VERSION, true );
		wp_enqueue_style( 'crpcrm-request-file-preview', CRPCRM_PLUGIN_URL . 'assets/css/request-file-preview.css', array( 'crpcrm-admin-ui' ), file_exists( $request_preview_css_path ) ? filemtime( $request_preview_css_path ) : CRPCRM_VERSION );
		wp_enqueue_script( 'crpcrm-request-file-preview', CRPCRM_PLUGIN_URL . 'assets/js/request-file-preview.js', array(), file_exists( $request_preview_js_path ) ? filemtime( $request_preview_js_path ) : CRPCRM_VERSION, true );
		wp_localize_script(
			'crpcrm-admin',
			'crpcrmAdmin',
			array(
				'ajaxUrl'              => admin_url( 'admin-ajax.php' ),
				'productSearchNonce'   => wp_create_nonce( 'crpcrm_search_products' ),
				'fileUploadNonce'      => wp_create_nonce( 'crpcrm_upload_request_file' ),
				'fileDeletePendingNonce' => wp_create_nonce( 'crpcrm_delete_pending_request_file' ),
				'fileDeletePendingError' => 'حذف فایل انجام نشد.',
				'fileDeletePendingFallback' => 'حذف این فایل در این مرورگر انجام نمی‌شود.',
				'productSearchMin'     => 2,
				'productSearchEmpty'   => 'محصولی پیدا نشد.',
				'productSearchLoading' => 'در حال جستجو...',
				'productRemoveLabel'   => 'حذف محصول',
				'unreadNotifications'  => CRPCRM_Notification_Service::is_enabled() && is_user_logged_in() ? $this->notification_service->get_unread_count( get_current_user_id() ) : 0,
				'notificationsPageUrl' => admin_url( 'admin.php?page=crpcrm-notifications' ),
				'fileUploadLoading'    => 'در حال بارگذاری...',
				'fileUploadFallback'   => 'بارگذاری خودکار در این مرورگر پشتیبانی نمی‌شود. فایل را همراه فرم ارسال کنید.',
				'fileUploadInvalid'    => 'نوع فایل مجاز نیست.',
				'fileUploadTooLarge'   => 'حجم فایل بیش از حد مجاز است.',
				'fileUploadNetwork'    => 'ارتباط با سرور برقرار نشد.',
				'fileUploadMaxSize'    => CRPCRM_Dynamic_Form_Renderer::MAX_FILE_SIZE,
				'fileUploadMaxFiles'   => CRPCRM_Dynamic_Form_Renderer::MAX_FILES_PER_FIELD,
				'fileUploadMaxTotalSize' => CRPCRM_Dynamic_Form_Renderer::MAX_TOTAL_REQUEST_SIZE,
				'fileUploadError'      => 'بارگذاری فایل انجام نشد.',
				'fileUploadedLabel'    => 'بارگذاری شده',
				'filePreviewLabel'     => 'مشاهده فایل',
				'fileDownloadLabel'    => 'دانلود فایل',
				'notificationsToast'   => CRPCRM_Notification_Service::is_enabled() && 'yes' === CRPCRM_Settings::get( 'notifications_toast_enabled', 'yes' ),
			)
		);

		if ( false !== strpos( $hook_suffix, 'crpcrm-reports' ) ) {
			wp_enqueue_style( 'crpcrm-reports', CRPCRM_PLUGIN_URL . 'assets/css/reports.css', array( 'crpcrm-admin-ui' ), file_exists( $reports_css_path ) ? filemtime( $reports_css_path ) : CRPCRM_VERSION );
			wp_enqueue_script( 'crpcrm-chartjs', CRPCRM_PLUGIN_URL . 'assets/vendor/chartjs/chart.umd.min.js', array(), file_exists( $chartjs_path ) ? filemtime( $chartjs_path ) : CRPCRM_VERSION, true );
			wp_enqueue_script( 'crpcrm-admin-reports', CRPCRM_PLUGIN_URL . 'assets/js/admin-reports.js', array( 'crpcrm-chartjs' ), file_exists( $reports_js_path ) ? filemtime( $reports_js_path ) : CRPCRM_VERSION, true );
		}

		if ( ! CRPCRM_Notification_Service::is_enabled() || ! is_user_logged_in() || ! CRPCRM_Notification_Service::current_user_can_view_notifications() ) {
			return;
		}

		$notifications_css_path = CRPCRM_PLUGIN_DIR . 'assets/css/admin-notifications.css';
		$notifications_js_path  = CRPCRM_PLUGIN_DIR . 'assets/js/admin-notifications.js';
		wp_enqueue_style( 'crpcrm-admin-notifications', CRPCRM_PLUGIN_URL . 'assets/css/admin-notifications.css', array( 'crpcrm-admin-ui' ), file_exists( $notifications_css_path ) ? filemtime( $notifications_css_path ) : CRPCRM_VERSION );
		wp_enqueue_script( 'crpcrm-admin-notifications', CRPCRM_PLUGIN_URL . 'assets/js/admin-notifications.js', array(), file_exists( $notifications_js_path ) ? filemtime( $notifications_js_path ) : CRPCRM_VERSION, true );
		wp_localize_script(
			'crpcrm-admin-notifications',
			'crpcrmNotifications',
			array(
				'ajax_url'              => admin_url( 'admin-ajax.php' ),
				'nonce'                 => wp_create_nonce( 'crpcrm_get_new_notifications' ),
				'poll_interval'         => 30000,
				'notifications_page_url'=> admin_url( 'admin.php?page=crpcrm-notifications' ),
				'enabled'               => 'yes' === CRPCRM_Settings::get( 'notifications_toast_enabled', 'yes' ),
				'max_toasts'            => 3,
				'debug'                 => defined( 'WP_DEBUG' ) && WP_DEBUG,
			)
		);
	}
}

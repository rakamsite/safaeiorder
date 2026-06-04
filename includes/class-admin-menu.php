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

	public function __construct( CRPCRM_Admin_Pages $admin_pages ) {
		$this->admin_pages = $admin_pages;
	}

	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function register_menu() {
		add_menu_page(
			'پرتال و CRM',
			'پرتال و CRM',
			'crpcrm_use_staff_portal',
			'crpcrm-dashboard',
			array( $this->admin_pages, 'dashboard' ),
			'dashicons-groups',
			26
		);

		add_submenu_page( 'crpcrm-dashboard', 'داشبورد', 'داشبورد', 'crpcrm_manage_plugin', 'crpcrm-dashboard', array( $this->admin_pages, 'dashboard' ) );
		add_submenu_page( 'crpcrm-dashboard', 'درخواست‌ها', 'درخواست‌ها', 'crpcrm_use_staff_portal', 'crpcrm-requests', array( $this->admin_pages, 'requests' ) );
		add_submenu_page( 'crpcrm-dashboard', 'مشتریان', 'مشتریان', 'crpcrm_view_all_requests', 'crpcrm-customers', array( $this->admin_pages, 'customers' ) );
		add_submenu_page( null, 'پروفایل مشتری', 'پروفایل مشتری', 'crpcrm_use_staff_portal', 'crpcrm-customer-profile', array( $this->admin_pages, 'customer_profile' ) );
		add_submenu_page( 'crpcrm-dashboard', 'گزارش‌ها', 'گزارش‌ها', 'crpcrm_view_reports', 'crpcrm-reports', array( $this->admin_pages, 'reports' ) );
		add_submenu_page( 'crpcrm-dashboard', 'کارکنان', 'کارکنان', 'crpcrm_manage_staff_portal', 'crpcrm-staff', array( $this->admin_pages, 'staff' ) );
		add_submenu_page( 'crpcrm-dashboard', 'تنظیمات', 'تنظیمات', 'crpcrm_manage_settings', 'crpcrm-settings', array( $this->admin_pages, 'settings' ) );
	}

	public function enqueue_assets( $hook_suffix ) {
		if ( false === strpos( $hook_suffix, 'crpcrm' ) ) {
			return;
		}

		wp_enqueue_style( 'crpcrm-admin', CRPCRM_PLUGIN_URL . 'assets/css/admin.css', array(), CRPCRM_VERSION );
		wp_enqueue_script( 'crpcrm-admin', CRPCRM_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), CRPCRM_VERSION, true );
	}
}

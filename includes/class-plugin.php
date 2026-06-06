<?php
/**
 * Main plugin bootstrap.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_Plugin {
	private $settings;
	private $admin_pages;
	private $admin_menu;
	private $public;
	private $attribution_service;
	private $admin_tools;

	public function __construct() {
		$this->settings            = new CRPCRM_Settings();
		$this->admin_pages         = new CRPCRM_Admin_Pages();
		$this->admin_menu          = new CRPCRM_Admin_Menu( $this->admin_pages );
		$this->public              = new CRPCRM_Public();
		$this->attribution_service = new CRPCRM_Attribution_Service();
		$this->admin_tools          = new CRPCRM_Admin_Tools();
	}

	public function run() {
		CRPCRM_Roles::register_hooks();
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'plugins_loaded', array( 'CRPCRM_DB', 'maybe_upgrade' ) );
		add_action( 'plugins_loaded', array( 'CRPCRM_Roles', 'add_roles_and_caps' ) );
		$this->settings->register_hooks();
		$this->admin_menu->register_hooks();
		$this->public->register_hooks();
		$this->attribution_service->register_hooks();
		$this->admin_tools->register_hooks();
	}

	public function load_textdomain() {
		load_plugin_textdomain( 'customer-request-portal-crm', false, dirname( plugin_basename( CRPCRM_PLUGIN_FILE ) ) . '/languages' );
	}
}

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

	public function __construct() {
		$this->settings    = new CRPCRM_Settings();
		$this->admin_pages = new CRPCRM_Admin_Pages();
		$this->admin_menu  = new CRPCRM_Admin_Menu( $this->admin_pages );
		$this->public      = new CRPCRM_Public();
	}

	public function run() {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		$this->settings->register_hooks();
		$this->admin_menu->register_hooks();
		$this->public->register_hooks();
	}

	public function load_textdomain() {
		load_plugin_textdomain( 'customer-request-portal-crm', false, dirname( plugin_basename( CRPCRM_PLUGIN_FILE ) ) . '/languages' );
	}
}

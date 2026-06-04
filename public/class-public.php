<?php
/**
 * Public-facing bootstrap for future portal features.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_Public {
	private $portal_shortcode;

	public function __construct() {
		$this->portal_shortcode = new CRPCRM_Portal_Shortcode();
	}

	public function register_hooks() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		$this->portal_shortcode->register_hooks();
	}

	public function enqueue_assets() {
		wp_enqueue_style( 'crpcrm-public', CRPCRM_PLUGIN_URL . 'assets/css/public.css', array(), CRPCRM_VERSION );
		wp_enqueue_script( 'crpcrm-public', CRPCRM_PLUGIN_URL . 'assets/js/public.js', array(), CRPCRM_VERSION, true );
	}
}

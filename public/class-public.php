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
		if ( ! CRPCRM_Feature_Manager::is_enabled( 'portal' ) ) {
			return;
		}

		$public_css_path = CRPCRM_PLUGIN_DIR . 'assets/css/public.css';
		$public_js_path  = CRPCRM_PLUGIN_DIR . 'assets/js/public.js';
		wp_register_style( 'crpcrm-public', CRPCRM_PLUGIN_URL . 'assets/css/public.css', array(), file_exists( $public_css_path ) ? filemtime( $public_css_path ) : CRPCRM_VERSION );
		wp_register_script( 'crpcrm-public', CRPCRM_PLUGIN_URL . 'assets/js/public.js', array(), file_exists( $public_js_path ) ? filemtime( $public_js_path ) : CRPCRM_VERSION, true );
	}
}

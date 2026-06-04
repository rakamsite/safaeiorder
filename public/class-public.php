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
	public function register_hooks() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function enqueue_assets() {
		wp_enqueue_style( 'crpcrm-public', CRPCRM_PLUGIN_URL . 'assets/css/public.css', array(), CRPCRM_VERSION );
		wp_enqueue_script( 'crpcrm-public', CRPCRM_PLUGIN_URL . 'assets/js/public.js', array(), CRPCRM_VERSION, true );
	}
}

<?php
/**
 * Contract for business-specific plugin configuration.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface CRPCRM_Business_Profile_Interface {
	public function get_id();

	public function get_label();

	public function get_request_types();

	public function get_forms();

	public function get_default_sms_templates();

	public function get_theme_tokens();

	public function has_profile_settings();

	public function get_profile_default_settings();

	public function sanitize_profile_settings( $input, $current );

	public function render_profile_settings( $settings );
}

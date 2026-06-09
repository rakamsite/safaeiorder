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
}

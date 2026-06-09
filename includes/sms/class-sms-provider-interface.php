<?php
/**
 * SMS provider contract.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface CRPCRM_SMS_Provider_Interface {
	public function get_id();

	public function get_label();

	public function get_settings_schema();

	public function validate_settings( array $settings );

	public function send( $phone_normalized, $template_key, array $variables = array() );

	public function send_otp( $phone_normalized, $code );
}

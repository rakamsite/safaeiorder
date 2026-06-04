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
	/**
	 * Send an OTP code.
	 *
	 * @param string $phone_normalized Phone in 989123456789 format.
	 * @param string $code Six-digit OTP code.
	 * @return true|WP_Error
	 */
	public function send_otp( $phone_normalized, $code );
}

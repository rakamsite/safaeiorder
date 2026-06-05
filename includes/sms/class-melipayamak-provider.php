<?php
/**
 * Melipayamak SMS provider.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_Melipayamak_Provider implements CRPCRM_SMS_Provider_Interface {
	private $username;
	private $api_key;
	private $pattern_code;
	private $sender;

	public function __construct() {
		$this->username     = (string) CRPCRM_Settings::get( 'melipayamak_username', '' );
		$this->api_key      = (string) CRPCRM_Settings::get( 'melipayamak_api_key', '' );
		if ( '' === $this->api_key ) {
			$this->api_key = (string) CRPCRM_Settings::get( 'melipayamak_password', '' );
		}
		$this->pattern_code = (string) CRPCRM_Settings::get( 'melipayamak_pattern_code', '' );
		$this->sender       = (string) CRPCRM_Settings::get( 'melipayamak_sender', '' );
	}

	public function send_otp( $phone_normalized, $code ) {
		if ( ! $this->has_required_credentials() ) {
			CRPCRM_Logger::warning( 'otp_send_failed', 'otp', array( 'reason' => 'melipayamak_missing_credentials' ) );
			return new WP_Error( 'crpcrm_sms_missing_credentials', 'Melipayamak credentials are incomplete.' );
		}

		$to   = $this->to_national_phone( $phone_normalized );
		$body = array(
			'username' => $this->username,
			'password' => $this->api_key,
			'text'     => $code,
			'to'       => $to,
			'bodyId'   => absint( $this->pattern_code ),
		);

		$response = wp_remote_post(
			'https://rest.payamak-panel.com/api/SendSMS/BaseServiceNumber',
			array(
				'timeout' => 15,
				'headers' => array( 'content-type' => 'application/x-www-form-urlencoded' ),
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$body_text   = trim( (string) wp_remote_retrieve_body( $response ) );

		if ( $status_code < 200 || $status_code >= 300 ) {
			return new WP_Error( 'crpcrm_sms_http_error', 'Melipayamak HTTP error.', array( 'status_code' => $status_code, 'response' => $body_text ) );
		}

		$decoded = json_decode( $body_text, true );
		$result  = is_array( $decoded ) && isset( $decoded['Value'] ) ? (string) $decoded['Value'] : $body_text;
		$result  = trim( $result, '" ' );

		if ( '-110' === $result ) {
			return new WP_Error( 'crpcrm_sms_api_key_required', 'Melipayamak requires an API key instead of the account password.', array( 'provider_result' => $result ) );
		}

		if ( preg_match( '/^\d{16,}$/', $result ) ) {
			return true;
		}

		return new WP_Error( 'crpcrm_sms_provider_error', 'Melipayamak provider returned an error.', array( 'provider_result' => $result ) );
	}

	private function has_required_credentials() {
		return '' !== $this->username && '' !== $this->api_key && '' !== $this->pattern_code;
	}

	private function to_national_phone( $phone_normalized ) {
		$phone_normalized = preg_replace( '/\D+/', '', (string) $phone_normalized );
		if ( 0 === strpos( $phone_normalized, '98' ) && 12 === strlen( $phone_normalized ) ) {
			return '0' . substr( $phone_normalized, 2 );
		}

		return $phone_normalized;
	}
}

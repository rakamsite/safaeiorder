<?php
/**
 * SMS.ir provider.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_SMS_IR_Provider implements CRPCRM_SMS_Provider_Interface {
	public function get_id() {
		return 'sms_ir';
	}

	public function get_label() {
		return 'SMS.ir';
	}

	public function get_settings_schema() {
		return array(
			'sms_ir_api_key'             => array( 'label' => 'API Key', 'required' => true ),
			'sms_ir_line_number'         => array( 'label' => 'شماره خط ارسال‌کننده', 'required' => false ),
			'sms_ir_template_id_otp'     => array( 'label' => 'شناسه قالب OTP', 'required' => true ),
			'sms_ir_template_id_request_created' => array( 'label' => 'شناسه قالب ثبت درخواست', 'required' => false ),
		);
	}

	public function validate_settings( array $settings ) {
		$missing = array();
		foreach ( $this->get_settings_schema() as $key => $field ) {
			if ( ! empty( $field['required'] ) && empty( $settings[ $key ] ) ) {
				$missing[] = $key;
			}
		}
		return $missing ? new WP_Error( 'crpcrm_sms_ir_settings_incomplete', 'تنظیمات SMS.ir کامل نیست.', array( 'missing' => $missing ) ) : true;
	}

	public function send( $phone_normalized, $template_key, array $variables = array() ) {
		$settings = CRPCRM_Settings::get();
		$valid    = $this->validate_settings( $settings );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$template_id = sanitize_text_field( CRPCRM_Settings::get( 'sms_ir_template_id_' . sanitize_key( $template_key ), '' ) );
		if ( '' === $template_id ) {
			return new WP_Error( 'crpcrm_sms_template_missing', 'قالب پیامک موردنظر برای SMS.ir تنظیم نشده است.' );
		}

		$parameters = array();
		foreach ( $variables as $name => $value ) {
			if ( is_scalar( $value ) ) {
				$parameters[] = array( 'name' => sanitize_key( $name ), 'value' => sanitize_text_field( (string) $value ) );
			}
		}
		// Endpoint and payload follow SMS.ir's verify API and should be checked against its current documentation before production rollout.
		$response = wp_remote_post(
			'https://api.sms.ir/v1/send/verify',
			array(
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'application/json', 'Accept' => 'text/plain', 'X-API-KEY' => sanitize_text_field( CRPCRM_Settings::get( 'sms_ir_api_key', '' ) ) ),
				'body'    => wp_json_encode( array( 'mobile' => $this->to_national_phone( $phone_normalized ), 'templateId' => absint( $template_id ), 'parameters' => $parameters ) ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$status_code = (int) wp_remote_retrieve_response_code( $response );
		if ( $status_code < 200 || $status_code >= 300 ) {
			return new WP_Error( 'crpcrm_sms_http_error', 'SMS.ir HTTP error.', array( 'status_code' => $status_code ) );
		}

		$response_body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( is_array( $response_body ) && isset( $response_body['status'] ) && 1 !== (int) $response_body['status'] ) {
			return new WP_Error( 'crpcrm_sms_provider_error', 'SMS.ir provider returned an error.', array( 'provider_status' => (int) $response_body['status'] ) );
		}

		return true;
	}

	public function send_otp( $phone_normalized, $code ) {
		return $this->send( $phone_normalized, 'otp', array( 'code' => $code ) );
	}

	private function to_national_phone( $phone_normalized ) {
		$phone_normalized = preg_replace( '/\D+/', '', (string) $phone_normalized );
		return 0 === strpos( $phone_normalized, '98' ) && 12 === strlen( $phone_normalized ) ? '0' . substr( $phone_normalized, 2 ) : $phone_normalized;
	}
}

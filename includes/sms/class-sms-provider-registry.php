<?php
/**
 * Registry and dispatcher for SMS providers.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_SMS_Provider_Registry {
	const DEFAULT_PROVIDER_ID = 'melipayamak';

	private static $instance;
	private $providers = array();

	private function __construct() {
		$this->register_provider( new CRPCRM_Melipayamak_Provider() );
		$this->register_provider( new CRPCRM_SMS_IR_Provider() );
		do_action( 'crpcrm_register_sms_providers', $this );
	}

	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function register_provider( CRPCRM_SMS_Provider_Interface $provider ) {
		$provider_id = sanitize_key( $provider->get_id() );
		if ( '' === $provider_id ) {
			return false;
		}
		$this->providers[ $provider_id ] = $provider;
		return true;
	}

	public function get_providers() {
		return $this->providers;
	}

	public function get_provider( $provider_id ) {
		$provider_id = sanitize_key( $provider_id );
		return isset( $this->providers[ $provider_id ] ) ? $this->providers[ $provider_id ] : null;
	}

	public function get_configured_provider_id() {
		return sanitize_key( CRPCRM_Settings::get( 'otp_provider', self::DEFAULT_PROVIDER_ID ) );
	}

	public function get_active_provider_id() {
		$provider = $this->get_active_provider();
		return $provider ? $provider->get_id() : self::DEFAULT_PROVIDER_ID;
	}

	public function get_active_provider() {
		$provider_id = $this->get_configured_provider_id();
		$provider    = $this->get_provider( $provider_id );

		if ( ! $provider ) {
			return $this->get_provider( self::DEFAULT_PROVIDER_ID );
		}

		$validation = $provider->validate_settings( CRPCRM_Settings::get() );
		if ( is_wp_error( $validation ) && self::DEFAULT_PROVIDER_ID !== $provider_id ) {
			$default_provider   = $this->get_provider( self::DEFAULT_PROVIDER_ID );
			$default_validation = $default_provider ? $default_provider->validate_settings( CRPCRM_Settings::get() ) : null;
			if ( $default_provider && ! is_wp_error( $default_validation ) ) {
				return $default_provider;
			}
		}

		return $provider;
	}

	public function send( $phone_normalized, $template_key, array $variables = array() ) {
		$provider = $this->get_active_provider();
		if ( ! $provider ) {
			return new WP_Error( 'crpcrm_sms_provider_unavailable', 'ارائه‌دهنده پیامک در دسترس نیست.' );
		}

		$result = $provider->send( $phone_normalized, sanitize_key( $template_key ), $variables );
		if ( is_wp_error( $result ) ) {
			CRPCRM_Logger::warning( 'sms_send_failed', 'sms', array( 'provider' => $provider->get_id(), 'template_key' => sanitize_key( $template_key ), 'code' => $result->get_error_code() ) );
		}
		return $result;
	}
}

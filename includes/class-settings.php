<?php
/**
 * Settings management.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_Settings {
	const OPTION_NAME = 'crpcrm_settings';

	public function register_hooks() {
		add_action( 'admin_post_crpcrm_save_settings', array( $this, 'handle_save' ) );
	}

	public static function defaults() {
		return array(
			'portal_page_id'                => 0,
			'portal_menu_id'                => 0,
			'customer_registration_enabled' => 'yes',
			'otp_provider'                  => 'melipayamak',
			'otp_expiration_minutes'        => 2,
			'otp_resend_seconds'            => 60,
			'otp_max_attempts'              => 5,
			'melipayamak_username'          => '',
			'melipayamak_password'          => '',
			'melipayamak_pattern_code'      => '',
			'melipayamak_sender'            => '',
			'attribution_window_hours'      => 24,
			'request_rate_limit_count'      => 5,
			'request_rate_limit_minutes'    => 10,
			'stale_request_hours'           => 48,
			'delete_data_on_uninstall'      => 'no',
		);
	}

	public static function add_default_options() {
		if ( false === get_option( self::OPTION_NAME, false ) ) {
			add_option( self::OPTION_NAME, self::defaults() );
			return;
		}

		update_option( self::OPTION_NAME, wp_parse_args( get_option( self::OPTION_NAME, array() ), self::defaults() ) );
	}

	public static function get( $key = null, $default = null ) {
		$settings = wp_parse_args( get_option( self::OPTION_NAME, array() ), self::defaults() );

		if ( null === $key ) {
			return $settings;
		}

		return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
	}

	public function handle_save() {
		if ( ! current_user_can( 'crpcrm_manage_settings' ) ) {
			wp_die( esc_html__( 'شما اجازه دسترسی به تنظیمات افزونه را ندارید.', 'customer-request-portal-crm' ) );
		}

		check_admin_referer( 'crpcrm_save_settings', 'crpcrm_settings_nonce' );

		$input    = isset( $_POST['crpcrm_settings'] ) && is_array( $_POST['crpcrm_settings'] ) ? wp_unslash( $_POST['crpcrm_settings'] ) : array();
		$settings = $this->sanitize_settings( $input );

		update_option( self::OPTION_NAME, $settings );
		CRPCRM_Logger::info( 'Settings updated.', 'settings', array( 'user_id' => get_current_user_id() ) );

		$redirect_url = add_query_arg(
			array(
				'page'              => 'crpcrm-settings',
				'settings-updated'  => 'true',
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $redirect_url );
		exit;
	}

	private function sanitize_settings( $input ) {
		$defaults = self::defaults();
		$current  = self::get();

		return array(
			'portal_page_id'                => isset( $input['portal_page_id'] ) ? absint( $input['portal_page_id'] ) : $defaults['portal_page_id'],
			'portal_menu_id'                => isset( $input['portal_menu_id'] ) ? absint( $input['portal_menu_id'] ) : $defaults['portal_menu_id'],
			'customer_registration_enabled' => isset( $input['customer_registration_enabled'] ) && 'yes' === $input['customer_registration_enabled'] ? 'yes' : 'no',
			'otp_provider'                  => isset( $input['otp_provider'] ) ? sanitize_key( $input['otp_provider'] ) : $defaults['otp_provider'],
			'otp_expiration_minutes'        => isset( $input['otp_expiration_minutes'] ) ? max( 1, absint( $input['otp_expiration_minutes'] ) ) : $defaults['otp_expiration_minutes'],
			'otp_resend_seconds'            => isset( $input['otp_resend_seconds'] ) ? max( 1, absint( $input['otp_resend_seconds'] ) ) : $defaults['otp_resend_seconds'],
			'otp_max_attempts'              => isset( $input['otp_max_attempts'] ) ? max( 1, absint( $input['otp_max_attempts'] ) ) : $defaults['otp_max_attempts'],
			'melipayamak_username'          => isset( $input['melipayamak_username'] ) ? sanitize_text_field( $input['melipayamak_username'] ) : '',
			'melipayamak_password'          => isset( $input['melipayamak_password'] ) && '' !== trim( (string) $input['melipayamak_password'] ) ? sanitize_text_field( $input['melipayamak_password'] ) : $current['melipayamak_password'],
			'melipayamak_pattern_code'      => isset( $input['melipayamak_pattern_code'] ) ? sanitize_text_field( $input['melipayamak_pattern_code'] ) : '',
			'melipayamak_sender'            => isset( $input['melipayamak_sender'] ) ? sanitize_text_field( $input['melipayamak_sender'] ) : '',
			'attribution_window_hours'      => isset( $input['attribution_window_hours'] ) ? max( 1, absint( $input['attribution_window_hours'] ) ) : $defaults['attribution_window_hours'],
			'request_rate_limit_count'      => isset( $input['request_rate_limit_count'] ) ? max( 1, absint( $input['request_rate_limit_count'] ) ) : $defaults['request_rate_limit_count'],
			'request_rate_limit_minutes'    => isset( $input['request_rate_limit_minutes'] ) ? max( 1, absint( $input['request_rate_limit_minutes'] ) ) : $defaults['request_rate_limit_minutes'],
			'stale_request_hours'           => isset( $input['stale_request_hours'] ) ? max( 1, absint( $input['stale_request_hours'] ) ) : $defaults['stale_request_hours'],
			'delete_data_on_uninstall'      => isset( $input['delete_data_on_uninstall'] ) && 'yes' === $input['delete_data_on_uninstall'] ? 'yes' : 'no',
		);
	}
}

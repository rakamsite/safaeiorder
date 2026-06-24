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
		add_action( 'admin_post_crpcrm_rebuild_roles', array( $this, 'handle_rebuild_roles' ) );
	}

	public static function defaults() {
		$site_host = wp_parse_url( home_url(), PHP_URL_HOST );

		return array(
			'portal_page_id'                       => 0,
			'portal_menu_id'                       => 0,
			'customer_registration_enabled'        => 'yes',
			'request_success_message'              => 'درخواست شما با موفقیت ثبت شد. کارشناسان ما در اولین فرصت آن را بررسی می‌کنند.',
			'otp_provider'                         => 'melipayamak',
			'otp_expiration_minutes'               => 2,
			'otp_resend_seconds'                   => 60,
			'otp_rate_limit_phone_limit'           => 6,
			'otp_rate_limit_ip_limit'              => 50,
			'otp_rate_limit_window_minutes'        => 15,
			'otp_max_attempts'                     => 5,
			'otp_debug_mode'                       => 'no',
			'melipayamak_username'                 => '',
			'melipayamak_api_key'                  => '',
			'melipayamak_password'                 => '',
			'melipayamak_pattern_code'             => '',
			'melipayamak_sender'                   => '',
			'sms_ir_api_key'                       => '',
			'sms_ir_line_number'                   => '',
			'sms_ir_template_id_otp'               => '',
			'sms_ir_template_id_request_created'   => '',
			'attribution_window_hours'             => 24,
			'attribution_enabled'                  => 'yes',
			'attribution_events_enabled'           => 'yes',
			'internal_domains'                     => $site_host ? $site_host : '',
			'request_rate_limit_count'             => 5,
			'request_rate_limit_minutes'           => 10,
			'stale_request_hours'                  => 48,
			'requests_per_page'                    => 20,
			'allow_manager_note_on_closed_request' => 'yes',
			'staff_portal_enabled'                 => 'yes',
			'daily_report_required'                => 'no',
			'daily_report_reminder_time'           => '',
			'staff_items_per_page'                 => 20,
			'notifications_toast_enabled'          => 'yes',
			'delete_data_on_uninstall'             => 'no',
			'log_retention_days'                   => 90,
			'log_level'                            => 'info',
		);
	}

	public static function add_default_options() {
		$stored_options = get_option( self::OPTION_NAME, false );
		if ( false === $stored_options ) {
			add_option( self::OPTION_NAME, self::defaults() );
			return;
		}

		$stored_options = is_array( $stored_options ) ? $stored_options : array();
		$defaults       = self::defaults();
		update_option( self::OPTION_NAME, wp_parse_args( $stored_options, $defaults ) );
	}

	public static function get( $key = null, $default = null ) {
		$settings = wp_parse_args( get_option( self::OPTION_NAME, array() ), self::defaults() );

		if ( null === $key ) {
			return $settings;
		}

		return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
	}

	public static function current_user_can_manage() {
		return current_user_can( 'crpcrm_manage_settings' ) || current_user_can( 'crpcrm_manage_plugin' ) || current_user_can( 'manage_options' );
	}

	public static function tabs() {
		$tabs = array( 'features' => 'امکانات افزونه' );
		if ( CRPCRM_Feature_Manager::is_enabled( 'customer_registration' ) ) {
			$tabs['registration_fields'] = 'اطلاعات تکمیلی';
		}
		if ( CRPCRM_Feature_Manager::is_enabled( 'portal' ) ) {
			$tabs['portal'] = 'تنظیمات پرتال مشتری';
		}
		if ( CRPCRM_Feature_Manager::is_enabled( 'otp' ) || CRPCRM_Feature_Manager::is_enabled( 'sms' ) ) {
			$tabs['otp'] = 'تنظیمات ورود OTP و پیامک';
		}
		if ( CRPCRM_Feature_Manager::is_enabled( 'tracking' ) ) {
			$tabs['attribution'] = 'تنظیمات رهگیری ورودی';
		}
		if ( CRPCRM_Feature_Manager::is_crm_ui_enabled() ) {
			$tabs['crm'] = 'تنظیمات درخواست‌ها و CRM';
		}
		if ( CRPCRM_Feature_Manager::is_enabled( 'staff' ) ) {
			$tabs['staff'] = 'تنظیمات پنل کارکنان';
		}
		if ( CRPCRM_Feature_Manager::is_enabled( 'notifications' ) ) {
			$tabs['notifications'] = 'تنظیمات اعلانات';
		}
		$tabs['roles']       = 'تنظیمات نقش‌ها و دسترسی‌ها';
		$tabs['maintenance'] = 'تنظیمات نگهداری داده‌ها';
		if ( CRPCRM_Feature_Manager::is_enabled( 'admin_tools' ) ) {
			$tabs['tools'] = 'ابزارها و نگهداری';
		}
		return $tabs;
	}

	public static function saveable_tabs() {
		$tabs = self::tabs();
		unset( $tabs['roles'], $tabs['tools'] );

		return $tabs;
	}

	public function handle_save() {
		if ( ! self::current_user_can_manage() ) {
			wp_die( esc_html__( 'شما اجازه دسترسی به تنظیمات افزونه را ندارید.', 'customer-request-portal-crm' ) );
		}

		check_admin_referer( 'crpcrm_save_settings', 'crpcrm_settings_nonce' );

		$active_tab = isset( $_POST['crpcrm_active_tab'] ) ? sanitize_key( wp_unslash( $_POST['crpcrm_active_tab'] ) ) : 'portal';
		if ( ! isset( self::saveable_tabs()[ $active_tab ] ) ) {
			wp_die( esc_html__( 'این تب تنظیمات قابل ذخیره‌سازی نیست.', 'customer-request-portal-crm' ) );
		}

		if ( 'features' === $active_tab ) {
			$input = isset( $_POST['crpcrm_features'] ) && is_array( $_POST['crpcrm_features'] ) ? wp_unslash( $_POST['crpcrm_features'] ) : array();
			CRPCRM_Feature_Manager::save_features( $input );
		} elseif ( 'registration_fields' === $active_tab ) {
			$field_settings = isset( $_POST['crpcrm_registration_fields'] ) && is_array( $_POST['crpcrm_registration_fields'] ) ? wp_unslash( $_POST['crpcrm_registration_fields'] ) : array();
			CRPCRM_Customer_Registration_Fields::save_settings( $field_settings );
		} else {
			$input    = isset( $_POST['crpcrm_settings'] ) && is_array( $_POST['crpcrm_settings'] ) ? wp_unslash( $_POST['crpcrm_settings'] ) : array();
			$settings = $this->sanitize_settings( $input, $active_tab );
			update_option( self::OPTION_NAME, $settings );
		}

		CRPCRM_Logger::info( 'settings_updated', 'settings_updated', array( 'user_id' => get_current_user_id(), 'tab' => $active_tab ) );

		$redirect_url = add_query_arg(
			array(
				'page'             => 'crpcrm-settings',
				'tab'              => $active_tab,
				'settings-updated' => 'true',
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $redirect_url );
		exit;
	}

	public function handle_rebuild_roles() {
		if ( ! self::current_user_can_manage() ) {
			wp_die( esc_html__( 'شما اجازه بازسازی نقش‌ها و دسترسی‌ها را ندارید.', 'customer-request-portal-crm' ) );
		}

		check_admin_referer( 'crpcrm_rebuild_roles', 'crpcrm_roles_nonce' );
		CRPCRM_Roles::add_roles_and_caps();
		CRPCRM_Logger::info( 'roles_rebuilt', 'roles_rebuilt', array( 'user_id' => get_current_user_id() ) );

		wp_safe_redirect( add_query_arg( array( 'page' => 'crpcrm-settings', 'tab' => 'roles', 'roles-rebuilt' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	private function sanitize_settings( $input, $active_tab ) {
		$defaults = self::defaults();
		$current  = self::get();
		$settings = $current;

		if ( 'portal' === $active_tab ) {
			$settings['portal_page_id']          = isset( $input['portal_page_id'] ) ? absint( $input['portal_page_id'] ) : $defaults['portal_page_id'];
			$settings['portal_menu_id']          = isset( $input['portal_menu_id'] ) ? absint( $input['portal_menu_id'] ) : $defaults['portal_menu_id'];
			if ( CRPCRM_Feature_Manager::is_enabled( 'customer_registration' ) ) {
				$settings['customer_registration_enabled'] = $this->checkbox( $input, 'customer_registration_enabled' );
			}
			$settings['request_success_message'] = isset( $input['request_success_message'] ) && '' !== trim( (string) $input['request_success_message'] ) ? sanitize_textarea_field( $input['request_success_message'] ) : $defaults['request_success_message'];
		} elseif ( 'otp' === $active_tab ) {
			$settings['otp_rate_limit_phone_limit']    = $this->bounded_absint( $input, 'otp_rate_limit_phone_limit', 1, 20, $defaults['otp_rate_limit_phone_limit'] );
			$settings['otp_rate_limit_ip_limit']       = $this->bounded_absint( $input, 'otp_rate_limit_ip_limit', 1, 200, $defaults['otp_rate_limit_ip_limit'] );
			$settings['otp_rate_limit_window_minutes'] = $this->bounded_absint( $input, 'otp_rate_limit_window_minutes', 1, 1440, $defaults['otp_rate_limit_window_minutes'] );
			$settings['otp_resend_seconds']            = $this->bounded_absint( $input, 'otp_resend_seconds', 10, 600, $defaults['otp_resend_seconds'] );
			if ( CRPCRM_Feature_Manager::is_enabled( 'otp' ) ) {
				$settings['otp_expiration_minutes'] = $this->bounded_absint( $input, 'otp_expiration_minutes', 1, 60, $defaults['otp_expiration_minutes'] );
				$settings['otp_max_attempts']       = $this->bounded_absint( $input, 'otp_max_attempts', 1, 20, $defaults['otp_max_attempts'] );
				$settings['otp_debug_mode']         = $this->checkbox( $input, 'otp_debug_mode' );
			}
			if ( CRPCRM_Feature_Manager::is_enabled( 'sms' ) ) {
				$provider_id                                  = isset( $input['otp_provider'] ) ? sanitize_key( $input['otp_provider'] ) : $defaults['otp_provider'];
				$settings['otp_provider']                     = CRPCRM_SMS_Provider_Registry::get_instance()->get_provider( $provider_id ) ? $provider_id : $defaults['otp_provider'];
				$settings['melipayamak_username']             = isset( $input['melipayamak_username'] ) ? sanitize_text_field( $input['melipayamak_username'] ) : '';
				$settings['melipayamak_api_key']              = isset( $input['melipayamak_api_key'] ) && '' !== trim( (string) $input['melipayamak_api_key'] ) ? sanitize_text_field( $input['melipayamak_api_key'] ) : $current['melipayamak_api_key'];
				$settings['melipayamak_password']             = isset( $input['melipayamak_password'] ) && '' !== trim( (string) $input['melipayamak_password'] ) ? sanitize_text_field( $input['melipayamak_password'] ) : $current['melipayamak_password'];
				$settings['melipayamak_pattern_code']         = isset( $input['melipayamak_pattern_code'] ) ? sanitize_text_field( $input['melipayamak_pattern_code'] ) : '';
				$settings['melipayamak_sender']               = isset( $input['melipayamak_sender'] ) ? sanitize_text_field( $input['melipayamak_sender'] ) : '';
				$settings['sms_ir_api_key']                   = isset( $input['sms_ir_api_key'] ) && '' !== trim( (string) $input['sms_ir_api_key'] ) ? sanitize_text_field( $input['sms_ir_api_key'] ) : $current['sms_ir_api_key'];
				$settings['sms_ir_line_number']               = isset( $input['sms_ir_line_number'] ) ? sanitize_text_field( $input['sms_ir_line_number'] ) : '';
				$settings['sms_ir_template_id_otp']           = isset( $input['sms_ir_template_id_otp'] ) ? sanitize_text_field( $input['sms_ir_template_id_otp'] ) : '';
				$settings['sms_ir_template_id_request_created'] = isset( $input['sms_ir_template_id_request_created'] ) ? sanitize_text_field( $input['sms_ir_template_id_request_created'] ) : '';
			}
		} elseif ( 'attribution' === $active_tab ) {
			$settings['attribution_window_hours']   = $this->bounded_absint( $input, 'attribution_window_hours', 1, 720, $defaults['attribution_window_hours'] );
			$settings['attribution_enabled']        = $this->checkbox( $input, 'attribution_enabled' );
			$settings['attribution_events_enabled'] = $this->checkbox( $input, 'attribution_events_enabled' );
			$settings['internal_domains']           = $this->sanitize_domains( isset( $input['internal_domains'] ) ? $input['internal_domains'] : $defaults['internal_domains'] );
		} elseif ( 'crm' === $active_tab ) {
			$settings['request_rate_limit_count']             = $this->bounded_absint( $input, 'request_rate_limit_count', 1, 100, $defaults['request_rate_limit_count'] );
			$settings['request_rate_limit_minutes']           = $this->bounded_absint( $input, 'request_rate_limit_minutes', 1, 1440, $defaults['request_rate_limit_minutes'] );
			$settings['stale_request_hours']                  = $this->bounded_absint( $input, 'stale_request_hours', 1, 720, $defaults['stale_request_hours'] );
			$settings['requests_per_page']                    = $this->bounded_absint( $input, 'requests_per_page', 5, 100, $defaults['requests_per_page'] );
			$settings['allow_manager_note_on_closed_request'] = $this->checkbox( $input, 'allow_manager_note_on_closed_request' );
		} elseif ( 'staff' === $active_tab ) {
			$time = isset( $input['daily_report_reminder_time'] ) ? sanitize_text_field( $input['daily_report_reminder_time'] ) : '';
			if ( '' !== $time && ! preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $time ) ) {
				$time = '';
				CRPCRM_Logger::warning( 'settings_validation_failed', 'settings_validation_failed', array( 'field' => 'daily_report_reminder_time', 'user_id' => get_current_user_id() ) );
			}
			$settings['staff_portal_enabled']       = $this->checkbox( $input, 'staff_portal_enabled' );
			$settings['daily_report_required']      = $this->checkbox( $input, 'daily_report_required' );
			$settings['daily_report_reminder_time'] = $time;
			$settings['staff_items_per_page']       = $this->bounded_absint( $input, 'staff_items_per_page', 5, 100, $defaults['staff_items_per_page'] );
		} elseif ( 'notifications' === $active_tab ) {
			$settings['notifications_toast_enabled'] = $this->checkbox( $input, 'notifications_toast_enabled' );
		} elseif ( 'maintenance' === $active_tab ) {
			$log_level = isset( $input['log_level'] ) ? sanitize_key( $input['log_level'] ) : $defaults['log_level'];
			if ( ! in_array( $log_level, array( 'debug', 'info', 'warning', 'error' ), true ) ) {
				$log_level = $defaults['log_level'];
				CRPCRM_Logger::warning( 'settings_validation_failed', 'settings_validation_failed', array( 'field' => 'log_level', 'user_id' => get_current_user_id() ) );
			}
			$settings['delete_data_on_uninstall'] = $this->checkbox( $input, 'delete_data_on_uninstall' );
			$settings['log_retention_days']       = $this->bounded_absint( $input, 'log_retention_days', 1, 3650, $defaults['log_retention_days'] );
			$settings['log_level']                = $log_level;
		}

		return wp_parse_args( $settings, $defaults );
	}

	private function checkbox( $input, $key ) {
		return isset( $input[ $key ] ) && 'yes' === $input[ $key ] ? 'yes' : 'no';
	}

	private function bounded_absint( $input, $key, $min, $max, $default ) {
		$value = isset( $input[ $key ] ) ? absint( $input[ $key ] ) : absint( $default );
		return min( absint( $max ), max( absint( $min ), $value ) );
	}

	private function sanitize_domains( $value ) {
		$domains = preg_split( '/\r\n|\r|\n/', (string) $value );
		$clean   = array();
		foreach ( $domains as $domain ) {
			$domain = strtolower( trim( sanitize_text_field( $domain ) ) );
			$domain = preg_replace( '/^https?:\/\//', '', $domain );
			$domain = preg_replace( '/\/.*$/', '', $domain );
			if ( '' !== $domain ) {
				$clean[] = $domain;
			}
		}
		return implode( "\n", array_unique( $clean ) );
	}
}

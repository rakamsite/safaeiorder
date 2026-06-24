<?php
/**
 * OTP application service.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_OTP_Service {
	const STATE_TRANSIENT_PREFIX = 'crpcrm_otp_state_';
	const DEFAULT_PHONE_WINDOW_LIMIT = 6;
	const DEFAULT_IP_WINDOW_LIMIT    = 50;
	const DEFAULT_WINDOW_MINUTES     = 15;
	const DEFAULT_RESEND_SECONDS     = 60;
	const RATE_LIMIT_STATUSES        = array( 'created', 'sent', 'verified', 'blocked', 'expired' );

	private $repository;
	private $customer_repository;
	private $provider;
	private $provider_registry;

	public function __construct( CRPCRM_OTP_Repository $repository = null, CRPCRM_Customer_Repository $customer_repository = null, CRPCRM_SMS_Provider_Interface $provider = null ) {
		$this->repository          = $repository ? $repository : new CRPCRM_OTP_Repository();
		$this->customer_repository = $customer_repository ? $customer_repository : new CRPCRM_Customer_Repository();
		$this->provider_registry   = CRPCRM_SMS_Provider_Registry::get_instance();
		$this->provider            = $provider;
	}

	public function request_code( $phone ) {
		if ( ! CRPCRM_Feature_Manager::is_enabled( 'otp' ) ) {
			return new WP_Error( 'otp_disabled', CRPCRM_Feature_Manager::disabled_message() );
		}
		if ( ! CRPCRM_Feature_Manager::is_enabled( 'sms' ) ) {
			return new WP_Error( 'sms_disabled', CRPCRM_Feature_Manager::disabled_message() );
		}

		$phone_normalized = CRPCRM_Helpers::normalize_iran_phone( $phone );
		if ( ! CRPCRM_Helpers::is_valid_iran_phone_normalized( $phone_normalized ) ) {
			return new WP_Error( 'invalid_phone', 'شماره موبایل واردشده معتبر نیست.' );
		}

		CRPCRM_Logger::info( 'otp_requested', 'otp', array( 'phone_hash' => $this->hash_value( $phone_normalized ) ) );

		if ( ( ! CRPCRM_Feature_Manager::is_enabled( 'customer_registration' ) || 'yes' !== CRPCRM_Settings::get( 'customer_registration_enabled', 'yes' ) ) && ! $this->find_existing_user_by_phone( $phone_normalized ) ) {
			CRPCRM_Logger::info( 'otp_registration_disabled_for_new_phone', 'otp', array( 'phone_hash' => $this->hash_value( $phone_normalized ) ) );
			return new WP_Error( 'registration_disabled', 'در حال حاضر ثبت‌نام کاربران جدید غیرفعال است.' );
		}

		$rate_check = $this->check_rate_limit( $phone_normalized );
		if ( is_wp_error( $rate_check ) ) {
			return $rate_check;
		}

		$code          = (string) wp_rand( 100, 999 );
		$token         = wp_generate_password( 48, false, false );
		$expiration    = max( 1, absint( CRPCRM_Settings::get( 'otp_expiration_minutes', 2 ) ) );
		$expires_at    = CRPCRM_Helpers::add_seconds_to_now( $expiration * MINUTE_IN_SECONDS );
		$ip_hash       = $this->current_ip_hash();
		$ua_hash       = $this->current_user_agent_hash();

		$otp_id = $this->repository->create(
			array(
				'phone_normalized'        => $phone_normalized,
				'otp_hash'                => wp_hash_password( $code ),
				'verification_token_hash' => wp_hash_password( $token ),
				'status'                  => 'created',
				'provider'                => $this->provider ? sanitize_key( $this->provider->get_id() ) : $this->provider_registry->get_active_provider_id(),
				'ip_hash'                 => $ip_hash,
				'user_agent_hash'         => $ua_hash,
				'expires_at'              => $expires_at,
			)
		);

		if ( ! $otp_id ) {
			return new WP_Error( 'otp_create_failed', 'ارسال کد تأیید با مشکل مواجه شد. لطفاً بعداً دوباره تلاش کنید.' );
		}

		if ( $this->can_log_debug_otp() ) {
			CRPCRM_Logger::debug( 'otp_debug_code', 'otp_debug_code', array( 'otp_id' => $otp_id, 'phone_hash' => $this->hash_value( $phone_normalized ), 'code' => $code ) );
		}

		$send_result = $this->provider ? $this->provider->send_otp( $phone_normalized, $code ) : $this->provider_registry->send( $phone_normalized, 'otp', array( 'code' => $code ) );
		if ( is_wp_error( $send_result ) ) {
			$this->repository->update(
				$otp_id,
				array(
					'status'            => 'send_failed',
					'provider_response' => $send_result->get_error_data() ? $send_result->get_error_data() : array( 'message' => $send_result->get_error_message() ),
				)
			);
			CRPCRM_Logger::warning( 'otp_send_failed', 'otp', array( 'otp_id' => $otp_id, 'code' => $send_result->get_error_code() ) );

			if ( $this->can_use_debug_delivery_fallback() ) {
				CRPCRM_Logger::debug( 'development_otp_code', 'otp', array( 'otp_id' => $otp_id, 'phone_hash' => $this->hash_value( $phone_normalized ), 'code' => $code ) );
				$this->repository->update( $otp_id, array( 'status' => 'sent' ) );
				CRPCRM_Logger::info( 'otp_sent', 'otp', array( 'otp_id' => $otp_id, 'phone_hash' => $this->hash_value( $phone_normalized ), 'transport' => 'development_log' ) );
			} else {
				return new WP_Error( 'otp_send_failed', 'ارسال کد تأیید با مشکل مواجه شد. لطفاً بعداً دوباره تلاش کنید.' );
			}
		} else {
			$this->repository->update( $otp_id, array( 'status' => 'sent', 'provider_response' => array( 'sent' => true ) ) );
			CRPCRM_Logger::info( 'otp_sent', 'otp', array( 'otp_id' => $otp_id, 'phone_hash' => $this->hash_value( $phone_normalized ) ) );
		}

		$state_token = wp_generate_password( 48, false, false );
		set_transient(
			$this->state_transient_key( $state_token ),
			array(
				'otp_id' => $otp_id,
				'token'  => $token,
				'phone'  => $phone_normalized,
			),
			$expiration * MINUTE_IN_SECONDS
		);

		return array(
			'otp_id'           => $otp_id,
			'state_token'      => $state_token,
			'phone_normalized' => $phone_normalized,
		);
	}

	public function resend_code( $state_token ) {
		if ( ! CRPCRM_Feature_Manager::is_enabled( 'otp' ) ) {
			return new WP_Error( 'otp_disabled', CRPCRM_Feature_Manager::disabled_message() );
		}

		$state = $this->get_state( $state_token );
		if ( is_wp_error( $state ) ) {
			return $state;
		}

		return $this->request_code( $state['phone'] );
	}

	public function verify_code( $state_token, $code ) {
		if ( ! CRPCRM_Feature_Manager::is_enabled( 'otp' ) ) {
			return new WP_Error( 'otp_disabled', CRPCRM_Feature_Manager::disabled_message() );
		}

		$state = $this->get_state( $state_token );
		if ( is_wp_error( $state ) ) {
			return $state;
		}

		$code = preg_replace( '/\D+/', '', CRPCRM_Helpers::convert_digits_public( $code ) );
		if ( ! preg_match( '/^\d{3}$/', $code ) ) {
			return new WP_Error( 'invalid_code', 'کد واردشده صحیح نیست.' );
		}

		$otp = $this->repository->find( $state['otp_id'] );
		if ( ! $otp || empty( $otp['verification_token_hash'] ) || ! wp_check_password( $state['token'], $otp['verification_token_hash'] ) ) {
			return new WP_Error( 'invalid_state', 'کد تأیید منقضی شده است.' );
		}

		if ( in_array( $otp['status'], array( 'blocked', 'verified', 'expired' ), true ) ) {
			$message = 'expired' === $otp['status'] ? 'کد تأیید منقضی شده است.' : 'کد واردشده صحیح نیست.';
			return new WP_Error( 'invalid_otp_status', $message );
		}

		if ( ! empty( $otp['expires_at'] ) && CRPCRM_Helpers::datetime_to_timestamp( $otp['expires_at'] ) < CRPCRM_Helpers::current_timestamp() ) {
			$this->repository->update( $otp['id'], array( 'status' => 'expired' ) );
			return new WP_Error( 'expired', 'کد تأیید منقضی شده است.' );
		}

		if ( ! wp_check_password( $code, $otp['otp_hash'] ) ) {
			$this->repository->increment_attempts( $otp['id'] );
			$attempts     = absint( $otp['attempts'] ) + 1;
			$max_attempts = max( 1, absint( CRPCRM_Settings::get( 'otp_max_attempts', 5 ) ) );
			CRPCRM_Logger::info( 'otp_failed_attempt', 'otp', array( 'otp_id' => absint( $otp['id'] ), 'attempts' => $attempts ) );

			if ( $attempts >= $max_attempts ) {
				$this->repository->update( $otp['id'], array( 'status' => 'blocked' ) );
				CRPCRM_Logger::warning( 'otp_blocked', 'otp', array( 'otp_id' => absint( $otp['id'] ) ) );
			}

			return new WP_Error( 'wrong_code', 'کد واردشده صحیح نیست.' );
		}

		if ( ( ! CRPCRM_Feature_Manager::is_enabled( 'customer_registration' ) || 'yes' !== CRPCRM_Settings::get( 'customer_registration_enabled', 'yes' ) ) && ! $this->find_existing_user_by_phone( $otp['phone_normalized'] ) ) {
			CRPCRM_Logger::info( 'customer_registration_disabled', 'customer', array( 'phone_hash' => $this->hash_value( $otp['phone_normalized'] ) ) );
			return new WP_Error( 'registration_disabled', 'در حال حاضر ثبت‌نام کاربران جدید غیرفعال است.' );
		}

		$this->repository->update(
			$otp['id'],
			array(
				'status'      => 'verified',
				'verified_at' => CRPCRM_Helpers::current_datetime(),
			)
		);
		delete_transient( $this->state_transient_key( $state_token ) );
		CRPCRM_Logger::info( 'otp_verified', 'otp', array( 'otp_id' => absint( $otp['id'] ) ) );

		$user = $this->find_or_create_user( $otp['phone_normalized'] );
		if ( is_wp_error( $user ) ) {
			return $user;
		}

		$customer_id = $this->ensure_customer_record( $user->ID, $otp['phone_normalized'] );
		$this->apply_current_attribution_after_login( $customer_id, $user->ID );

		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true );
		do_action( 'wp_login', $user->user_login, $user );
		CRPCRM_Logger::info( 'customer_logged_in', 'customer', array( 'user_id' => $user->ID ) );

		return $user;
	}

	public function ensure_customer_record( $user_id, $phone_normalized = '' ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return false;
		}

		if ( '' !== $phone_normalized ) {
			$phone_normalized = CRPCRM_Helpers::normalize_iran_phone( $phone_normalized );
			if ( CRPCRM_Helpers::is_valid_iran_phone_normalized( $phone_normalized ) ) {
				update_user_meta( $user_id, 'crpcrm_phone_normalized', $phone_normalized );
			}
		}

		$customer = $this->customer_repository->ensure_for_user( $user_id );
		if ( is_wp_error( $customer ) || ! $customer ) {
			return false;
		}

		return absint( $customer['id'] );
	}

	public function get_state( $state_token ) {
		$state_token = sanitize_text_field( $state_token );
		if ( '' === $state_token ) {
			return new WP_Error( 'missing_state', 'کد تأیید منقضی شده است.' );
		}

		$state = get_transient( $this->state_transient_key( $state_token ) );
		if ( ! is_array( $state ) || empty( $state['otp_id'] ) || empty( $state['token'] ) || empty( $state['phone'] ) ) {
			return new WP_Error( 'invalid_state', 'کد تأیید منقضی شده است.' );
		}

		return $state;
	}


	private function apply_current_attribution_after_login( $customer_id, $user_id ) {
		$customer_id = absint( $customer_id );
		$user_id     = absint( $user_id );

		if ( ! $customer_id || ! class_exists( 'CRPCRM_Attribution_Service' ) ) {
			return;
		}

		$attribution_service = new CRPCRM_Attribution_Service();
		$attribution         = $attribution_service->get_current_attribution();
		if ( ! $attribution ) {
			$attribution_service->persist_customer_landing_attribution( $customer_id, $user_id );
			return;
		}

		$attribution_service->apply_to_customer( $customer_id, $user_id, $attribution );
		$attribution_service->persist_customer_landing_attribution( $customer_id, $user_id );
		$attribution_service->record_event( $attribution, $customer_id, $user_id, true );
	}

	private function find_or_create_user( $phone_normalized ) {
		$user = $this->find_existing_user_by_phone( $phone_normalized );

		if ( $user ) {
			update_user_meta( $user->ID, 'crpcrm_phone_normalized', $phone_normalized );
			return $user;
		}

		if ( ! CRPCRM_Feature_Manager::is_enabled( 'customer_registration' ) || 'yes' !== CRPCRM_Settings::get( 'customer_registration_enabled', 'yes' ) ) {
			CRPCRM_Logger::info( 'customer_registration_disabled', 'customer', array( 'phone_hash' => $this->hash_value( $phone_normalized ) ) );
			return new WP_Error( 'registration_disabled', 'در حال حاضر ثبت‌نام کاربران جدید غیرفعال است.' );
		}

		$user_id = wp_insert_user(
			array(
				'user_login'   => $phone_normalized,
				'user_pass'    => wp_generate_password( 32, true, true ),
				'role'         => 'customer',
				'display_name' => $phone_normalized,
			)
		);

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		update_user_meta( $user_id, 'crpcrm_phone_normalized', $phone_normalized );
		CRPCRM_Logger::info( 'customer_user_created', 'customer', array( 'user_id' => $user_id ) );

		return get_user_by( 'id', $user_id );
	}

	private function find_existing_user_by_phone( $phone_normalized ) {
		$phone_normalized = CRPCRM_Helpers::normalize_iran_phone( $phone_normalized );
		$user             = get_user_by( 'login', $phone_normalized );

		if ( $user ) {
			return $user;
		}

		$users = get_users(
			array(
				'meta_key'    => 'crpcrm_phone_normalized',
				'meta_value'  => $phone_normalized,
				'number'      => 1,
				'count_total' => false,
			)
		);

		if ( ! empty( $users ) ) {
			return $users[0];
		}

		$customer = $this->customer_repository->find_by_phone_normalized( $phone_normalized );
		if ( $customer && ! empty( $customer['user_id'] ) ) {
			$customer_user = get_user_by( 'id', absint( $customer['user_id'] ) );
			if ( $customer_user ) {
				return $customer_user;
			}
		}

		return false;
	}

	private function check_rate_limit( $phone_normalized ) {
		$now            = CRPCRM_Helpers::current_timestamp();
		$resend_seconds = $this->get_resend_seconds();
		$window_minutes = $this->get_rate_limit_window_minutes();
		$window_seconds = $window_minutes * MINUTE_IN_SECONDS;
		$since          = CRPCRM_Helpers::subtract_seconds_from_now( $window_seconds );
		$latest         = $this->repository->find_latest_active_by_phone( $phone_normalized, self::RATE_LIMIT_STATUSES );

		if ( $latest && ! empty( $latest['created_at'] ) ) {
			$elapsed = $now - CRPCRM_Helpers::datetime_to_timestamp( $latest['created_at'] );
			if ( $elapsed < $resend_seconds ) {
				$remaining = max( 1, $resend_seconds - $elapsed );
				return new WP_Error( 'otp_resend_too_soon', sprintf( 'برای این شماره %d ثانیه دیگر دوباره تلاش کنید.', $remaining ) );
			}
		}

		$phone_requests = $this->repository->count_recent_by_phone( $phone_normalized, $since, self::RATE_LIMIT_STATUSES );
		if ( $phone_requests >= $this->get_phone_rate_limit() ) {
			$oldest_phone = $this->repository->find_oldest_recent_by_phone( $phone_normalized, $since, self::RATE_LIMIT_STATUSES );
			$remaining    = $this->get_rate_limit_window_remaining_seconds( $oldest_phone['created_at'] ?? '', $window_seconds, $now );
			return new WP_Error( 'otp_phone_rate_limited', sprintf( 'برای این شماره تعداد درخواست‌ها به سقف رسیده است. %s دیگر دوباره تلاش کنید.', $this->format_wait_time( $remaining ) ) );
		}

		if ( $phone_requests >= $this->get_phone_rate_limit() ) {
			return new WP_Error( 'otp_phone_rate_limited', 'برای این شماره فعلاً درخواست‌های زیادی ثبت شده است. کمی بعد دوباره تلاش کنید.' );
		}

		$ip_hash = $this->current_ip_hash();
		$ip_requests = '' !== $ip_hash ? $this->repository->count_recent_by_ip_hash( $ip_hash, $since, self::RATE_LIMIT_STATUSES ) : 0;
		if ( '' !== $ip_hash && $ip_requests >= $this->get_ip_rate_limit() ) {
			$oldest_ip = $this->repository->find_oldest_recent_by_ip_hash( $ip_hash, $since, self::RATE_LIMIT_STATUSES );
			$remaining = $this->get_rate_limit_window_remaining_seconds( $oldest_ip['created_at'] ?? '', $window_seconds, $now );
			return new WP_Error( 'otp_ip_rate_limited', sprintf( 'از این اتصال در بازه کوتاه درخواست‌های زیادی ثبت شده است. %s دیگر دوباره تلاش کنید.', $this->format_wait_time( $remaining ) ) );
		}
		if ( '' !== $ip_hash && $ip_requests >= $this->get_ip_rate_limit() ) {
			return new WP_Error( 'otp_ip_rate_limited', 'از این اتصال درخواست‌های زیادی ثبت شده است. کمی بعد دوباره تلاش کنید.' );
		}

		return true;
	}

	private function can_log_debug_otp() {
		return 'yes' === CRPCRM_Settings::get( 'otp_debug_mode', 'no' ) && $this->is_debug_delivery_environment();
	}

	private function can_use_debug_delivery_fallback() {
		return $this->is_debug_delivery_environment();
	}

	private function is_debug_delivery_environment() {
		$environment = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';
		if ( in_array( $environment, array( 'local', 'development' ), true ) ) {
			return true;
		}

		return 'production' !== $environment && 'yes' === CRPCRM_Settings::get( 'otp_debug_mode', 'no' );
	}

	private function get_rate_limit_window_remaining_seconds( $created_at, $window_seconds, $now ) {
		if ( empty( $created_at ) ) {
			return max( MINUTE_IN_SECONDS, absint( $window_seconds ) );
		}

		$elapsed = max( 0, $now - CRPCRM_Helpers::datetime_to_timestamp( $created_at ) );
		return max( MINUTE_IN_SECONDS, absint( $window_seconds ) - $elapsed );
	}

	private function format_wait_time( $seconds ) {
		$seconds = max( 1, absint( $seconds ) );
		if ( $seconds < MINUTE_IN_SECONDS ) {
			return sprintf( '%d ثانیه', $seconds );
		}

		$minutes = (int) ceil( $seconds / MINUTE_IN_SECONDS );
		return sprintf( '%d دقیقه', $minutes );
	}

	private function current_ip_hash() {
		$ip = $this->current_ip_address();
		return '' === $ip ? '' : $this->hash_value( $ip );
	}

	private function current_ip_address() {
		// REMOTE_ADDR is the only trusted IP source for now; proxy/CDN support can be added later behind an allowlist.
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	}

	private function current_user_agent_hash() {
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		return $this->hash_value( $user_agent );
	}

	private function hash_value( $value ) {
		return hash_hmac( 'sha256', (string) $value, wp_salt( 'auth' ) );
	}

	private function state_transient_key( $state_token ) {
		return self::STATE_TRANSIENT_PREFIX . hash_hmac( 'sha256', sanitize_text_field( $state_token ), wp_salt( 'nonce' ) );
	}

	private function get_phone_rate_limit() {
		return min( 20, max( 1, absint( CRPCRM_Settings::get( 'otp_rate_limit_phone_limit', self::DEFAULT_PHONE_WINDOW_LIMIT ) ) ) );
	}

	private function get_ip_rate_limit() {
		return min( 200, max( 1, absint( CRPCRM_Settings::get( 'otp_rate_limit_ip_limit', self::DEFAULT_IP_WINDOW_LIMIT ) ) ) );
	}

	private function get_rate_limit_window_minutes() {
		return min( 1440, max( 1, absint( CRPCRM_Settings::get( 'otp_rate_limit_window_minutes', self::DEFAULT_WINDOW_MINUTES ) ) ) );
	}

	private function get_resend_seconds() {
		return min( 600, max( 10, absint( CRPCRM_Settings::get( 'otp_resend_seconds', self::DEFAULT_RESEND_SECONDS ) ) ) );
	}
}

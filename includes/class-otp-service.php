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
	const PHONE_WINDOW_LIMIT     = 5;
	const IP_WINDOW_LIMIT        = 10;
	const WINDOW_MINUTES         = 15;

	private $repository;
	private $customer_repository;
	private $provider;

	public function __construct( CRPCRM_OTP_Repository $repository = null, CRPCRM_Customer_Repository $customer_repository = null, CRPCRM_SMS_Provider_Interface $provider = null ) {
		$this->repository          = $repository ? $repository : new CRPCRM_OTP_Repository();
		$this->customer_repository = $customer_repository ? $customer_repository : new CRPCRM_Customer_Repository();
		$this->provider            = $provider ? $provider : $this->make_provider();
	}

	public function request_code( $phone ) {
		$phone_normalized = CRPCRM_Helpers::normalize_iran_phone( $phone );
		if ( ! CRPCRM_Helpers::is_valid_iran_phone_normalized( $phone_normalized ) ) {
			return new WP_Error( 'invalid_phone', 'شماره موبایل واردشده معتبر نیست.' );
		}

		CRPCRM_Logger::info( 'otp_requested', 'otp', array( 'phone_hash' => $this->hash_value( $phone_normalized ) ) );

		if ( 'yes' !== CRPCRM_Settings::get( 'customer_registration_enabled', 'yes' ) && ! $this->find_existing_user_by_phone( $phone_normalized ) ) {
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
		$expires_stamp = current_time( 'timestamp' ) + ( $expiration * MINUTE_IN_SECONDS );
		$ip_hash       = $this->current_ip_hash();
		$ua_hash       = $this->current_user_agent_hash();

		$otp_id = $this->repository->create(
			array(
				'phone_normalized'        => $phone_normalized,
				'otp_hash'                => wp_hash_password( $code ),
				'verification_token_hash' => wp_hash_password( $token ),
				'status'                  => 'created',
				'provider'                => CRPCRM_Settings::get( 'otp_provider', 'melipayamak' ),
				'ip_hash'                 => $ip_hash,
				'user_agent_hash'         => $ua_hash,
				'expires_at'              => date( 'Y-m-d H:i:s', $expires_stamp ),
			)
		);

		if ( ! $otp_id ) {
			return new WP_Error( 'otp_create_failed', 'ارسال کد تأیید با مشکل مواجه شد. لطفاً بعداً دوباره تلاش کنید.' );
		}

		if ( 'yes' === CRPCRM_Settings::get( 'otp_debug_mode', 'no' ) ) {
			CRPCRM_Logger::debug( 'otp_debug_code', 'otp_debug_code', array( 'otp_id' => $otp_id, 'phone_hash' => $this->hash_value( $phone_normalized ), 'code' => $code ) );
		}

		$send_result = $this->provider->send_otp( $phone_normalized, $code );
		if ( is_wp_error( $send_result ) ) {
			$this->repository->update(
				$otp_id,
				array(
					'status'            => 'send_failed',
					'provider_response' => $send_result->get_error_data() ? $send_result->get_error_data() : array( 'message' => $send_result->get_error_message() ),
				)
			);
			CRPCRM_Logger::warning( 'otp_send_failed', 'otp', array( 'otp_id' => $otp_id, 'code' => $send_result->get_error_code() ) );

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
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
		$state = $this->get_state( $state_token );
		if ( is_wp_error( $state ) ) {
			return $state;
		}

		return $this->request_code( $state['phone'] );
	}

	public function verify_code( $state_token, $code ) {
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

		if ( ! empty( $otp['expires_at'] ) && strtotime( $otp['expires_at'] ) < current_time( 'timestamp' ) ) {
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

		if ( 'yes' !== CRPCRM_Settings::get( 'customer_registration_enabled', 'yes' ) && ! $this->find_existing_user_by_phone( $otp['phone_normalized'] ) ) {
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
			return;
		}

		$attribution_service->apply_to_customer( $customer_id, $user_id, $attribution );
		$attribution_service->record_event( $attribution, $customer_id, $user_id, true );
	}

	private function find_or_create_user( $phone_normalized ) {
		$user = $this->find_existing_user_by_phone( $phone_normalized );

		if ( $user ) {
			update_user_meta( $user->ID, 'crpcrm_phone_normalized', $phone_normalized );
			return $user;
		}

		if ( 'yes' !== CRPCRM_Settings::get( 'customer_registration_enabled', 'yes' ) ) {
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
		$now            = current_time( 'timestamp' );
		$resend_seconds = max( 1, absint( CRPCRM_Settings::get( 'otp_resend_seconds', 60 ) ) );
		$latest         = $this->repository->find_latest_active_by_phone( $phone_normalized );

		if ( $latest && ! empty( $latest['created_at'] ) && ( $now - strtotime( $latest['created_at'] ) ) < $resend_seconds ) {
			return new WP_Error( 'rate_limited', 'تعداد درخواست‌های کد تأیید زیاد است. لطفاً کمی بعد دوباره تلاش کنید.' );
		}

		$since = date( 'Y-m-d H:i:s', $now - ( self::WINDOW_MINUTES * MINUTE_IN_SECONDS ) );
		if ( $this->repository->count_recent_by_phone( $phone_normalized, $since ) >= self::PHONE_WINDOW_LIMIT ) {
			return new WP_Error( 'rate_limited', 'تعداد درخواست‌های کد تأیید زیاد است. لطفاً کمی بعد دوباره تلاش کنید.' );
		}

		if ( $this->repository->count_recent_by_ip_hash( $this->current_ip_hash(), $since ) >= self::IP_WINDOW_LIMIT ) {
			return new WP_Error( 'rate_limited', 'تعداد درخواست‌های کد تأیید زیاد است. لطفاً کمی بعد دوباره تلاش کنید.' );
		}

		return true;
	}

	private function make_provider() {
		$provider = CRPCRM_Settings::get( 'otp_provider', 'melipayamak' );
		if ( 'melipayamak' === $provider ) {
			return new CRPCRM_Melipayamak_Provider();
		}

		return new CRPCRM_Melipayamak_Provider();
	}

	private function current_ip_hash() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return $this->hash_value( $ip );
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
}

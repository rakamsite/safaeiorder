<?php
/**
 * Front-end customer portal shortcode.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_Portal_Shortcode {
	private $otp_service;
	private $customer_repository;

	public function __construct( CRPCRM_OTP_Service $otp_service = null ) {
		$this->otp_service          = $otp_service ? $otp_service : new CRPCRM_OTP_Service();
		$this->customer_repository = new CRPCRM_Customer_Repository();
	}

	public function register_hooks() {
		add_shortcode( 'crpcrm_portal', array( $this, 'render' ) );
		add_action( 'admin_post_nopriv_crpcrm_request_otp', array( $this, 'handle_request_otp' ) );
		add_action( 'admin_post_crpcrm_request_otp', array( $this, 'handle_request_otp' ) );
		add_action( 'admin_post_nopriv_crpcrm_verify_otp', array( $this, 'handle_verify_otp' ) );
		add_action( 'admin_post_crpcrm_verify_otp', array( $this, 'handle_verify_otp' ) );
		add_action( 'admin_post_nopriv_crpcrm_resend_otp', array( $this, 'handle_resend_otp' ) );
		add_action( 'admin_post_crpcrm_resend_otp', array( $this, 'handle_resend_otp' ) );
		add_action( 'admin_post_nopriv_crpcrm_change_otp_phone', array( $this, 'handle_change_phone' ) );
		add_action( 'admin_post_crpcrm_change_otp_phone', array( $this, 'handle_change_phone' ) );
		add_action( 'admin_post_crpcrm_portal_logout', array( $this, 'handle_logout' ) );
		add_action( 'admin_post_crpcrm_save_profile', array( $this, 'handle_save_profile' ) );
	}

	public function render() {
		$this->enqueue_assets();
		$notice = $this->get_notice();

		if ( is_user_logged_in() ) {
			$user_id         = get_current_user_id();
			$customer        = $this->ensure_current_customer( $user_id );
			$logout_url      = wp_nonce_url( admin_url( 'admin-post.php?action=crpcrm_portal_logout&redirect_to=' . rawurlencode( $this->current_url() ) ), 'crpcrm_portal_logout' );
			$base_portal_url  = remove_query_arg( 'crpcrm_page', $this->clean_portal_url() );
			$profile_url      = add_query_arg( 'crpcrm_page', 'profile', $base_portal_url );

			if ( is_wp_error( $customer ) || ! $customer ) {
				CRPCRM_Logger::warning( 'customer_record_missing', 'customer', array( 'user_id' => $user_id ) );
				return $this->render_view(
					'portal-placeholder.php',
					array(
						'notice'              => array( 'type' => 'error', 'message' => 'اطلاعات حساب شما کامل نیست. لطفاً دوباره وارد شوید.' ),
						'logout_url'          => $logout_url,
						'placeholder_message' => 'اطلاعات حساب شما کامل نیست. لطفاً دوباره وارد شوید.',
					)
				);
			}

			$is_profile_page = isset( $_GET['crpcrm_page'] ) && 'profile' === sanitize_key( wp_unslash( $_GET['crpcrm_page'] ) );
			if ( ! absint( $customer['profile_completed'] ) || $is_profile_page ) {
				CRPCRM_Logger::info( 'customer_profile_viewed', 'customer', array( 'user_id' => $user_id, 'customer_id' => absint( $customer['id'] ), 'mode' => absint( $customer['profile_completed'] ) ? 'edit' : 'complete' ) );

				return $this->render_view(
					'profile-form.php',
					array(
						'notice'             => $notice,
						'logout_url'         => $logout_url,
						'customer'           => $customer,
						'phone_display'      => $this->format_phone_for_display( ! empty( $customer['phone_normalized'] ) ? $customer['phone_normalized'] : $customer['phone'] ),
						'provinces'          => $this->get_iran_provinces(),
						'is_edit'            => (bool) absint( $customer['profile_completed'] ),
						'admin_post_url'     => admin_url( 'admin-post.php' ),
						'portal_redirect_to' => $is_profile_page && absint( $customer['profile_completed'] ) ? $profile_url : $base_portal_url,
					)
				);
			}

			return $this->render_view( 'portal-placeholder.php', array( 'notice' => $notice, 'logout_url' => $logout_url, 'profile_url' => $profile_url ) );
		}

		$state_token = isset( $_GET['crpcrm_otp_state'] ) ? sanitize_text_field( wp_unslash( $_GET['crpcrm_otp_state'] ) ) : '';
		if ( $state_token ) {
			$state = $this->otp_service->get_state( $state_token );
			if ( ! is_wp_error( $state ) ) {
				return $this->render_view(
					'otp-verify-form.php',
					array(
						'notice'             => $notice,
						'state_token'        => $state_token,
						'phone_normalized'   => $state['phone'],
						'resend_seconds'     => absint( CRPCRM_Settings::get( 'otp_resend_seconds', 60 ) ),
						'admin_post_url'     => admin_url( 'admin-post.php' ),
						'portal_redirect_to' => $this->clean_portal_url(),
					)
				);
			}
			$notice = array( 'type' => 'error', 'message' => $state->get_error_message() );
		}

		return $this->render_view(
			'otp-phone-form.php',
			array(
				'notice'             => $notice,
				'admin_post_url'     => admin_url( 'admin-post.php' ),
				'portal_redirect_to' => $this->clean_portal_url(),
			)
		);
	}

	public function handle_request_otp() {
		check_admin_referer( 'crpcrm_request_otp', 'crpcrm_otp_nonce' );
		$redirect_to = $this->posted_redirect_url();
		$phone       = isset( $_POST['crpcrm_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['crpcrm_phone'] ) ) : '';
		$result      = $this->otp_service->request_code( $phone );

		if ( is_wp_error( $result ) ) {
			$this->redirect_with_notice( $redirect_to, 'error', $result->get_error_message() );
		}

		$url = add_query_arg( 'crpcrm_otp_state', $result['state_token'], $redirect_to );
		$this->redirect_with_notice( $url, 'success', 'کد تأیید به شماره ' . $this->format_phone_for_display( $result['phone_normalized'] ) . ' ارسال شد.' );
	}

	public function handle_resend_otp() {
		check_admin_referer( 'crpcrm_resend_otp', 'crpcrm_otp_nonce' );
		$redirect_to = $this->posted_redirect_url();
		$state_token = isset( $_POST['crpcrm_otp_state'] ) ? sanitize_text_field( wp_unslash( $_POST['crpcrm_otp_state'] ) ) : '';
		$result      = $this->otp_service->resend_code( $state_token );

		if ( is_wp_error( $result ) ) {
			$this->redirect_with_notice( add_query_arg( 'crpcrm_otp_state', $state_token, $redirect_to ), 'error', $result->get_error_message() );
		}

		$url = add_query_arg( 'crpcrm_otp_state', $result['state_token'], $redirect_to );
		$this->redirect_with_notice( $url, 'success', 'کد تأیید دوباره ارسال شد.' );
	}

	public function handle_verify_otp() {
		check_admin_referer( 'crpcrm_verify_otp', 'crpcrm_otp_nonce' );
		$redirect_to = $this->posted_redirect_url();
		$state_token = isset( $_POST['crpcrm_otp_state'] ) ? sanitize_text_field( wp_unslash( $_POST['crpcrm_otp_state'] ) ) : '';
		$code        = isset( $_POST['crpcrm_otp_code'] ) ? sanitize_text_field( wp_unslash( $_POST['crpcrm_otp_code'] ) ) : '';
		$result      = $this->otp_service->verify_code( $state_token, $code );

		if ( is_wp_error( $result ) ) {
			$this->redirect_with_notice( add_query_arg( 'crpcrm_otp_state', $state_token, $redirect_to ), 'error', $result->get_error_message() );
		}

		$this->redirect_with_notice( $redirect_to, 'success', 'ورود شما با موفقیت انجام شد.' );
	}

	public function handle_save_profile() {
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( $this->clean_portal_url() );
			exit;
		}

		$redirect_to = $this->posted_redirect_url();
		$user_id     = get_current_user_id();

		if ( ! isset( $_POST['crpcrm_profile_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['crpcrm_profile_nonce'] ) ), 'crpcrm_save_profile' ) ) {
			CRPCRM_Logger::warning( 'customer_profile_validation_failed', 'customer', array( 'user_id' => $user_id, 'reason' => 'nonce' ) );
			$this->redirect_with_notice( $redirect_to, 'error', 'خطایی در ذخیره اطلاعات رخ داد. لطفاً دوباره تلاش کنید.' );
		}

		$customer = $this->ensure_current_customer( $user_id );
		if ( is_wp_error( $customer ) || ! $customer ) {
			CRPCRM_Logger::warning( 'customer_record_missing', 'customer', array( 'user_id' => $user_id ) );
			$this->redirect_with_notice( $redirect_to, 'error', 'اطلاعات حساب شما کامل نیست. لطفاً دوباره وارد شوید.' );
		}

		$full_name = isset( $_POST['crpcrm_full_name'] ) ? sanitize_text_field( wp_unslash( $_POST['crpcrm_full_name'] ) ) : '';
		$province  = isset( $_POST['crpcrm_province'] ) ? sanitize_text_field( wp_unslash( $_POST['crpcrm_province'] ) ) : '';
		$city      = isset( $_POST['crpcrm_city'] ) ? sanitize_text_field( wp_unslash( $_POST['crpcrm_city'] ) ) : '';
		$errors    = array();

		if ( '' === trim( $full_name ) ) {
			$errors[] = 'نام و نام خانوادگی الزامی است.';
		}
		if ( '' === trim( $province ) ) {
			$errors[] = 'استان الزامی است.';
		} elseif ( ! in_array( $province, $this->get_iran_provinces(), true ) ) {
			$errors[] = 'استان انتخاب‌شده معتبر نیست.';
		}
		if ( '' === trim( $city ) ) {
			$errors[] = 'شهر الزامی است.';
		}

		if ( ! empty( $errors ) ) {
			CRPCRM_Logger::info( 'customer_profile_validation_failed', 'customer', array( 'user_id' => $user_id, 'customer_id' => absint( $customer['id'] ), 'error_count' => count( $errors ) ) );
			$this->redirect_with_notice( $redirect_to, 'error', implode( ' ', $errors ) );
		}

		$was_completed = (bool) absint( $customer['profile_completed'] );
		$updated       = $this->customer_repository->update(
			absint( $customer['id'] ),
			array(
				'full_name'         => $full_name,
				'province'          => $province,
				'city'              => $city,
				'profile_completed' => 1,
			)
		);

		if ( ! $updated ) {
			CRPCRM_Logger::error( 'customer_profile_validation_failed', 'customer', array( 'user_id' => $user_id, 'customer_id' => absint( $customer['id'] ), 'reason' => 'db_update_failed' ) );
			$this->redirect_with_notice( $redirect_to, 'error', 'خطایی در ذخیره اطلاعات رخ داد. لطفاً دوباره تلاش کنید.' );
		}

		$this->maybe_update_display_name( $user_id, $full_name, $customer );
		CRPCRM_Logger::info( $was_completed ? 'customer_profile_updated' : 'customer_profile_completed', 'customer', array( 'user_id' => $user_id, 'customer_id' => absint( $customer['id'] ) ) );

		$success_message = $was_completed ? 'پروفایل شما با موفقیت بروزرسانی شد.' : 'اطلاعات شما با موفقیت ذخیره شد.';
		$this->redirect_with_notice( $redirect_to, 'success', $success_message );
	}

	public function handle_change_phone() {
		check_admin_referer( 'crpcrm_change_otp_phone', 'crpcrm_otp_nonce' );
		wp_safe_redirect( $this->posted_redirect_url() );
		exit;
	}

	public function handle_logout() {
		check_admin_referer( 'crpcrm_portal_logout' );
		$redirect_to = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : home_url( '/' );
		wp_logout();
		$this->redirect_with_notice( $redirect_to, 'success', 'با موفقیت از حساب کاربری خارج شدید.' );
	}

	private function ensure_current_customer( $user_id ) {
		$customer = $this->customer_repository->ensure_for_user( $user_id );
		if ( is_wp_error( $customer ) || ! $customer ) {
			return $customer;
		}

		return $customer;
	}

	private function get_iran_provinces() {
		return array(
			'آذربایجان شرقی',
			'آذربایجان غربی',
			'اردبیل',
			'اصفهان',
			'البرز',
			'ایلام',
			'بوشهر',
			'تهران',
			'چهارمحال و بختیاری',
			'خراسان جنوبی',
			'خراسان رضوی',
			'خراسان شمالی',
			'خوزستان',
			'زنجان',
			'سمنان',
			'سیستان و بلوچستان',
			'فارس',
			'قزوین',
			'قم',
			'کردستان',
			'کرمان',
			'کرمانشاه',
			'کهگیلویه و بویراحمد',
			'گلستان',
			'گیلان',
			'لرستان',
			'مازندران',
			'مرکزی',
			'هرمزگان',
			'همدان',
			'یزد',
		);
	}

	private function maybe_update_display_name( $user_id, $full_name, $customer ) {
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return;
		}

		$phone_normalized = ! empty( $customer['phone_normalized'] ) ? $customer['phone_normalized'] : get_user_meta( $user_id, 'crpcrm_phone_normalized', true );
		$phone_display    = $this->format_phone_for_display( $phone_normalized );
		if ( $user->display_name === $phone_normalized || $user->display_name === $phone_display || $user->display_name === $user->user_login ) {
			wp_update_user(
				array(
					'ID'           => $user_id,
					'display_name' => $full_name,
				)
			);
		}
	}

	private function render_view( $view, $args = array() ) {
		extract( $args, EXTR_SKIP );
		ob_start();
		include CRPCRM_PLUGIN_DIR . 'public/views/' . $view;
		return ob_get_clean();
	}

	private function enqueue_assets() {
		wp_enqueue_style( 'crpcrm-public' );
	}

	private function get_notice() {
		if ( empty( $_GET['crpcrm_portal_notice'] ) || empty( $_GET['crpcrm_portal_message'] ) ) {
			return null;
		}

		$type = sanitize_key( wp_unslash( $_GET['crpcrm_portal_notice'] ) );
		$type = in_array( $type, array( 'success', 'error' ), true ) ? $type : 'error';

		return array(
			'type'    => $type,
			'message' => sanitize_text_field( wp_unslash( $_GET['crpcrm_portal_message'] ) ),
		);
	}

	private function redirect_with_notice( $url, $type, $message ) {
		$url = add_query_arg(
			array(
				'crpcrm_portal_notice'  => sanitize_key( $type ),
				'crpcrm_portal_message' => $message,
			),
			$url
		);
		wp_safe_redirect( $url );
		exit;
	}

	private function posted_redirect_url() {
		$redirect_to = isset( $_POST['crpcrm_redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['crpcrm_redirect_to'] ) ) : $this->clean_portal_url();
		return $redirect_to ? $redirect_to : home_url( '/' );
	}

	private function clean_portal_url() {
		return remove_query_arg( array( 'crpcrm_otp_state', 'crpcrm_portal_notice', 'crpcrm_portal_message' ), $this->current_url() );
	}

	private function current_url() {
		$scheme = is_ssl() ? 'https://' : 'http://';
		$host   = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : wp_parse_url( home_url(), PHP_URL_HOST );
		$uri    = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		return esc_url_raw( $scheme . $host . $uri );
	}

	private function format_phone_for_display( $phone_normalized ) {
		if ( 0 === strpos( $phone_normalized, '98' ) && 12 === strlen( $phone_normalized ) ) {
			return '0' . substr( $phone_normalized, 2 );
		}

		return $phone_normalized;
	}
}

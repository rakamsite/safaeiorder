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

	public function __construct( CRPCRM_OTP_Service $otp_service = null ) {
		$this->otp_service = $otp_service ? $otp_service : new CRPCRM_OTP_Service();
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
	}

	public function render() {
		$this->enqueue_assets();
		$notice = $this->get_notice();

		if ( is_user_logged_in() ) {
			$user_id          = get_current_user_id();
			$phone_normalized = get_user_meta( $user_id, 'crpcrm_phone_normalized', true );
			$this->otp_service->ensure_customer_record( $user_id, $phone_normalized );
			$logout_url = wp_nonce_url( admin_url( 'admin-post.php?action=crpcrm_portal_logout&redirect_to=' . rawurlencode( $this->current_url() ) ), 'crpcrm_portal_logout' );

			return $this->render_view( 'portal-placeholder.php', array( 'notice' => $notice, 'logout_url' => $logout_url ) );
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

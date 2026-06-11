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
	private $request_repository;
	private $activity_repository;
	private $attribution_service;

	public function __construct( CRPCRM_OTP_Service $otp_service = null ) {
		$this->otp_service           = $otp_service ? $otp_service : new CRPCRM_OTP_Service();
		$this->customer_repository  = new CRPCRM_Customer_Repository();
		$this->request_repository   = new CRPCRM_Request_Repository();
		$this->activity_repository  = new CRPCRM_Activity_Repository();
		$this->attribution_service  = new CRPCRM_Attribution_Service();
	}

	public function register_hooks() {
		add_filter( 'query_vars', array( $this, 'register_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'maybe_send_no_cache_headers' ), 0 );
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
		add_action( 'admin_post_crpcrm_submit_request', array( $this, 'handle_submit_request' ) );
	}

	public function register_query_vars( $vars ) {
		foreach ( array( 'crpcrm_page', 'form_id', 'request_id', 'request_code', 'created', 'crpcrm_otp_state', 'crpcrm_portal_notice', 'crpcrm_portal_message' ) as $var ) {
			if ( ! in_array( $var, $vars, true ) ) {
				$vars[] = $var;
			}
		}

		return $vars;
	}

	public function maybe_send_no_cache_headers() {
		if ( ! $this->is_portal_request() ) {
			return;
		}

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		if ( ! defined( 'DONOTCACHEOBJECT' ) ) {
			define( 'DONOTCACHEOBJECT', true );
		}
		if ( ! defined( 'DONOTCACHEDB' ) ) {
			define( 'DONOTCACHEDB', true );
		}

		nocache_headers();
	}

	public function render() {
		if ( ! CRPCRM_Feature_Manager::is_enabled( 'portal' ) ) {
			return '<div class="crpcrm-feature-disabled" dir="rtl">' . esc_html( CRPCRM_Feature_Manager::disabled_message() ) . '</div>';
		}

		$this->enqueue_assets();
		$notice = $this->get_notice();

		if ( is_user_logged_in() ) {
			$user_id = get_current_user_id();
			$user    = get_user_by( 'id', $user_id );

			if ( $this->should_redirect_to_dashboard_after_otp( $user ) ) {
				wp_safe_redirect( $this->get_staff_dashboard_url() );
				exit;
			}

			$customer = $this->ensure_current_customer( $user_id );

			if ( is_wp_error( $customer ) || ! $customer ) {
				CRPCRM_Logger::warning( 'customer_record_missing', 'customer', array( 'user_id' => $user_id ) );
				return $this->render_view(
					'portal-placeholder.php',
					array(
						'notice'              => array( 'type' => 'error', 'message' => 'امکان ساخت اطلاعات مشتری برای حساب شما وجود ندارد. لطفاً دوباره وارد شوید یا با پشتیبانی تماس بگیرید.' ),
						'logout_url'          => $this->get_logout_url(),
						'placeholder_message' => 'امکان ساخت اطلاعات مشتری برای حساب شما وجود ندارد. لطفاً دوباره وارد شوید یا با پشتیبانی تماس بگیرید.',
					)
				);
			}

			if ( ! absint( $customer['profile_completed'] ) ) {
				CRPCRM_Logger::info( 'customer_profile_viewed', 'customer', array( 'user_id' => $user_id, 'customer_id' => absint( $customer['id'] ), 'mode' => 'complete' ) );

				return $this->render_view(
					'profile-form.php',
					array(
						'notice'             => $notice,
						'logout_url'         => $this->get_logout_url(),
						'customer'           => $customer,
						'registration_fields'=> CRPCRM_Customer_Registration_Fields::get_enabled_fields(),
						'registration_values'=> CRPCRM_Customer_Registration_Fields::get_customer_values( $customer ),
						'phone_display'      => $this->format_phone_for_display( ! empty( $customer['phone_normalized'] ) ? $customer['phone_normalized'] : $customer['phone'] ),
						'provinces'          => $this->get_iran_provinces(),
						'is_edit'            => false,
						'is_embedded'        => false,
						'admin_post_url'     => admin_url( 'admin-post.php' ),
						'portal_redirect_to' => $this->get_portal_url( 'dashboard' ),
					)
				);
			}

			$page = $this->get_current_portal_page();
			$this->log_portal_page_view( $page, $customer, $user_id );

			return $this->render_portal( $page, $customer, $notice );
		}

		if ( ! CRPCRM_Feature_Manager::is_enabled( 'otp' ) ) {
			return '<div class="crpcrm-feature-disabled" dir="rtl">' . esc_html( CRPCRM_Feature_Manager::disabled_message() ) . '</div>';
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
		$this->guard_feature( 'portal' );
		$this->guard_feature( 'otp' );
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
		$this->guard_feature( 'portal' );
		$this->guard_feature( 'otp' );
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
		$this->guard_feature( 'portal' );
		$this->guard_feature( 'otp' );
		check_admin_referer( 'crpcrm_verify_otp', 'crpcrm_otp_nonce' );
		$redirect_to = $this->posted_redirect_url();
		$state_token = isset( $_POST['crpcrm_otp_state'] ) ? sanitize_text_field( wp_unslash( $_POST['crpcrm_otp_state'] ) ) : '';
		$code        = isset( $_POST['crpcrm_otp_code'] ) ? sanitize_text_field( wp_unslash( $_POST['crpcrm_otp_code'] ) ) : '';
		$result      = $this->otp_service->verify_code( $state_token, $code );

		if ( is_wp_error( $result ) ) {
			$this->redirect_with_notice( add_query_arg( 'crpcrm_otp_state', $state_token, $redirect_to ), 'error', $result->get_error_message() );
		}

		if ( $this->should_redirect_to_dashboard_after_otp( $result ) ) {
			CRPCRM_Logger::info( 'staff_otp_login_redirected', 'otp', array( 'user_id' => absint( $result->ID ), 'roles' => (array) $result->roles ) );
			wp_safe_redirect( $this->get_staff_dashboard_url() );
			exit;
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

		$registration_input = isset( $_POST['crpcrm_registration'] ) && is_array( $_POST['crpcrm_registration'] ) ? $_POST['crpcrm_registration'] : array();
		$registration_data  = CRPCRM_Customer_Registration_Fields::sanitize_registration_input( $registration_input );
		$errors             = CRPCRM_Customer_Registration_Fields::validate_registration_input( $registration_input, $user_id );

		if ( isset( $registration_data['province'] ) && '' !== $registration_data['province'] && ! in_array( $registration_data['province'], $this->get_iran_provinces(), true ) ) {
			$errors[] = 'استان انتخاب‌شده معتبر نیست.';
		}

		if ( ! empty( $errors ) ) {
			CRPCRM_Logger::info( 'customer_profile_validation_failed', 'customer', array( 'user_id' => $user_id, 'customer_id' => absint( $customer['id'] ), 'error_count' => count( $errors ) ) );
			$this->redirect_with_notice( $redirect_to, 'error', implode( ' ', $errors ) );
		}

		$was_completed = (bool) absint( $customer['profile_completed'] );
		$customer_update = array(
			'profile_completed' => 1,
		);
		foreach ( array( 'full_name', 'province', 'city' ) as $column ) {
			if ( array_key_exists( $column, $registration_data ) ) {
				$customer_update[ $column ] = $registration_data[ $column ];
			}
		}
		$updated = $this->customer_repository->update( absint( $customer['id'] ), $customer_update );

		if ( ! $updated ) {
			CRPCRM_Logger::error( 'customer_profile_validation_failed', 'customer', array( 'user_id' => $user_id, 'customer_id' => absint( $customer['id'] ), 'reason' => 'db_update_failed' ) );
			$this->redirect_with_notice( $redirect_to, 'error', 'خطایی در ذخیره اطلاعات رخ داد. لطفاً دوباره تلاش کنید.' );
		}

		CRPCRM_Customer_Registration_Fields::save_customer_values( $customer, $registration_data );
		if ( isset( $registration_data['full_name'] ) && '' !== $registration_data['full_name'] ) {
			$this->maybe_update_display_name( $user_id, $registration_data['full_name'], $customer );
		}
		CRPCRM_Logger::info( $was_completed ? 'customer_profile_updated' : 'customer_profile_completed', 'customer', array( 'user_id' => $user_id, 'customer_id' => absint( $customer['id'] ) ) );

		$success_message = $was_completed ? 'پروفایل شما با موفقیت بروزرسانی شد.' : 'اطلاعات شما با موفقیت ذخیره شد.';
		$this->redirect_with_notice( $redirect_to, 'success', $success_message );
	}


	public function handle_submit_request() {
		$this->guard_feature( 'portal' );

		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( $this->clean_portal_url() );
			exit;
		}

		$user_id     = get_current_user_id();
		$redirect_to = $this->posted_redirect_url();
		$page        = isset( $_POST['crpcrm_request_page'] ) ? sanitize_key( wp_unslash( $_POST['crpcrm_request_page'] ) ) : '';
		$form_id     = isset( $_POST['crpcrm_form_id'] ) ? sanitize_key( wp_unslash( $_POST['crpcrm_form_id'] ) ) : $page;
		$form        = CRPCRM_Form_Registry::get_enabled_form( $form_id );
		$nonce_value = isset( $_POST['crpcrm_request_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['crpcrm_request_nonce'] ) ) : '';
		$nonce_valid = $form && wp_verify_nonce( $nonce_value, 'crpcrm_submit_request_' . sanitize_key( $form['id'] ) );
		if ( ! $nonce_valid && $form && $page ) {
			$nonce_valid = wp_verify_nonce( $nonce_value, 'crpcrm_submit_request_' . $page );
		}

		if ( ! $form || ! $nonce_valid ) {
			CRPCRM_Logger::warning( 'customer_request_validation_failed', 'request', array( 'user_id' => $user_id, 'page' => $page, 'form_id' => $form_id, 'reason' => 'nonce_or_form' ) );
			$this->redirect_with_notice( $redirect_to, 'error', 'در ثبت درخواست خطایی رخ داد. لطفاً دوباره تلاش کنید.' );
		}

		$customer = $this->ensure_current_customer( $user_id );
		if ( is_wp_error( $customer ) || ! $customer ) {
			CRPCRM_Logger::warning( 'customer_request_validation_failed', 'request', array( 'user_id' => $user_id, 'page' => $page, 'reason' => 'customer_missing' ) );
			$this->redirect_with_notice( $redirect_to, 'error', 'اطلاعات حساب شما کامل نیست. لطفاً دوباره وارد شوید.' );
		}

		$customer_id = absint( $customer['id'] );
		if ( ! absint( $customer['profile_completed'] ) ) {
			CRPCRM_Logger::warning( 'customer_request_validation_failed', 'request', array( 'user_id' => $user_id, 'customer_id' => $customer_id, 'reason' => 'profile_incomplete' ) );
			$this->redirect_with_notice( $this->get_portal_url( 'profile' ), 'error', 'لطفاً ابتدا پروفایل خود را تکمیل کنید.' );
		}

		if ( $this->is_request_rate_limited( $customer_id, $user_id ) ) {
			CRPCRM_Logger::warning( 'customer_request_rate_limited', 'request', array( 'user_id' => $user_id, 'customer_id' => $customer_id ) );
			$this->redirect_with_notice( $redirect_to, 'error', 'تعداد درخواست‌های ثبت‌شده زیاد است. لطفاً کمی بعد دوباره تلاش کنید.' );
		}

		$validated = CRPCRM_Request_Forms::sanitize_and_validate( $form, $_POST );
		if ( ! empty( $validated['errors'] ) ) {
			CRPCRM_Logger::info( 'customer_request_validation_failed', 'request', array( 'user_id' => $user_id, 'customer_id' => $customer_id, 'page' => $page, 'error_count' => count( $validated['errors'] ) ) );
			$this->redirect_with_notice( $redirect_to, 'error', implode( ' ', $validated['errors'] ) );
		}

		$submitted_fields = $validated['data'];
		$request_data     = $submitted_fields;
		$request_summary  = CRPCRM_Request_Forms::build_summary( $form, $request_data );
		$form_id          = sanitize_key( $form['id'] );
		$form_version     = sanitize_text_field( $form['version'] ?? '1' );
		$request_type     = sanitize_key( $form['request_type'] ?? '' );
		$request_type     = $request_type ? $request_type : $form_id;
		$attribution      = $this->attribution_service->get_attribution_for_new_request();
		$now             = CRPCRM_Helpers::current_datetime();

		$request_id = $this->request_repository->create(
			array(
				'customer_id'          => $customer_id,
				'user_id'              => $user_id,
				'request_type'         => $request_type,
				'form_id'              => $form_id,
				'form_version'         => $form_version ? $form_version : '1',
				'status'               => 'new',
				'owner_id'             => null,
				'request_title'        => $form['title'],
				'request_summary'      => $request_summary,
				'request_data'         => $request_data,
				'request_source'       => $attribution['source'],
				'request_medium'       => $attribution['medium'],
				'request_campaign'     => $attribution['campaign'],
				'request_content'      => $attribution['content'],
				'request_term'         => $attribution['term'],
				'request_landing_page' => $attribution['landing_page'],
				'request_referrer'     => $attribution['referrer'],
				'last_activity_at'     => $now,
				'created_at'           => $now,
				'updated_at'           => $now,
			)
		);

		if ( ! $request_id ) {
			CRPCRM_Logger::error( 'customer_request_validation_failed', 'request', array( 'user_id' => $user_id, 'customer_id' => $customer_id, 'reason' => 'request_insert_failed' ) );
			$this->redirect_with_notice( $redirect_to, 'error', 'در ثبت درخواست خطایی رخ داد. لطفاً دوباره تلاش کنید.' );
		}

		$request      = $this->request_repository->get( $request_id );
		$request_code = $request && ! empty( $request['request_code'] ) ? $request['request_code'] : $this->request_repository->generate_request_code( $request_id );

		$this->activity_repository->add_activity(
			$request_id,
			'request_created',
			array(
				'customer_id'   => $customer_id,
				'actor_user_id' => $user_id,
				'actor_type'    => 'customer',
				'new_status'    => 'new',
				'note'          => 'درخواست توسط مشتری ثبت شد.',
				'is_internal'   => 0,
				'meta'          => array(
					'request_type' => $request_type,
					'request_code' => $request_code,
					'source'       => $attribution['source'],
					'campaign'     => $attribution['campaign'],
				),
			)
		);

		CRPCRM_Logger::info( 'customer_request_created', 'request', array( 'user_id' => $user_id, 'customer_id' => $customer_id, 'request_id' => $request_id, 'request_code' => $request_code, 'request_type' => $request_type, 'source' => $attribution['source'], 'campaign' => $attribution['campaign'] ) );

		wp_safe_redirect(
			$this->get_portal_url(
				'request_detail',
				array(
					'request_id'             => $request_id,
					'request_code'           => $request_code,
					'created'                => 1,
					'crpcrm_portal_notice'   => 'success',
					'crpcrm_portal_message'  => CRPCRM_Settings::get( 'request_success_message', 'درخواست شما با موفقیت ثبت شد. کارشناسان ما در اولین فرصت آن را بررسی می‌کنند.' ),
				)
			)
		);
		exit;
	}

	public function handle_change_phone() {
		check_admin_referer( 'crpcrm_change_otp_phone', 'crpcrm_otp_nonce' );
		wp_safe_redirect( $this->posted_redirect_url() );
		exit;
	}

	public function handle_logout() {
		check_admin_referer( 'crpcrm_portal_logout' );
		$user_id     = get_current_user_id();
		$redirect_to = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : $this->get_base_portal_url();

		CRPCRM_Logger::info( 'customer_logout_from_portal', 'customer', array( 'user_id' => $user_id ) );
		wp_logout();
		$this->redirect_with_notice( $redirect_to, 'success', 'شما از حساب کاربری خارج شدید.' );
	}

	private function render_portal( $page, $customer, $notice = null ) {
		$portal_data = $this->get_portal_page_data( $page, $customer );

		return $this->render_view(
			'portal-layout.php',
			array(
				'notice'          => $notice,
				'portal_data'     => $portal_data,
				'current_page'    => $page,
				'customer'        => $customer,
				'registration_fields' => CRPCRM_Customer_Registration_Fields::get_enabled_fields(),
				'registration_values' => CRPCRM_Customer_Registration_Fields::get_customer_values( $customer ),
				'menu_items'      => $this->get_required_menu_items( $page ),
				'custom_links'    => $this->get_custom_menu_links(),
				'logout_url'      => $this->get_logout_url(),
				'portal_urls'     => $this->get_portal_urls(),
				'phone_display'   => $this->format_phone_for_display( ! empty( $customer['phone_normalized'] ) ? $customer['phone_normalized'] : $customer['phone'] ),
				'provinces'       => $this->get_iran_provinces(),
				'admin_post_url'  => admin_url( 'admin-post.php' ),
			)
		);
	}

	private function get_current_portal_page() {
		$page = sanitize_key( $this->get_query_value( 'crpcrm_page', 'dashboard' ) );
		if ( '' === $page ) {
			return 'dashboard';
		}

		if ( ! in_array( $page, $this->get_allowed_portal_pages(), true ) ) {
			CRPCRM_Logger::warning( 'customer_portal_invalid_page', 'customer', array( 'requested_page' => $page, 'user_id' => get_current_user_id() ) );
			return 'dashboard';
		}

		return $page;
	}

	private function get_allowed_portal_pages() {
		return array_merge( array( 'dashboard', 'new_request', 'profile', 'my_requests', 'request_detail' ), CRPCRM_Form_Registry::get_form_pages() );
	}

	private function get_portal_urls() {
		$urls = array(
			'dashboard'   => $this->get_portal_url( 'dashboard' ),
			'new_request' => $this->get_portal_url( 'new_request' ),
			'profile'     => $this->get_portal_url( 'profile' ),
			'my_requests' => $this->get_portal_url( 'my_requests' ),
		);
		foreach ( CRPCRM_Request_Forms::get_form_pages() as $form_page ) {
			$urls[ $form_page ] = $this->get_portal_url( $form_page );
		}
		return $urls;
	}

	private function should_redirect_to_dashboard_after_otp( $user ) {
		if ( ! $user instanceof WP_User ) {
			return false;
		}

		return CRPCRM_Roles::is_staff_user( $user );
	}

	private function get_staff_dashboard_url() {
		return admin_url( 'admin.php?page=crpcrm-dashboard' );
	}

	private function get_required_menu_items( $current_page ) {
		return array(
			array(
				'key'    => 'dashboard',
				'label'  => 'داشبورد',
				'url'    => $this->get_portal_url( 'dashboard' ),
				'active' => 'dashboard' === $current_page,
			),
			array(
				'key'    => 'new_request',
				'label'  => 'ثبت درخواست',
				'url'    => $this->get_portal_url( 'new_request' ),
				'active' => 'new_request' === $current_page || in_array( $current_page, CRPCRM_Form_Registry::get_form_pages(), true ),
			),
			array(
				'key'    => 'my_requests',
				'label'  => 'درخواست‌های من',
				'url'    => $this->get_portal_url( 'my_requests' ),
				'active' => 'my_requests' === $current_page,
			),
			array(
				'key'    => 'profile',
				'label'  => 'پروفایل من',
				'url'    => $this->get_portal_url( 'profile' ),
				'active' => 'profile' === $current_page,
			),
			array(
				'key'    => 'logout',
				'label'  => 'خروج',
				'url'    => $this->get_logout_url(),
				'active' => false,
			),
		);
	}

	private function get_custom_menu_links() {
		$menu_id = absint( CRPCRM_Settings::get( 'portal_menu_id', 0 ) );
		if ( ! $menu_id ) {
			return array();
		}

		$items = wp_get_nav_menu_items( $menu_id );
		if ( empty( $items ) || ! is_array( $items ) ) {
			return array();
		}

		$links = array();
		foreach ( $items as $item ) {
			if ( empty( $item->url ) || empty( $item->title ) ) {
				continue;
			}

			$links[] = array(
				'label'  => wp_strip_all_tags( $item->title ),
				'url'    => esc_url_raw( $item->url ),
				'target' => ! empty( $item->target ) ? sanitize_key( $item->target ) : '',
			);
		}

		return $links;
	}

	private function get_portal_url( $page, $args = array() ) {
		$page = sanitize_key( $page );
		if ( ! in_array( $page, $this->get_allowed_portal_pages(), true ) ) {
			$page = 'dashboard';
		}

		$query_args = array_merge( array( 'crpcrm_page' => $page ), $this->sanitize_query_args( $args ) );
		return add_query_arg( $query_args, $this->get_base_portal_url() );
	}

	private function get_logout_url() {
		$url = add_query_arg(
			array(
				'action'      => 'crpcrm_portal_logout',
				'redirect_to' => $this->get_base_portal_url(),
			),
			admin_url( 'admin-post.php' )
		);

		return wp_nonce_url( $url, 'crpcrm_portal_logout' );
	}

	private function get_base_portal_url() {
		$portal_page_id = absint( CRPCRM_Settings::get( 'portal_page_id', 0 ) );
		if ( $portal_page_id ) {
			$permalink = get_permalink( $portal_page_id );
			if ( $permalink ) {
				return esc_url_raw( $permalink );
			}
		}

		return remove_query_arg( array( 'crpcrm_page', 'form_id', 'request_id', 'request_code', 'created' ), $this->clean_portal_url() );
	}

	private function sanitize_query_args( $args ) {
		$clean = array();
		if ( empty( $args ) || ! is_array( $args ) ) {
			return $clean;
		}

		foreach ( $args as $key => $value ) {
			$key = sanitize_key( $key );
			if ( '' === $key || 'crpcrm_page' === $key ) {
				continue;
			}
			$clean[ $key ] = is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
		}

		return $clean;
	}


	private function get_portal_page_data( $page, $customer ) {
		$customer_id = absint( $customer['id'] );
		$data        = array(
			'form'            => null,
			'my_requests'     => array(),
			'latest_requests' => $this->request_repository->list_for_customer( $customer_id, array( 'limit' => 3, 'user_id' => get_current_user_id() ) ),
			'request_detail'  => null,
			'access_denied'   => false,
			'form_error'      => '',
			'request_forms'   => CRPCRM_Request_Forms::get_forms(),
		);

		if ( 'new_request' === $page ) {
			$form_id = sanitize_key( $this->get_query_value( 'form_id' ) );
			if ( $form_id ) {
				$data['form'] = CRPCRM_Form_Registry::get_enabled_form( $form_id );
				if ( ! $data['form'] ) {
					$data['form_error'] = 'فرم درخواست انتخاب‌شده معتبر یا فعال نیست.';
				}
			}
			return $data;
		}

		if ( in_array( $page, CRPCRM_Form_Registry::get_form_pages(), true ) ) {
			$data['form'] = CRPCRM_Form_Registry::get_enabled_form( $page );
			if ( ! $data['form'] ) {
				$data['form_error'] = 'فرم درخواست انتخاب‌شده معتبر یا فعال نیست.';
			}
			return $data;
		}

		if ( 'my_requests' === $page ) {
			$data['my_requests'] = $this->request_repository->list_for_customer( $customer_id, array( 'limit' => 50, 'user_id' => get_current_user_id() ) );
			return $data;
		}

		if ( 'request_detail' === $page ) {
			$request_id   = absint( $this->get_query_value( 'request_id' ) );
			$request_code = $this->get_query_value( 'request_code' );
			$request      = $request_id ? $this->request_repository->get( $request_id ) : null;
			if ( ! $request && $request_code ) {
				$request = $this->request_repository->get_by_code( $request_code );
			}

			if ( ! $request || CRPCRM_System_Request_Types::is_system_type( $request['request_type'] ) || ( absint( $request['customer_id'] ) !== $customer_id && absint( $request['user_id'] ) !== get_current_user_id() ) ) {
				CRPCRM_Logger::warning( 'customer_request_access_denied', 'request', array( 'user_id' => get_current_user_id(), 'customer_id' => $customer_id, 'request_id' => $request_id, 'request_code' => $request_code ) );
				$data['access_denied'] = true;
				return $data;
			}

			$data['request_detail'] = $request;
		}

		return $data;
	}

	private function is_request_rate_limited( $customer_id, $user_id ) {
		$limit   = max( 1, absint( CRPCRM_Settings::get( 'request_rate_limit_count', 5 ) ) );
		$minutes = max( 1, absint( CRPCRM_Settings::get( 'request_rate_limit_minutes', 10 ) ) );
		$since   = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - ( $minutes * MINUTE_IN_SECONDS ) );
		$count   = $this->request_repository->count_recent_for_customer_user( $customer_id, $user_id, $since );

		return $count >= $limit;
	}

	private function log_portal_page_view( $page, $customer, $user_id ) {
		$meta = array( 'user_id' => $user_id, 'customer_id' => absint( $customer['id'] ), 'page' => $page );
		if ( 'dashboard' === $page ) {
			CRPCRM_Logger::info( 'customer_dashboard_viewed', 'customer', $meta );
			return;
		}

		if ( in_array( $page, CRPCRM_Request_Forms::get_form_pages(), true ) ) {
			CRPCRM_Logger::info( 'customer_request_form_viewed', 'request', $meta );
			return;
		}

		if ( 'request_detail' === $page ) {
			CRPCRM_Logger::info( 'customer_request_detail_viewed', 'request', $meta );
			return;
		}

		if ( in_array( $page, array( 'profile', 'my_requests' ), true ) ) {
			CRPCRM_Logger::info( 'customer_portal_page_viewed', 'customer', $meta );
		}
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
		$args = wp_parse_args(
			$args,
			array(
				'portal_theme_class'   => CRPCRM_Public_Theme::get_theme_class(),
				'portal_theme_style'   => CRPCRM_Public_Theme::get_inline_style(),
			)
		);
		extract( $args, EXTR_SKIP );
		ob_start();
		include CRPCRM_PLUGIN_DIR . 'public/views/' . $view;
		return ob_get_clean();
	}

	private function enqueue_assets() {
		wp_enqueue_style( 'crpcrm-public' );
		wp_enqueue_script( 'crpcrm-public' );
	}

	private function guard_feature( $feature ) {
		if ( ! CRPCRM_Feature_Manager::is_enabled( $feature ) ) {
			wp_die( esc_html( CRPCRM_Feature_Manager::disabled_message() ) );
		}
	}

	private function get_notice() {
		if ( '' === $this->get_query_value( 'crpcrm_portal_notice' ) || '' === $this->get_query_value( 'crpcrm_portal_message' ) ) {
			return null;
		}

		$type = sanitize_key( $this->get_query_value( 'crpcrm_portal_notice' ) );
		$type = in_array( $type, array( 'success', 'error' ), true ) ? $type : 'error';

		return array(
			'type'    => $type,
			'message' => sanitize_text_field( $this->get_query_value( 'crpcrm_portal_message' ) ),
		);
	}

	private function redirect_with_notice( $url, $type, $message ) {
		$url = add_query_arg(
			array(
				'crpcrm_portal_notice'  => sanitize_key( $type ),
				'crpcrm_portal_message'  => $message,
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

	private function get_query_value( $key, $default = '' ) {
		$key = sanitize_key( $key );
		if ( '' === $key ) {
			return $default;
		}

		$value = null;
		if ( isset( $_GET[ $key ] ) ) {
			$value = wp_unslash( $_GET[ $key ] );
		} elseif ( function_exists( 'get_query_var' ) ) {
			$value = get_query_var( $key, null );
		}

		if ( null === $value || is_array( $value ) || is_object( $value ) ) {
			return $default;
		}

		return sanitize_text_field( (string) $value );
	}

	private function is_portal_request() {
		$portal_page_id = absint( CRPCRM_Settings::get( 'portal_page_id', 0 ) );
		if ( $portal_page_id && function_exists( 'is_page' ) && is_page( $portal_page_id ) ) {
			return true;
		}

		foreach ( array( 'crpcrm_page', 'form_id', 'request_id', 'request_code', 'crpcrm_otp_state', 'crpcrm_portal_notice', 'crpcrm_portal_message' ) as $key ) {
			if ( '' !== $this->get_query_value( $key ) ) {
				return true;
			}
		}

		return false;
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

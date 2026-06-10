<?php
/**
 * Business profile registration and active-profile access.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_Business_Profile_Manager {
	const DEFAULT_PROFILE_ID = 'safaei';
	const PROFILE_OPTION = 'crpcrm_business_profile_id';
	const SETUP_COMPLETED_OPTION = 'crpcrm_setup_completed';
	const SETUP_COMPLETED_AT_OPTION = 'crpcrm_setup_completed_at';

	private static $instance;
	private $profiles = array();
	private $profiles_initialized = false;

	private function __construct() {
		$this->register_profile( new CRPCRM_Safaei_Business_Profile() );
	}

	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function register_hooks() {
		add_action( 'plugins_loaded', array( $this, 'initialize_profiles' ), 20 );
		add_action( 'plugins_loaded', array( $this, 'maybe_auto_lock_profile' ), 30 );
		add_action( 'admin_notices', array( $this, 'render_invalid_profile_notice' ) );
	}

	public function initialize_profiles() {
		if ( $this->profiles_initialized ) {
			return;
		}

		$this->profiles_initialized = true;
		do_action( 'crpcrm_register_business_profiles', $this );
	}

	public function register_profile( $profile ) {
		if ( ! $profile instanceof CRPCRM_Business_Profile_Interface ) {
			if ( class_exists( 'CRPCRM_Logger' ) ) {
				CRPCRM_Logger::warning( 'business_profile_registration_invalid', 'business_profile', array( 'value_type' => is_object( $profile ) ? get_class( $profile ) : gettype( $profile ) ) );
			}
			return false;
		}

		$profile_id = sanitize_key( $profile->get_id() );
		if ( '' === $profile_id ) {
			return false;
		}

		$this->profiles[ $profile_id ] = $profile;
		return true;
	}

	public function get_profiles() {
		if ( did_action( 'plugins_loaded' ) ) {
			$this->initialize_profiles();
		}

		return $this->profiles;
	}

	public static function is_setup_completed() {
		return 'yes' === get_option( self::SETUP_COMPLETED_OPTION, 'no' ) && '' !== self::get_locked_profile_id();
	}

	public static function get_locked_profile_id() {
		return sanitize_key( get_option( self::PROFILE_OPTION, '' ) );
	}

	public function can_change_profile() {
		return ! self::is_setup_completed();
	}

	public function is_locked_profile_valid() {
		$profile_id = self::get_locked_profile_id();
		return self::is_setup_completed() && isset( $this->get_profiles()[ $profile_id ] );
	}

	public function get_active_profile_id() {
		if ( ! self::is_setup_completed() ) {
			return '';
		}

		$profile_id = self::get_locked_profile_id();
		return isset( $this->get_profiles()[ $profile_id ] ) ? $profile_id : '';
	}

	public function get_active_profile() {
		$profile_id = $this->get_active_profile_id();
		return isset( $this->profiles[ $profile_id ] ) ? $this->profiles[ $profile_id ] : null;
	}

	public function get_active_forms() {
		$profile = $this->get_active_profile();
		$forms   = $profile ? $profile->get_forms() : array();
		return is_array( $forms ) ? $forms : array();
	}

	public function get_active_request_types() {
		$profile       = $this->get_active_profile();
		$request_types = $profile ? $profile->get_request_types() : array();
		return is_array( $request_types ) ? $request_types : array();
	}

	public function complete_setup( $profile_id ) {
		if ( 'yes' === get_option( self::SETUP_COMPLETED_OPTION, 'no' ) ) {
			return false;
		}

		$profile_id = sanitize_key( $profile_id );
		if ( '' === $profile_id || ! isset( $this->get_profiles()[ $profile_id ] ) ) {
			return false;
		}

		update_option( self::PROFILE_OPTION, $profile_id );
		update_option( self::SETUP_COMPLETED_AT_OPTION, current_time( 'mysql', true ) );
		update_option( self::SETUP_COMPLETED_OPTION, 'yes' );
		return true;
	}

	public function maybe_auto_lock_profile() {
		if ( self::is_setup_completed() ) {
			return;
		}

		$profiles = $this->get_profiles();
		if ( 1 === count( $profiles ) ) {
			$this->complete_setup( sanitize_key( array_key_first( $profiles ) ) );
		}
	}

	public function render_invalid_profile_notice() {
		if ( ! current_user_can( 'manage_options' ) || ! self::is_setup_completed() || $this->is_locked_profile_valid() ) {
			return;
		}

		echo '<div class="notice notice-error"><p>' . esc_html( 'پروفایل کسب‌وکار قفل‌شده ثبت نشده یا نامعتبر است. تا رفع مشکل، بخش‌های افزونه غیرفعال هستند.' ) . '</p></div>';
	}
}

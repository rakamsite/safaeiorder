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

	private static $instance;
	private $profiles = array();

	private function __construct() {
		$this->register_profile( new CRPCRM_Safaei_Business_Profile() );
	}

	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function register_profile( CRPCRM_Business_Profile_Interface $profile ) {
		$profile_id = sanitize_key( $profile->get_id() );
		if ( '' === $profile_id ) {
			return false;
		}

		$this->profiles[ $profile_id ] = $profile;
		return true;
	}

	public function get_profiles() {
		return $this->profiles;
	}

	public function get_active_profile_id() {
		$profile_id = self::DEFAULT_PROFILE_ID;
		if ( class_exists( 'CRPCRM_Settings' ) ) {
			$profile_id = sanitize_key( CRPCRM_Settings::get( 'business_profile', self::DEFAULT_PROFILE_ID ) );
		}

		return isset( $this->profiles[ $profile_id ] ) ? $profile_id : self::DEFAULT_PROFILE_ID;
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
}

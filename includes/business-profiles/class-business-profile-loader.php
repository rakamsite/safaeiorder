<?php
/**
 * Registers bundled business profiles through the public registration hook.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_Business_Profile_Loader {
	public static function register_hooks() {
		add_action( 'crpcrm_register_business_profiles', array( __CLASS__, 'register_bundled_profiles' ) );
		add_filter( 'crpcrm_default_form_field_options', array( __CLASS__, 'filter_legacy_field_options' ), 10, 4 );
	}

	public static function register_bundled_profiles( $manager ) {
		if ( $manager instanceof CRPCRM_Business_Profile_Manager ) {
			require_once CRPCRM_PLUGIN_DIR . 'includes/business-profiles/class-safaei-business-profile.php';
			$manager->register_profile( new CRPCRM_Safaei_Business_Profile() );
		}
	}

	public static function filter_legacy_field_options( $options, $form_id, $field_name, $field ) {
		if ( ! in_array( sanitize_key( $field_name ), array( 'desired_vehicle', 'vehicle_model' ), true ) ) {
			return $options;
		}

		$profile = CRPCRM_Business_Profile_Manager::get_instance()->get_active_profile();
		if ( ! $profile || ! method_exists( $profile, 'get_active_vehicle_options' ) ) {
			return $options;
		}

		$active_options = $profile->get_active_vehicle_options( $form_id );
		return ! empty( $active_options ) ? $active_options : $options;
	}
}

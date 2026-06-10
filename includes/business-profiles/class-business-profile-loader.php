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
	}

	public static function register_bundled_profiles( $manager ) {
		if ( $manager instanceof CRPCRM_Business_Profile_Manager ) {
			require_once CRPCRM_PLUGIN_DIR . 'includes/business-profiles/class-safaei-business-profile.php';
			$manager->register_profile( new CRPCRM_Safaei_Business_Profile() );
		}
	}
}

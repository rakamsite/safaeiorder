<?php
/**
 * Plugin activation tasks.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_Activator {
	public static function activate() {
		$is_existing_install = false !== get_option( CRPCRM_Settings::OPTION_NAME, false );

		CRPCRM_DB::create_tables();
		CRPCRM_Roles::add_roles_and_caps();
		CRPCRM_Settings::add_default_options();
		if ( ! $is_existing_install ) {
			add_option( CRPCRM_Business_Profile_Manager::PROFILE_OPTION, '' );
			add_option( CRPCRM_Business_Profile_Manager::SETUP_COMPLETED_OPTION, 'no' );
			add_option( CRPCRM_Business_Profile_Manager::SETUP_COMPLETED_AT_OPTION, '' );
		}
		if ( class_exists( 'CRPCRM_Admin_Tools' ) ) {
			CRPCRM_Admin_Tools::schedule_cron();
			CRPCRM_Lead_Follow_Up_Service::schedule_cron();
		}
	}
}

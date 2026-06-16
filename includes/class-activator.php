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
		CRPCRM_DB::create_tables();
		CRPCRM_Roles::add_roles_and_caps();
		CRPCRM_Settings::add_default_options();
		if ( class_exists( 'CRPCRM_Form_Builder_Repository' ) ) {
			$forms = new CRPCRM_Form_Builder_Repository();
			$forms->seed_default_forms_if_empty();
		}
		if ( class_exists( 'CRPCRM_Admin_Tools' ) ) {
			CRPCRM_Admin_Tools::schedule_cron();
			CRPCRM_Lead_Follow_Up_Service::schedule_cron();
		}
		if ( class_exists( 'CRPCRM_Dynamic_Form_Renderer' ) ) {
			CRPCRM_Dynamic_Form_Renderer::schedule_pending_upload_cleanup();
		}
	}
}

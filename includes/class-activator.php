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
	}
}

<?php
/**
 * Plugin deactivation tasks.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_Deactivator {
	public static function deactivate() {
		if ( class_exists( 'CRPCRM_Admin_Tools' ) ) {
			CRPCRM_Admin_Tools::clear_cron();
			CRPCRM_Lead_Follow_Up_Service::clear_cron();
		}
		// Roles, capabilities, tables, and options are intentionally kept for safe reactivation.
	}
}

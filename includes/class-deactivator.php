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
		// Roles, capabilities, tables, and options are intentionally kept for safe reactivation.
	}
}

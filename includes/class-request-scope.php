<?php
/**
 * Transitional value for the legacy requests.business_profile column.
 *
 * The column and its query filters are removed in Phase 4. Until then, all
 * runtime reads and writes use one generic partition without Business Profile.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_Request_Scope {
	const LEGACY_PARTITION = 'default';

	public static function get_legacy_partition() {
		return self::LEGACY_PARTITION;
	}
}

<?php
/**
 * Request activity service.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_Activity {
	public static function add( $request_id, $activity_type, $args = array() ) {
		$repository = new CRPCRM_Activity_Repository();
		return $repository->add_activity( $request_id, $activity_type, $args );
	}
}

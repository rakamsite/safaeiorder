<?php
/**
 * Staff repository skeleton for future employee portal features.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_Staff_Repository {
	public function tables() {
		return array(
			'daily_reports'   => CRPCRM_DB::table( 'staff_daily_reports' ),
			'staff_requests'  => CRPCRM_DB::table( 'staff_requests' ),
			'staff_issues'    => CRPCRM_DB::table( 'staff_issues' ),
			'staff_tasks'     => CRPCRM_DB::table( 'staff_tasks' ),
			'announcements'   => CRPCRM_DB::table( 'manager_announcements' ),
			'announcement_reads' => CRPCRM_DB::table( 'announcement_reads' ),
		);
	}
}

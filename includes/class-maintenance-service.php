<?php
/**
 * Maintenance operations.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_Maintenance_Service {
	public function cleanup_old_logs( $days ) {
		global $wpdb;
		$days   = max( 1, absint( $days ) );
		$cutoff = wp_date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - ( DAY_IN_SECONDS * $days ) );
		return (int) $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . CRPCRM_DB::table( 'plugin_logs' ) . ' WHERE created_at < %s', $cutoff ) );
	}

	public function repair_tables() {
		CRPCRM_DB::create_tables();
		return true;
	}

	public function rebuild_roles() {
		CRPCRM_Roles::add_roles_and_caps();
		return true;
	}

	public function fix_missing_request_codes() {
		global $wpdb;
		$table = CRPCRM_DB::table( 'requests' );
		$ids   = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE business_profile = %s AND (request_code = '' OR request_code IS NULL) ORDER BY id ASC", CRPCRM_Business_Profile_Manager::get_locked_profile_id() ) );
		$count = 0;
		$repo  = new CRPCRM_Request_Repository();
		foreach ( $ids as $id ) {
			$id   = absint( $id );
			$code = $repo->generate_request_code( $id );
			if ( false !== $wpdb->update( $table, array( 'request_code' => $code, 'updated_at' => CRPCRM_Helpers::current_datetime() ), array( 'id' => $id ), array( '%s', '%s' ), array( '%d' ) ) ) {
				$count++;
			}
		}
		return $count;
	}

	public function get_table_counts() {
		global $wpdb;
		$counts = array();
		foreach ( CRPCRM_DB::table_names() as $key => $table ) {
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
			$counts[ $key ] = $exists ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ) : null;
		}
		return $counts;
	}
}

<?php
/**
 * Uninstall handler.
 *
 * @package CRPCRM
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$settings = get_option( 'crpcrm_settings', array() );

if ( isset( $settings['delete_data_on_uninstall'] ) && 'yes' === $settings['delete_data_on_uninstall'] ) {
	global $wpdb;

	$tables = array(
		'customers',
		'customer_attribution_events',
		'requests',
		'request_activities',
		'otp_logs',
		'staff_daily_reports',
		'staff_requests',
		'staff_issues',
		'staff_tasks',
		'manager_announcements',
		'announcement_reads',
		'plugin_logs',
	);

	foreach ( $tables as $table ) {
		$table_name = esc_sql( $wpdb->prefix . 'crpcrm_' . $table );
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
	}

	delete_option( 'crpcrm_settings' );
	delete_option( 'crpcrm_db_version' );
}

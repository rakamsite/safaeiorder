<?php
/**
 * Uninstall handler.
 *
 * @package CRPCRM
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

wp_clear_scheduled_hook( 'crpcrm_daily_log_cleanup' );
wp_clear_scheduled_hook( 'crpcrm_pending_upload_cleanup' );
require_once __DIR__ . '/includes/registries/class-system-request-types.php';
require_once __DIR__ . '/includes/class-helpers.php';
require_once __DIR__ . '/includes/forms/class-dynamic-form-renderer.php';
require_once __DIR__ . '/includes/class-request-file-cleanup-service.php';
wp_clear_scheduled_hook( CRPCRM_System_Request_Types::LEAD_FOLLOW_UP_CRON );
delete_option( 'crpcrm_pending_upload_tokens' );
delete_option( 'crpcrm_daily_upload_usage' );

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
		'notifications',
		'landing_links',
		'landing_clicks',
		'plugin_logs',
	);

	foreach ( $tables as $table ) {
		$table_name = esc_sql( $wpdb->prefix . 'crpcrm_' . $table );
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
	}

	delete_option( 'crpcrm_settings' );
	delete_option( 'crpcrm_enabled_forms' );
	delete_option( 'crpcrm_db_version' );
	delete_option( 'crpcrm_activation_redirect' );
	delete_option( 'crpcrm_business_profile_id' );
	delete_option( 'crpcrm_setup_completed' );
	delete_option( 'crpcrm_setup_completed_at' );
	delete_option( 'crpcrm_enabled_features' );
	delete_option( 'crpcrm_customer_registration_fields' );
	delete_option( 'crpcrm_custom_forms' );
	delete_option( 'crpcrm_default_forms_seeded' );
	$pending_transient_prefix = $wpdb->esc_like( '_transient_crpcrm_pending_upload_' ) . '%';
	$pending_timeout_prefix   = $wpdb->esc_like( '_transient_timeout_crpcrm_pending_upload_' ) . '%';
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $pending_transient_prefix, $pending_timeout_prefix ) );
	// Cleanup for the pre-profile-settings test builds.
	delete_option( 'crpcrm_safaei_catalog' );
	$profile_settings_prefix = $wpdb->esc_like( 'crpcrm_profile_settings_' ) . '%';
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $profile_settings_prefix ) );
	$feature_settings_prefix = $wpdb->esc_like( 'crpcrm_enabled_features_' ) . '%';
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $feature_settings_prefix ) );
	$registration_fields_prefix = $wpdb->esc_like( 'crpcrm_customer_registration_fields_' ) . '%';
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $registration_fields_prefix ) );
	$cleanup_service = new CRPCRM_Request_File_Cleanup_Service();
	$cleanup_service->delete_protected_upload_directories();
}

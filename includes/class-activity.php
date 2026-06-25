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

	public static function add_required( $request_id, $activity_type, $args = array(), $context = 'request_activity' ) {
		$activity_id = self::add( $request_id, $activity_type, $args );
		if ( false !== $activity_id ) {
			return $activity_id;
		}

		global $wpdb;

		CRPCRM_Logger::error(
			'activity_insert_failed',
			$context,
			array(
				'request_id'    => absint( $request_id ),
				'activity_type' => sanitize_key( $activity_type ),
				'db_error'      => isset( $wpdb->last_error ) ? sanitize_text_field( $wpdb->last_error ) : '',
			)
		);

		return new WP_Error( 'activity_insert_failed', __( 'ثبت فعالیت درخواست انجام نشد. لطفاً دوباره تلاش کنید.', 'customer-request-portal-crm' ) );
	}
}

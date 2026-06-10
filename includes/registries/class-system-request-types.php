<?php
/**
 * Request types created by the plugin rather than a business-profile form.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_System_Request_Types {
	const LEAD_FOLLOW_UP = 'lead_follow_up';
	const LEAD_FOLLOW_UP_CRON = 'crpcrm_create_lead_follow_ups';
	const LEAD_FOLLOW_UP_CREATED = 'lead_follow_up_created';

	public static function get_types() {
		return array(
			self::LEAD_FOLLOW_UP => 'پیگیری سرنخ',
		);
	}

	public static function get_type_ids() {
		return array_values( array_filter( array_map( 'sanitize_key', array_keys( self::get_types() ) ) ) );
	}

	public static function get_metadata( $request_type ) {
		$request_type = sanitize_key( $request_type );
		if ( self::LEAD_FOLLOW_UP === $request_type ) {
			return array(
				'form_id'      => 'system_lead_follow_up',
				'form_version' => '1.0.0',
			);
		}

		return array();
	}

	public static function is_system_type( $request_type ) {
		return isset( self::get_types()[ sanitize_key( $request_type ) ] );
	}
}

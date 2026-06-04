<?php
/**
 * Internal plugin logger.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_Logger {
	public static function debug( $message, $context = null, $meta = array() ) {
		return self::log( 'debug', $message, $context, $meta );
	}

	public static function info( $message, $context = null, $meta = array() ) {
		return self::log( 'info', $message, $context, $meta );
	}

	public static function warning( $message, $context = null, $meta = array() ) {
		return self::log( 'warning', $message, $context, $meta );
	}

	public static function error( $message, $context = null, $meta = array() ) {
		return self::log( 'error', $message, $context, $meta );
	}

	private static function log( $level, $message, $context = null, $meta = array() ) {
		global $wpdb;

		$allowed_levels = array( 'debug', 'info', 'warning', 'error' );
		$level          = in_array( $level, $allowed_levels, true ) ? $level : 'info';

		return false !== $wpdb->insert(
			CRPCRM_DB::table( 'plugin_logs' ),
			array(
				'level'      => $level,
				'context'    => null !== $context ? sanitize_text_field( $context ) : null,
				'message'    => sanitize_textarea_field( $message ),
				'meta'       => CRPCRM_Helpers::maybe_json_encode( CRPCRM_Helpers::sanitize_array( $meta ) ),
				'created_at' => CRPCRM_Helpers::current_datetime(),
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);
	}
}

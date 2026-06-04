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
		if ( ! self::should_log( $level ) ) {
			return false;
		}

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

	private static function should_log( $level ) {
		$weights = array( 'debug' => 10, 'info' => 20, 'warning' => 30, 'error' => 40 );
		$setting = 'info';
		if ( class_exists( 'CRPCRM_Settings' ) ) {
			$setting = CRPCRM_Settings::get( 'log_level', 'info' );
		}
		if ( ! isset( $weights[ $setting ] ) ) {
			$setting = 'info';
		}
		return $weights[ $level ] >= $weights[ $setting ];
	}
}

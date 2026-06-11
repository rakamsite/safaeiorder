<?php
/**
 * Registry for request types exposed by default forms and the system registry.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_Request_Type_Registry {
	public static function get_request_types() {
		$types = array();
		foreach ( CRPCRM_Form_Registry::get_forms() as $form ) {
			$request_type = sanitize_key( $form['request_type'] ?? '' );
			if ( $request_type ) {
				$types[ $request_type ] = sanitize_text_field( $form['title'] ?? $form['label'] ?? $request_type );
			}
		}
		return array_merge( $types, CRPCRM_System_Request_Types::get_types() );
	}

	public static function get_form_id_for_request_type( $request_type ) {
		$form = CRPCRM_Form_Registry::get_form_by_request_type( $request_type );
		return $form ? sanitize_key( $form['id'] ?? '' ) : '';
	}

	public static function get_request_type_ids() {
		return array_values( array_unique( array_filter( array_map( 'sanitize_key', array_keys( self::get_request_types() ) ) ) ) );
	}

	public static function is_registered( $request_type ) {
		return in_array( sanitize_key( $request_type ), self::get_request_type_ids(), true );
	}

	public static function get_label( $request_type ) {
		$request_type = sanitize_key( $request_type );
		$types        = self::get_request_types();
		if ( isset( $types[ $request_type ] ) ) {
			return sanitize_text_field( $types[ $request_type ] );
		}

		return self::format_unknown_type( $request_type );
	}

	private static function format_unknown_type( $request_type ) {
		if ( '' === $request_type ) {
			return 'ثبت نشده';
		}

		return $request_type;
	}
}

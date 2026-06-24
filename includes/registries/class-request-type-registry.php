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
	public static function get_request_types_for_submission() {
		$types = array();
		foreach ( CRPCRM_Form_Registry::get_enabled_forms() as $form ) {
			self::add_form_type( $types, $form );
		}
		return array_merge( $types, CRPCRM_System_Request_Types::get_types() );
	}

	public static function get_request_types_for_display() {
		$types = array();
		foreach ( CRPCRM_Form_Registry::get_forms() as $form ) {
			self::add_form_type( $types, $form );
		}
		return array_merge( $types, CRPCRM_System_Request_Types::get_types() );
	}

	public static function get_request_types() {
		return self::get_request_types_for_display();
	}

	public static function get_form_id_for_request_type( $request_type ) {
		$request_type = sanitize_key( $request_type );
		foreach ( CRPCRM_Form_Registry::get_enabled_forms() as $form ) {
			$form_request_type = sanitize_key( $form['request_type'] ?? '' );
			if ( $request_type === $form_request_type ) {
				return sanitize_key( $form['id'] ?? '' );
			}
		}

		return '';
	}

	public static function get_request_type_ids() {
		return array_values( array_unique( array_filter( array_map( 'sanitize_key', array_keys( self::get_request_types() ) ) ) ) );
	}

	public static function is_registered( $request_type ) {
		return in_array( sanitize_key( $request_type ), array_keys( self::get_request_types_for_submission() ), true );
	}

	public static function get_label( $request_type, $request = null ) {
		$request_type = sanitize_key( $request_type );
		$types        = self::get_request_types_for_display();
		if ( isset( $types[ $request_type ] ) ) {
			return sanitize_text_field( $types[ $request_type ] );
		}
		if ( is_array( $request ) ) {
			$request_data = CRPCRM_Request_Repository::get_merged_request_data( $request );
			if ( ! empty( $request_data['_submitted_form_title'] ) ) {
				return sanitize_text_field( $request_data['_submitted_form_title'] );
			}
		}

		return self::format_unknown_type( $request_type );
	}

	private static function add_form_type( &$types, $form ) {
		$request_type = sanitize_key( $form['request_type'] ?? '' );
		if ( $request_type ) {
			$types[ $request_type ] = sanitize_text_field( ! empty( $form['title'] ) ? $form['title'] : ( ! empty( $form['label'] ) ? $form['label'] : $request_type ) );
		}
	}

	private static function format_unknown_type( $request_type ) {
		if ( '' === $request_type ) {
			return 'ثبت نشده';
		}

		return $request_type;
	}
}

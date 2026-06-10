<?php
/** Registry for forms exposed by the active business profile. @package CRPCRM */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class CRPCRM_Form_Registry {
	public static function get_forms() {
		return CRPCRM_Business_Profile_Manager::get_instance()->get_active_forms();
	}

	public static function get_aliases() {
		$profile = CRPCRM_Business_Profile_Manager::get_instance()->get_active_profile();
		$aliases = $profile && method_exists( $profile, 'get_form_aliases' ) ? $profile->get_form_aliases() : array();
		$clean   = array();
		foreach ( is_array( $aliases ) ? $aliases : array() as $old_id => $new_id ) {
			$clean[ sanitize_key( $old_id ) ] = sanitize_key( $new_id );
		}
		return array_filter( $clean );
	}

	public static function resolve_form_id( $form_id ) {
		$form_id = sanitize_key( $form_id );
		$aliases = self::get_aliases();
		return isset( $aliases[ $form_id ] ) ? $aliases[ $form_id ] : $form_id;
	}

	public static function get_form( $form_id ) {
		$forms   = self::get_forms();
		$form_id = self::resolve_form_id( $form_id );
		if ( isset( $forms[ $form_id ] ) && is_array( $forms[ $form_id ] ) ) { return $forms[ $form_id ]; }
		foreach ( $forms as $form ) {
			if ( is_array( $form ) && ( $form_id === self::resolve_form_id( $form['id'] ?? '' ) || $form_id === self::resolve_form_id( $form['page'] ?? '' ) ) ) { return $form; }
		}
		return null;
	}

	public static function get_form_by_request_type( $request_type ) {
		$request_type = sanitize_key( $request_type );
		foreach ( self::get_forms() as $form ) {
			if ( is_array( $form ) && $request_type === sanitize_key( $form['request_type'] ?? '' ) ) { return $form; }
		}
		return null;
	}

	public static function get_form_for_request( $request_type, $request_data ) {
		$request_data = is_array( $request_data ) ? $request_data : array();
		$form_id      = sanitize_key( $request_data['form_id'] ?? '' );
		$profile_id   = sanitize_key( $request_data['business_profile'] ?? '' );
		if ( $form_id && $profile_id ) {
			$profiles = CRPCRM_Business_Profile_Manager::get_instance()->get_profiles();
			$profile  = $profiles[ $profile_id ] ?? null;
			$forms    = $profile ? $profile->get_forms() : array();
			$aliases  = $profile && method_exists( $profile, 'get_form_aliases' ) ? $profile->get_form_aliases() : array();
			$form_id  = isset( $aliases[ $form_id ] ) ? sanitize_key( $aliases[ $form_id ] ) : $form_id;
			if ( isset( $forms[ $form_id ] ) && is_array( $forms[ $form_id ] ) ) { return $forms[ $form_id ]; }
		}
		if ( $form_id ) {
			$form = self::get_form( $form_id );
			if ( $form ) { return $form; }
		}
		return self::get_form_by_request_type( $request_type );
	}

	public static function get_enabled_forms() {
		$forms      = self::get_forms();
		$profile_id = CRPCRM_Business_Profile_Manager::get_instance()->get_active_profile_id();
		$stored     = CRPCRM_Settings::get( 'enabled_forms', null );
		$override   = is_array( $stored ) && array_key_exists( $profile_id, $stored ) ? array_map( array( __CLASS__, 'resolve_form_id' ), (array) $stored[ $profile_id ] ) : null;
		return array_filter( $forms, function ( $form ) use ( $override ) {
			if ( ! is_array( $form ) || ( isset( $form['enabled'] ) && empty( $form['enabled'] ) ) ) { return false; }
			return null === $override || in_array( self::resolve_form_id( $form['id'] ?? '' ), $override, true );
		} );
	}

	public static function get_enabled_form( $form_id ) {
		$form = self::get_form( $form_id );
		if ( ! $form ) { return null; }
		foreach ( self::get_enabled_forms() as $enabled ) { if ( $enabled === $form ) { return $form; } }
		return null;
	}

	public static function get_form_pages() {
		$pages = array_keys( self::get_aliases() );
		foreach ( self::get_forms() as $form_id => $form ) { $pages[] = sanitize_key( $form['page'] ?? $form_id ); }
		return array_values( array_unique( array_filter( $pages ) ) );
	}
	public static function get_active_forms() { return self::get_enabled_forms(); }
}

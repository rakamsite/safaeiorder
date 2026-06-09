<?php
/**
 * Registry for forms exposed by the active business profile.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_Form_Registry {
	public static function get_forms() {
		return CRPCRM_Business_Profile_Manager::get_instance()->get_active_forms();
	}

	public static function get_form( $form_id ) {
		$forms   = self::get_forms();
		$form_id = sanitize_key( $form_id );
		if ( isset( $forms[ $form_id ] ) && is_array( $forms[ $form_id ] ) ) {
			return $forms[ $form_id ];
		}

		foreach ( $forms as $form ) {
			if ( ! is_array( $form ) ) {
				continue;
			}
			if ( $form_id === sanitize_key( isset( $form['id'] ) ? $form['id'] : '' ) || $form_id === sanitize_key( isset( $form['page'] ) ? $form['page'] : '' ) ) {
				return $form;
			}
		}

		return null;
	}

	public static function get_enabled_forms() {
		return array_filter(
			self::get_forms(),
			function ( $form ) {
				return is_array( $form ) && ( ! isset( $form['enabled'] ) || ! empty( $form['enabled'] ) );
			}
		);
	}

	public static function get_enabled_form( $form_id ) {
		$form = self::get_form( $form_id );
		return $form && ( ! isset( $form['enabled'] ) || ! empty( $form['enabled'] ) ) ? $form : null;
	}

	public static function get_form_pages() {
		$pages = array();
		foreach ( self::get_forms() as $form_id => $form ) {
			$page    = ! empty( $form['page'] ) ? sanitize_key( $form['page'] ) : sanitize_key( $form_id );
			$pages[] = $page;
		}
		return array_values( array_unique( array_filter( $pages ) ) );
	}

	public static function get_active_forms() {
		return self::get_enabled_forms();
	}
}

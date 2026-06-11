<?php
/** Registry for stored forms used by request flows. @package CRPCRM */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class CRPCRM_Form_Registry {
	private static function get_repository() {
		return new CRPCRM_Form_Builder_Repository();
	}

	private static function to_runtime_form( $form ) {
		$fields = array();
		foreach ( (array) ( $form['fields'] ?? array() ) as $field ) {
			if ( isset( $field['enabled'] ) && ! $field['enabled'] ) {
				continue;
			}
			$fields[] = array(
				'name'             => sanitize_key( $field['key'] ?? '' ),
				'label'            => sanitize_text_field( $field['label'] ?? '' ),
				'type'             => sanitize_key( $field['type'] ?? 'text' ),
				'required'         => ! empty( $field['required'] ),
				'required_message' => sanitize_text_field( $field['required_message'] ?? '' ),
				'placeholder'      => sanitize_text_field( $field['placeholder'] ?? '' ),
				'help'             => sanitize_textarea_field( $field['help_text'] ?? '' ),
				'options'          => is_array( $field['options'] ?? null ) ? $field['options'] : array(),
				'default'          => sanitize_text_field( $field['default'] ?? '' ),
				'enabled'          => true,
				'sort_order'       => absint( $field['sort_order'] ?? 0 ),
			);
		}

		usort( $fields, function ( $a, $b ) {
			return $a['sort_order'] <=> $b['sort_order'];
		} );

		$form_id = sanitize_key( $form['form_id'] ?? '' );
		$request_type = sanitize_key( $form['request_type'] ?? '' );
		$request_type = $request_type ? $request_type : $form_id;
		return array(
			'id'               => $form_id,
			'form_id'          => $form_id,
			'title'            => sanitize_text_field( $form['title'] ?? '' ),
			'label'            => sanitize_text_field( $form['label'] ?? $form['title'] ?? '' ),
			'page'             => sanitize_key( $form['page'] ?? $form_id ),
			'icon'             => sanitize_key( $form['icon'] ?? '' ),
			'description'      => sanitize_textarea_field( $form['description'] ?? '' ),
			'request_type'     => $request_type,
			'submit_label'     => sanitize_text_field( $form['submit_label'] ?? '' ),
			'enabled'          => ! empty( $form['enabled'] ),
			'sort_order'       => absint( $form['sort_order'] ?? 0 ),
			'version'          => sanitize_text_field( $form['version'] ?? '1' ),
			'summary_template' => sanitize_textarea_field( $form['summary_template'] ?? '' ),
			'fields'           => $fields,
		);
	}

	public static function get_forms() {
		$repository = self::get_repository();
		$repository->seed_default_forms_if_empty();
		$stored_forms = $repository->get_forms();

		if ( empty( $stored_forms ) ) {
			$stored_forms = CRPCRM_Default_Form_Definitions::get_forms();
		}

		$forms = array();
		foreach ( $stored_forms as $form ) {
			$runtime_form = self::to_runtime_form( $repository->normalize_form( $form ) );
			if ( $runtime_form['id'] ) {
				$forms[ $runtime_form['id'] ] = $runtime_form;
			}
		}
		uasort( $forms, function ( $a, $b ) {
			return $a['sort_order'] <=> $b['sort_order'];
		} );
		return $forms;
	}

	public static function get_aliases() {
		$clean = array();
		foreach ( CRPCRM_Default_Form_Definitions::get_aliases() as $old_id => $new_id ) {
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
		if ( $form_id ) {
			$form = self::get_form( $form_id );
			if ( $form ) { return $form; }
		}
		return self::get_form_by_request_type( $request_type );
	}

	public static function get_enabled_forms() {
		return array_filter( self::get_forms(), function ( $form ) {
			return is_array( $form ) && ! empty( $form['enabled'] );
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

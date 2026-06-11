<?php
/**
 * Storage and schema validation for custom forms managed in wp-admin.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_Form_Builder_Repository {
	const OPTION_NAME = 'crpcrm_custom_forms';

	public function get_forms() {
		$forms = get_option( self::OPTION_NAME, array() );
		$forms = is_array( $forms ) ? $forms : array();
		uasort( $forms, function ( $a, $b ) {
			return absint( $a['sort_order'] ?? 0 ) <=> absint( $b['sort_order'] ?? 0 );
		} );
		return $forms;
	}

	public function get_enabled_forms() {
		return array_filter( $this->get_forms(), function ( $form ) {
			return ! empty( $form['enabled'] );
		} );
	}

	public function get_form( $form_id ) {
		$forms   = $this->get_forms();
		$form_id = sanitize_key( $form_id );
		return isset( $forms[ $form_id ] ) ? $forms[ $form_id ] : null;
	}

	public function save_form( $form, $original_form_id = '' ) {
		$form   = $this->sanitize_form( $form );
		$errors = $this->validate_form_schema( $form );
		if ( ! empty( $errors ) ) {
			return new WP_Error( 'invalid_form_schema', implode( ' ', $errors ) );
		}

		$forms            = $this->get_forms();
		$original_form_id = sanitize_key( $original_form_id );
		if ( isset( $forms[ $form['form_id'] ] ) && $original_form_id !== $form['form_id'] ) {
			return new WP_Error( 'duplicate_form_id', 'شناسه فرم تکراری مجاز نیست.' );
		}
		if ( $original_form_id && $original_form_id !== $form['form_id'] ) {
			return new WP_Error( 'immutable_form_id', 'شناسه فرم ذخیره‌شده قابل تغییر نیست.' );
		}
		$forms[ $form['form_id'] ] = $form;
		return update_option( self::OPTION_NAME, $forms );
	}

	public function delete_form( $form_id ) {
		$forms   = $this->get_forms();
		$form_id = sanitize_key( $form_id );
		if ( ! isset( $forms[ $form_id ] ) ) {
			return false;
		}
		unset( $forms[ $form_id ] );
		return update_option( self::OPTION_NAME, $forms );
	}

	public function generate_form_id( $title ) {
		$base  = sanitize_key( $title );
		$base  = $base ? $base : 'custom_form';
		$id    = $base;
		$index = 2;
		$forms = $this->get_forms();
		while ( isset( $forms[ $id ] ) ) {
			$id = $base . '_' . $index;
			$index++;
		}
		return $id;
	}

	public function sanitize_form( $form ) {
		$form      = is_array( $form ) ? $form : array();
		$title     = sanitize_text_field( $form['title'] ?? '' );
		$form_id   = sanitize_key( $form['form_id'] ?? '' );
		$form_id   = $form_id ? $form_id : $this->generate_form_id( $title );
		$fields    = array();

		foreach ( isset( $form['fields'] ) && is_array( $form['fields'] ) ? $form['fields'] : array() as $field ) {
			$field = is_array( $field ) ? $field : array();
			$key   = sanitize_key( $field['key'] ?? '' );
			$label = sanitize_text_field( $field['label'] ?? '' );
			if ( '' === $key && '' === $label ) {
				continue;
			}
			$type = sanitize_key( $field['type'] ?? 'text' );
			$type = in_array( $type, array( 'text', 'textarea', 'select' ), true ) ? $type : 'text';
			$options = isset( $field['options'] ) && is_array( $field['options'] )
				? $field['options']
				: preg_split( '/\r\n|\r|\n/', (string) ( $field['options'] ?? '' ) );
			$options = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $options ), 'strlen' ) ) );
			$fields[] = array(
				'key'         => $key,
				'label'       => $label,
				'type'        => $type,
				'required'    => ! empty( $field['required'] ),
				'placeholder' => sanitize_text_field( $field['placeholder'] ?? '' ),
				'help_text'   => sanitize_textarea_field( $field['help_text'] ?? '' ),
				'options'     => 'select' === $type ? $options : array(),
				'enabled'     => ! empty( $field['enabled'] ),
				'sort_order'  => absint( $field['sort_order'] ?? 0 ),
			);
		}
		usort( $fields, function ( $a, $b ) {
			return $a['sort_order'] <=> $b['sort_order'];
		} );

		return array(
			'form_id'       => $form_id,
			'title'         => $title,
			'description'   => sanitize_textarea_field( $form['description'] ?? '' ),
			'request_type'  => sanitize_key( $form['request_type'] ?? $form_id ),
			'submit_label'  => sanitize_text_field( $form['submit_label'] ?? 'ثبت درخواست' ),
			'enabled'       => ! empty( $form['enabled'] ),
			'sort_order'    => absint( $form['sort_order'] ?? 0 ),
			'version'       => sanitize_text_field( $form['version'] ?? '1.0.0' ),
			'fields'        => $fields,
		);
	}

	public function normalize_form( $form ) {
		return $this->sanitize_form( $form );
	}

	public function validate_form_schema( $form ) {
		$errors = array();
		if ( empty( $form['form_id'] ) ) {
			$errors[] = 'شناسه فرم الزامی است.';
		}
		if ( empty( $form['title'] ) ) {
			$errors[] = 'عنوان فرم الزامی است.';
		}
		if ( empty( $form['request_type'] ) ) {
			$errors[] = 'نوع درخواست الزامی است.';
		}
		$keys = array();
		foreach ( $form['fields'] as $field ) {
			if ( empty( $field['key'] ) || empty( $field['label'] ) ) {
				$errors[] = 'کلید و عنوان تمام فیلدها الزامی است.';
				continue;
			}
			if ( isset( $keys[ $field['key'] ] ) ) {
				$errors[] = 'کلید فیلد تکراری مجاز نیست: ' . $field['key'];
			}
			$keys[ $field['key'] ] = true;
		}
		return array_values( array_unique( $errors ) );
	}
}

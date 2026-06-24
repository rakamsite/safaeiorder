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
	const SEED_OPTION_NAME = 'crpcrm_default_forms_seeded';
	const FIELD_WIDTHS = array( '25', '33', '50', '100' );

	public function get_forms() {
		$forms = get_option( self::OPTION_NAME, array() );
		$forms = is_array( $forms ) ? $forms : array();
		$normalized_forms = array();
		foreach ( $forms as $form ) {
			$normalized = $this->normalize_form( $form );
			if ( $normalized['form_id'] ) {
				$normalized_forms[ $normalized['form_id'] ] = $normalized;
			}
		}
		uasort( $normalized_forms, function ( $a, $b ) {
			return absint( $a['sort_order'] ?? 0 ) <=> absint( $b['sort_order'] ?? 0 );
		} );
		return $normalized_forms;
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

	public function has_custom_forms() {
		$forms = get_option( self::OPTION_NAME, array() );
		return is_array( $forms ) && ! empty( $forms );
	}

	public function seed_default_forms_if_empty() {
		if ( $this->has_custom_forms() ) {
			update_option( self::SEED_OPTION_NAME, 'yes' );
			return false;
		}
		if ( 'yes' === get_option( self::SEED_OPTION_NAME, '' ) ) {
			return false;
		}

		$seeded = array();
		foreach ( CRPCRM_Default_Form_Definitions::get_forms() as $form_id => $form ) {
			$form['form_id'] = sanitize_key( $form['form_id'] ?? $form['id'] ?? $form_id );
			$form['version'] = '1';
			$normalized      = $this->normalize_form( $form );
			if ( empty( $this->validate_form_schema( $normalized ) ) ) {
				$seeded[ $normalized['form_id'] ] = $normalized;
			}
		}

		if ( empty( $seeded ) || ! update_option( self::OPTION_NAME, $seeded ) ) {
			return false;
		}
		update_option( self::SEED_OPTION_NAME, 'yes' );
		return true;
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
		if ( empty( $form['enabled'] ) ) {
			$enabled_forms = $this->get_enabled_forms();
			$is_last_enabled = isset( $enabled_forms[ $form['form_id'] ] ) && count( $enabled_forms ) <= 1;
			if ( $is_last_enabled ) {
				return new WP_Error( 'last_enabled_form', 'حداقل یک فرم فعال باید باقی بماند.' );
			}
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

		if ( count( $forms ) <= 1 ) {
			return new WP_Error( 'last_form', 'حداقل یک فرم باید در سیستم باقی بماند.' );
		}

		if ( ! empty( $forms[ $form_id ]['enabled'] ) && count( $this->get_enabled_forms() ) <= 1 ) {
			return new WP_Error( 'last_enabled_form', 'حداقل یک فرم فعال باید باقی بماند.' );
		}

		if ( $this->is_default_form_id( $form_id ) ) {
			$forms[ $form_id ]['enabled'] = false;
			return update_option( self::OPTION_NAME, $forms );
		}

		unset( $forms[ $form_id ] );
		return update_option( self::OPTION_NAME, $forms );
	}

	public function restore_default_forms() {
		$forms    = $this->get_forms();
		$restored = 0;

		foreach ( CRPCRM_Default_Form_Definitions::get_forms() as $default_form_id => $default_form ) {
			$default_form['form_id'] = sanitize_key( $default_form['form_id'] ?? $default_form['id'] ?? $default_form_id );
			$default_form['version'] = sanitize_text_field( $default_form['version'] ?? '1.0.0' );
			$normalized              = $this->normalize_form( $default_form );
			if ( empty( $normalized['form_id'] ) ) {
				continue;
			}

			$forms[ $normalized['form_id'] ] = $normalized;
			$restored++;
		}

		if ( $restored > 0 ) {
			update_option( self::SEED_OPTION_NAME, 'yes' );
			update_option( self::OPTION_NAME, $forms );
		}

		return $restored;
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
		return $this->normalize_form( $form );
	}

	public function normalize_field( $field, $default_sort_order = 0 ) {
		$field = is_array( $field ) ? $field : array();
		$key   = sanitize_key( $field['key'] ?? $field['name'] ?? '' );
		$label = sanitize_text_field( $field['label'] ?? '' );
		$type  = sanitize_key( $field['type'] ?? 'text' );
		$type  = in_array( $type, array( 'text', 'textarea', 'select', 'product_search', 'file_upload', 'display_html' ), true ) ? $type : 'text';
		$options = isset( $field['options'] ) && is_array( $field['options'] )
			? $field['options']
			: preg_split( '/\r\n|\r|\n/', (string) ( $field['options'] ?? '' ) );
		$options = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $options ), 'strlen' ) ) );
		$width = (string) ( $field['width'] ?? '100' );
		$width = in_array( $width, self::FIELD_WIDTHS, true ) ? $width : '100';

		return array(
			'key'              => $key,
			'label'            => $label,
			'type'             => $type,
			'required'         => $this->normalize_flag( $field['required'] ?? false, false ),
			'required_message' => sanitize_text_field( $field['required_message'] ?? '' ),
			'placeholder'      => sanitize_text_field( $field['placeholder'] ?? '' ),
			'help_text'        => sanitize_textarea_field( $field['help_text'] ?? $field['help'] ?? '' ),
			'options'          => 'select' === $type ? $options : array(),
			'content'          => wp_kses_post( $field['content'] ?? '' ),
			'width'            => $width,
			'default'          => sanitize_text_field( $field['default'] ?? '' ),
			'enabled'          => $this->normalize_flag( $field['enabled'] ?? null, true ),
			'sort_order'       => absint( $field['sort_order'] ?? $default_sort_order ),
		);
	}

	public function normalize_form( $form ) {
		$form      = is_array( $form ) ? $form : array();
		$title     = sanitize_text_field( $form['title'] ?? '' );
		$form_id   = sanitize_key( $form['form_id'] ?? $form['id'] ?? '' );
		$form_id   = $form_id ? $form_id : $this->generate_form_id( $title );
		$request_type = sanitize_key( $form['request_type'] ?? '' );
		$request_type = $request_type ? $request_type : $form_id;
		$fields    = array();
		$icon_key  = CRPCRM_Form_Icon_Pack::sanitize_icon_key( $form['icon'] ?? '' );

		foreach ( isset( $form['fields'] ) && is_array( $form['fields'] ) ? $form['fields'] : array() as $index => $field ) {
			$field = $this->normalize_field( $field, $index );
			if ( '' === $field['key'] && '' === $field['label'] && ! $this->has_renderable_display_content( $field['content'] ?? '' ) ) {
				continue;
			}
			$fields[] = $field;
		}
		usort( $fields, function ( $a, $b ) {
			return $a['sort_order'] <=> $b['sort_order'];
		} );

		return array(
			'form_id'       => $form_id,
			'title'         => $title,
			'label'         => sanitize_text_field( $form['label'] ?? $title ),
			'page'          => sanitize_key( $form['page'] ?? $form_id ),
			'icon'          => $icon_key,
			'description'   => sanitize_textarea_field( $form['description'] ?? '' ),
			'request_type'  => $request_type,
			'submit_label'  => sanitize_text_field( $form['submit_label'] ?? 'ثبت درخواست' ),
			'summary_template' => sanitize_textarea_field( $form['summary_template'] ?? '' ),
			'enabled'       => $this->normalize_flag( $form['enabled'] ?? null, true ),
			'sort_order'    => absint( $form['sort_order'] ?? 0 ),
			'version'       => sanitize_text_field( $form['version'] ?? '1.0.0' ),
			'fields'        => $fields,
		);
	}

	public function get_form_version( $form_id ) {
		$form = $this->get_form( $form_id );
		return $form ? sanitize_text_field( $form['version'] ?? '' ) : '';
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
		$enabled_field_count = 0;
		foreach ( $form['fields'] as $field ) {
			if ( 'display_html' === $field['type'] ) {
				if ( empty( $field['key'] ) || ! $this->has_renderable_display_content( $field['content'] ?? '' ) ) {
					$errors[] = 'فیلد نمایشی باید کلید و محتوا داشته باشد.';
					continue;
				}
			} elseif ( empty( $field['key'] ) || empty( $field['label'] ) ) {
				$errors[] = 'کلید و عنوان تمام فیلدها الزامی است.';
				continue;
			}
			if ( ! empty( $field['enabled'] ) ) {
				$field_is_valid = true;
				if ( 'select' === $field['type'] && empty( $field['options'] ) ) {
					$errors[] = 'فیلد انتخابی فعال باید حداقل یک گزینه داشته باشد: ' . $field['label'];
					$field_is_valid = false;
				}
				if ( $field_is_valid ) {
					$enabled_field_count++;
				}
			}
			if ( isset( $keys[ $field['key'] ] ) ) {
				$errors[] = 'کلید فیلد تکراری مجاز نیست: ' . $field['key'];
			}
			$keys[ $field['key'] ] = true;
		}
		if ( ! empty( $form['enabled'] ) && 0 === $enabled_field_count ) {
			$errors[] = 'فرم فعال باید حداقل یک فیلد فعال و معتبر داشته باشد.';
		}
		return array_values( array_unique( $errors ) );
	}

	private function normalize_flag( $value, $default = false ) {
		if ( null === $value ) {
			return (bool) $default;
		}
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_numeric( $value ) ) {
			return absint( $value ) > 0;
		}
		$value = strtolower( trim( (string) $value ) );
		if ( in_array( $value, array( '', '0', 'false', 'off', 'no' ), true ) ) {
			return false;
		}
		return true;
	}

	private function is_default_form_id( $form_id ) {
		$form_id = sanitize_key( $form_id );
		return '' !== $form_id && isset( CRPCRM_Default_Form_Definitions::get_forms()[ $form_id ] );
	}

	private function has_renderable_display_content( $content ) {
		$content = wp_kses_post( $content );
		if ( '' === trim( $content ) ) {
			return false;
		}

		if ( '' !== trim( wp_strip_all_tags( $content ) ) ) {
			return true;
		}

		$normalized = preg_replace( '/(?:&nbsp;|&#160;|\s)+/u', '', $content );
		$normalized = preg_replace( '/<([a-z0-9]+)(?:\s[^>]*)?>\s*<\/\1>/iu', '', $normalized );

		return (bool) preg_match( '/<(img|hr|br|svg|path|use|circle|rect|line|polyline|polygon|ellipse)\b/i', $normalized );
	}
}

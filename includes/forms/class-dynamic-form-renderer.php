<?php
/**
 * Shared rendering, validation, and display helpers for dynamic request forms.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_Dynamic_Form_Renderer {
	const ALLOWED_TYPES = array( 'text', 'textarea', 'select' );

	public static function render_fields( $form, $context = 'public' ) {
		$form    = is_array( $form ) ? $form : array();
		$context = 'admin' === $context ? 'admin' : 'public';

		foreach ( self::get_enabled_fields( $form ) as $field ) {
			self::render_field( $field, sanitize_key( $form['id'] ?? $form['form_id'] ?? 'form' ), $context );
		}
	}

	public static function sanitize_and_validate( $form, $posted ) {
		$posted = is_array( $posted ) ? $posted : array();
		$data   = array();
		$errors = array();

		foreach ( self::get_enabled_fields( $form ) as $field ) {
			$name  = sanitize_key( $field['name'] ?? $field['key'] ?? '' );
			$type  = self::normalize_type( $field['type'] ?? 'text' );
			$value = isset( $posted[ $name ] ) ? wp_unslash( $posted[ $name ] ) : '';
			$value = self::sanitize_value( $type, $value );

			if ( ! empty( $field['required'] ) && '' === $value ) {
				$errors[] = self::field_error( $field, 'این فیلد الزامی است.' );
				continue;
			}

			if ( 'select' === $type && '' !== $value && ! array_key_exists( $value, self::get_field_options( $field ) ) ) {
				$errors[] = self::field_error( $field, 'مقدار واردشده معتبر نیست.' );
				continue;
			}

			$data[ $name ] = $value;
		}

		return array(
			'data'   => $data,
			'errors' => array_values( array_unique( $errors ) ),
		);
	}

	public static function get_field_options( $field ) {
		$options = isset( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : array();
		$clean   = array();

		foreach ( $options as $value => $label ) {
			if ( is_int( $value ) ) {
				$value = $label;
			}
			$value = sanitize_text_field( $value );
			$label = sanitize_text_field( $label );
			if ( '' !== $value ) {
				$clean[ $value ] = $label;
			}
		}

		return $clean;
	}

	public static function get_field_labels( $form ) {
		$labels = array();
		foreach ( self::get_enabled_fields( $form ) as $field ) {
			$name = sanitize_key( $field['name'] ?? $field['key'] ?? '' );
			if ( $name ) {
				$labels[ $name ] = sanitize_text_field( $field['label'] ?? $name );
			}
		}
		return $labels;
	}

	public static function get_submission_snapshot( $form ) {
		$form         = is_array( $form ) ? $form : array();
		$form_id      = sanitize_key( $form['id'] ?? $form['form_id'] ?? '' );
		$form_version = sanitize_text_field( $form['version'] ?? '1' );

		return array(
			'_submitted_form_title'    => sanitize_text_field( $form['title'] ?? '' ),
			'_submitted_form_id'       => $form_id,
			'_submitted_form_version'  => $form_version ? $form_version : '1',
			'_submitted_field_labels'  => self::get_field_labels( $form ),
		);
	}

	public static function get_display_items( $form, $request_data ) {
		$request_data = is_array( $request_data ) ? $request_data : array();
		$labels       = $form ? self::get_field_labels( $form ) : array();
		$snapshot     = isset( $request_data['_submitted_field_labels'] ) && is_array( $request_data['_submitted_field_labels'] ) ? $request_data['_submitted_field_labels'] : array();
		$items        = array();

		foreach ( $labels as $key => $label ) {
			if ( array_key_exists( $key, $request_data ) ) {
				$items[ $key ] = array( 'label' => $label, 'value' => self::display_value( $request_data[ $key ] ) );
			}
		}

		foreach ( $request_data as $key => $value ) {
			$key = sanitize_key( $key );
			if ( ! $key || isset( $items[ $key ] ) || 0 === strpos( $key, '_submitted_' ) || in_array( $key, array( 'form_id', 'form_version', 'request_type', 'fields', 'submitted_fields' ), true ) || ! is_scalar( $value ) ) {
				continue;
			}
			$label = isset( $snapshot[ $key ] ) ? sanitize_text_field( $snapshot[ $key ] ) : $key;
			$items[ $key ] = array( 'label' => $label, 'value' => self::display_value( $value ) );
		}

		return $items;
	}

	public static function get_enabled_fields( $form ) {
		$fields = array();
		foreach ( (array) ( $form['fields'] ?? array() ) as $field ) {
			if ( ! is_array( $field ) || ( isset( $field['enabled'] ) && ! $field['enabled'] ) ) {
				continue;
			}
			$field['type'] = self::normalize_type( $field['type'] ?? 'text' );
			$fields[]      = $field;
		}
		usort( $fields, function ( $a, $b ) {
			return absint( $a['sort_order'] ?? 0 ) <=> absint( $b['sort_order'] ?? 0 );
		} );
		return $fields;
	}

	private static function render_field( $field, $form_id, $context ) {
		$type        = self::normalize_type( $field['type'] ?? 'text' );
		$name        = sanitize_key( $field['name'] ?? $field['key'] ?? '' );
		$label       = sanitize_text_field( $field['label'] ?? $name );
		$field_id    = 'crpcrm-field-' . $form_id . '-' . $name;
		$required    = ! empty( $field['required'] );
		$placeholder = sanitize_text_field( $field['placeholder'] ?? '' );
		$help        = sanitize_textarea_field( $field['help'] ?? $field['help_text'] ?? '' );
		$default     = self::sanitize_value( $type, $field['default'] ?? '' );
		$wrapper     = 'admin' === $context ? ( 'textarea' === $type ? 'crpcrm-full-field' : '' ) : 'crpcrm-field crpcrm-field-' . sanitize_html_class( $type );

		$control_class = 'admin' === $context ? '' : ' class="crpcrm-' . esc_attr( $type ) . '"';
		echo '<div class="' . esc_attr( $wrapper ) . '"><label class="crpcrm-field-label" for="' . esc_attr( $field_id ) . '">' . esc_html( $label ) . '</label>';
		if ( 'textarea' === $type ) {
			echo '<textarea' . $control_class . ' id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $name ) . '" placeholder="' . esc_attr( $placeholder ) . '"' . ( $required ? ' required' : '' ) . '>' . esc_textarea( $default ) . '</textarea>';
		} elseif ( 'select' === $type ) {
			echo '<select' . $control_class . ' id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $name ) . '"' . ( $required ? ' required' : '' ) . '><option value="">' . esc_html( 'انتخاب کنید' ) . '</option>';
			foreach ( self::get_field_options( $field ) as $value => $option_label ) {
				echo '<option value="' . esc_attr( $value ) . '"' . selected( $default, $value, false ) . '>' . esc_html( $option_label ) . '</option>';
			}
			echo '</select>';
		} else {
			$input_class = 'admin' === $context ? '' : ' class="crpcrm-input"';
			echo '<input' . $input_class . ' id="' . esc_attr( $field_id ) . '" type="text" name="' . esc_attr( $name ) . '" value="' . esc_attr( $default ) . '" placeholder="' . esc_attr( $placeholder ) . '"' . ( $required ? ' required' : '' ) . '>';
		}
		if ( $help ) {
			echo '<div class="crpcrm-help-text">' . esc_html( $help ) . '</div>';
		}
		echo '</div>';
	}

	private static function normalize_type( $type ) {
		$type = sanitize_key( $type );
		return in_array( $type, self::ALLOWED_TYPES, true ) ? $type : 'text';
	}

	private static function sanitize_value( $type, $value ) {
		if ( is_array( $value ) || is_object( $value ) ) {
			return '';
		}
		return 'textarea' === $type ? trim( sanitize_textarea_field( $value ) ) : trim( sanitize_text_field( $value ) );
	}

	private static function field_error( $field, $fallback ) {
		return ! empty( $field['required_message'] ) ? sanitize_text_field( $field['required_message'] ) : $fallback;
	}

	private static function display_value( $value ) {
		return is_scalar( $value ) ? (string) $value : '';
	}
}

<?php
/**
 * Backward-compatible request form adapter and helpers.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_Request_Forms {
	public static function get_forms() {
		return CRPCRM_Form_Registry::get_enabled_forms();
	}

	public static function get_form( $page ) {
		$page = sanitize_key( $page );
		return CRPCRM_Form_Registry::get_enabled_form( $page );
	}

	public static function get_form_by_request_type( $request_type ) {
		$request_type = sanitize_key( $request_type );
		foreach ( self::get_forms() as $form ) {
			if ( isset( $form['request_type'] ) && $request_type === $form['request_type'] ) {
				return $form;
			}
		}

		return null;
	}

	public static function get_field_labels_for_request_type( $request_type ) {
		$form = self::get_form_by_request_type( $request_type );
		if ( ! $form ) {
			return array();
		}

		$labels = array();
		foreach ( $form['fields'] as $field ) {
			$labels[ $field['name'] ] = $field['label'];
		}

		return $labels;
	}

	public static function build_display_summary( $request_type, $request_data, $fallback = '' ) {
		$form = self::get_form_by_request_type( $request_type );
		if ( ! $form || ! is_array( $request_data ) ) {
			return $fallback;
		}

		$summary = self::build_summary( $form, $request_data );
		return '' !== $summary ? $summary : $fallback;
	}

	public static function get_form_pages() {
		$pages = array();
		foreach ( self::get_forms() as $form_id => $form ) {
			$page    = ! empty( $form['page'] ) ? sanitize_key( $form['page'] ) : sanitize_key( $form_id );
			$pages[] = $page;
		}
		return array_values( array_unique( array_filter( $pages ) ) );
	}

	public static function get_type_label( $request_type ) {
		return CRPCRM_Request_Type_Registry::get_label( $request_type );
	}

	public static function get_customer_status_label( $status ) {
		$labels = array(
			'new'         => 'ثبت شده',
			'in_progress' => 'در حال بررسی',
			'no_answer'   => 'در انتظار تماس',
			'follow_up'   => 'در حال پیگیری',
			'won'         => 'تکمیل شده',
			'lost'        => 'بسته شده',
			'invalid'     => 'بسته شده',
		);
		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}

	public static function get_field_options( $field ) {
		$options    = isset( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : array();
		$normalized = array();
		foreach ( $options as $value => $label ) {
			if ( is_int( $value ) ) {
				$value = $label;
			}
			$normalized[ (string) $value ] = (string) $label;
		}
		return $normalized;
	}

	public static function sanitize_and_validate( $form, $posted ) {
		$data   = array();
		$errors = array();

		foreach ( $form['fields'] as $field ) {
			$name  = sanitize_key( $field['name'] );
			$type  = isset( $field['type'] ) ? sanitize_key( $field['type'] ) : 'text';
			$value = isset( $posted[ $name ] ) ? wp_unslash( $posted[ $name ] ) : '';
			$value = self::sanitize_field_value( $type, $value );

			if ( ! empty( $field['required'] ) && self::is_empty_value( $value ) ) {
				$errors[] = self::get_field_error( $field, 'این فیلد الزامی است.' );
				continue;
			}

			if ( ! self::is_empty_value( $value ) && ! self::is_valid_field_value( $field, $value ) ) {
				$errors[] = self::get_field_error( $field, 'مقدار واردشده معتبر نیست.' );
				continue;
			}

			$data[ $name ] = $value;
		}

		if ( ! empty( $errors ) ) {
			array_unshift( $errors, 'لطفاً فیلدهای الزامی را تکمیل کنید.' );
		}

		return array( 'data' => $data, 'errors' => array_values( array_unique( $errors ) ) );
	}

	public static function build_summary( $form, $data ) {
		if ( ! empty( $form['summary_template'] ) ) {
			$replacements = array();
			foreach ( $data as $key => $value ) {
				if ( is_scalar( $value ) ) {
					$replacements[ '{' . sanitize_key( $key ) . '}' ] = (string) $value;
				}
			}
			$summary = trim( strtr( (string) $form['summary_template'], $replacements ) );
			if ( '' !== $summary ) {
				return $summary;
			}
		}

		$parts = array();
		foreach ( $form['fields'] as $field ) {
			$name = $field['name'];
			if ( isset( $data[ $name ] ) && ! self::is_empty_value( $data[ $name ] ) ) {
				$parts[] = $field['label'] . ': ' . ( is_array( $data[ $name ] ) ? implode( '، ', $data[ $name ] ) : $data[ $name ] );
			}
		}

		return implode( ' | ', $parts );
	}

	private static function sanitize_field_value( $type, $value ) {
		if ( 'checkbox' === $type && is_array( $value ) ) {
			return array_values( array_filter( array_map( 'sanitize_text_field', $value ), 'strlen' ) );
		}
		if ( is_array( $value ) ) {
			return '';
		}
		if ( 'textarea' === $type ) {
			return trim( sanitize_textarea_field( $value ) );
		}
		if ( 'email' === $type ) {
			return sanitize_email( $value );
		}
		return trim( sanitize_text_field( $value ) );
	}

	private static function is_valid_field_value( $field, $value ) {
		$type = isset( $field['type'] ) ? sanitize_key( $field['type'] ) : 'text';
		if ( in_array( $type, array( 'select', 'radio', 'checkbox' ), true ) && ! empty( $field['options'] ) ) {
			$allowed = array_keys( self::get_field_options( $field ) );
			$values  = is_array( $value ) ? $value : array( $value );
			if ( array_diff( $values, $allowed ) ) {
				return false;
			}
		}
		if ( 'email' === $type ) {
			return (bool) is_email( $value );
		}
		if ( 'number' === $type ) {
			return is_numeric( $value );
		}
		if ( 'mobile' === $type ) {
			return (bool) preg_match( '/^\+?[0-9]{10,15}$/', $value );
		}
		if ( 'date' === $type ) {
			$date = date_parse_from_format( 'Y-m-d', $value );
			return 0 === $date['warning_count'] && 0 === $date['error_count'];
		}
		return true;
	}

	private static function is_empty_value( $value ) {
		return '' === $value || array() === $value;
	}

	private static function get_field_error( $field, $fallback ) {
		return ! empty( $field['required_message'] ) ? sanitize_text_field( $field['required_message'] ) : $fallback;
	}
}

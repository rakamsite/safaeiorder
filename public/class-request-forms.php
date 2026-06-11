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
		return CRPCRM_Form_Registry::get_form_by_request_type( $request_type );
	}

	public static function get_form_for_request( $request_type, $request_data ) {
		return CRPCRM_Form_Registry::get_form_for_request( $request_type, $request_data );
	}

	public static function get_field_labels_for_request_type( $request_type, $request_data = array() ) {
		$form = self::get_form_for_request( $request_type, $request_data );
		return $form ? CRPCRM_Dynamic_Form_Renderer::get_field_labels( $form ) : array();
	}

	public static function build_display_summary( $request_type, $request_data, $fallback = '' ) {
		$form = self::get_form_for_request( $request_type, $request_data );
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
		return CRPCRM_Dynamic_Form_Renderer::get_field_options( $field );
	}

	public static function sanitize_and_validate( $form, $posted ) {
		return CRPCRM_Dynamic_Form_Renderer::sanitize_and_validate( $form, $posted );
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

	private static function is_empty_value( $value ) {
		return '' === $value || array() === $value;
	}
}

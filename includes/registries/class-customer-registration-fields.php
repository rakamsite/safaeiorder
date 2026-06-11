<?php
/**
 * Central customer registration field definitions and settings.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_Customer_Registration_Fields {
	const OPTION_NAME   = 'crpcrm_customer_registration_fields';
	const META_PREFIX   = 'crpcrm_registration_';

	public static function get_default_activity_options() {
		return array(
			'تولید کننده ماشین آلات سنگین',
			'راه آهن و ریلی',
			'نفت و پتروشیمی',
			'کمپرسور و ژنراتور',
			'معادن',
			'سایر',
		);
	}

	public static function get_fields() {
		return array(
			'full_name'      => array( 'label' => 'نام و نام خانوادگی', 'type' => 'text' ),
			'company_name'   => array( 'label' => 'نام شرکت / فروشگاه', 'type' => 'text' ),
			'activity_field' => array( 'label' => 'حوزه فعالیت', 'type' => 'select' ),
			'role_title'     => array( 'label' => 'سمت شما', 'type' => 'text' ),
			'birth_date'     => array( 'label' => 'تاریخ تولد', 'type' => 'date' ),
			'city'           => array( 'label' => 'شهر', 'type' => 'text' ),
			'province'       => array( 'label' => 'استان', 'type' => 'province' ),
			'address'        => array( 'label' => 'آدرس', 'type' => 'textarea' ),
			'postal_code'    => array( 'label' => 'کد پستی', 'type' => 'text' ),
			'national_id'    => array( 'label' => 'کد ملی / شناسه ملی', 'type' => 'text' ),
			'email'          => array( 'label' => 'ایمیل', 'type' => 'email' ),
		);
	}

	public static function get_default_settings() {
		$defaults = array();
		foreach ( array_keys( self::get_fields() ) as $order => $field ) {
			$defaults[ $field ] = array(
				'enabled'  => in_array( $field, array( 'full_name', 'activity_field' ), true ),
				'required' => in_array( $field, array( 'full_name', 'activity_field' ), true ),
				'order'    => $order,
			);
			if ( 'activity_field' === $field ) {
				$defaults[ $field ]['options'] = self::get_default_activity_options();
			}
		}
		return $defaults;
	}

	public static function get_settings() {
		$settings = self::get_default_settings();
		if ( ! CRPCRM_Feature_Manager::is_enabled( 'customer_registration' ) ) {
			return $settings;
		}

		$stored = get_option( self::OPTION_NAME, array() );
		foreach ( $settings as $field => $default ) {
			if ( isset( $stored[ $field ] ) && is_array( $stored[ $field ] ) ) {
				$enabled                       = ! empty( $stored[ $field ]['enabled'] );
				$settings[ $field ]['enabled']  = $enabled;
				$settings[ $field ]['required'] = $enabled && ! empty( $stored[ $field ]['required'] );
				$settings[ $field ]['order']    = isset( $stored[ $field ]['order'] ) ? absint( $stored[ $field ]['order'] ) : $settings[ $field ]['order'];
				if ( 'activity_field' === $field && isset( $stored[ $field ]['options'] ) && is_array( $stored[ $field ]['options'] ) ) {
					$settings[ $field ]['options'] = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $stored[ $field ]['options'] ) ) ) );
					if ( $settings[ $field ]['enabled'] && $settings[ $field ]['required'] && empty( $settings[ $field ]['options'] ) ) {
						$settings[ $field ]['options'] = self::get_default_activity_options();
					}
				}
			}
		}
		return $settings;
	}

	public static function save_settings( $settings ) {
		if ( ! CRPCRM_Feature_Manager::is_enabled( 'customer_registration' ) ) {
			return false;
		}

		$clean           = array();
		$submitted_order = array();
		foreach ( array_keys( self::get_fields() ) as $default_order => $field ) {
			$enabled = isset( $settings[ $field ]['enabled'] ) && 'yes' === $settings[ $field ]['enabled'];
			$clean[ $field ] = array(
				'enabled'  => $enabled,
				'required' => $enabled && isset( $settings[ $field ]['required'] ) && 'yes' === $settings[ $field ]['required'],
				'order'    => $default_order,
			);
			$submitted_order[ $field ] = isset( $settings[ $field ]['order'] ) ? absint( $settings[ $field ]['order'] ) : $default_order;
			if ( 'activity_field' === $field ) {
				$options = isset( $settings[ $field ]['options'] ) ? preg_split( '/\r\n|\r|\n/', (string) $settings[ $field ]['options'] ) : array();
				$clean[ $field ]['options'] = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $options ) ) ) );
				if ( $clean[ $field ]['enabled'] && $clean[ $field ]['required'] && empty( $clean[ $field ]['options'] ) ) {
					$clean[ $field ]['options'] = self::get_default_activity_options();
				}
			}
		}
		asort( $submitted_order, SORT_NUMERIC );
		foreach ( array_keys( $submitted_order ) as $order => $field ) {
			$clean[ $field ]['order'] = $order;
		}
		return update_option( self::OPTION_NAME, $clean );
	}

	public static function get_ordered_fields() {
		$fields   = self::get_fields();
		$settings = self::get_settings();
		uksort(
			$fields,
			static function ( $first, $second ) use ( $settings ) {
				$first_order  = isset( $settings[ $first ]['order'] ) ? absint( $settings[ $first ]['order'] ) : PHP_INT_MAX;
				$second_order = isset( $settings[ $second ]['order'] ) ? absint( $settings[ $second ]['order'] ) : PHP_INT_MAX;
				return $first_order === $second_order ? strcmp( $first, $second ) : $first_order - $second_order;
			}
		);
		return $fields;
	}

	public static function get_enabled_fields() {
		if ( ! CRPCRM_Feature_Manager::is_enabled( 'customer_registration' ) ) {
			return array();
		}

		$enabled  = array();
		$settings = self::get_settings();
		foreach ( self::get_ordered_fields() as $field => $definition ) {
			if ( ! empty( $settings[ $field ]['enabled'] ) ) {
				$definition['required'] = ! empty( $settings[ $field ]['required'] );
				if ( isset( $settings[ $field ]['options'] ) ) {
					$definition['options'] = $settings[ $field ]['options'];
				}
				$enabled[ $field ]      = $definition;
			}
		}
		return $enabled;
	}

	public static function is_enabled( $field ) {
		return isset( self::get_enabled_fields()[ sanitize_key( $field ) ] );
	}

	public static function is_required( $field ) {
		$field    = sanitize_key( $field );
		$settings = self::get_settings();
		return ! empty( $settings[ $field ]['enabled'] ) && ! empty( $settings[ $field ]['required'] );
	}

	public static function sanitize_registration_input( $input ) {
		if ( ! CRPCRM_Feature_Manager::is_enabled( 'customer_registration' ) ) {
			return array();
		}

		$clean = array();
		foreach ( self::get_enabled_fields() as $field => $definition ) {
			$value = isset( $input[ $field ] ) ? wp_unslash( $input[ $field ] ) : '';
			$clean[ $field ] = self::sanitize_field_value( $value, $definition['type'] );
		}
		return $clean;
	}

	public static function validate_registration_input( $input, $user_id = 0 ) {
		$errors = array();
		foreach ( self::get_enabled_fields() as $field => $definition ) {
			$raw_value = isset( $input[ $field ] ) ? wp_unslash( $input[ $field ] ) : '';
			$value     = self::sanitize_field_value( $raw_value, $definition['type'] );
			if ( ! empty( $definition['required'] ) && '' === $value ) {
				$errors[] = sprintf( '%s الزامی است.', $definition['label'] );
			}
			if ( 'email' === $definition['type'] && '' !== trim( (string) $raw_value ) && ! is_email( $raw_value ) ) {
				$errors[] = 'ایمیل واردشده معتبر نیست.';
			}
			if ( 'email' === $definition['type'] && is_email( $value ) ) {
				$existing_user_id = email_exists( $value );
				if ( $existing_user_id && absint( $existing_user_id ) !== absint( $user_id ) ) {
					$errors[] = 'این ایمیل قبلاً برای حساب دیگری ثبت شده است.';
				}
			}
			if ( 'date' === $definition['type'] && '' !== trim( (string) $raw_value ) && ! self::is_valid_date( $raw_value ) ) {
				$errors[] = sprintf( '%s معتبر نیست.', $definition['label'] );
			}
			if ( 'select' === $definition['type'] && '' !== $value && ! in_array( $value, $definition['options'], true ) ) {
				$errors[] = sprintf( '%s معتبر نیست.', $definition['label'] );
			}
		}
		return $errors;
	}

	private static function sanitize_field_value( $value, $type ) {
		if ( 'email' === $type ) {
			return sanitize_email( $value );
		}
		if ( 'textarea' === $type ) {
			return sanitize_textarea_field( $value );
		}
		if ( 'date' === $type ) {
			$value = sanitize_text_field( $value );
			return self::is_valid_date( $value ) ? $value : '';
		}
		return sanitize_text_field( $value );
	}

	private static function is_valid_date( $value ) {
		$value = sanitize_text_field( $value );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return false;
		}

		list( $year, $month, $day ) = array_map( 'absint', explode( '-', $value ) );
		return checkdate( $month, $day, $year );
	}

	public static function save_customer_values( $customer, $values ) {
		if ( ! CRPCRM_Feature_Manager::is_enabled( 'customer_registration' ) || empty( $customer['user_id'] ) ) {
			return;
		}

		$user_id = absint( $customer['user_id'] );
		foreach ( self::get_enabled_fields() as $field => $definition ) {
			$value = isset( $values[ $field ] ) ? $values[ $field ] : '';
			if ( in_array( $field, array( 'full_name', 'city', 'province' ), true ) ) {
				continue;
			}
			if ( 'email' === $field ) {
				if ( '' !== $value && is_email( $value ) ) {
					wp_update_user( array( 'ID' => $user_id, 'user_email' => $value ) );
				}
				continue;
			}
			update_user_meta( $user_id, self::META_PREFIX . $field, $value );
		}
	}

	public static function get_customer_values( $customer ) {
		$values  = array();
		$user_id = isset( $customer['user_id'] ) ? absint( $customer['user_id'] ) : 0;
		$user    = $user_id ? get_userdata( $user_id ) : null;
		foreach ( self::get_fields() as $field => $definition ) {
			if ( in_array( $field, array( 'full_name', 'city', 'province' ), true ) ) {
				$values[ $field ] = isset( $customer[ $field ] ) ? $customer[ $field ] : '';
			} elseif ( 'email' === $field ) {
				$values[ $field ] = $user ? $user->user_email : '';
			} else {
				$values[ $field ] = $user_id ? get_user_meta( $user_id, self::META_PREFIX . $field, true ) : '';
			}
		}
		return $values;
	}
}

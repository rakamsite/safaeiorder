<?php
/**
 * Safe public theme values supplied by the active business profile.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_Public_Theme {
	private static $token_map = array(
		'primary'         => '--crpcrm-primary',
		'primary_hover'   => '--crpcrm-primary-hover',
		'secondary'       => '--crpcrm-secondary',
		'bg'              => '--crpcrm-bg',
		'card_bg'         => '--crpcrm-card-bg',
		'text'            => '--crpcrm-text',
		'muted_text'      => '--crpcrm-muted-text',
		'border'          => '--crpcrm-border',
		'field_bg'        => '--crpcrm-field-bg',
		'field_border'    => '--crpcrm-field-border',
		'error'           => '--crpcrm-error',
		'success'         => '--crpcrm-success',
		'warning'         => '--crpcrm-warning',
		'radius'          => '--crpcrm-radius',
		'radius_sm'       => '--crpcrm-radius-sm',
	);

	public static function get_profile_class() {
		$profile_id = CRPCRM_Business_Profile_Manager::get_instance()->get_active_profile_id();
		return 'crpcrm-profile-' . sanitize_html_class( $profile_id, 'default' );
	}

	public static function get_inline_style() {
		$profile = CRPCRM_Business_Profile_Manager::get_instance()->get_active_profile();
		$tokens  = $profile ? $profile->get_theme_tokens() : array();
		$styles  = array();

		foreach ( is_array( $tokens ) ? $tokens : array() as $name => $value ) {
			$name = sanitize_key( $name );
			if ( ! isset( self::$token_map[ $name ] ) ) {
				continue;
			}

			$value = self::sanitize_token_value( $name, $value );
			if ( '' !== $value ) {
				$styles[] = self::$token_map[ $name ] . ':' . $value;
			}
		}

		return implode( ';', $styles );
	}

	private static function sanitize_token_value( $name, $value ) {
		$value = trim( (string) $value );
		if ( in_array( $name, array( 'radius', 'radius_sm' ), true ) ) {
			return preg_match( '/^(?:0|\d+(?:\.\d+)?(?:px|rem|em))$/', $value ) ? $value : '';
		}

		$color = sanitize_hex_color( $value );
		return $color ? $color : '';
	}
}

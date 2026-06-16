<?php
/**
 * General feature toggle manager.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_Feature_Manager {
	const OPTION_NAME = 'crpcrm_enabled_features';

	public static function get_features() {
		return array(
			'portal'                => 'پرتال مشتری',
			'customer_registration' => 'ثبت‌نام مشتری جدید',
			'staff'                 => 'کارکنان و کارشناسان',
			'reports'               => 'گزارش‌ها',
			'otp'                   => 'ورود/احراز هویت OTP',
			'sms'                   => 'پیامک',
			'tracking'              => 'رهگیری ورودی‌ها',
			'lead_followup'         => 'پیگیری لیدها',
			'form_builder'          => 'فرم‌ساز',
			'landing_manager'       => 'مدیریت لندینگ‌ها',
			'notifications'         => 'اعلانات مرکزی',
			'admin_tools'           => 'ابزارهای مدیریتی',
		);
	}

	public static function get_default_features() {
		return array_fill_keys( array_keys( self::get_features() ), true );
	}

	public static function get_enabled_features() {
		$stored   = get_option( self::OPTION_NAME, array() );
		$stored   = is_array( $stored ) ? $stored : array();
		$features = self::get_default_features();

		foreach ( $features as $feature => $default ) {
			if ( array_key_exists( $feature, $stored ) ) {
				$features[ $feature ] = (bool) $stored[ $feature ];
			}
		}

		return $features;
	}

	public static function is_enabled( $feature ) {
		$features = self::get_enabled_features();
		$feature  = sanitize_key( $feature );
		return isset( $features[ $feature ] ) && true === $features[ $feature ];
	}

	public static function is_crm_ui_enabled() {
		foreach ( array( 'portal', 'customer_registration', 'staff', 'reports', 'otp', 'tracking', 'lead_followup', 'admin_tools', 'form_builder', 'landing_manager' ) as $feature ) {
			if ( self::is_enabled( $feature ) ) {
				return true;
			}
		}

		return false;
	}

	public static function save_features( $features ) {
		$clean = array();
		foreach ( array_keys( self::get_features() ) as $feature ) {
			$clean[ $feature ] = isset( $features[ $feature ] ) && in_array( $features[ $feature ], array( true, 1, '1', 'yes', 'on' ), true );
		}

		$saved = update_option( self::OPTION_NAME, $clean );

		if ( class_exists( 'CRPCRM_Lead_Follow_Up_Service' ) ) {
			$clean['lead_followup'] ? CRPCRM_Lead_Follow_Up_Service::schedule_cron() : CRPCRM_Lead_Follow_Up_Service::clear_cron();
		}

		if ( class_exists( 'CRPCRM_Admin_Tools' ) ) {
			$clean['admin_tools'] ? CRPCRM_Admin_Tools::schedule_cron() : CRPCRM_Admin_Tools::clear_cron();
		}

		return $saved;
	}

	public static function disabled_message() {
		return 'این قابلیت غیرفعال است';
	}
}

<?php
/**
 * Profile-aware feature toggle manager.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_Feature_Manager {
	const OPTION_PREFIX = 'crpcrm_enabled_features_';

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
			'vehicle_catalog'       => 'تنظیمات خودرو',
			'admin_tools'           => 'ابزارهای مدیریتی',
		);
	}

	public static function get_default_features() {
		return array_fill_keys( array_keys( self::get_features() ), true );
	}

	public static function get_profile_default_features() {
		$defaults = self::get_default_features();
		$profile  = CRPCRM_Business_Profile_Manager::get_instance()->get_active_profile();
		if ( $profile && method_exists( $profile, 'get_default_features' ) ) {
			foreach ( (array) $profile->get_default_features() as $feature => $enabled ) {
				$feature = sanitize_key( $feature );
				if ( array_key_exists( $feature, $defaults ) ) {
					$defaults[ $feature ] = (bool) $enabled;
				}
			}
		}
		return $defaults;
	}

	public static function get_enabled_features() {
		$profile_id = CRPCRM_Business_Profile_Manager::get_locked_profile_id();
		$stored     = $profile_id ? get_option( self::OPTION_PREFIX . $profile_id, array() ) : array();
		$stored     = is_array( $stored ) ? $stored : array();
		$features   = self::get_profile_default_features();
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

	public static function save_features( $features ) {
		$profile_id = CRPCRM_Business_Profile_Manager::get_locked_profile_id();
		if ( ! $profile_id ) {
			return false;
		}
		$clean = array();
		foreach ( array_keys( self::get_features() ) as $feature ) {
			$clean[ $feature ] = isset( $features[ $feature ] ) && in_array( $features[ $feature ], array( true, 1, '1', 'yes', 'on' ), true );
		}
		$saved = update_option( self::OPTION_PREFIX . $profile_id, $clean );
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

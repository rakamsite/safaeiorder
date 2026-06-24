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
	const NOTICE_TRANSIENT = 'crpcrm_feature_toggle_notice';

	public static function get_feature_definitions() {
		return array(
			'portal' => array(
				'label' => 'پرتال مشتری',
				'note'  => 'برای ورود عمومی به OTP و پیامک وابسته است، اما برای کاربر واردشده همچنان قابل استفاده می‌ماند.',
				'dependencies' => array(),
			),
			'customer_registration' => array(
				'label' => 'ثبت‌نام مشتری جدید',
				'note'  => 'پروفایل مشتری و ثبت اولیه را مدیریت می‌کند.',
				'dependencies' => array(),
			),
			'staff' => array(
				'label' => 'کارکنان و کارشناسان',
				'note'  => 'فقط منو، تخصیص، claim/release و آمار کارکنان را کنترل می‌کند.',
				'dependencies' => array(),
			),
			'reports' => array(
				'label' => 'گزارش‌ها',
				'note'  => 'در نبود staff یا landing فقط بخش‌های وابسته را خالی نشان می‌دهد.',
				'dependencies' => array(),
			),
			'otp' => array(
				'label' => 'ورود/احراز هویت OTP',
				'note'  => 'برای ارسال کد به پیامک وابسته است.',
				'dependencies' => array( 'sms' ),
			),
			'sms' => array(
				'label' => 'پیامک',
				'note'  => 'زیرساخت ارسال OTP و اعلان‌های پیامکی.',
				'dependencies' => array(),
			),
			'tracking' => array(
				'label' => 'رهگیری ورودی‌ها',
				'note'  => 'داده‌های attribution و ثبت کلیک‌های landing را فعال می‌کند.',
				'dependencies' => array(),
			),
			'lead_followup' => array(
				'label' => 'پیگیری لیدها',
				'note'  => 'فقط وقتی معنی دارد که ثبت‌نام مشتری فعال باشد.',
				'dependencies' => array( 'customer_registration' ),
			),
			'form_builder' => array(
				'label' => 'فرم‌ساز',
				'note'  => 'فرم‌های ذخیره‌شده باقی می‌مانند؛ فقط مدیریت آنها را می‌بندد.',
				'dependencies' => array(),
			),
			'landing_manager' => array(
				'label' => 'مدیریت لندینگ‌ها',
				'note'  => 'اگر tracking خاموش باشد، فقط مدیریت لندینگ باقی می‌ماند و ثبت کلیک غیرفعال می‌شود.',
				'dependencies' => array(),
			),
			'notifications' => array(
				'label' => 'اعلانات مرکزی',
				'note'  => 'اعلان‌های staff فقط وقتی staff روشن باشد ارسال می‌شوند.',
				'dependencies' => array(),
			),
			'admin_tools' => array(
				'label' => 'ابزارهای مدیریتی',
				'note'  => 'ابزارهای نگهداری و خروجی را کنترل می‌کند.',
				'dependencies' => array(),
			),
		);
	}

	public static function get_features() {
		$labels = array();
		foreach ( self::get_feature_definitions() as $feature_id => $feature ) {
			$labels[ $feature_id ] = $feature['label'];
		}

		return $labels;
	}

	public static function get_default_features() {
		return array_fill_keys( array_keys( self::get_features() ), true );
	}

	public static function get_feature_dependencies( $feature ) {
		$feature = sanitize_key( $feature );
		$defs    = self::get_feature_definitions();

		if ( empty( $defs[ $feature ]['dependencies'] ) || ! is_array( $defs[ $feature ]['dependencies'] ) ) {
			return array();
		}

		return array_values( array_filter( array_map( 'sanitize_key', $defs[ $feature ]['dependencies'] ) ) );
	}

	public static function get_feature_note( $feature ) {
		$feature = sanitize_key( $feature );
		$defs    = self::get_feature_definitions();

		return isset( $defs[ $feature ]['note'] ) ? sanitize_text_field( $defs[ $feature ]['note'] ) : '';
	}

	public static function get_dependency_summary( $feature ) {
		$dependencies = self::get_feature_dependencies( $feature );
		if ( empty( $dependencies ) ) {
			return '';
		}

		$labels = array();
		$all    = self::get_features();
		foreach ( $dependencies as $dependency ) {
			$labels[] = isset( $all[ $dependency ] ) ? $all[ $dependency ] : $dependency;
		}

		return implode( '، ', $labels );
	}

	public static function get_feature_toggle_message( $feature ) {
		$messages = array();
		$note      = self::get_feature_note( $feature );
		$deps      = self::get_dependency_summary( $feature );

		if ( '' !== $note ) {
			$messages[] = $note;
		}
		if ( '' !== $deps ) {
			$messages[] = 'وابسته به: ' . $deps . '.';
		}

		return implode( ' ', $messages );
	}

	public static function normalize_features( $features, &$messages = array() ) {
		$features = is_array( $features ) ? $features : array();
		$clean    = self::get_default_features();

		foreach ( $clean as $feature => $default ) {
			if ( array_key_exists( $feature, $features ) ) {
				$clean[ $feature ] = (bool) $features[ $feature ];
			}
		}

		$changed = true;
		while ( $changed ) {
			$changed = false;
			foreach ( self::get_feature_definitions() as $feature_id => $config ) {
				if ( empty( $clean[ $feature_id ] ) ) {
					continue;
				}

				foreach ( self::get_feature_dependencies( $feature_id ) as $dependency ) {
					if ( empty( $clean[ $dependency ] ) ) {
						$clean[ $feature_id ] = false;
						$messages[] = sprintf(
							'%s به دلیل خاموش بودن %s غیرفعال شد.',
							self::get_features()[ $feature_id ],
							self::get_features()[ $dependency ]
						);
						$changed = true;
						break;
					}
				}
			}
		}

		return $clean;
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

		$messages   = array();
		$normalized = self::normalize_features( $features, $messages );

		if ( $normalized !== $features ) {
			update_option( self::OPTION_NAME, $normalized );
			if ( ! empty( $messages ) ) {
				self::set_toggle_notice( $messages );
			}
		}

		return $normalized;
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

	public static function get_dependency_issues( $features = null ) {
		$features = null === $features ? self::get_enabled_features() : ( is_array( $features ) ? $features : array() );
		$issues   = array();
		$labels   = self::get_features();

		foreach ( self::get_feature_definitions() as $feature_id => $config ) {
			if ( empty( $features[ $feature_id ] ) ) {
				continue;
			}

			foreach ( self::get_feature_dependencies( $feature_id ) as $dependency ) {
				if ( empty( $features[ $dependency ] ) ) {
					$issues[] = array(
						'feature'    => $feature_id,
						'dependency' => $dependency,
						'message'    => sprintf(
							'%s به %s وابسته است و غیرفعال شد.',
							isset( $labels[ $feature_id ] ) ? $labels[ $feature_id ] : $feature_id,
							isset( $labels[ $dependency ] ) ? $labels[ $dependency ] : $dependency
						),
					);
					break;
				}
			}
		}

		return $issues;
	}

	public static function save_features( $features ) {
		$raw = array();
		foreach ( array_keys( self::get_features() ) as $feature ) {
			$raw[ $feature ] = isset( $features[ $feature ] ) && in_array( $features[ $feature ], array( true, 1, '1', 'yes', 'on' ), true );
		}

		$messages = array();
		$clean    = self::normalize_features( $raw, $messages );

		$saved = update_option( self::OPTION_NAME, $clean );
		self::set_toggle_notice( $messages );

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

	public static function disabled_message_for_feature( $feature ) {
		$feature = sanitize_key( $feature );
		$labels  = self::get_features();
		$label   = isset( $labels[ $feature ] ) ? $labels[ $feature ] : $feature;
		$note    = self::get_feature_toggle_message( $feature );

		if ( '' === $note ) {
			return sprintf( '%s غیرفعال است.', $label );
		}

		return sprintf( '%s غیرفعال است. %s', $label, $note );
	}

	public static function get_toggle_notice() {
		$notice = get_transient( self::NOTICE_TRANSIENT );
		delete_transient( self::NOTICE_TRANSIENT );

		return is_array( $notice ) ? $notice : array();
	}

	public static function set_toggle_notice( array $messages ) {
		$messages = array_values( array_filter( array_map( 'sanitize_text_field', $messages ) ) );
		if ( empty( $messages ) ) {
			delete_transient( self::NOTICE_TRANSIENT );
			return;
		}

		set_transient( self::NOTICE_TRANSIENT, $messages, MINUTE_IN_SECONDS * 2 );
	}
}

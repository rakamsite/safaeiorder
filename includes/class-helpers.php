<?php
/**
 * Shared helper functions.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_Helpers {
	public static function normalize_iran_phone( $phone ) {
		$phone = self::convert_persian_digits( (string) $phone );
		$phone = preg_replace( '/[^0-9+]/', '', $phone );

		if ( 0 === strpos( $phone, '+98' ) ) {
			$phone = substr( $phone, 1 );
		} elseif ( 0 === strpos( $phone, '09' ) && 11 === strlen( $phone ) ) {
			$phone = '98' . substr( $phone, 1 );
		} elseif ( 0 === strpos( $phone, '9' ) && 10 === strlen( $phone ) ) {
			$phone = '98' . $phone;
		}

		return sanitize_text_field( $phone );
	}

	public static function is_valid_iran_phone_normalized( $phone ) {
		return 1 === preg_match( '/^989\d{9}$/', (string) $phone );
	}

	public static function convert_digits_public( $value ) {
		return self::convert_persian_digits( (string) $value );
	}

	public static function current_datetime() {
		return current_time( 'mysql' );
	}

	public static function sanitize_array( $value ) {
		if ( is_array( $value ) ) {
			$clean = array();
			foreach ( $value as $key => $item ) {
				$clean[ sanitize_key( $key ) ] = self::sanitize_array( $item );
			}
			return $clean;
		}

		if ( is_bool( $value ) || is_numeric( $value ) ) {
			return $value;
		}

		return sanitize_textarea_field( (string) $value );
	}

	public static function maybe_json_encode( $value ) {
		if ( null === $value || '' === $value ) {
			return null;
		}

		if ( is_string( $value ) ) {
			json_decode( $value );
			return JSON_ERROR_NONE === json_last_error() ? $value : wp_json_encode( $value );
		}

		return wp_json_encode( $value );
	}

	public static function maybe_json_decode( $value, $assoc = true ) {
		if ( empty( $value ) || ! is_string( $value ) ) {
			return $value;
		}

		$decoded = json_decode( $value, $assoc );
		return JSON_ERROR_NONE === json_last_error() ? $decoded : $value;
	}

	public static function get_persian_status_label( $status ) {
		$labels = array(
			'new'         => 'جدید',
			'in_progress' => 'در حال پیگیری',
			'no_answer'   => 'پاسخ نداد',
			'follow_up'   => 'پیگیری بعدی',
			'won'         => 'موفق',
			'lost'        => 'ناموفق',
			'invalid'     => 'نامعتبر',
			'low'         => 'کم',
			'normal'      => 'معمولی',
			'high'        => 'زیاد',
		);

		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}


	public static function get_request_type_label( $request_type ) {
		$labels = array(
			'car_registration' => 'ثبت‌نام خودرو',
			'parts_request'    => 'درخواست قطعات',
			'repair_booking'   => 'درخواست تعمیرات',
		);

		return isset( $labels[ $request_type ] ) ? $labels[ $request_type ] : $request_type;
	}

	public static function get_source_label( $source ) {
		$labels = array(
			'direct'    => 'مستقیم',
			'instagram' => 'اینستاگرام',
			'whatsapp'  => 'واتساپ',
			'google'    => 'گوگل',
			'bing'      => 'بینگ',
			'telegram'  => 'تلگرام',
			'other'     => 'سایر',
		);

		return isset( $labels[ $source ] ) ? $labels[ $source ] : ( $source ? $source : 'ثبت نشده' );
	}


	public static function get_medium_label( $medium ) {
		$labels = array(
			'social'   => 'شبکه اجتماعی',
			'referral' => 'ارجاعی',
			'organic'  => 'ارگانیک',
			'none'     => 'بدون مدیوم',
		);

		return isset( $labels[ $medium ] ) ? $labels[ $medium ] : ( $medium ? $medium : 'ثبت نشده' );
	}

	public static function get_activity_type_label( $activity_type ) {
		$labels = array(
			'request_created'          => 'ثبت درخواست',
			'request_claimed'          => 'شروع پیگیری',
			'request_owner_changed'    => 'تغییر مسئول',
			'request_owner_released'   => 'آزادسازی مسئول',
			'call_answered'            => 'تماس پاسخ داده شد',
			'call_no_answer'           => 'تماس پاسخ داده نشد',
			'whatsapp_sent'            => 'پیام واتساپ ارسال شد',
			'internal_note'            => 'یادداشت داخلی',
			'follow_up_scheduled'      => 'پیگیری بعدی تنظیم شد',
			'request_won'              => 'درخواست موفق شد',
			'request_lost'             => 'درخواست ناموفق شد',
			'request_invalid'          => 'درخواست نامعتبر شد',
			'status_changed'           => 'تغییر وضعیت',
		);

		return isset( $labels[ $activity_type ] ) ? $labels[ $activity_type ] : $activity_type;
	}

	public static function get_owner_label( $owner_id ) {
		$owner_id = absint( $owner_id );
		if ( ! $owner_id ) {
			return 'بدون مسئول';
		}

		$user = get_userdata( $owner_id );
		if ( ! $user ) {
			return 'در حال پیگیری توسط: کاربر حذف‌شده';
		}

		return 'در حال پیگیری توسط: ' . $user->display_name;
	}

	private static function convert_persian_digits( $value ) {
		$persian = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' );
		$latin   = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );
		return str_replace( $persian, $latin, $value );
	}
}

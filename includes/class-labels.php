<?php
/**
 * Central Persian labels for visible plugin UI.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_Labels {
	private static function from_map( $value, $map, $empty = 'ثبت نشده' ) {
		$value = (string) $value;
		if ( '' === $value ) {
			return $empty;
		}
		return isset( $map[ $value ] ) ? $map[ $value ] : $value;
	}

	public static function get_request_type_label( $type ) {
		if ( class_exists( 'CRPCRM_Request_Type_Registry' ) ) {
			return CRPCRM_Request_Type_Registry::get_label( $type );
		}

		return self::from_map( $type, array(
			'car_registration' => 'ثبت‌نام خودرو',
			'parts_request'    => 'درخواست قطعات',
			'repair_booking'   => 'درخواست تعمیرات',
			'lead_follow_up'   => 'پیگیری سرنخ',
		) );
	}

	public static function get_status_label( $status ) {
		return self::from_map( $status, array(
			'new'         => 'جدید',
			'in_progress' => 'در حال پیگیری',
			'no_answer'   => 'پاسخ داده نشد',
			'follow_up'   => 'نیازمند پیگیری',
			'won'         => 'موفق',
			'lost'        => 'ناموفق',
			'invalid'     => 'نامعتبر',
			'closed'      => 'بسته‌شده',
			'open'        => 'باز',
		) );
	}

	public static function get_customer_status_label( $status ) {
		return self::from_map( $status, array(
			'lead'      => 'سرنخ',
			'active'    => 'فعال',
			'inactive'  => 'غیرفعال',
			'converted' => 'تبدیل‌شده',
			'blocked'   => 'مسدود',
		) );
	}

	public static function get_source_label( $source ) {
		return self::from_map( $source, array(
			'direct'    => 'مستقیم',
			'instagram' => 'اینستاگرام',
			'whatsapp'  => 'واتساپ',
			'google'    => 'گوگل',
			'bing'      => 'بینگ',
			'telegram'  => 'تلگرام',
			'utm'       => 'کمپین UTM',
			'referral'  => 'ارجاعی',
			'other'     => 'سایر',
		) );
	}

	public static function get_medium_label( $medium ) {
		return self::from_map( $medium, array(
			'social'   => 'شبکه اجتماعی',
			'referral' => 'ارجاعی',
			'organic'  => 'ارگانیک',
			'utm'      => 'کمپین',
			'none'     => 'بدون مدیوم',
		) );
	}

	public static function get_activity_label( $activity_type ) {
		return self::from_map( $activity_type, array(
			'request_created'          => 'ثبت درخواست',
			'manual_request_created'   => 'ثبت دستی درخواست',
			'lead_follow_up_created'   => 'ایجاد پیگیری سرنخ',
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
			'settings_viewed'          => 'مشاهده تنظیمات',
			'settings_updated'         => 'به‌روزرسانی تنظیمات',
			'roles_rebuilt'            => 'بازسازی نقش‌ها',
		) );
	}

	public static function get_priority_label( $priority ) {
		return self::from_map( $priority, array(
			'low'    => 'کم',
			'normal' => 'معمولی',
			'medium' => 'متوسط',
			'high'   => 'زیاد',
			'urgent' => 'فوری',
		) );
	}


	public static function get_severity_label( $severity ) {
		return self::from_map( $severity, array(
			'low'      => 'کم',
			'medium'   => 'متوسط',
			'high'     => 'زیاد',
			'critical' => 'بحرانی',
			'urgent'   => 'فوری',
		) );
	}

	public static function get_staff_status_label( $status, $context = '' ) {
		return self::from_map( $status, array(
			'new'        => 'جدید',
			'pending'    => 'در انتظار بررسی',
			'submitted'  => 'ثبت‌شده',
			'seen'       => 'دیده‌شده',
			'responded'  => 'پاسخ داده‌شده',
			'in_review'  => 'در حال بررسی',
			'in_progress'=> 'در حال انجام',
			'done'       => 'انجام‌شده',
			'closed'     => 'بسته‌شده',
			'rejected'   => 'ردشده',
		) );
	}

	public static function get_close_reason_label( $reason ) {
		return self::from_map( $reason, array(
			'purchased'       => 'خرید انجام شد',
			'price'           => 'عدم توافق قیمت',
			'no_budget'       => 'بودجه کافی ندارد',
			'competitor'      => 'انتخاب رقیب',
			'not_interested'  => 'عدم تمایل مشتری',
			'duplicate'       => 'درخواست تکراری',
			'other'           => 'سایر',
		) );
	}

	public static function get_invalid_reason_label( $reason ) {
		return self::from_map( $reason, array(
			'wrong_number' => 'شماره اشتباه',
			'test'         => 'درخواست آزمایشی',
			'spam'         => 'هرزنامه',
			'irrelevant'   => 'نامرتبط',
			'duplicate'    => 'تکراری',
			'other'        => 'سایر',
		) );
	}
}

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
	public static function now() {
		return new DateTimeImmutable( 'now', wp_timezone() );
	}

	public static function current_timestamp() {
		return self::now()->getTimestamp();
	}

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
		return self::now()->format( 'Y-m-d H:i:s' );
	}

	public static function current_date() {
		return self::now()->format( 'Y-m-d' );
	}

	public static function datetime_to_timestamp( $value ) {
		if ( empty( $value ) || '0000-00-00' === $value || '0000-00-00 00:00:00' === $value ) {
			return false;
		}

		$date = self::parse_local_datetime( (string) $value, wp_timezone() );
		return $date ? $date->getTimestamp() : false;
	}

	public static function is_valid_datetime( $value ) {
		return false !== self::datetime_to_timestamp( $value );
	}

	public static function format_datetime_for_db( DateTimeInterface $datetime ) {
		return $datetime->setTimezone( wp_timezone() )->format( 'Y-m-d H:i:s' );
	}

	public static function add_seconds_to_now( $seconds ) {
		return self::format_datetime_for_db( self::now()->modify( sprintf( '%+d seconds', (int) $seconds ) ) );
	}

	public static function subtract_seconds_from_now( $seconds ) {
		return self::add_seconds_to_now( 0 - absint( $seconds ) );
	}

	public static function add_seconds_to_datetime( $value, $seconds ) {
		$timestamp = self::datetime_to_timestamp( $value );
		if ( false === $timestamp ) {
			return '';
		}

		return self::format_datetime_for_db(
			( new DateTimeImmutable( '@' . $timestamp ) )
				->setTimezone( wp_timezone() )
				->modify( sprintf( '%+d seconds', (int) $seconds ) )
		);
	}

	public static function start_of_today() {
		return self::format_datetime_for_db( self::now()->setTime( 0, 0, 0 ) );
	}

	public static function end_of_today() {
		return self::format_datetime_for_db( self::now()->setTime( 23, 59, 59 ) );
	}

	public static function get_today_range() {
		return array(
			'start' => self::start_of_today(),
			'end'   => self::end_of_today(),
		);
	}

	public static function build_date_range( $preset, $from = '', $to = '' ) {
		$timezone = wp_timezone();
		$now      = self::now();
		$start    = null;
		$end      = null;

		switch ( sanitize_key( $preset ) ) {
			case 'yesterday':
				$start = $now->modify( 'yesterday' )->setTime( 0, 0, 0 );
				$end   = $now->modify( 'yesterday' )->setTime( 23, 59, 59 );
				break;
			case 'last_7_days':
				$start = $now->modify( '-6 days' )->setTime( 0, 0, 0 );
				$end   = $now->setTime( 23, 59, 59 );
				break;
			case 'last_30_days':
				$start = $now->modify( '-29 days' )->setTime( 0, 0, 0 );
				$end   = $now->setTime( 23, 59, 59 );
				break;
			case 'current_month':
				$range = self::get_jalali_month_range();
				$start = self::parse_local_datetime( $range['start'], $timezone );
				$end   = self::parse_local_datetime( $range['end'], $timezone );
				break;
			case 'last_month':
				$range = self::get_jalali_month_range( -1 );
				$start = self::parse_local_datetime( $range['start'], $timezone );
				$end   = self::parse_local_datetime( $range['end'], $timezone );
				break;
			case 'current_year':
				$jalali      = self::gregorian_to_jalali( (int) $now->format( 'Y' ), (int) $now->format( 'n' ), (int) $now->format( 'j' ) );
				$year        = (int) $jalali[0];
				$start_parts = self::jalali_to_gregorian( $year, 1, 1 );
				$end_parts   = self::jalali_to_gregorian( $year + 1, 1, 1 );
				$start       = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', sprintf( '%04d-%02d-%02d 00:00:00', $start_parts[0], $start_parts[1], $start_parts[2] ), $timezone );
				$end         = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', sprintf( '%04d-%02d-%02d 00:00:00', $end_parts[0], $end_parts[1], $end_parts[2] ), $timezone )->modify( '-1 second' );
				break;
			case 'custom':
				if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $from ) ) {
					$start = self::parse_local_datetime( $from . ' 00:00:00', $timezone );
				}
				if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $to ) ) {
					$end = self::parse_local_datetime( $to . ' 23:59:59', $timezone );
				}
				break;
			case 'today':
			default:
				$start = $now->setTime( 0, 0, 0 );
				$end   = $now->setTime( 23, 59, 59 );
				break;
		}

		return array(
			'start' => $start ? self::format_datetime_for_db( $start ) : '',
			'end'   => $end ? self::format_datetime_for_db( $end ) : '',
		);
	}


	public static function format_jalali_date( $value, $include_time = false ) {
		if ( empty( $value ) || '0000-00-00' === $value || '0000-00-00 00:00:00' === $value ) {
			return '';
		}

		$value    = self::convert_persian_digits( (string) $value );
		$timezone = wp_timezone();
		$date     = self::parse_local_datetime( $value, $timezone );
		if ( ! $date ) {
			return sanitize_text_field( (string) $value );
		}
		$jalali   = self::gregorian_to_jalali( (int) $date->format( 'Y' ), (int) $date->format( 'n' ), (int) $date->format( 'j' ) );
		$output   = sprintf( '%04d/%02d/%02d', $jalali[0], $jalali[1], $jalali[2] );

		if ( $include_time ) {
			$output .= ' ' . $date->format( 'H:i' );
		}

		return self::to_persian_digits( $output );
	}

	public static function format_jalali_datetime( $value ) {
		return self::format_jalali_date( $value, true );
	}


	/**
	 * Parse database DATETIME values as site-local time.
	 *
	 * CRPCRM stores DATETIME values in site-local WordPress time.
	 * Parsing those values with strtotime() would use PHP's server timezone and
	 * can shift comparisons or report ranges unexpectedly.
	 *
	 * @param string       $value    Date or datetime value.
	 * @param DateTimeZone $timezone WordPress site timezone.
	 * @return DateTimeImmutable|false
	 */
	private static function parse_local_datetime( $value, DateTimeZone $timezone ) {
		$formats = array( 'Y-m-d H:i:s', 'Y-m-d' );
		foreach ( $formats as $format ) {
			$date   = DateTimeImmutable::createFromFormat( '!' . $format, $value, $timezone );
			$errors = DateTimeImmutable::getLastErrors();
			if ( $date && ( false === $errors || ( 0 === $errors['warning_count'] && 0 === $errors['error_count'] ) ) ) {
				return $date;
			}
		}

		try {
			return ( new DateTimeImmutable( $value, $timezone ) )->setTimezone( $timezone );
		} catch ( Exception $exception ) {
			return false;
		}
	}


	public static function get_jalali_month_range( $month_offset = 0 ) {
		$timezone = wp_timezone();
		$now      = new DateTimeImmutable( 'now', $timezone );
		$jalali   = self::gregorian_to_jalali( (int) $now->format( 'Y' ), (int) $now->format( 'n' ), (int) $now->format( 'j' ) );
		$year     = (int) $jalali[0];
		$month    = (int) $jalali[1] + (int) $month_offset;
		while ( $month < 1 ) {
			$month += 12;
			$year--;
		}
		while ( $month > 12 ) {
			$month -= 12;
			$year++;
		}

		$next_year  = $year;
		$next_month = $month + 1;
		if ( $next_month > 12 ) {
			$next_month = 1;
			$next_year++;
		}

		$start_parts = self::jalali_to_gregorian( $year, $month, 1 );
		$next_parts  = self::jalali_to_gregorian( $next_year, $next_month, 1 );
		$start       = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', sprintf( '%04d-%02d-%02d 00:00:00', $start_parts[0], $start_parts[1], $start_parts[2] ), $timezone );
		$end         = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', sprintf( '%04d-%02d-%02d 00:00:00', $next_parts[0], $next_parts[1], $next_parts[2] ), $timezone )->modify( '-1 second' );

		return array(
			'start' => $start->format( 'Y-m-d H:i:s' ),
			'end'   => $end->format( 'Y-m-d H:i:s' ),
		);
	}

	public static function normalize_date_input( $value ) {
		$value = trim( self::convert_persian_digits( (string) $value ) );
		if ( '' === $value ) {
			return '';
		}

		$value = str_replace( '/', '-', $value );
		if ( preg_match( '/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value, $matches ) ) {
			$year  = (int) $matches[1];
			$month = (int) $matches[2];
			$day   = (int) $matches[3];
			if ( $year < 1700 ) {
				if ( $month < 1 || $month > 12 || $day < 1 || $day > self::jalali_month_days( $year, $month ) ) {
					return '';
				}
				$gregorian = self::jalali_to_gregorian( $year, $month, $day );
				return sprintf( '%04d-%02d-%02d', $gregorian[0], $gregorian[1], $gregorian[2] );
			}
			if ( checkdate( $month, $day, $year ) ) {
				return sprintf( '%04d-%02d-%02d', $year, $month, $day );
			}
		}

		$date = self::parse_local_datetime( $value, wp_timezone() );
		return $date ? $date->format( 'Y-m-d' ) : '';
	}

	public static function normalize_datetime_input( $value ) {
		$value = trim( self::convert_persian_digits( (string) $value ) );
		if ( '' === $value ) {
			return '';
		}

		$value = str_replace( 'T', ' ', $value );
		if ( preg_match( '/^(\d{4}[\/-]\d{1,2}[\/-]\d{1,2})(?:\s+(\d{1,2}:\d{2})(?::\d{2})?)?$/', $value, $matches ) ) {
			$date = self::normalize_date_input( $matches[1] );
			$time = isset( $matches[2] ) && '' !== $matches[2] ? $matches[2] : '00:00';
			if ( $date ) {
				return $date . ' ' . $time . ':00';
			}
		}

		$date = self::parse_local_datetime( $value, wp_timezone() );
		return $date ? $date->format( 'Y-m-d H:i:s' ) : '';
	}

	public static function jalali_date_input( $name, $value = '', $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'required'    => false,
				'class'       => '',
				'placeholder' => 'مثلاً ۱۴۰۳/۰۱/۱۵',
				'id'          => '',
			)
		);
		$id       = $args['id'] ? $args['id'] : 'crpcrm-' . sanitize_key( $name ) . '-' . wp_generate_uuid4();
		$iso      = self::normalize_date_input( $value );
		$display  = $iso ? self::format_jalali_date( $iso ) : '';
		$required = $args['required'] ? ' required' : '';
		$class    = trim( 'crpcrm-jalali-date ' . $args['class'] );

		return sprintf(
			'<span class="crpcrm-jalali-field"><input type="hidden" name="%1$s" id="%2$s" value="%3$s"><input type="text" class="%4$s" data-crpcrm-date-target="%2$s" value="%5$s" placeholder="%6$s" autocomplete="off"%7$s></span>',
			esc_attr( $name ),
			esc_attr( $id ),
			esc_attr( $iso ),
			esc_attr( $class ),
			esc_attr( $display ),
			esc_attr( $args['placeholder'] ),
			$required
		);
	}

	public static function jalali_datetime_input( $name, $value = '', $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'required' => false,
				'class'    => '',
				'id'       => '',
			)
		);
		$id       = $args['id'] ? $args['id'] : 'crpcrm-' . sanitize_key( $name ) . '-' . wp_generate_uuid4();
		$datetime = self::normalize_datetime_input( $value );
		$date     = $datetime ? substr( $datetime, 0, 10 ) : '';
		$time     = $datetime ? substr( $datetime, 11, 5 ) : '';
		$required = $args['required'] ? ' required' : '';
		$class    = trim( 'crpcrm-jalali-date crpcrm-jalali-datetime-date ' . $args['class'] );

		return sprintf(
			'<span class="crpcrm-jalali-field crpcrm-jalali-datetime-field"><input type="hidden" name="%1$s" id="%2$s" value="%3$s"><input type="text" class="%4$s" data-crpcrm-date-target="%2$s" data-crpcrm-datetime-time="%2$s-time" value="%5$s" placeholder="مثلاً ۱۴۰۳/۰۱/۱۵" autocomplete="off"%7$s><input type="time" id="%2$s-time" class="crpcrm-jalali-time" value="%6$s" data-crpcrm-datetime-target="%2$s"%7$s></span>',
			esc_attr( $name ),
			esc_attr( $id ),
			esc_attr( $datetime ? str_replace( ' ', 'T', substr( $datetime, 0, 16 ) ) : '' ),
			esc_attr( $class ),
			esc_attr( $date ? self::format_jalali_date( $date ) : '' ),
			esc_attr( $time ),
			$required
		);
	}

	public static function to_persian_digits( $value ) {
		$latin   = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );
		$persian = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );
		return str_replace( $latin, $persian, (string) $value );
	}

	public static function get_jalali_parts( $value ) {
		if ( empty( $value ) ) {
			return array();
		}

		$date = self::parse_local_datetime( self::convert_persian_digits( (string) $value ), wp_timezone() );
		if ( ! $date ) {
			return array();
		}

		return self::gregorian_to_jalali( (int) $date->format( 'Y' ), (int) $date->format( 'n' ), (int) $date->format( 'j' ) );
	}

	public static function get_jalali_month_name( $month ) {
		$months = array(
			1  => 'فروردین',
			2  => 'اردیبهشت',
			3  => 'خرداد',
			4  => 'تیر',
			5  => 'مرداد',
			6  => 'شهریور',
			7  => 'مهر',
			8  => 'آبان',
			9  => 'آذر',
			10 => 'دی',
			11 => 'بهمن',
			12 => 'اسفند',
		);

		$month = absint( $month );
		return $months[ $month ] ?? '';
	}


	private static function jalali_month_days( $year, $month ) {
		if ( $month <= 6 ) {
			return 31;
		}
		if ( $month <= 11 ) {
			return 30;
		}
		$current = self::jalali_to_gregorian( $year, 1, 1 );
		$next    = self::jalali_to_gregorian( $year + 1, 1, 1 );
		$timezone = wp_timezone();
		$start    = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', sprintf( '%04d-%02d-%02d 00:00:00', $current[0], $current[1], $current[2] ), $timezone );
		$end      = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', sprintf( '%04d-%02d-%02d 00:00:00', $next[0], $next[1], $next[2] ), $timezone );
		if ( ! $start || ! $end ) {
			return 29;
		}
		return ( ( $end->getTimestamp() - $start->getTimestamp() ) / DAY_IN_SECONDS ) === 366 ? 30 : 29;
	}

	public static function gregorian_to_jalali( $gy, $gm, $gd ) {
		$g_d_m = array( 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 );
		$gy2   = ( $gm > 2 ) ? ( $gy + 1 ) : $gy;
		$days  = 355666 + ( 365 * $gy ) + (int) floor( ( $gy2 + 3 ) / 4 ) - (int) floor( ( $gy2 + 99 ) / 100 ) + (int) floor( ( $gy2 + 399 ) / 400 ) + $gd + $g_d_m[ $gm - 1 ];
		$jy    = -1595 + ( 33 * (int) floor( $days / 12053 ) );
		$days %= 12053;
		$jy   += 4 * (int) floor( $days / 1461 );
		$days %= 1461;
		if ( $days > 365 ) {
			$jy   += (int) floor( ( $days - 1 ) / 365 );
			$days  = ( $days - 1 ) % 365;
		}
		$jm = ( $days < 186 ) ? 1 + (int) floor( $days / 31 ) : 7 + (int) floor( ( $days - 186 ) / 30 );
		$jd = 1 + ( ( $days < 186 ) ? ( $days % 31 ) : ( ( $days - 186 ) % 30 ) );
		return array( $jy, $jm, $jd );
	}

	public static function jalali_to_gregorian( $jy, $jm, $jd ) {
		$jy   += 1595;
		$days  = -355668 + ( 365 * $jy ) + ( (int) floor( $jy / 33 ) * 8 ) + (int) floor( ( ( $jy % 33 ) + 3 ) / 4 ) + $jd + ( ( $jm < 7 ) ? ( ( $jm - 1 ) * 31 ) : ( ( ( $jm - 7 ) * 30 ) + 186 ) );
		$gy    = 400 * (int) floor( $days / 146097 );
		$days %= 146097;
		if ( $days > 36524 ) {
			$gy   += 100 * (int) floor( --$days / 36524 );
			$days %= 36524;
			if ( $days >= 365 ) {
				$days++;
			}
		}
		$gy   += 4 * (int) floor( $days / 1461 );
		$days %= 1461;
		if ( $days > 365 ) {
			$gy   += (int) floor( ( $days - 1 ) / 365 );
			$days  = ( $days - 1 ) % 365;
		}
		$gd    = $days + 1;
		$sal_a = array( 0, 31, ( ( 0 === $gy % 4 && 0 !== $gy % 100 ) || ( 0 === $gy % 400 ) ) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 );
		for ( $gm = 1; $gm <= 12 && $gd > $sal_a[ $gm ]; $gm++ ) {
			$gd -= $sal_a[ $gm ];
		}
		return array( $gy, $gm, $gd );
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
		return CRPCRM_Labels::get_status_label( $status );
	}

	public static function get_request_type_label( $request_type ) {
		return CRPCRM_Labels::get_request_type_label( $request_type );
	}

	public static function get_source_label( $source ) {
		return CRPCRM_Labels::get_source_label( $source );
	}

	public static function get_medium_label( $medium ) {
		return CRPCRM_Labels::get_medium_label( $medium );
	}

	public static function get_activity_type_label( $activity_type ) {
		return CRPCRM_Labels::get_activity_label( $activity_type );
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

if ( ! function_exists( 'crpcrm_admin_requests_url' ) ) {
	function crpcrm_admin_requests_url( $args = array() ) {
		return add_query_arg( wp_parse_args( $args, array( 'page' => 'crpcrm-requests' ) ), admin_url( 'admin.php' ) );
	}
}

<?php
/**
 * CSV export helper.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_CSV_Exporter {
	public function output_csv( $filename, $headers, $rows ) {
		if ( headers_sent() ) {
			wp_die( esc_html__( 'امکان ارسال فایل CSV وجود ندارد؛ خروجی قبلاً ارسال شده است.', 'customer-request-portal-crm' ) );
		}

		$filename = sanitize_file_name( $filename );
		if ( '' === $filename ) {
			$filename = 'crpcrm-export-' . current_time( 'Y-m-d' ) . '.csv';
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$this->add_utf8_bom();
		$handle = fopen( 'php://output', 'w' );
		fputcsv( $handle, array_map( array( $this, 'sanitize_csv_cell' ), $headers ) );
		foreach ( $rows as $row ) {
			fputcsv( $handle, array_map( array( $this, 'sanitize_csv_cell' ), $row ) );
		}
		fclose( $handle );
		exit;
	}

	public function add_utf8_bom() {
		echo "\xEF\xBB\xBF";
	}

	public function sanitize_csv_cell( $value ) {
		if ( is_bool( $value ) ) {
			$value = $this->format_boolean( $value );
		} elseif ( is_array( $value ) || is_object( $value ) ) {
			$value = wp_json_encode( $value );
		}

		$value = wp_strip_all_tags( (string) $value );
		$value = str_replace( array( "\r\n", "\r" ), "\n", $value );
		$value = sanitize_textarea_field( $value );
		if ( preg_match( '/^[=+\-@]/', $value ) ) {
			$value = "'" . $value;
		}
		return $value;
	}

	public function format_datetime( $value ) {
		if ( empty( $value ) || '0000-00-00 00:00:00' === $value || '0000-00-00' === $value ) {
			return '';
		}
		$timestamp = strtotime( $value );
		return $timestamp ? wp_date( 'Y-m-d H:i', $timestamp ) : sanitize_text_field( $value );
	}

	public function format_boolean( $value ) {
		return (bool) $value ? 'بله' : 'خیر';
	}

	public function flatten_snapshot( $json ) {
		$data = CRPCRM_Helpers::maybe_json_decode( $json, true );
		if ( ! is_array( $data ) ) {
			return '';
		}

		$actions       = $this->snapshot_value( $data, array( 'actions', 'action_count', 'total_actions' ) );
		$successful    = $this->snapshot_value( $data, array( 'successful_calls', 'call_answered', 'answered_calls' ) );
		$unsuccessful  = $this->snapshot_value( $data, array( 'unsuccessful_calls', 'call_no_answer', 'no_answer_calls' ) );
		$overdue       = $this->snapshot_value( $data, array( 'overdue_followups', 'overdue_follow_ups' ) );
		$won           = $this->snapshot_value( $data, array( 'won', 'won_requests' ) );
		$lost          = $this->snapshot_value( $data, array( 'lost', 'lost_requests' ) );

		return sprintf( 'اقدام: %s، تماس موفق: %s، تماس ناموفق: %s، پیگیری عقب‌افتاده: %s، موفق: %s، ناموفق: %s', $actions, $successful, $unsuccessful, $overdue, $won, $lost );
	}

	private function snapshot_value( $data, $keys ) {
		foreach ( $keys as $key ) {
			if ( isset( $data[ $key ] ) ) {
				return (string) absint( $data[ $key ] );
			}
		}
		return '0';
	}
}

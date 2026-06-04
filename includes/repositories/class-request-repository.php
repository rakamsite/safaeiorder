<?php
/**
 * Request repository.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_Request_Repository {
	private $table;

	public function __construct() {
		$this->table = CRPCRM_DB::table( 'requests' );
	}

	public function create( $data ) {
		global $wpdb;

		$now  = CRPCRM_Helpers::current_datetime();
		$data = $this->sanitize_data( $data );
		$data = wp_parse_args(
			$data,
			array(
				'request_code' => 'TEMP-' . wp_generate_uuid4(),
				'status'       => 'new',
				'created_at'   => $now,
				'updated_at'   => $now,
			)
		);

		$result = $wpdb->insert( $this->table, $data );
		if ( false === $result ) {
			return false;
		}

		$id           = (int) $wpdb->insert_id;
		$request_code = $this->generate_request_code( $id );
		$this->update( $id, array( 'request_code' => $request_code ) );

		return $id;
	}

	public function update( $id, $data ) {
		global $wpdb;

		$data               = $this->sanitize_data( $data );
		$data['updated_at'] = CRPCRM_Helpers::current_datetime();

		return false !== $wpdb->update( $this->table, $data, array( 'id' => absint( $id ) ) );
	}

	public function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d LIMIT 1", absint( $id ) ), ARRAY_A );
	}

	public function get_by_code( $request_code ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE request_code = %s LIMIT 1", sanitize_text_field( $request_code ) ), ARRAY_A );
	}

	public function generate_request_code( $id ) {
		return 'REQ-' . str_pad( absint( $id ), 6, '0', STR_PAD_LEFT );
	}

	private function sanitize_data( $data ) {
		$clean = array();
		$int_fields = array( 'customer_id', 'user_id', 'owner_id' );
		$text_fields = array( 'request_code', 'request_type', 'status', 'request_title', 'request_source', 'request_medium', 'request_campaign', 'request_content', 'request_term', 'last_action', 'close_reason', 'invalid_reason' );
		$textarea_fields = array( 'request_summary', 'request_landing_page', 'request_referrer' );
		$date_fields = array( 'first_assigned_at', 'last_activity_at', 'next_follow_up_at', 'closed_at', 'created_at', 'updated_at' );

		foreach ( $int_fields as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$clean[ $field ] = null === $data[ $field ] || '' === $data[ $field ] ? null : absint( $data[ $field ] );
			}
		}
		foreach ( $text_fields as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$clean[ $field ] = sanitize_text_field( $data[ $field ] );
			}
		}
		foreach ( $textarea_fields as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$clean[ $field ] = sanitize_textarea_field( $data[ $field ] );
			}
		}
		foreach ( $date_fields as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$clean[ $field ] = sanitize_text_field( $data[ $field ] );
			}
		}
		if ( array_key_exists( 'request_data', $data ) ) {
			$clean['request_data'] = is_string( $data['request_data'] ) ? wp_kses_post( $data['request_data'] ) : CRPCRM_Helpers::maybe_json_encode( CRPCRM_Helpers::sanitize_array( $data['request_data'] ) );
		}

		return $clean;
	}
}

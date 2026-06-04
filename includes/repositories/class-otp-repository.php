<?php
/**
 * OTP repository.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_OTP_Repository {
	private $table;

	public function __construct() {
		$this->table = CRPCRM_DB::table( 'otp_logs' );
	}

	public function create( $data ) {
		global $wpdb;

		$defaults = array(
			'phone_normalized'         => '',
			'otp_hash'                 => null,
			'verification_token_hash'  => null,
			'status'                   => 'created',
			'provider'                 => null,
			'provider_response'        => null,
			'ip_hash'                  => null,
			'user_agent_hash'          => null,
			'attempts'                 => 0,
			'expires_at'               => null,
			'verified_at'              => null,
			'created_at'               => CRPCRM_Helpers::current_datetime(),
		);

		$data = wp_parse_args( $this->sanitize_data( $data ), $defaults );

		$result = $wpdb->insert( $this->table, $data );
		return false === $result ? false : (int) $wpdb->insert_id;
	}

	public function find( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d LIMIT 1", absint( $id ) ), ARRAY_A );
	}

	public function find_latest_active_by_phone( $phone_normalized ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE phone_normalized = %s AND status IN ('created','sent') ORDER BY id DESC LIMIT 1",
				sanitize_text_field( $phone_normalized )
			),
			ARRAY_A
		);
	}

	public function count_recent_by_phone( $phone_normalized, $since ) {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table} WHERE phone_normalized = %s AND created_at >= %s",
				sanitize_text_field( $phone_normalized ),
				sanitize_text_field( $since )
			)
		);
	}

	public function count_recent_by_ip_hash( $ip_hash, $since ) {
		global $wpdb;
		if ( empty( $ip_hash ) ) {
			return 0;
		}

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table} WHERE ip_hash = %s AND created_at >= %s",
				sanitize_text_field( $ip_hash ),
				sanitize_text_field( $since )
			)
		);
	}

	public function update( $id, $data ) {
		global $wpdb;
		$data = $this->sanitize_data( $data );
		return false !== $wpdb->update( $this->table, $data, array( 'id' => absint( $id ) ) );
	}

	public function increment_attempts( $id ) {
		global $wpdb;
		return false !== $wpdb->query( $wpdb->prepare( "UPDATE {$this->table} SET attempts = attempts + 1 WHERE id = %d", absint( $id ) ) );
	}

	private function sanitize_data( $data ) {
		$clean       = array();
		$text_fields = array( 'phone_normalized', 'otp_hash', 'verification_token_hash', 'status', 'provider', 'ip_hash', 'user_agent_hash', 'expires_at', 'verified_at', 'created_at' );

		foreach ( $text_fields as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$clean[ $field ] = null === $data[ $field ] ? null : sanitize_text_field( $data[ $field ] );
			}
		}

		if ( array_key_exists( 'provider_response', $data ) ) {
			$clean['provider_response'] = CRPCRM_Helpers::maybe_json_encode( CRPCRM_Helpers::sanitize_array( $data['provider_response'] ) );
		}

		if ( array_key_exists( 'attempts', $data ) ) {
			$clean['attempts'] = absint( $data['attempts'] );
		}

		return $clean;
	}
}

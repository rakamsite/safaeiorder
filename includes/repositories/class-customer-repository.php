<?php
/**
 * Customer repository.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_Customer_Repository {
	private $table;

	public function __construct() {
		$this->table = CRPCRM_DB::table( 'customers' );
	}

	public function find_by_user_id( $user_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE user_id = %d LIMIT 1", absint( $user_id ) ), ARRAY_A );
	}

	public function find_by_phone_normalized( $phone_normalized ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE phone_normalized = %s LIMIT 1", sanitize_text_field( $phone_normalized ) ), ARRAY_A );
	}

	public function ensure_for_user( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return null;
		}

		$existing = $this->find_by_user_id( $user_id );
		if ( $existing ) {
			return $existing;
		}

		$phone_normalized = get_user_meta( $user_id, 'crpcrm_phone_normalized', true );
		$phone_normalized = CRPCRM_Helpers::normalize_iran_phone( $phone_normalized );
		if ( ! CRPCRM_Helpers::is_valid_iran_phone_normalized( $phone_normalized ) ) {
			return null;
		}

		$by_phone = $this->find_by_phone_normalized( $phone_normalized );
		if ( $by_phone ) {
			if ( absint( $by_phone['user_id'] ) === $user_id ) {
				return $by_phone;
			}

			return new WP_Error( 'customer_phone_conflict', 'اطلاعات حساب شما کامل نیست. لطفاً دوباره وارد شوید.' );
		}

		$customer_id = $this->create(
			array(
				'user_id'           => $user_id,
				'phone'             => $phone_normalized,
				'phone_normalized'  => $phone_normalized,
				'profile_completed' => 0,
			)
		);

		return $customer_id ? $this->get( $customer_id ) : null;
	}

	public function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d LIMIT 1", absint( $id ) ), ARRAY_A );
	}

	public function create( $data ) {
		global $wpdb;

		$now  = CRPCRM_Helpers::current_datetime();
		$data = $this->sanitize_data( $data );
		$data = wp_parse_args(
			$data,
			array(
				'profile_completed' => 0,
				'created_at'        => $now,
				'updated_at'        => $now,
			)
		);

		$result = $wpdb->insert( $this->table, $data );
		return false === $result ? false : (int) $wpdb->insert_id;
	}

	public function update( $id, $data ) {
		global $wpdb;

		$data               = $this->sanitize_data( $data );
		$data['updated_at'] = CRPCRM_Helpers::current_datetime();

		return false !== $wpdb->update( $this->table, $data, array( 'id' => absint( $id ) ) );
	}

	private function sanitize_data( $data ) {
		$clean = array();
		$text_fields = array( 'full_name', 'phone', 'phone_normalized', 'province', 'city', 'first_source', 'first_medium', 'first_campaign', 'first_content', 'first_term', 'last_source', 'last_medium', 'last_campaign', 'last_content', 'last_term' );
		$textarea_fields = array( 'first_landing_page', 'first_referrer', 'last_landing_page', 'last_referrer' );
		$date_fields = array( 'first_seen_at', 'last_seen_at', 'created_at', 'updated_at' );

		if ( isset( $data['user_id'] ) ) {
			$clean['user_id'] = absint( $data['user_id'] );
		}
		if ( isset( $data['profile_completed'] ) ) {
			$clean['profile_completed'] = absint( $data['profile_completed'] );
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

		return $clean;
	}
}

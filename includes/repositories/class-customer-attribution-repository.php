<?php
/**
 * Customer attribution event repository.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_Customer_Attribution_Repository {
	private $table;

	public function __construct() {
		$this->table = CRPCRM_DB::table( 'customer_attribution_events' );
	}

	public function add_event( $data ) {
		global $wpdb;

		$data = $this->sanitize_data( $data );
		$data = wp_parse_args(
			$data,
			array(
				'detected_at' => CRPCRM_Helpers::current_datetime(),
				'created_at'  => CRPCRM_Helpers::current_datetime(),
			)
		);

		$result = $wpdb->insert( $this->table, $data );
		return false === $result ? false : (int) $wpdb->insert_id;
	}

	public function get_events_by_customer( $customer_id, $limit = 20 ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE customer_id = %d ORDER BY detected_at DESC, id DESC LIMIT %d",
				absint( $customer_id ),
				max( 1, absint( $limit ) )
			),
			ARRAY_A
		);
	}

	public function get_by_customer_id( $customer_id, $limit = 50, $offset = 0 ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE customer_id = %d ORDER BY detected_at DESC, id DESC LIMIT %d OFFSET %d",
				absint( $customer_id ),
				absint( $limit ),
				absint( $offset )
			),
			ARRAY_A
		);
	}

	public function get_recent_events( $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'limit'     => 20,
				'source'    => '',
				'user_id'   => 0,
				'logged_in' => null,
			)
		);

		$where  = array( '1=1' );
		$params = array();

		if ( '' !== $args['source'] ) {
			$where[]  = 'source = %s';
			$params[] = sanitize_text_field( $args['source'] );
		}

		if ( ! empty( $args['user_id'] ) ) {
			$where[]  = 'user_id = %d';
			$params[] = absint( $args['user_id'] );
		}

		if ( null !== $args['logged_in'] ) {
			$where[]  = 'is_logged_in = %d';
			$params[] = ! empty( $args['logged_in'] ) ? 1 : 0;
		}

		$params[] = max( 1, absint( $args['limit'] ) );
		$sql      = "SELECT * FROM {$this->table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY detected_at DESC, id DESC LIMIT %d';

		return $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
	}

	private function sanitize_data( $data ) {
		$clean = array();
		$int_fields = array( 'customer_id', 'user_id', 'is_logged_in' );
		$text_fields = array( 'source', 'medium', 'campaign', 'content', 'term', 'ip_hash', 'user_agent_hash', 'detected_at', 'created_at' );
		$textarea_fields = array( 'landing_page', 'referrer' );

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

		return $clean;
	}
}

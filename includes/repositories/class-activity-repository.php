<?php
/**
 * Activity repository.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_Activity_Repository {
	private $table;

	public function __construct() {
		$this->table = CRPCRM_DB::table( 'request_activities' );
	}

	public function add_activity( $request_id, $activity_type, $args = array() ) {
		global $wpdb;

		$defaults = array(
			'customer_id'   => null,
			'actor_user_id' => get_current_user_id(),
			'actor_type'    => 'system',
			'old_status'    => null,
			'new_status'    => null,
			'note'          => null,
			'is_internal'   => 1,
			'meta'          => null,
		);
		$args = wp_parse_args( $args, $defaults );

		$result = $wpdb->insert(
			$this->table,
			array(
				'request_id'     => absint( $request_id ),
				'customer_id'    => null === $args['customer_id'] ? null : absint( $args['customer_id'] ),
				'actor_user_id'  => null === $args['actor_user_id'] ? null : absint( $args['actor_user_id'] ),
				'actor_type'     => sanitize_key( $args['actor_type'] ),
				'activity_type'  => sanitize_key( $activity_type ),
				'old_status'     => null === $args['old_status'] ? null : sanitize_key( $args['old_status'] ),
				'new_status'     => null === $args['new_status'] ? null : sanitize_key( $args['new_status'] ),
				'note'           => null === $args['note'] ? null : sanitize_textarea_field( $args['note'] ),
				'is_internal'    => absint( $args['is_internal'] ),
				'meta'           => CRPCRM_Helpers::maybe_json_encode( CRPCRM_Helpers::sanitize_array( $args['meta'] ) ),
				'created_at'     => CRPCRM_Helpers::current_datetime(),
			)
		);

		return false === $result ? false : (int) $wpdb->insert_id;
	}

	public function get_by_request_id( $request_id, $limit = 50, $offset = 0 ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE request_id = %d ORDER BY created_at ASC, id ASC LIMIT %d OFFSET %d",
				absint( $request_id ),
				absint( $limit ),
				absint( $offset )
			),
			ARRAY_A
		);
	}
}

<?php
/**
 * Landing link repository.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_Landing_Repository {
	public function table_name() {
		return CRPCRM_DB::table( 'landing_links' );
	}

	public function insert( array $data ) {
		global $wpdb;

		$inserted = $wpdb->insert(
			$this->table_name(),
			array(
				'title'                => isset( $data['title'] ) ? sanitize_text_field( $data['title'] ) : '',
				'slug'                 => isset( $data['slug'] ) ? sanitize_key( $data['slug'] ) : '',
				'destination_post_id'  => ! empty( $data['destination_post_id'] ) ? absint( $data['destination_post_id'] ) : null,
				'destination_url'      => isset( $data['destination_url'] ) ? esc_url_raw( $data['destination_url'] ) : null,
				'source_label'         => isset( $data['source_label'] ) ? sanitize_text_field( $data['source_label'] ) : '',
				'source_code'          => isset( $data['source_code'] ) ? sanitize_text_field( $data['source_code'] ) : '',
				'medium_label'         => isset( $data['medium_label'] ) ? sanitize_text_field( $data['medium_label'] ) : '',
				'medium_code'          => isset( $data['medium_code'] ) ? sanitize_text_field( $data['medium_code'] ) : '',
				'campaign_label'       => isset( $data['campaign_label'] ) ? sanitize_text_field( $data['campaign_label'] ) : '',
				'campaign_code'        => isset( $data['campaign_code'] ) ? sanitize_text_field( $data['campaign_code'] ) : '',
				'content_label'        => isset( $data['content_label'] ) && '' !== trim( (string) $data['content_label'] ) ? sanitize_text_field( $data['content_label'] ) : null,
				'content_code'         => isset( $data['content_code'] ) && '' !== trim( (string) $data['content_code'] ) ? sanitize_text_field( $data['content_code'] ) : null,
				'term_label'           => isset( $data['term_label'] ) && '' !== trim( (string) $data['term_label'] ) ? sanitize_text_field( $data['term_label'] ) : null,
				'term_code'            => isset( $data['term_code'] ) && '' !== trim( (string) $data['term_code'] ) ? sanitize_text_field( $data['term_code'] ) : null,
				'append_standard_utm'  => ! empty( $data['append_standard_utm'] ) ? 1 : 0,
				'status'               => isset( $data['status'] ) ? sanitize_key( $data['status'] ) : 'active',
				'created_by'           => ! empty( $data['created_by'] ) ? absint( $data['created_by'] ) : null,
				'updated_by'           => ! empty( $data['updated_by'] ) ? absint( $data['updated_by'] ) : null,
				'created_at'           => isset( $data['created_at'] ) ? $data['created_at'] : CRPCRM_Helpers::current_datetime(),
				'updated_at'           => isset( $data['updated_at'] ) ? $data['updated_at'] : CRPCRM_Helpers::current_datetime(),
			),
			array( '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return new WP_Error( 'landing_insert_failed', $wpdb->last_error ? $wpdb->last_error : 'insert_failed' );
		}

		return absint( $wpdb->insert_id );
	}

	public function update( $id, array $data ) {
		global $wpdb;

		$updated = $wpdb->update(
			$this->table_name(),
			array(
				'title'               => isset( $data['title'] ) ? sanitize_text_field( $data['title'] ) : '',
				'slug'                => isset( $data['slug'] ) ? sanitize_key( $data['slug'] ) : '',
				'destination_post_id' => ! empty( $data['destination_post_id'] ) ? absint( $data['destination_post_id'] ) : null,
				'destination_url'     => isset( $data['destination_url'] ) ? esc_url_raw( $data['destination_url'] ) : null,
				'source_label'        => isset( $data['source_label'] ) ? sanitize_text_field( $data['source_label'] ) : '',
				'source_code'         => isset( $data['source_code'] ) ? sanitize_text_field( $data['source_code'] ) : '',
				'medium_label'        => isset( $data['medium_label'] ) ? sanitize_text_field( $data['medium_label'] ) : '',
				'medium_code'         => isset( $data['medium_code'] ) ? sanitize_text_field( $data['medium_code'] ) : '',
				'campaign_label'      => isset( $data['campaign_label'] ) ? sanitize_text_field( $data['campaign_label'] ) : '',
				'campaign_code'       => isset( $data['campaign_code'] ) ? sanitize_text_field( $data['campaign_code'] ) : '',
				'content_label'       => isset( $data['content_label'] ) && '' !== trim( (string) $data['content_label'] ) ? sanitize_text_field( $data['content_label'] ) : null,
				'content_code'        => isset( $data['content_code'] ) && '' !== trim( (string) $data['content_code'] ) ? sanitize_text_field( $data['content_code'] ) : null,
				'term_label'          => isset( $data['term_label'] ) && '' !== trim( (string) $data['term_label'] ) ? sanitize_text_field( $data['term_label'] ) : null,
				'term_code'           => isset( $data['term_code'] ) && '' !== trim( (string) $data['term_code'] ) ? sanitize_text_field( $data['term_code'] ) : null,
				'append_standard_utm' => ! empty( $data['append_standard_utm'] ) ? 1 : 0,
				'status'              => isset( $data['status'] ) ? sanitize_key( $data['status'] ) : 'active',
				'updated_by'          => ! empty( $data['updated_by'] ) ? absint( $data['updated_by'] ) : null,
				'updated_at'          => isset( $data['updated_at'] ) ? $data['updated_at'] : CRPCRM_Helpers::current_datetime(),
			),
			array( 'id' => absint( $id ) ),
			array( '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return new WP_Error( 'landing_update_failed', $wpdb->last_error ? $wpdb->last_error : 'update_failed' );
		}

		return true;
	}

	public function get( $id ) {
		global $wpdb;

		$id   = absint( $id );
		$table = $this->table_name();
		$sql  = $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id );
		$row  = $wpdb->get_row( $sql, ARRAY_A );

		return $row ? $row : null;
	}

	public function get_by_slug( $slug, $exclude_id = 0 ) {
		global $wpdb;

		$slug  = sanitize_key( $slug );
		$table = $this->table_name();
		$sql   = "SELECT * FROM {$table} WHERE slug = %s";
		$args  = array( $slug );
		if ( absint( $exclude_id ) > 0 ) {
			$sql  .= ' AND id <> %d';
			$args[] = absint( $exclude_id );
		}
		$sql .= ' LIMIT 1';

		$row = $wpdb->get_row( call_user_func_array( array( $wpdb, 'prepare' ), array_merge( array( $sql ), $args ) ), ARRAY_A );
		return $row ? $row : null;
	}

	public function count( array $args = array() ) {
		global $wpdb;

		$filters = $this->normalize_list_args( $args );
		$table   = $this->table_name();
		$where   = array( '1=1' );
		$params  = array();

		if ( '' !== $filters['status'] ) {
			$where[]  = 'status = %s';
			$params[] = $filters['status'];
		}

		$sql = "SELECT COUNT(*) FROM {$table} WHERE " . implode( ' AND ', $where );
		if ( ! empty( $params ) ) {
			$sql = call_user_func_array( array( $wpdb, 'prepare' ), array_merge( array( $sql ), $params ) );
		}

		return absint( $wpdb->get_var( $sql ) );
	}

	public function get_list( array $args = array() ) {
		global $wpdb;

		$filters = $this->normalize_list_args( $args );
		$table   = $this->table_name();
		$where   = array( '1=1' );
		$params  = array();

		if ( '' !== $filters['status'] ) {
			$where[]  = 'status = %s';
			$params[] = $filters['status'];
		}

		$base_sql = "FROM {$table} WHERE " . implode( ' AND ', $where );
		$count_sql = 'SELECT COUNT(*) ' . $base_sql;
		$list_sql  = 'SELECT * ' . $base_sql . ' ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d';

		$total = ! empty( $params ) ? absint( $wpdb->get_var( call_user_func_array( array( $wpdb, 'prepare' ), array_merge( array( $count_sql ), $params ) ) ) ) : absint( $wpdb->get_var( $count_sql ) );

		$list_params = $params;
		$list_params[] = $filters['limit'];
		$list_params[] = $filters['offset'];
		$items = $wpdb->get_results( call_user_func_array( array( $wpdb, 'prepare' ), array_merge( array( $list_sql ), $list_params ) ), ARRAY_A );

		return array(
			'items' => $items ? $items : array(),
			'total' => $total,
		);
	}

	public function set_status( $id, $status, $updated_by = 0 ) {
		global $wpdb;

		$status = sanitize_key( $status );
		if ( ! in_array( $status, array( 'active', 'inactive', 'archived' ), true ) ) {
			return new WP_Error( 'invalid_status', 'invalid_status' );
		}

		$updated = $wpdb->update(
			$this->table_name(),
			array(
				'status'      => $status,
				'updated_by'  => absint( $updated_by ),
				'updated_at'  => CRPCRM_Helpers::current_datetime(),
			),
			array( 'id' => absint( $id ) ),
			array( '%s', '%d', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return new WP_Error( 'landing_status_failed', $wpdb->last_error ? $wpdb->last_error : 'update_failed' );
		}

		return true;
	}

	public function delete_or_archive( $id, $updated_by = 0 ) {
		return $this->set_status( $id, 'archived', $updated_by );
	}

	public function hard_delete( $id ) {
		global $wpdb;
		$deleted = $wpdb->delete( $this->table_name(), array( 'id' => absint( $id ) ), array( '%d' ) );
		if ( false === $deleted ) {
			return new WP_Error( 'landing_delete_failed', $wpdb->last_error ? $wpdb->last_error : 'delete_failed' );
		}
		return true;
	}

	private function normalize_list_args( array $args ) {
		return wp_parse_args(
			$args,
			array(
				'status' => '',
				'limit'  => 20,
				'offset' => 0,
			)
		);
	}
}

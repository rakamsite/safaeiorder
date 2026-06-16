<?php
/**
 * Landing click repository.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_Landing_Click_Repository {
	public function table_name() {
		return CRPCRM_DB::table( 'landing_clicks' );
	}

	public function insert( array $data ) {
		global $wpdb;

		$inserted = $wpdb->insert(
			$this->table_name(),
			array(
				'landing_id'         => ! empty( $data['landing_id'] ) ? absint( $data['landing_id'] ) : 0,
				'landing_slug'       => isset( $data['landing_slug'] ) ? sanitize_key( $data['landing_slug'] ) : '',
				'visitor_id'         => isset( $data['visitor_id'] ) ? sanitize_text_field( $data['visitor_id'] ) : null,
				'click_hash'         => isset( $data['click_hash'] ) ? sanitize_text_field( $data['click_hash'] ) : null,
				'source_code'        => isset( $data['source_code'] ) ? sanitize_text_field( $data['source_code'] ) : null,
				'medium_code'        => isset( $data['medium_code'] ) ? sanitize_text_field( $data['medium_code'] ) : null,
				'campaign_code'      => isset( $data['campaign_code'] ) ? sanitize_text_field( $data['campaign_code'] ) : null,
				'content_code'       => isset( $data['content_code'] ) ? sanitize_text_field( $data['content_code'] ) : null,
				'term_code'          => isset( $data['term_code'] ) ? sanitize_text_field( $data['term_code'] ) : null,
				'source_label'       => isset( $data['source_label'] ) ? sanitize_text_field( $data['source_label'] ) : null,
				'medium_label'       => isset( $data['medium_label'] ) ? sanitize_text_field( $data['medium_label'] ) : null,
				'campaign_label'     => isset( $data['campaign_label'] ) ? sanitize_text_field( $data['campaign_label'] ) : null,
				'content_label'      => isset( $data['content_label'] ) ? sanitize_text_field( $data['content_label'] ) : null,
				'term_label'         => isset( $data['term_label'] ) ? sanitize_text_field( $data['term_label'] ) : null,
				'current_url'        => isset( $data['current_url'] ) ? esc_url_raw( $data['current_url'] ) : null,
				'referrer'           => isset( $data['referrer'] ) ? esc_url_raw( $data['referrer'] ) : null,
				'user_agent'         => isset( $data['user_agent'] ) ? sanitize_text_field( $data['user_agent'] ) : null,
				'ip_hash'            => isset( $data['ip_hash'] ) ? sanitize_text_field( $data['ip_hash'] ) : null,
				'created_at'         => isset( $data['created_at'] ) ? $data['created_at'] : CRPCRM_Helpers::current_datetime(),
				'converted_request_id' => ! empty( $data['converted_request_id'] ) ? absint( $data['converted_request_id'] ) : null,
				'converted_at'       => isset( $data['converted_at'] ) ? $data['converted_at'] : null,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		if ( false === $inserted ) {
			return new WP_Error( 'landing_click_insert_failed', $wpdb->last_error ? $wpdb->last_error : 'insert_failed' );
		}

		return absint( $wpdb->insert_id );
	}

	public function get( $click_id ) {
		global $wpdb;

		$click_id = absint( $click_id );
		if ( ! $click_id ) {
			return null;
		}

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table_name()} WHERE id = %d LIMIT 1", $click_id ), ARRAY_A );
		return $row ? $row : null;
	}

	public function find_recent_duplicate( $landing_id, $visitor_id, $ip_hash, $user_agent_hash, $minutes = 30 ) {
		global $wpdb;

		$landing_id = absint( $landing_id );
		if ( ! $landing_id ) {
			return null;
		}

		$minutes = max( 1, absint( $minutes ) );
		$now     = CRPCRM_Helpers::current_datetime();
		$cutoff  = $now;
		try {
			$timezone = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );
			$date     = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $now, $timezone );
			if ( $date instanceof DateTimeImmutable ) {
				$cutoff = $date->sub( new DateInterval( 'PT' . $minutes . 'M' ) )->format( 'Y-m-d H:i:s' );
			}
		} catch ( Exception $e ) {
			$cutoff = $now;
		}
		$table  = $this->table_name();
		$sql    = "SELECT * FROM {$table} WHERE landing_id = %d AND created_at >= %s AND ((%s <> '' AND visitor_id = %s) OR (ip_hash = %s AND user_agent = %s)) ORDER BY id DESC LIMIT 1";
		$args   = array( $landing_id, $cutoff, (string) $visitor_id, (string) $visitor_id, (string) $ip_hash, (string) $user_agent_hash );
		$row    = $wpdb->get_row( call_user_func_array( array( $wpdb, 'prepare' ), array_merge( array( $sql ), $args ) ), ARRAY_A );

		return $row ? $row : null;
	}

	public function find_latest_for_visitor( $landing_id, $visitor_id ) {
		global $wpdb;

		$landing_id = absint( $landing_id );
		$visitor_id  = sanitize_text_field( (string) $visitor_id );
		if ( ! $landing_id || '' === $visitor_id ) {
			return null;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name()} WHERE landing_id = %d AND visitor_id = %s ORDER BY created_at DESC, id DESC LIMIT 1",
				$landing_id,
				$visitor_id
			),
			ARRAY_A
		);

		return $row ? $row : null;
	}

	public function get_stats_for_landing( $landing_id ) {
		$map = $this->get_stats_for_landing_ids( array( absint( $landing_id ) ) );
		$landing_id = absint( $landing_id );

		return isset( $map[ $landing_id ] ) ? $map[ $landing_id ] : $this->empty_stats();
	}

	public function get_stats_for_landing_ids( array $landing_ids ) {
		global $wpdb;

		$landing_ids = array_values( array_filter( array_map( 'absint', $landing_ids ) ) );
		if ( empty( $landing_ids ) ) {
			return array();
		}

		$placeholders = implode( ', ', array_fill( 0, count( $landing_ids ), '%d' ) );
		$sql          = $wpdb->prepare(
			"SELECT landing_id,
				COUNT(*) AS valid_clicks,
				SUM(CASE WHEN converted_request_id IS NOT NULL AND converted_request_id > 0 THEN 1 ELSE 0 END) AS conversions,
				MAX(created_at) AS last_click_at,
				MAX(CASE WHEN converted_request_id IS NOT NULL AND converted_request_id > 0 THEN converted_at ELSE NULL END) AS last_conversion_at
			FROM {$this->table_name()}
			WHERE landing_id IN ( {$placeholders} )
			GROUP BY landing_id",
			$landing_ids
		);

		$rows = $wpdb->get_results( $sql, ARRAY_A );
		$map  = array();
		foreach ( $rows as $row ) {
			$landing_id = absint( $row['landing_id'] );
			$clicks     = absint( $row['valid_clicks'] );
			$conversions = absint( $row['conversions'] );
			$map[ $landing_id ] = array(
				'landing_id'          => $landing_id,
				'valid_clicks'        => $clicks,
				'conversions'         => $conversions,
				'conversion_rate'     => $clicks > 0 ? round( ( $conversions / $clicks ) * 100, 1 ) : null,
				'last_click_at'       => ! empty( $row['last_click_at'] ) ? $row['last_click_at'] : '',
				'last_conversion_at'  => ! empty( $row['last_conversion_at'] ) ? $row['last_conversion_at'] : '',
			);
		}

		return $map;
	}

	public function mark_converted( $click_id, $request_id, $converted_at = '' ) {
		global $wpdb;

		$click_id  = absint( $click_id );
		$request_id = absint( $request_id );
		if ( ! $click_id || ! $request_id ) {
			return new WP_Error( 'landing_click_update_failed', 'invalid_click_or_request' );
		}

		$current = $this->get( $click_id );
		if ( empty( $current ) ) {
			return new WP_Error( 'landing_click_update_failed', 'click_not_found' );
		}
		if ( ! empty( $current['converted_request_id'] ) && absint( $current['converted_request_id'] ) !== $request_id ) {
			return true;
		}

		$updated = $wpdb->update(
			$this->table_name(),
			array(
				'converted_request_id' => $request_id,
				'converted_at'         => $converted_at ? $converted_at : CRPCRM_Helpers::current_datetime(),
			),
			array( 'id' => $click_id ),
			array( '%d', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return new WP_Error( 'landing_click_update_failed', $wpdb->last_error ? $wpdb->last_error : 'update_failed' );
		}

		return true;
	}

	private function empty_stats() {
		return array(
			'landing_id'         => 0,
			'valid_clicks'       => 0,
			'conversions'        => 0,
			'conversion_rate'    => null,
			'last_click_at'      => '',
			'last_conversion_at'  => '',
		);
	}
}

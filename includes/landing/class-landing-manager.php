<?php
/**
 * Landing link domain service.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_Landing_Manager {
	private $repository;
	private $click_repository;
	private $request_repository;

	public function __construct( CRPCRM_Landing_Repository $repository = null ) {
		$this->repository         = $repository ? $repository : new CRPCRM_Landing_Repository();
		$this->click_repository   = class_exists( 'CRPCRM_Landing_Click_Repository' ) ? new CRPCRM_Landing_Click_Repository() : null;
		$this->request_repository = class_exists( 'CRPCRM_Request_Repository' ) ? new CRPCRM_Request_Repository() : null;
	}

	public function create( array $data ) {
		return $this->save_landing( 0, $data );
	}

	public function update( $id, array $data ) {
		return $this->save_landing( absint( $id ), $data );
	}

	public function get( $id ) {
		return $this->decorate_landing( $this->repository->get( $id ) );
	}

	public function get_by_slug( $slug ) {
		return $this->decorate_landing( $this->repository->get_by_slug( $slug ) );
	}

	public function list_landings( array $args = array() ) {
		$result = $this->repository->get_list( $args );
		$items  = array();
		foreach ( $result['items'] as $item ) {
			$items[] = $this->decorate_landing( $item );
		}

		return array(
			'items' => $items,
			'total' => absint( $result['total'] ),
		);
	}

	public function set_status( $id, $status, $updated_by = 0 ) {
		return $this->repository->set_status( $id, $status, $updated_by );
	}

	public function delete_or_archive( $id, $updated_by = 0 ) {
		return $this->repository->delete_or_archive( $id, $updated_by );
	}

	public function get_landing_stats( $landing_id ) {
		$landing = $this->get( $landing_id );
		if ( ! is_array( $landing ) || empty( $landing['id'] ) ) {
			return array(
				'landing_id'         => absint( $landing_id ),
				'valid_clicks'       => 0,
				'conversions'        => 0,
				'conversion_rate'    => null,
				'last_click_at'      => '',
				'last_conversion_at'  => '',
			);
		}

		$stats = $this->get_landing_stats_for_items( array( $landing ) );

		return isset( $stats[ absint( $landing_id ) ] ) ? $stats[ absint( $landing_id ) ] : array(
			'landing_id'         => absint( $landing_id ),
			'valid_clicks'       => 0,
			'conversions'        => 0,
			'conversion_rate'    => null,
			'last_click_at'      => '',
			'last_conversion_at' => '',
		);
	}

	public function get_landing_stats_for_ids( array $landing_ids ) {
		$landing_ids = array_values( array_filter( array_map( 'absint', $landing_ids ) ) );
		if ( empty( $landing_ids ) ) {
			return array();
		}

		$landings = array();
		foreach ( $landing_ids as $landing_id ) {
			$landing = $this->get( $landing_id );
			if ( is_array( $landing ) && ! empty( $landing['id'] ) ) {
				$landings[] = $landing;
			}
		}

		return $this->get_landing_stats_for_items( $landings );
	}

	public function get_landing_stats_for_items( array $landings ) {
		$landings = array_values( array_filter( $landings, 'is_array' ) );
		if ( ! $this->click_repository ) {
			return array();
		}

		$landing_ids      = array_values( array_filter( array_map( 'absint', wp_list_pluck( $landings, 'id' ) ) ) );
		$click_stats_map  = $this->click_repository->get_stats_for_landing_ids( $landing_ids );
		$request_stats_map = $this->request_repository ? $this->request_repository->get_landing_request_stats_map( $landings ) : array();
		$stats_map        = array();

		foreach ( $landings as $landing ) {
			$landing_id    = absint( $landing['id'] ?? 0 );
			$click_stats   = isset( $click_stats_map[ $landing_id ] ) ? $click_stats_map[ $landing_id ] : array();
			$request_stats = isset( $request_stats_map[ $landing_id ] ) ? $request_stats_map[ $landing_id ] : array();
			$valid_clicks  = absint( $click_stats['valid_clicks'] ?? 0 );
			$conversions   = absint( $request_stats['request_conversions'] ?? 0 );

			$stats_map[ $landing_id ] = array(
				'landing_id'         => $landing_id,
				'valid_clicks'       => $valid_clicks,
				'conversions'        => $conversions,
				'conversion_rate'    => $valid_clicks > 0 ? round( ( $conversions / $valid_clicks ) * 100, 1 ) : null,
				'last_click_at'      => ! empty( $click_stats['last_click_at'] ) ? $click_stats['last_click_at'] : '',
				'last_conversion_at' => ! empty( $request_stats['last_conversion_at'] ) ? $request_stats['last_conversion_at'] : ( ! empty( $click_stats['last_conversion_at'] ) ? $click_stats['last_conversion_at'] : '' ),
			);
		}

		return $stats_map;
	}

	public function get_overview_stats() {
		$total_requests = $this->request_repository ? $this->request_repository->count_requests_with_landing_attribution() : 0;
		$all_landings   = $this->list_landings(
			array(
				'status' => '',
				'limit'  => 5000,
				'offset' => 0,
			)
		);
		$stats_map      = $this->get_landing_stats_for_items( $all_landings['items'] );
		$total_clicks   = 0;

		foreach ( $stats_map as $stats ) {
			$total_clicks += absint( $stats['valid_clicks'] ?? 0 );
		}

		return array(
			'total'             => $this->repository->count(),
			'active'            => $this->repository->count( array( 'status' => 'active' ) ),
			'inactive'          => $this->repository->count( array( 'status' => 'inactive' ) ),
			'archived'          => $this->repository->count( array( 'status' => 'archived' ) ),
			'valid_clicks'      => $total_clicks,
			'request_count'     => absint( $total_requests ),
			'conversion_rate'   => $total_clicks > 0 ? round( ( $total_requests / $total_clicks ) * 100, 1 ) : null,
		);
	}

	public function record_conversion_for_request( $request_id, array $request_context = array() ) {
		if ( ! $this->click_repository || ! $this->repository ) {
			return false;
		}

		$request_id      = absint( $request_id );
		$request_context = wp_parse_args(
			$request_context,
			array(
				'click_id'      => 0,
				'visitor_id'    => '',
				'landing_id'    => 0,
				'landing_slug'  => '',
				'converted_at'  => CRPCRM_Helpers::current_datetime(),
			)
		);

		$click_row = null;
		$click_id  = ! empty( $request_context['click_id'] ) ? absint( $request_context['click_id'] ) : 0;
		if ( $click_id ) {
			$click_row = $this->click_repository->get( $click_id );
		}

		if ( ! $click_row && ! empty( $request_context['landing_id'] ) && ! empty( $request_context['visitor_id'] ) ) {
			$click_row = $this->click_repository->find_latest_for_visitor( absint( $request_context['landing_id'] ), sanitize_text_field( (string) $request_context['visitor_id'] ) );
		}

		if ( ! $click_row && ! empty( $request_context['landing_slug'] ) && ! empty( $request_context['visitor_id'] ) ) {
			$landing = $this->get_by_slug( $request_context['landing_slug'] );
			if ( $landing && ! empty( $landing['id'] ) ) {
				$click_row = $this->click_repository->find_latest_for_visitor( absint( $landing['id'] ), sanitize_text_field( (string) $request_context['visitor_id'] ) );
			}
		}

		if ( ! $click_row || empty( $click_row['id'] ) ) {
			return false;
		}

		if ( ! empty( $click_row['converted_request_id'] ) && absint( $click_row['converted_request_id'] ) !== $request_id ) {
			return true;
		}

		return $this->click_repository->mark_converted( absint( $click_row['id'] ), $request_id, sanitize_text_field( (string) $request_context['converted_at'] ) );
	}

	public function build_landing_url( $landing ) {
		$landing = $this->normalize_landing( $landing );
		if ( empty( $landing ) ) {
			return home_url( '/' );
		}

		$destination_url = $this->resolve_destination_url( $landing );
		$slug            = isset( $landing['slug'] ) ? sanitize_key( $landing['slug'] ) : '';
		$url             = $destination_url ? $destination_url : home_url( '/' );

		$url = add_query_arg( 'u', $slug, $url );

		if ( ! empty( $landing['append_standard_utm'] ) ) {
			$utm_args = array(
				'utm_source'   => isset( $landing['source_code'] ) ? $landing['source_code'] : '',
				'utm_medium'   => isset( $landing['medium_code'] ) ? $landing['medium_code'] : '',
				'utm_campaign' => isset( $landing['campaign_code'] ) ? $landing['campaign_code'] : '',
			);
			if ( ! empty( $landing['content_code'] ) ) {
				$utm_args['utm_content'] = $landing['content_code'];
			}
			if ( ! empty( $landing['term_code'] ) ) {
				$utm_args['utm_term'] = $landing['term_code'];
			}
			$url = add_query_arg( array_filter( $utm_args ), $url );
		}

		return esc_url_raw( $url );
	}

	public function normalize_slug( $slug ) {
		$slug = strtolower( trim( (string) $slug ) );
		$slug = preg_replace( '/\s+/', '-', $slug );
		return $slug;
	}

	public function validate_slug( $slug, $exclude_id = 0 ) {
		$slug = $this->normalize_slug( $slug );
		if ( '' === $slug ) {
			return new WP_Error( 'invalid_slug', 'اسلاگ نمی‌تواند خالی باشد.' );
		}
		if ( ! preg_match( '/^[a-z0-9_-]+$/', $slug ) ) {
			return new WP_Error( 'invalid_slug', 'اسلاگ فقط می‌تواند شامل حروف لاتین کوچک، عدد، خط تیره و underscore باشد.' );
		}
		if ( $this->repository->get_by_slug( $slug, $exclude_id ) ) {
			return new WP_Error( 'duplicate_slug', 'این اسلاگ قبلاً استفاده شده است.' );
		}

		return $slug;
	}

	public function search_destinations( $term ) {
		$term = sanitize_text_field( (string) $term );
		$term_length = function_exists( 'mb_strlen' ) ? mb_strlen( $term ) : strlen( $term );
		if ( $term_length < 2 ) {
			return array();
		}

		$post_types = array( 'post', 'page' );
		if ( post_type_exists( 'product' ) ) {
			$post_types[] = 'product';
		}

		$query = new WP_Query(
			array(
				'post_type'              => $post_types,
				'post_status'            => 'publish',
				's'                      => $term,
				'posts_per_page'         => 10,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'update_post_meta_cache' => false,
			)
		);

		$results = array();
		foreach ( $query->posts as $post ) {
			$permalink = get_permalink( $post->ID );
			if ( ! $permalink ) {
				continue;
			}
			$results[] = array(
				'id'    => absint( $post->ID ),
				'label' => sanitize_text_field( get_the_title( $post->ID ) ),
				'type'  => sanitize_key( $post->post_type ),
				'url'   => esc_url_raw( $permalink ),
			);
		}

		return $results;
	}

	private function save_landing( $id, array $data ) {
		$id      = absint( $id );
		$slug    = $this->validate_slug( isset( $data['slug'] ) ? $data['slug'] : '', $id );
		if ( is_wp_error( $slug ) ) {
			return $slug;
		}

		if ( $id ) {
			$current = $this->get( $id );
			if ( is_array( $current ) && ! empty( $current['slug'] ) ) {
				$current_stats = $this->get_landing_stats( $id );
				if ( absint( $current_stats['valid_clicks'] ?? 0 ) > 0 && $slug !== sanitize_key( $current['slug'] ) ) {
					return new WP_Error( 'slug_locked', 'این لندینگ کلیک ثبت‌شده دارد و اسلاگ آن دیگر قابل تغییر نیست.' );
				}
			}
		}

		$title = isset( $data['title'] ) ? sanitize_text_field( $data['title'] ) : '';
		if ( '' === $title ) {
			return new WP_Error( 'invalid_title', 'عنوان لندینگ الزامی است.' );
		}

		$destination_post_id = isset( $data['destination_post_id'] ) ? absint( $data['destination_post_id'] ) : 0;
		if ( ! $destination_post_id || ! get_post( $destination_post_id ) ) {
			return new WP_Error( 'invalid_destination', 'صفحه مقصد معتبر نیست.' );
		}

		$destination_url = get_permalink( $destination_post_id );
		$destination_url = $this->normalize_internal_url( $destination_url );
		if ( '' === $destination_url ) {
			return new WP_Error( 'invalid_destination', 'صفحه مقصد معتبر نیست.' );
		}

		$source_code   = $this->validate_technical_code( isset( $data['source_code'] ) ? $data['source_code'] : '' );
		$medium_code   = $this->validate_technical_code( isset( $data['medium_code'] ) ? $data['medium_code'] : '' );
		$campaign_code = $this->validate_technical_code( isset( $data['campaign_code'] ) ? $data['campaign_code'] : '' );
		if ( is_wp_error( $source_code ) || is_wp_error( $medium_code ) || is_wp_error( $campaign_code ) ) {
			return is_wp_error( $source_code ) ? $source_code : ( is_wp_error( $medium_code ) ? $medium_code : $campaign_code );
		}

		$content_code = '';
		if ( isset( $data['content_code'] ) && '' !== trim( (string) $data['content_code'] ) ) {
			$content_code = $this->validate_technical_code( $data['content_code'] );
			if ( is_wp_error( $content_code ) ) {
				return $content_code;
			}
		}

		$term_code = '';
		if ( isset( $data['term_code'] ) && '' !== trim( (string) $data['term_code'] ) ) {
			$term_code = $this->validate_technical_code( $data['term_code'] );
			if ( is_wp_error( $term_code ) ) {
				return $term_code;
			}
		}

		$status = isset( $data['status'] ) ? sanitize_key( $data['status'] ) : 'active';
		if ( ! in_array( $status, array( 'active', 'inactive', 'archived' ), true ) ) {
			$status = 'active';
		}

		$record = array(
			'title'               => $title,
			'slug'                => $slug,
			'destination_post_id' => $destination_post_id,
			'destination_url'     => $destination_url,
			'source_label'        => isset( $data['source_label'] ) ? sanitize_text_field( $data['source_label'] ) : '',
			'source_code'         => $source_code,
			'medium_label'        => isset( $data['medium_label'] ) ? sanitize_text_field( $data['medium_label'] ) : '',
			'medium_code'         => $medium_code,
			'campaign_label'      => isset( $data['campaign_label'] ) ? sanitize_text_field( $data['campaign_label'] ) : '',
			'campaign_code'       => $campaign_code,
			'content_label'       => isset( $data['content_label'] ) ? sanitize_text_field( $data['content_label'] ) : '',
			'content_code'        => $content_code,
			'term_label'          => isset( $data['term_label'] ) ? sanitize_text_field( $data['term_label'] ) : '',
			'term_code'           => $term_code,
			'append_standard_utm' => ! empty( $data['append_standard_utm'] ) ? 1 : 0,
			'status'              => $status,
			'updated_by'          => ! empty( $data['updated_by'] ) ? absint( $data['updated_by'] ) : get_current_user_id(),
			'updated_at'          => current_time( 'mysql' ),
		);

		if ( $id ) {
			$result = $this->repository->update( $id, $record );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return $id;
		}

		$record['created_by'] = ! empty( $data['created_by'] ) ? absint( $data['created_by'] ) : get_current_user_id();
		$record['created_at']  = current_time( 'mysql' );
		$result = $this->repository->insert( $record );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return absint( $result );
	}

	private function decorate_landing( $landing ) {
		if ( empty( $landing ) || ! is_array( $landing ) ) {
			return $landing;
		}

		$landing['slug'] = isset( $landing['slug'] ) ? $this->normalize_slug( $landing['slug'] ) : '';
		$landing['final_url'] = $this->build_landing_url( $landing );
		$landing['destination_url'] = $this->normalize_internal_url( isset( $landing['destination_url'] ) ? $landing['destination_url'] : '' );
		$landing['destination_label'] = $this->get_destination_label( $landing );

		return $landing;
	}

	private function normalize_landing( $landing ) {
		if ( is_numeric( $landing ) ) {
			$landing = $this->repository->get( absint( $landing ) );
		}
		if ( ! is_array( $landing ) ) {
			return array();
		}
		return $landing;
	}

	private function get_destination_label( array $landing ) {
		$post_id = ! empty( $landing['destination_post_id'] ) ? absint( $landing['destination_post_id'] ) : 0;
		if ( ! $post_id ) {
			return '';
		}

		$title = get_the_title( $post_id );
		if ( '' === (string) $title ) {
			return '';
		}

		$post_type = get_post_type( $post_id );
		$type_label = $post_type ? get_post_type_object( $post_type ) : null;

		return trim( $title . ( $type_label && ! empty( $type_label->labels->singular_name ) ? ' (' . $type_label->labels->singular_name . ')' : '' ) );
	}

	private function normalize_internal_url( $url ) {
		$url = esc_url_raw( (string) $url );
		if ( '' === $url ) {
			return '';
		}
		return wp_validate_redirect( $url, home_url( '/' ) );
	}

	private function validate_technical_code( $value ) {
		$value = strtolower( trim( (string) $value ) );
		if ( '' === $value || ! preg_match( '/^[a-z0-9_-]+$/', $value ) ) {
			return new WP_Error( 'invalid_code', 'کد فنی فقط می‌تواند شامل حروف لاتین کوچک، عدد، خط تیره و underscore باشد.' );
		}
		return $value;
	}

	private function resolve_destination_url( array $landing ) {
		$post_id = ! empty( $landing['destination_post_id'] ) ? absint( $landing['destination_post_id'] ) : 0;
		if ( $post_id ) {
			$url = get_permalink( $post_id );
			if ( $url ) {
				return $this->normalize_internal_url( $url );
			}
		}

		return $this->normalize_internal_url( isset( $landing['destination_url'] ) ? $landing['destination_url'] : '' );
	}
}

<?php
/**
 * Attribution detection and current session service.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_Attribution_Service {
	const COOKIE_NAME = 'crpcrm_current_attr';
	const LANDING_VISITOR_COOKIE = 'crpcrm_visitor_id';
	const LANDING_FIRST_TOUCH_COOKIE = 'crpcrm_first_touch';
	const LANDING_LAST_TOUCH_COOKIE = 'crpcrm_last_touch';

	private $attribution_repository;
	private $customer_repository;
	private $landing_manager;

	public function __construct( CRPCRM_Customer_Attribution_Repository $attribution_repository = null, CRPCRM_Customer_Repository $customer_repository = null ) {
		$this->attribution_repository = $attribution_repository ? $attribution_repository : new CRPCRM_Customer_Attribution_Repository();
		$this->customer_repository     = $customer_repository ? $customer_repository : new CRPCRM_Customer_Repository();
		$this->landing_manager         = class_exists( 'CRPCRM_Landing_Manager' ) ? new CRPCRM_Landing_Manager() : null;
	}

	public function register_hooks() {
		add_action( 'init', array( $this, 'maybe_track_current_request' ), 5 );
	}

	public function maybe_track_current_request() {
		if ( ! CRPCRM_Feature_Manager::is_enabled( 'tracking' ) || 'yes' !== CRPCRM_Settings::get( 'attribution_enabled', 'yes' ) ) {
			return;
		}

		if ( $this->should_skip_tracking() ) {
			return;
		}

		$current   = $this->get_current_attribution();
		$detected  = $this->detect_from_request();
		$trackable = ! empty( $detected['_trackable'] );

		if ( ! $trackable ) {
			return;
		}

		if ( 'direct' === $detected['source'] && $current ) {
			return;
		}

		$this->set_current_attribution( $detected );

		$customer_id = null;
		$user_id     = is_user_logged_in() ? get_current_user_id() : null;
		if ( $user_id ) {
			$customer = $this->customer_repository->ensure_for_user( $user_id );
			if ( is_array( $customer ) && ! empty( $customer['id'] ) ) {
				$customer_id = absint( $customer['id'] );
				$this->apply_to_customer( $customer_id, $user_id, $detected );
			}
		}

		$this->record_event( $detected, $customer_id, $user_id, (bool) $user_id );
	}

	public function detect_from_request() {
		$landing_page = $this->get_current_url();
		$referrer     = $this->get_http_referrer();
		$detected_at  = CRPCRM_Helpers::current_datetime();
		$expires_at   = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) + $this->get_window_seconds() );

		$utm_source = $this->get_request_text( 'utm_source' );
		$has_utm    = $this->request_has_any_utm();

		if ( $has_utm ) {
			$data = array(
				'source'       => '' !== $utm_source ? $utm_source : 'utm',
				'medium'       => $this->get_request_text( 'utm_medium', 'utm' ),
				'campaign'     => $this->get_request_text( 'utm_campaign' ),
				'content'      => $this->get_request_text( 'utm_content' ),
				'term'         => $this->get_request_text( 'utm_term' ),
				'landing_page' => $landing_page,
				'referrer'     => $referrer,
				'detected_at'  => $detected_at,
				'expires_at'   => $expires_at,
				'_trackable'   => true,
				'_reason'      => 'utm',
			);
			CRPCRM_Logger::info( 'attribution_detected_utm', 'attribution', array( 'source' => $data['source'], 'medium' => $data['medium'] ) );
			return $this->sanitize_attribution( $data, true );
		}

		if ( '' !== $referrer ) {
			if ( $this->is_internal_referrer( $referrer ) ) {
				CRPCRM_Logger::info( 'attribution_skipped_internal_referrer', 'attribution', array( 'referrer_host' => $this->get_url_host( $referrer ) ) );
				return array( '_trackable' => false, '_reason' => 'internal_referrer' );
			}

			$source = $this->infer_source_from_referrer( $referrer );
			$data   = array(
				'source'       => $source['source'],
				'medium'       => $source['medium'],
				'campaign'     => '',
				'content'      => '',
				'term'         => '',
				'landing_page' => $landing_page,
				'referrer'     => $referrer,
				'detected_at'  => $detected_at,
				'expires_at'   => $expires_at,
				'_trackable'   => true,
				'_reason'      => 'referrer',
			);
			CRPCRM_Logger::info( 'attribution_detected_referrer', 'attribution', array( 'source' => $data['source'], 'medium' => $data['medium'] ) );
			return $this->sanitize_attribution( $data, true );
		}

		$current = $this->get_current_attribution();
		if ( $current ) {
			return array( '_trackable' => false, '_reason' => 'existing_session' );
		}

		$data = array(
			'source'       => 'direct',
			'medium'       => 'none',
			'campaign'     => '',
			'content'      => '',
			'term'         => '',
			'landing_page' => $landing_page,
			'referrer'     => '',
			'detected_at'  => $detected_at,
			'expires_at'   => $expires_at,
			'_trackable'   => true,
			'_reason'      => 'direct',
		);
		CRPCRM_Logger::info( 'attribution_detected_direct', 'attribution', array( 'source' => 'direct', 'medium' => 'none' ) );
		return $this->sanitize_attribution( $data, true );
	}

	public function get_current_attribution() {
		if ( empty( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			return null;
		}

		$raw = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) );
		$json = base64_decode( $raw, true );
		if ( false === $json ) {
			$this->log_invalid_cookie();
			return null;
		}

		$decoded = json_decode( $json, true );
		if ( ! is_array( $decoded ) || empty( $decoded['data'] ) || empty( $decoded['sig'] ) || ! is_array( $decoded['data'] ) ) {
			$this->log_invalid_cookie();
			return null;
		}

		$expected = $this->sign_attribution( $decoded['data'] );
		if ( ! hash_equals( $expected, (string) $decoded['sig'] ) ) {
			$this->log_invalid_cookie();
			return null;
		}

		$data = $this->sanitize_attribution( $decoded['data'] );
		if ( ! $this->is_valid_attribution( $data ) ) {
			$this->log_invalid_cookie();
			return null;
		}

		if ( strtotime( $data['expires_at'] ) < current_time( 'timestamp' ) ) {
			return null;
		}

		return $data;
	}

	public function set_current_attribution( $data ) {
		$data = $this->sanitize_attribution( $data );
		if ( ! $this->is_valid_attribution( $data ) ) {
			return false;
		}

		$payload = array(
			'data' => $data,
			'sig'  => $this->sign_attribution( $data ),
		);
		$value   = base64_encode( wp_json_encode( $payload ) );
		$expire  = strtotime( $data['expires_at'] );

		$_COOKIE[ self::COOKIE_NAME ] = $value;
		$this->set_cookie_compat( self::COOKIE_NAME, $value, $expire );
		CRPCRM_Logger::info( 'attribution_session_updated', 'attribution', array( 'source' => $data['source'], 'medium' => $data['medium'] ) );
		return true;
	}

	public function clear_current_attribution() {
		unset( $_COOKIE[ self::COOKIE_NAME ] );
		$this->set_cookie_compat( self::COOKIE_NAME, '', time() - HOUR_IN_SECONDS );
	}

	public function apply_to_customer( $customer_id, $user_id, $attribution ) {
		if ( ! CRPCRM_Feature_Manager::is_enabled( 'tracking' ) ) {
			return false;
		}

		$customer_id = absint( $customer_id );
		$user_id     = absint( $user_id );
		$attribution = $this->sanitize_attribution( $attribution );

		if ( ! $customer_id || ! $this->is_valid_attribution( $attribution ) ) {
			return false;
		}

		$customer = $this->customer_repository->get( $customer_id );
		if ( ! $customer ) {
			return false;
		}

		$update = array(
			'last_source'       => $attribution['source'],
			'last_medium'       => $attribution['medium'],
			'last_campaign'     => $attribution['campaign'],
			'last_content'      => $attribution['content'],
			'last_term'         => $attribution['term'],
			'last_landing_page' => $attribution['landing_page'],
			'last_referrer'     => $attribution['referrer'],
			'last_seen_at'      => $attribution['detected_at'],
		);

		if ( empty( $customer['first_source'] ) ) {
			$update['first_source']       = $attribution['source'];
			$update['first_medium']       = $attribution['medium'];
			$update['first_campaign']     = $attribution['campaign'];
			$update['first_content']      = $attribution['content'];
			$update['first_term']         = $attribution['term'];
			$update['first_landing_page'] = $attribution['landing_page'];
			$update['first_referrer']     = $attribution['referrer'];
			$update['first_seen_at']      = $attribution['detected_at'];
		}

		$result = $this->customer_repository->update( $customer_id, $update );
		if ( $result ) {
			CRPCRM_Logger::info( 'attribution_applied_to_customer', 'attribution', array( 'customer_id' => $customer_id, 'user_id' => $user_id, 'source' => $attribution['source'] ) );
		}

		return $result;
	}

	public function record_event( $attribution, $customer_id = null, $user_id = null, $is_logged_in = false ) {
		if ( ! CRPCRM_Feature_Manager::is_enabled( 'tracking' ) || 'yes' !== CRPCRM_Settings::get( 'attribution_events_enabled', 'yes' ) ) {
			return false;
		}

		$attribution = $this->sanitize_attribution( $attribution );
		if ( ! $this->is_valid_attribution( $attribution ) ) {
			return false;
		}

		$event_id = $this->attribution_repository->add_event(
			array(
				'customer_id'     => $customer_id ? absint( $customer_id ) : null,
				'user_id'         => $user_id ? absint( $user_id ) : null,
				'source'          => $attribution['source'],
				'medium'          => $attribution['medium'],
				'campaign'        => $attribution['campaign'],
				'content'         => $attribution['content'],
				'term'            => $attribution['term'],
				'landing_page'    => $attribution['landing_page'],
				'referrer'        => $attribution['referrer'],
				'detected_at'     => $attribution['detected_at'],
				'is_logged_in'    => $is_logged_in ? 1 : 0,
				'ip_hash'         => $this->current_ip_hash(),
				'user_agent_hash' => $this->current_user_agent_hash(),
			)
		);

		if ( $event_id ) {
			CRPCRM_Logger::info( 'attribution_event_recorded', 'attribution', array( 'event_id' => $event_id, 'source' => $attribution['source'], 'logged_in' => $is_logged_in ? 1 : 0 ) );
		}

		return $event_id;
	}

	public function infer_source_from_referrer( $referrer ) {
		$host = strtolower( $this->get_url_host( $referrer ) );

		if ( false !== strpos( $host, 'instagram.com' ) ) {
			return array( 'source' => 'instagram', 'medium' => 'referral' );
		}
		if ( false !== strpos( $host, 'whatsapp.com' ) || 'wa.me' === $host || false !== strpos( $host, '.wa.me' ) ) {
			return array( 'source' => 'whatsapp', 'medium' => 'referral' );
		}
		if ( 't.me' === $host || false !== strpos( $host, '.t.me' ) || false !== strpos( $host, 'telegram.me' ) ) {
			return array( 'source' => 'telegram', 'medium' => 'referral' );
		}
		if ( false !== strpos( $host, 'google.' ) ) {
			return array( 'source' => 'google', 'medium' => 'organic' );
		}
		if ( false !== strpos( $host, 'bing.' ) ) {
			return array( 'source' => 'bing', 'medium' => 'organic' );
		}

		return array( 'source' => $host ? $host : 'referral', 'medium' => 'referral' );
	}

	public function is_internal_referrer( $referrer ) {
		$referrer_host = strtolower( $this->get_url_host( $referrer ) );
		$site_host     = strtolower( wp_parse_url( home_url(), PHP_URL_HOST ) );

		if ( '' === $referrer_host || '' === $site_host ) {
			return false;
		}

		$normalized_referrer = preg_replace( '/^www\./', '', $referrer_host );
		$domains = preg_split( '/\r\n|\r|\n/', (string) CRPCRM_Settings::get( 'internal_domains', $site_host ) );
		$domains[] = $site_host;
		foreach ( $domains as $domain ) {
			$domain = strtolower( trim( (string) $domain ) );
			$domain = preg_replace( '/^https?:\/\//', '', $domain );
			$domain = preg_replace( '/\/.*$/', '', $domain );
			$domain = preg_replace( '/^www\./', '', $domain );
			if ( '' !== $domain && $normalized_referrer === $domain ) {
				return true;
			}
		}

		return false;
	}

	public function get_attribution_for_new_request() {
		if ( ! CRPCRM_Feature_Manager::is_enabled( 'tracking' ) ) {
			$now = CRPCRM_Helpers::current_datetime();
			return array(
				'source'       => 'direct',
				'medium'       => 'none',
				'campaign'     => '',
				'content'      => '',
				'term'         => '',
				'landing_page' => '',
				'referrer'     => '',
				'detected_at'  => $now,
				'expires_at'   => $now,
			);
		}

		$current = $this->get_current_attribution();
		if ( $current ) {
			return $current;
		}

		$now = CRPCRM_Helpers::current_datetime();
		return array(
			'source'       => 'direct',
			'medium'       => 'none',
			'campaign'     => '',
			'content'      => '',
			'term'         => '',
			'landing_page' => $this->get_current_url(),
			'referrer'     => '',
			'detected_at'  => $now,
			'expires_at'   => date( 'Y-m-d H:i:s', current_time( 'timestamp' ) + $this->get_window_seconds() ),
		);
	}

	public function get_landing_attribution_for_new_request( $context = array() ) {
		if ( ! CRPCRM_Feature_Manager::is_enabled( 'landing_manager' ) ) {
			return array();
		}

		$context      = is_array( $context ) ? $context : array();
		$visitor_id   = $this->get_landing_cookie_value( self::LANDING_VISITOR_COOKIE );
		$first_touch  = $this->get_landing_touch_cookie( self::LANDING_FIRST_TOUCH_COOKIE );
		$last_touch   = $this->get_landing_touch_cookie( self::LANDING_LAST_TOUCH_COOKIE );
		$request_url  = $this->get_request_context_url( $context );
		$referrer     = $this->get_request_context_referrer( $context );

		if ( empty( $first_touch ) && empty( $last_touch ) && '' !== $request_url ) {
			$fallback = $this->resolve_landing_touch_from_url( $request_url );
			if ( ! empty( $fallback ) ) {
				$first_touch = $fallback;
				$last_touch  = $fallback;
			}
		}

		$first_touch = $this->enrich_landing_touch( $first_touch );
		$last_touch  = $this->enrich_landing_touch( $last_touch );

		if ( empty( $first_touch ) && empty( $last_touch ) ) {
			return array();
		}

		return array(
			'visitor_id'      => $visitor_id,
			'conversion_page' => '' !== $request_url ? $request_url : $this->get_current_url(),
			'converted_at'    => CRPCRM_Helpers::current_datetime(),
			'referrer'        => $referrer,
			'first_touch'     => $first_touch,
			'last_touch'      => $last_touch,
		);
	}

	private function should_skip_tracking() {
		if ( is_admin() ) {
			return true;
		}
		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
			return true;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return true;
		}

		return false;
	}

	private function request_has_any_utm() {
		foreach ( array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term' ) as $key ) {
			if ( isset( $_GET[ $key ] ) && '' !== sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) ) {
				return true;
			}
		}

		return false;
	}

	private function get_request_text( $key, $default = '' ) {
		if ( ! isset( $_GET[ $key ] ) ) {
			return $default;
		}

		return sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
	}

	private function sanitize_attribution( $data, $keep_private = false ) {
		$clean = array(
			'source'       => isset( $data['source'] ) ? sanitize_text_field( $data['source'] ) : '',
			'medium'       => isset( $data['medium'] ) ? sanitize_text_field( $data['medium'] ) : '',
			'campaign'     => isset( $data['campaign'] ) ? sanitize_text_field( $data['campaign'] ) : '',
			'content'      => isset( $data['content'] ) ? sanitize_text_field( $data['content'] ) : '',
			'term'         => isset( $data['term'] ) ? sanitize_text_field( $data['term'] ) : '',
			'landing_page' => isset( $data['landing_page'] ) ? esc_url_raw( $data['landing_page'] ) : '',
			'referrer'     => isset( $data['referrer'] ) ? esc_url_raw( $data['referrer'] ) : '',
			'detected_at'  => isset( $data['detected_at'] ) ? sanitize_text_field( $data['detected_at'] ) : '',
			'expires_at'   => isset( $data['expires_at'] ) ? sanitize_text_field( $data['expires_at'] ) : '',
		);

		if ( $keep_private ) {
			$clean['_trackable'] = ! empty( $data['_trackable'] );
			$clean['_reason']    = isset( $data['_reason'] ) ? sanitize_key( $data['_reason'] ) : '';
		}

		return $clean;
	}

	private function is_valid_attribution( $data ) {
		if ( ! is_array( $data ) || empty( $data['source'] ) || empty( $data['medium'] ) || empty( $data['detected_at'] ) || empty( $data['expires_at'] ) ) {
			return false;
		}

		return (bool) strtotime( $data['detected_at'] ) && (bool) strtotime( $data['expires_at'] );
	}

	private function get_current_url() {
		$host        = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : wp_parse_url( home_url(), PHP_URL_HOST );
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		$scheme      = is_ssl() ? 'https://' : 'http://';

		return esc_url_raw( $scheme . $host . $request_uri );
	}

	private function get_http_referrer() {
		if ( empty( $_SERVER['HTTP_REFERER'] ) ) {
			return '';
		}

		$referrer = esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
		return wp_http_validate_url( $referrer ) ? $referrer : '';
	}

	private function get_url_host( $url ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		return $host ? sanitize_text_field( $host ) : '';
	}

	private function get_window_seconds() {
		return max( 1, absint( CRPCRM_Settings::get( 'attribution_window_hours', 24 ) ) ) * HOUR_IN_SECONDS;
	}

	private function set_cookie_compat( $name, $value, $expire ) {
		if ( headers_sent( $file, $line ) ) {
			CRPCRM_Logger::warning(
				'attribution_cookie_headers_sent',
				'attribution',
				array(
					'file' => $file,
					'line' => $line,
				)
			);
			return false;
		}

		$args = array(
			'expires'  => $expire,
			'path'     => '/',
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax',
		);

		if ( PHP_VERSION_ID >= 70300 ) {
			return setcookie( $name, $value, $args );
		}

		return setcookie( $name, $value, $expire, '/; samesite=Lax', '', is_ssl(), true );
	}

	private function sign_attribution( $data ) {
		$public_data = $this->sanitize_attribution( $data );
		return hash_hmac( 'sha256', wp_json_encode( $public_data ), wp_salt( 'auth' ) );
	}

	private function current_ip_hash() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return $this->hash_value( $ip );
	}

	private function current_user_agent_hash() {
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		return $this->hash_value( $user_agent );
	}

	private function hash_value( $value ) {
		return hash_hmac( 'sha256', (string) $value, wp_salt( 'auth' ) );
	}

	private function log_invalid_cookie() {
		CRPCRM_Logger::warning( 'attribution_invalid_cookie', 'attribution' );
	}

	private function get_landing_cookie_value( $name ) {
		if ( empty( $_COOKIE[ $name ] ) ) {
			return '';
		}

		$value = sanitize_text_field( wp_unslash( $_COOKIE[ $name ] ) );
		return preg_match( '/^[a-z0-9\-_]{8,100}$/i', $value ) ? $value : '';
	}

	private function get_landing_touch_cookie( $name ) {
		if ( empty( $_COOKIE[ $name ] ) ) {
			return array();
		}

		$raw = sanitize_text_field( wp_unslash( $_COOKIE[ $name ] ) );
		$json = base64_decode( $raw, true );
		if ( false === $json ) {
			return array();
		}

		$decoded = json_decode( $json, true );
		if ( ! is_array( $decoded ) || empty( $decoded['data'] ) || empty( $decoded['sig'] ) || ! is_array( $decoded['data'] ) ) {
			return array();
		}

		$expected = hash_hmac( 'sha256', wp_json_encode( $this->sanitize_landing_touch( $decoded['data'] ) ), wp_salt( 'auth' ) );
		if ( ! hash_equals( $expected, (string) $decoded['sig'] ) ) {
			return array();
		}

		return $this->sanitize_landing_touch( $decoded['data'] );
	}

	private function sanitize_landing_touch( $data ) {
		$data = is_array( $data ) ? $data : array();
		return array(
			'landing_id'    => isset( $data['landing_id'] ) ? absint( $data['landing_id'] ) : 0,
			'landing_slug'  => isset( $data['landing_slug'] ) ? sanitize_key( $data['landing_slug'] ) : '',
			'source_code'   => isset( $data['source_code'] ) ? sanitize_text_field( $data['source_code'] ) : '',
			'medium_code'   => isset( $data['medium_code'] ) ? sanitize_text_field( $data['medium_code'] ) : '',
			'campaign_code' => isset( $data['campaign_code'] ) ? sanitize_text_field( $data['campaign_code'] ) : '',
			'content_code'  => isset( $data['content_code'] ) ? sanitize_text_field( $data['content_code'] ) : '',
			'term_code'     => isset( $data['term_code'] ) ? sanitize_text_field( $data['term_code'] ) : '',
			'source_label'  => isset( $data['source_label'] ) ? sanitize_text_field( $data['source_label'] ) : '',
			'medium_label'  => isset( $data['medium_label'] ) ? sanitize_text_field( $data['medium_label'] ) : '',
			'campaign_label'=> isset( $data['campaign_label'] ) ? sanitize_text_field( $data['campaign_label'] ) : '',
			'content_label' => isset( $data['content_label'] ) ? sanitize_text_field( $data['content_label'] ) : '',
			'term_label'    => isset( $data['term_label'] ) ? sanitize_text_field( $data['term_label'] ) : '',
			'clicked_at'    => isset( $data['clicked_at'] ) ? sanitize_text_field( $data['clicked_at'] ) : '',
			'click_id'      => isset( $data['click_id'] ) ? absint( $data['click_id'] ) : 0,
			'current_url'   => isset( $data['current_url'] ) ? esc_url_raw( $data['current_url'] ) : '',
			'visitor_id'    => isset( $data['visitor_id'] ) ? sanitize_text_field( $data['visitor_id'] ) : '',
		);
	}

	private function enrich_landing_touch( $touch ) {
		$touch = $this->sanitize_landing_touch( $touch );
		if ( empty( $touch['landing_id'] ) && empty( $touch['landing_slug'] ) ) {
			return array();
		}

		$landing = null;
		if ( $this->landing_manager ) {
			if ( ! empty( $touch['landing_id'] ) ) {
				$landing = $this->landing_manager->get( absint( $touch['landing_id'] ) );
			}
			if ( ! $landing && ! empty( $touch['landing_slug'] ) ) {
				$landing = $this->landing_manager->get_by_slug( $touch['landing_slug'] );
			}
		}

		if ( ! is_array( $landing ) || empty( $landing['id'] ) || 'active' !== ( $landing['status'] ?? '' ) ) {
			return array();
		}

		$touch['landing_id']     = absint( $landing['id'] );
		$touch['landing_slug']   = isset( $landing['slug'] ) ? sanitize_key( $landing['slug'] ) : $touch['landing_slug'];
		$touch['landing_title']  = isset( $landing['title'] ) ? sanitize_text_field( $landing['title'] ) : '';
		$touch['destination_url'] = isset( $landing['destination_url'] ) ? esc_url_raw( $landing['destination_url'] ) : '';
		$touch['source_code']    = isset( $landing['source_code'] ) ? sanitize_text_field( $landing['source_code'] ) : $touch['source_code'];
		$touch['source_label']   = isset( $landing['source_label'] ) ? sanitize_text_field( $landing['source_label'] ) : $touch['source_label'];
		$touch['medium_code']    = isset( $landing['medium_code'] ) ? sanitize_text_field( $landing['medium_code'] ) : $touch['medium_code'];
		$touch['medium_label']   = isset( $landing['medium_label'] ) ? sanitize_text_field( $landing['medium_label'] ) : $touch['medium_label'];
		$touch['campaign_code']  = isset( $landing['campaign_code'] ) ? sanitize_text_field( $landing['campaign_code'] ) : $touch['campaign_code'];
		$touch['campaign_label'] = isset( $landing['campaign_label'] ) ? sanitize_text_field( $landing['campaign_label'] ) : $touch['campaign_label'];
		$touch['content_code']   = isset( $landing['content_code'] ) ? sanitize_text_field( $landing['content_code'] ) : $touch['content_code'];
		$touch['content_label']  = isset( $landing['content_label'] ) ? sanitize_text_field( $landing['content_label'] ) : $touch['content_label'];
		$touch['term_code']      = isset( $landing['term_code'] ) ? sanitize_text_field( $landing['term_code'] ) : $touch['term_code'];
		$touch['term_label']     = isset( $landing['term_label'] ) ? sanitize_text_field( $landing['term_label'] ) : $touch['term_label'];

		return $touch;
	}

	private function resolve_landing_touch_from_url( $url ) {
		$url = esc_url_raw( (string) $url );
		if ( '' === $url || ! $this->landing_manager ) {
			return array();
		}

		$slug = '';
		$query = wp_parse_url( $url, PHP_URL_QUERY );
		if ( ! empty( $query ) ) {
			parse_str( $query, $params );
			$slug = ! empty( $params['u'] ) ? sanitize_key( $params['u'] ) : ( ! empty( $params['U'] ) ? sanitize_key( $params['U'] ) : '' );
		}

		if ( '' === $slug ) {
			return array();
		}

		$landing = $this->landing_manager->get_by_slug( $slug );
		if ( ! is_array( $landing ) || empty( $landing['id'] ) || 'active' !== ( $landing['status'] ?? '' ) ) {
			return array();
		}

		$landing_url = isset( $landing['destination_url'] ) ? esc_url_raw( $landing['destination_url'] ) : '';
		if ( '' !== $landing_url ) {
			$landing_path = $this->normalize_url_for_landing_match( $landing_url );
			$request_path = $this->normalize_url_for_landing_match( $url );
			if ( '' !== $landing_path && '' !== $request_path && $landing_path !== $request_path ) {
				return array();
			}
		}

		return array(
			'landing_id'    => absint( $landing['id'] ),
			'landing_slug'  => isset( $landing['slug'] ) ? sanitize_key( $landing['slug'] ) : '',
			'source_code'   => isset( $landing['source_code'] ) ? sanitize_text_field( $landing['source_code'] ) : '',
			'source_label'  => isset( $landing['source_label'] ) ? sanitize_text_field( $landing['source_label'] ) : '',
			'medium_code'   => isset( $landing['medium_code'] ) ? sanitize_text_field( $landing['medium_code'] ) : '',
			'medium_label'  => isset( $landing['medium_label'] ) ? sanitize_text_field( $landing['medium_label'] ) : '',
			'campaign_code' => isset( $landing['campaign_code'] ) ? sanitize_text_field( $landing['campaign_code'] ) : '',
			'campaign_label'=> isset( $landing['campaign_label'] ) ? sanitize_text_field( $landing['campaign_label'] ) : '',
			'content_code'  => isset( $landing['content_code'] ) ? sanitize_text_field( $landing['content_code'] ) : '',
			'content_label' => isset( $landing['content_label'] ) ? sanitize_text_field( $landing['content_label'] ) : '',
			'term_code'     => isset( $landing['term_code'] ) ? sanitize_text_field( $landing['term_code'] ) : '',
			'term_label'    => isset( $landing['term_label'] ) ? sanitize_text_field( $landing['term_label'] ) : '',
			'clicked_at'    => CRPCRM_Helpers::current_datetime(),
			'click_id'      => 0,
			'current_url'   => esc_url_raw( $url ),
			'visitor_id'    => $this->get_landing_cookie_value( self::LANDING_VISITOR_COOKIE ),
		);
	}

	private function get_request_context_url( array $context ) {
		foreach ( array( 'request_url', 'current_url', 'url' ) as $key ) {
			if ( ! empty( $context[ $key ] ) ) {
				$url = esc_url_raw( (string) $context[ $key ] );
				if ( '' !== $url ) {
					return $url;
				}
			}
		}

		if ( ! empty( $_SERVER['HTTP_REFERER'] ) ) {
			$referrer = esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
			if ( '' !== $referrer ) {
				return $referrer;
			}
		}

		return '';
	}

	private function get_request_context_referrer( array $context ) {
		if ( ! empty( $context['referrer'] ) ) {
			$referrer = esc_url_raw( (string) $context['referrer'] );
			return wp_http_validate_url( $referrer ) ? $referrer : '';
		}

		if ( ! empty( $_SERVER['HTTP_REFERER'] ) ) {
			$referrer = esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
			return wp_http_validate_url( $referrer ) ? $referrer : '';
		}

		return '';
	}

	private function normalize_url_for_landing_match( $url ) {
		$url = esc_url_raw( (string) $url );
		if ( '' === $url ) {
			return '';
		}

		$parsed = wp_parse_url( $url );
		if ( empty( $parsed['host'] ) ) {
			return '';
		}

		$path = isset( $parsed['path'] ) ? untrailingslashit( '/' . ltrim( $parsed['path'], '/' ) ) : '';
		$query = array();
		if ( ! empty( $parsed['query'] ) ) {
			parse_str( $parsed['query'], $query );
			foreach ( array( 'u', 'U', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'fbclid', 'gclid', 'yclid' ) as $tracking_key ) {
				unset( $query[ $tracking_key ] );
			}
			ksort( $query );
		}

		$host = preg_replace( '/^www\./', '', strtolower( $parsed['host'] ) );
		$normalized = $host . $path;
		if ( ! empty( $query ) ) {
			$normalized .= '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
		}

		return $normalized;
	}
}

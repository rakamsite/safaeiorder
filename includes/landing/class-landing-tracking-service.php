<?php
/**
 * Landing tracking service.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_Landing_Tracking_Service {
	const VISITOR_COOKIE = 'crpcrm_visitor_id';
	const FIRST_TOUCH_COOKIE = 'crpcrm_first_touch';
	const LAST_TOUCH_COOKIE = 'crpcrm_last_touch';
	const TRACKING_ACTION = 'crpcrm_track_landing_click';
	const DUPLICATE_WINDOW_MINUTES = 30;
	const VISITOR_COOKIE_DAYS = 90;

	private $landing_manager;
	private $click_repository;

	public function __construct( CRPCRM_Landing_Manager $landing_manager = null, CRPCRM_Landing_Click_Repository $click_repository = null ) {
		$this->landing_manager  = $landing_manager ? $landing_manager : new CRPCRM_Landing_Manager();
		$this->click_repository = $click_repository ? $click_repository : new CRPCRM_Landing_Click_Repository();
	}

	public function register_hooks() {
		add_action( 'template_redirect', array( $this, 'maybe_track_current_request' ), 1 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ), 20 );
		add_action( 'wp_ajax_' . self::TRACKING_ACTION, array( $this, 'handle_ajax_tracking' ) );
		add_action( 'wp_ajax_nopriv_' . self::TRACKING_ACTION, array( $this, 'handle_ajax_tracking' ) );
	}

	public function enqueue_assets() {
		if ( ! CRPCRM_Feature_Manager::is_enabled( 'landing_manager' ) || is_admin() ) {
			return;
		}

		$script_path = CRPCRM_PLUGIN_DIR . 'assets/js/landing-tracking.js';
		$handle      = 'crpcrm-landing-tracking';

		wp_register_script(
			$handle,
			CRPCRM_PLUGIN_URL . 'assets/js/landing-tracking.js',
			array(),
			file_exists( $script_path ) ? filemtime( $script_path ) : CRPCRM_VERSION,
			true
		);

		wp_localize_script(
			$handle,
			'crpcrmLandingTracking',
			array(
				'enabled'   => true,
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'action'    => self::TRACKING_ACTION,
				'token'     => $this->get_tracking_token(),
				'debug'     => defined( 'WP_DEBUG' ) && WP_DEBUG,
			)
		);

		wp_enqueue_script( $handle );
	}

	public function maybe_track_current_request() {
		if ( ! $this->is_tracking_available() ) {
			return;
		}

		$slug = $this->get_requested_slug();
		if ( '' === $slug ) {
			return;
		}

		$result = $this->track_request(
			array(
				'source'      => 'php',
				'slug'        => $slug,
				'current_url' => $this->get_current_url(),
				'referrer'    => $this->get_http_referrer(),
				'method'      => isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET',
			)
		);

		if ( is_wp_error( $result ) ) {
			$this->log_error( 'landing_tracking_failed', array( 'code' => $result->get_error_code(), 'source' => 'php' ) );
		}
	}

	public function handle_ajax_tracking() {
		$token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
		if ( '' === $token || ! hash_equals( $this->get_tracking_token(), $token ) ) {
			wp_send_json_success(
				array(
					'tracking' => false,
					'reason'   => 'invalid_token',
				)
			);
		}

		if ( ! CRPCRM_Feature_Manager::is_enabled( 'landing_manager' ) ) {
			wp_send_json_success(
				array(
					'tracking' => false,
					'reason'   => 'feature_disabled',
				)
			);
		}

		$result = $this->track_request(
			array(
				'source'      => 'js',
				'slug'        => isset( $_POST['slug'] ) ? sanitize_text_field( wp_unslash( $_POST['slug'] ) ) : '',
				'current_url' => isset( $_POST['current_url'] ) ? wp_unslash( $_POST['current_url'] ) : '',
				'referrer'    => isset( $_POST['referrer'] ) ? wp_unslash( $_POST['referrer'] ) : '',
				'method'      => isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'POST',
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_success(
				array(
					'tracking' => false,
					'reason'   => $result->get_error_code(),
				)
			);
		}

		wp_send_json_success( $result );
	}

	private function track_request( array $context, $allow_ajax = false ) {
		if ( ! $this->is_tracking_available( $allow_ajax ) ) {
			return array( 'tracking' => false, 'reason' => 'feature_disabled' );
		}

		$slug = isset( $context['slug'] ) ? $this->sanitize_slug( $context['slug'] ) : '';
		if ( '' === $slug ) {
			return array( 'tracking' => false, 'reason' => 'missing_slug' );
		}

		if ( ! empty( $context['method'] ) && 'HEAD' === strtoupper( (string) $context['method'] ) ) {
			return array( 'tracking' => false, 'reason' => 'head_request' );
		}

		$user_agent = $this->get_user_agent();
		if ( '' === $user_agent || $this->is_bot_user_agent( $user_agent ) ) {
			return array( 'tracking' => false, 'reason' => 'bot' );
		}

		$landing = $this->landing_manager->get_by_slug( $slug );
		if ( ! $landing || empty( $landing['id'] ) || 'active' !== ( $landing['status'] ?? '' ) ) {
			return array( 'tracking' => false, 'reason' => 'landing_not_found' );
		}

		$current_url = isset( $context['current_url'] ) ? $this->normalize_current_url( $context['current_url'] ) : '';
		if ( '' === $current_url ) {
			$current_url = $this->get_current_url();
		}

		if ( ! $this->landing_matches_current_request( $landing, $current_url ) ) {
			$this->log_debug(
				'landing_tracking_destination_mismatch',
				array(
					'landing_id' => absint( $landing['id'] ),
					'slug'       => $slug,
				)
			);
			return array( 'tracking' => false, 'reason' => 'destination_mismatch' );
		}

		$visitor_id = $this->get_or_create_visitor_id();
		$ip_hash    = $this->current_ip_hash();
		$ua_hash    = $this->hash_value( $user_agent );

		$duplicate = $this->click_repository->find_recent_duplicate( absint( $landing['id'] ), $visitor_id, $ip_hash, $ua_hash, self::DUPLICATE_WINDOW_MINUTES );
		if ( $duplicate && ! empty( $duplicate['id'] ) ) {
			$this->maybe_refresh_touch_cookies( $landing, $duplicate, $current_url, $visitor_id );

			return array(
				'tracking' => true,
				'duplicate' => true,
				'click_id'  => absint( $duplicate['id'] ),
				'landing_id' => absint( $landing['id'] ),
				'landing_slug' => $slug,
			);
		}

		$click_data = array(
			'landing_id'    => absint( $landing['id'] ),
			'landing_slug'  => $slug,
			'visitor_id'    => $visitor_id,
			'click_hash'    => $this->build_click_hash( $landing, $visitor_id, $ip_hash, $ua_hash ),
			'source_code'   => $landing['source_code'] ?? '',
			'medium_code'   => $landing['medium_code'] ?? '',
			'campaign_code' => $landing['campaign_code'] ?? '',
			'content_code'  => $landing['content_code'] ?? '',
			'term_code'     => $landing['term_code'] ?? '',
			'source_label'  => $landing['source_label'] ?? '',
			'medium_label'  => $landing['medium_label'] ?? '',
			'campaign_label'=> $landing['campaign_label'] ?? '',
			'content_label' => $landing['content_label'] ?? '',
			'term_label'    => $landing['term_label'] ?? '',
			'current_url'   => $current_url,
			'referrer'      => isset( $context['referrer'] ) ? $this->normalize_internal_referrer( $context['referrer'] ) : $this->get_http_referrer(),
			'user_agent'    => $user_agent,
			'ip_hash'       => $ip_hash,
			'created_at'    => CRPCRM_Helpers::current_datetime(),
		);

		$click_id = $this->click_repository->insert( $click_data );
		if ( is_wp_error( $click_id ) ) {
			$this->log_error( 'landing_tracking_insert_failed', array( 'code' => $click_id->get_error_code(), 'landing_id' => absint( $landing['id'] ) ) );
			return $click_id;
		}

		$this->maybe_set_first_touch_cookie( $landing, absint( $click_id ), $current_url );
		$this->set_last_touch_cookie( $landing, absint( $click_id ), $current_url );

		return array(
			'tracking'     => true,
			'duplicate'    => false,
			'click_id'     => absint( $click_id ),
			'landing_id'   => absint( $landing['id'] ),
			'landing_slug' => $slug,
		);
	}

	private function maybe_refresh_touch_cookies( array $landing, array $click_row, $current_url, $visitor_id ) {
		$this->maybe_set_first_touch_cookie( $landing, absint( $click_row['id'] ), $current_url, $visitor_id );
		$this->set_last_touch_cookie( $landing, absint( $click_row['id'] ), $current_url, $visitor_id );
	}

	private function maybe_set_first_touch_cookie( array $landing, $click_id, $current_url, $visitor_id = '' ) {
		if ( ! empty( $this->get_signed_cookie( self::FIRST_TOUCH_COOKIE ) ) ) {
			return;
		}

		$this->set_signed_cookie(
			self::FIRST_TOUCH_COOKIE,
			$this->build_touch_payload( $landing, $click_id, $current_url, $visitor_id ),
			time() + ( self::VISITOR_COOKIE_DAYS * DAY_IN_SECONDS )
		);
	}

	private function set_last_touch_cookie( array $landing, $click_id, $current_url, $visitor_id = '' ) {
		$this->set_signed_cookie(
			self::LAST_TOUCH_COOKIE,
			$this->build_touch_payload( $landing, $click_id, $current_url, $visitor_id ),
			time() + ( self::VISITOR_COOKIE_DAYS * DAY_IN_SECONDS )
		);
	}

	private function build_touch_payload( array $landing, $click_id, $current_url, $visitor_id = '' ) {
		return array(
			'landing_id'    => absint( $landing['id'] ),
			'landing_slug'  => isset( $landing['slug'] ) ? sanitize_key( $landing['slug'] ) : '',
			'source_code'   => isset( $landing['source_code'] ) ? sanitize_text_field( $landing['source_code'] ) : '',
			'medium_code'   => isset( $landing['medium_code'] ) ? sanitize_text_field( $landing['medium_code'] ) : '',
			'campaign_code' => isset( $landing['campaign_code'] ) ? sanitize_text_field( $landing['campaign_code'] ) : '',
			'content_code'  => isset( $landing['content_code'] ) ? sanitize_text_field( $landing['content_code'] ) : '',
			'term_code'     => isset( $landing['term_code'] ) ? sanitize_text_field( $landing['term_code'] ) : '',
			'source_label'  => isset( $landing['source_label'] ) ? sanitize_text_field( $landing['source_label'] ) : '',
			'medium_label'  => isset( $landing['medium_label'] ) ? sanitize_text_field( $landing['medium_label'] ) : '',
			'campaign_label'=> isset( $landing['campaign_label'] ) ? sanitize_text_field( $landing['campaign_label'] ) : '',
			'content_label' => isset( $landing['content_label'] ) ? sanitize_text_field( $landing['content_label'] ) : '',
			'term_label'    => isset( $landing['term_label'] ) ? sanitize_text_field( $landing['term_label'] ) : '',
			'clicked_at'    => CRPCRM_Helpers::current_datetime(),
			'click_id'      => absint( $click_id ),
			'current_url'   => esc_url_raw( $current_url ),
			'visitor_id'    => sanitize_text_field( (string) $visitor_id ),
		);
	}

	private function get_or_create_visitor_id() {
		$visitor_id = $this->get_cookie_value( self::VISITOR_COOKIE );
		if ( '' !== $visitor_id ) {
			return $visitor_id;
		}

		$visitor_id = strtolower( wp_generate_uuid4() );
		$this->set_plain_cookie( self::VISITOR_COOKIE, $visitor_id, time() + ( self::VISITOR_COOKIE_DAYS * DAY_IN_SECONDS ) );
		return $visitor_id;
	}

	private function get_cookie_value( $name ) {
		if ( empty( $_COOKIE[ $name ] ) ) {
			return '';
		}

		$value = sanitize_text_field( wp_unslash( $_COOKIE[ $name ] ) );
		return preg_match( '/^[a-z0-9\-_]{8,100}$/i', $value ) ? $value : '';
	}

	private function get_signed_cookie( $name ) {
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

		$expected = $this->sign_payload( $decoded['data'] );
		if ( ! hash_equals( $expected, (string) $decoded['sig'] ) ) {
			return array();
		}

		return $this->sanitize_payload( $decoded['data'] );
	}

	private function set_signed_cookie( $name, array $data, $expire ) {
		if ( headers_sent() ) {
			return false;
		}

		$payload = array(
			'data' => $this->sanitize_payload( $data ),
			'sig'  => $this->sign_payload( $data ),
		);
		$value = base64_encode( wp_json_encode( $payload ) );

		return $this->set_cookie( $name, $value, $expire );
	}

	private function set_plain_cookie( $name, $value, $expire ) {
		if ( headers_sent() ) {
			return false;
		}

		return $this->set_cookie( $name, sanitize_text_field( (string) $value ), $expire );
	}

	private function set_cookie( $name, $value, $expire ) {
		$args = array(
			'expires'  => absint( $expire ),
			'path'     => defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/',
			'domain'   => defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '',
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax',
		);

		if ( PHP_VERSION_ID >= 70300 ) {
			return setcookie( $name, $value, $args );
		}

		return setcookie( $name, $value, absint( $expire ), $args['path'] . '; samesite=Lax', $args['domain'], $args['secure'], $args['httponly'] );
	}

	private function sign_payload( array $data ) {
		$clean = $this->sanitize_payload( $data );
		return hash_hmac( 'sha256', wp_json_encode( $clean ), wp_salt( 'auth' ) );
	}

	private function sanitize_payload( array $data ) {
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

	private function is_tracking_available( $allow_ajax = false ) {
		if ( ! CRPCRM_Feature_Manager::is_enabled( 'landing_manager' ) ) {
			return false;
		}

		if ( is_admin() && ! $allow_ajax ) {
			return false;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return false;
		}

		return true;
	}

	private function get_requested_slug() {
		$slug = '';
		if ( isset( $_GET['u'] ) ) {
			$slug = wp_unslash( $_GET['u'] );
		} elseif ( isset( $_GET['U'] ) ) {
			$slug = wp_unslash( $_GET['U'] );
		}

		return $this->sanitize_slug( $slug );
	}

	private function sanitize_slug( $slug ) {
		$slug = strtolower( trim( sanitize_text_field( (string) $slug ) ) );
		return preg_match( '/^[a-z0-9_-]+$/', $slug ) ? $slug : '';
	}

	private function landing_matches_current_request( array $landing, $current_url ) {
		$current_post_id = 0;
		if ( function_exists( 'get_queried_object_id' ) ) {
			$current_post_id = absint( get_queried_object_id() );
		}
		if ( ! $current_post_id && ! empty( $current_url ) ) {
			$current_post_id = absint( url_to_postid( $current_url ) );
		}

		$landing_post_id = ! empty( $landing['destination_post_id'] ) ? absint( $landing['destination_post_id'] ) : 0;
		if ( $landing_post_id && $current_post_id && $landing_post_id === $current_post_id ) {
			return true;
		}

		$destination = ! empty( $landing['destination_url'] ) ? $this->normalize_current_url( $landing['destination_url'] ) : '';
		$current     = $this->normalize_current_url( $current_url );

		if ( '' === $destination || '' === $current ) {
			return false;
		}

		return $destination === $current;
	}

	private function normalize_current_url( $url ) {
		$url = esc_url_raw( (string) $url );
		if ( '' === $url ) {
			return '';
		}

		if ( false === strpos( $url, '://' ) ) {
			$url = home_url( $url );
		}

		$parsed = wp_parse_url( $url );
		if ( empty( $parsed['host'] ) || empty( $parsed['path'] ) && empty( $parsed['query'] ) ) {
			return '';
		}

		$site_host = strtolower( wp_parse_url( home_url(), PHP_URL_HOST ) );
		$host      = strtolower( preg_replace( '/^www\./', '', $parsed['host'] ) );
		$site_host = preg_replace( '/^www\./', '', $site_host );
		if ( '' === $host || '' === $site_host || $host !== $site_host ) {
			return '';
		}

		$path = isset( $parsed['path'] ) ? '/' . ltrim( $parsed['path'], '/' ) : '/';
		$path = '/' === $path ? '/' : untrailingslashit( $path );

		$query = array();
		if ( ! empty( $parsed['query'] ) ) {
			parse_str( $parsed['query'], $query );
			foreach ( array( 'u', 'U', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'fbclid', 'gclid', 'yclid' ) as $tracking_key ) {
				unset( $query[ $tracking_key ] );
			}
			ksort( $query );
		}

		$normalized = $host . $path;
		if ( ! empty( $query ) ) {
			$normalized .= '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
		}

		return $normalized;
	}

	private function get_current_url() {
		$scheme = is_ssl() ? 'https://' : 'http://';
		$host   = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : wp_parse_url( home_url(), PHP_URL_HOST );
		$uri    = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';

		return $this->normalize_current_url( $scheme . $host . $uri );
	}

	private function get_http_referrer() {
		if ( empty( $_SERVER['HTTP_REFERER'] ) ) {
			return '';
		}

		return $this->normalize_internal_referrer( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
	}

	private function normalize_internal_referrer( $referrer ) {
		$referrer = esc_url_raw( (string) $referrer );
		if ( '' === $referrer ) {
			return '';
		}

		return wp_http_validate_url( $referrer ) ? $referrer : '';
	}

	private function build_click_hash( array $landing, $visitor_id, $ip_hash, $ua_hash ) {
		$payload = implode(
			'|',
			array(
				absint( $landing['id'] ),
				(string) $visitor_id,
				(string) $ip_hash,
				(string) $ua_hash,
				gmdate( 'YmdHi', current_time( 'timestamp', true ) ),
			)
		);

		return hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) );
	}

	private function current_ip_hash() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return $this->hash_value( $ip );
	}

	private function get_user_agent() {
		if ( empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return '';
		}

		$user_agent = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
		return function_exists( 'mb_substr' ) ? mb_substr( $user_agent, 0, 300 ) : substr( $user_agent, 0, 300 );
	}

	private function is_bot_user_agent( $user_agent ) {
		$ua = strtolower( (string) $user_agent );
		if ( '' === $ua ) {
			return true;
		}

		$patterns = array(
			'facebookexternalhit',
			'facebot',
			'telegrambot',
			'twitterbot',
			'linkedinbot',
			'slackbot',
			'discordbot',
			'googlebot',
			'bingbot',
			'crawler',
			'spider',
			'preview',
		);

		foreach ( $patterns as $pattern ) {
			if ( false !== strpos( $ua, $pattern ) ) {
				return true;
			}
		}

		return false !== strpos( $ua, 'bot' );
	}

	private function hash_value( $value ) {
		return hash_hmac( 'sha256', (string) $value, wp_salt( 'auth' ) );
	}

	private function get_tracking_token() {
		return hash_hmac( 'sha256', home_url( '/' ) . '|crpcrm_landing_tracking', wp_salt( 'auth' ) );
	}

	private function log_debug( $message, array $meta = array() ) {
		if ( class_exists( 'CRPCRM_Logger' ) && method_exists( 'CRPCRM_Logger', 'debug' ) ) {
			CRPCRM_Logger::debug( $message, 'landing_tracking', $meta );
		}
	}

	private function log_error( $message, array $meta = array() ) {
		if ( class_exists( 'CRPCRM_Logger' ) && method_exists( 'CRPCRM_Logger', 'error' ) ) {
			CRPCRM_Logger::error( $message, 'landing_tracking', $meta );
		}
	}
}

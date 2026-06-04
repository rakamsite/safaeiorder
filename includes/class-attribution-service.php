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

	private $attribution_repository;
	private $customer_repository;

	public function __construct( CRPCRM_Customer_Attribution_Repository $attribution_repository = null, CRPCRM_Customer_Repository $customer_repository = null ) {
		$this->attribution_repository = $attribution_repository ? $attribution_repository : new CRPCRM_Customer_Attribution_Repository();
		$this->customer_repository     = $customer_repository ? $customer_repository : new CRPCRM_Customer_Repository();
	}

	public function register_hooks() {
		add_action( 'init', array( $this, 'maybe_track_current_request' ), 5 );
	}

	public function maybe_track_current_request() {
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

		return $referrer_host === $site_host || preg_replace( '/^www\./', '', $referrer_host ) === preg_replace( '/^www\./', '', $site_host );
	}

	public function get_attribution_for_new_request() {
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
		$args = array(
			'expires'  => $expire,
			'path'     => '/',
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax',
		);

		if ( PHP_VERSION_ID >= 70300 ) {
			setcookie( $name, $value, $args );
			return;
		}

		setcookie( $name, $value, $expire, '/; samesite=Lax', '', is_ssl(), true );
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
}

<?php
/**
 * Secure request file access service.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_Request_File_Access_Service {
	const ACTION = 'crpcrm_request_file';
	const LINK_TTL = 3600;

	public function register_hooks() {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_request_file' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( $this, 'handle_unauthorized_request_file' ) );
	}

	public function handle_unauthorized_request_file() {
		wp_die( esc_html__( 'شما اجازه دسترسی به این فایل را ندارید.', 'customer-request-portal-crm' ), '', array( 'response' => 403 ) );
	}

	public function handle_request_file() {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'شما اجازه دسترسی به این فایل را ندارید.', 'customer-request-portal-crm' ), '', array( 'response' => 403 ) );
		}

		$reference = isset( $_GET['ref'] ) ? sanitize_text_field( wp_unslash( $_GET['ref'] ) ) : '';
		$signature = isset( $_GET['sig'] ) ? sanitize_text_field( wp_unslash( $_GET['sig'] ) ) : '';
		$mode      = isset( $_GET['mode'] ) ? sanitize_key( wp_unslash( $_GET['mode'] ) ) : 'download';
		$payload   = $this->verify_reference( $reference, $signature );

		if ( is_wp_error( $payload ) ) {
			$response = 'crpcrm_file_expired' === $payload->get_error_code() ? 410 : 400;
			wp_die( esc_html( $payload->get_error_message() ), '', array( 'response' => $response ) );
		}

		if ( empty( $payload ) ) {
			wp_die( esc_html__( 'اطلاعات فایل معتبر نیست.', 'customer-request-portal-crm' ), '', array( 'response' => 400 ) );
		}

		$file = $this->resolve_file_for_payload( $payload );
		if ( ! is_array( $file ) || empty( $file ) ) {
			wp_die( esc_html__( 'فایل موردنظر یافت نشد یا دسترسی به آن مجاز نیست.', 'customer-request-portal-crm' ), '', array( 'response' => 404 ) );
		}

		$path = CRPCRM_Dynamic_Form_Renderer::resolve_uploaded_file_real_path( $file );
		if ( '' === $path || ! CRPCRM_Request_File_Cleanup_Service::is_allowed_uploaded_path( $path ) || ! file_exists( $path ) || ! is_readable( $path ) ) {
			wp_die( esc_html__( 'فایل موردنظر یافت نشد.', 'customer-request-portal-crm' ), '', array( 'response' => 404 ) );
		}

		$this->stream_file( $path, $file, 'preview' === $mode ? 'preview' : 'download' );
	}

	public static function build_file_url( $file, $field = array(), $mode = 'download' ) {
		$payload = self::build_payload( $file, $field, $mode );
		if ( empty( $payload ) ) {
			return '';
		}

		$payload['exp'] = CRPCRM_Helpers::current_timestamp() + self::LINK_TTL;

		$reference = self::encode_reference( $payload );
		$signature = self::sign_reference( $reference );

		return add_query_arg(
			array(
				'action' => self::ACTION,
				'ref'    => $reference,
				'sig'    => $signature,
				'mode'   => 'preview' === $mode ? 'preview' : 'download',
			),
			admin_url( 'admin-post.php' )
		);
	}

	private static function build_payload( $file, $field, $mode ) {
		$file  = CRPCRM_Dynamic_Form_Renderer::normalize_uploaded_file_meta( $file );
		$field = is_array( $field ) ? $field : array();

		$source_type  = sanitize_key( $field['source_type'] ?? '' );
		$source_id    = absint( $field['source_id'] ?? 0 );
		$source_field = sanitize_key( $field['source_field'] ?? $file['field_key'] ?? '' );
		$file_index   = isset( $field['file_index'] ) ? absint( $field['file_index'] ) : 0;

		if ( ! $source_type && ! empty( $file['upload_token'] ) ) {
			$source_type = 'pending';
		}

		if ( 'pending' === $source_type ) {
			$token = sanitize_text_field( $file['upload_token'] ?? '' );
			if ( '' === $token ) {
				return array();
			}

			return array(
				'type'      => 'pending',
				'token'     => $token,
				'field_key' => $source_field,
				'mode'      => 'preview' === $mode ? 'preview' : 'download',
			);
		}

		if ( ! $source_type && ! empty( $file['request_id'] ) ) {
			$source_type = 'request';
			$source_id   = absint( $file['request_id'] );
		}

		if ( ! in_array( $source_type, array( 'request', 'staff_request' ), true ) || ! $source_id ) {
			return array();
		}

		$payload = array(
			'type'       => $source_type,
			'source_id'  => $source_id,
			'source_key' => $source_field,
			'file_index' => $file_index,
			'mode'       => 'preview' === $mode ? 'preview' : 'download',
		);

		if ( ! empty( $file['relative_path'] ) ) {
			$payload['relative_path'] = sanitize_text_field( $file['relative_path'] );
		}
		if ( ! empty( $file['filename'] ) ) {
			$payload['filename'] = sanitize_file_name( $file['filename'] );
		}
		if ( ! empty( $file['mime_type'] ) ) {
			$payload['mime_type'] = sanitize_text_field( $file['mime_type'] );
		}
		if ( ! empty( $file['stored_name'] ) ) {
			$payload['stored_name'] = sanitize_file_name( $file['stored_name'] );
		}
		if ( ! empty( $file['original_name'] ) ) {
			$payload['original_name'] = sanitize_file_name( $file['original_name'] );
		}
		if ( isset( $file['is_image'] ) ) {
			$payload['is_image'] = ! empty( $file['is_image'] ) ? 1 : 0;
		}
		if ( isset( $file['is_pdf'] ) ) {
			$payload['is_pdf'] = ! empty( $file['is_pdf'] ) ? 1 : 0;
		}

		return $payload;
	}

	private function resolve_file_for_payload( $payload ) {
		$type = sanitize_key( $payload['type'] ?? '' );

		if ( 'pending' === $type ) {
			return $this->resolve_pending_file( $payload );
		}

		if ( 'request' === $type ) {
			return $this->resolve_request_file( $payload );
		}

		if ( 'staff_request' === $type ) {
			return $this->resolve_staff_request_file( $payload );
		}

		return array();
	}

	private function resolve_pending_file( $payload ) {
		$token   = sanitize_text_field( $payload['token'] ?? '' );
		$pending = CRPCRM_Dynamic_Form_Renderer::get_pending_upload_meta( $token );

		if ( ! $pending || ! CRPCRM_Dynamic_Form_Renderer::pending_upload_belongs_to_actor( $pending, sanitize_key( $payload['field_key'] ?? '' ) ) ) {
			return array();
		}

		return CRPCRM_Dynamic_Form_Renderer::normalize_uploaded_file_meta( $pending );
	}

	private function resolve_request_file( $payload ) {
		$request_id = absint( $payload['source_id'] ?? 0 );
		if ( ! $request_id ) {
			return array();
		}

		$request_repository = new CRPCRM_Request_Repository();
		$request            = $request_repository->get( $request_id );

		if ( ! $request || ! $this->current_user_can_view_request_file( $request ) ) {
			return array();
		}

		$file = $this->find_file_in_request_data(
			$request['request_data'] ?? array(),
			sanitize_key( $payload['source_key'] ?? '' ),
			absint( $payload['file_index'] ?? 0 )
		);

		if ( ! empty( $file ) && '' !== CRPCRM_Dynamic_Form_Renderer::resolve_uploaded_file_real_path( $file ) ) {
			return $file;
		}

		return $this->build_file_from_payload_meta( $payload );
	}

	private function resolve_staff_request_file( $payload ) {
		$request_id = absint( $payload['source_id'] ?? 0 );
		if ( ! $request_id ) {
			return array();
		}

		$repository = new CRPCRM_Staff_Repository();
		$request    = $repository->get_staff_request( $request_id );

		if ( ! $request || ! $this->current_user_can_view_staff_request_file( $request ) ) {
			return array();
		}

		$field = sanitize_key( $payload['source_key'] ?? '' );
		if ( ! in_array( $field, array( 'request_attachment', 'manager_response_attachment' ), true ) ) {
			return array();
		}

		$file = $this->find_file_in_value( $request[ $field ] ?? '', absint( $payload['file_index'] ?? 0 ) );

		if ( ! empty( $file ) && '' !== CRPCRM_Dynamic_Form_Renderer::resolve_uploaded_file_real_path( $file ) ) {
			return $file;
		}

		return $this->build_file_from_payload_meta( $payload );
	}

	private function current_user_can_view_request_file( $request ) {
		$user_id = get_current_user_id();

		if ( CRPCRM_Request_Access_Service::can_view_request( $request, $user_id ) ) {
			return true;
		}

		$customer_id = absint( $request['customer_id'] ?? 0 );
		$request_uid = absint( $request['user_id'] ?? 0 );

		if ( $request_uid && $request_uid === $user_id ) {
			return true;
		}

		if ( $customer_id ) {
			$customer_repository = new CRPCRM_Customer_Repository();
			$customer            = $customer_repository->find_by_user_id( $user_id );
			if ( $customer && absint( $customer['id'] ?? 0 ) === $customer_id ) {
				return true;
			}
		}

		return false;
	}

	private function current_user_can_view_staff_request_file( $request ) {
		$user_id = get_current_user_id();

		if ( current_user_can( 'manage_options' ) || current_user_can( 'crpcrm_manage_staff_portal' ) ) {
			return true;
		}

		return current_user_can( 'crpcrm_use_staff_portal' ) && absint( $request['user_id'] ?? 0 ) === $user_id;
	}

	private function find_file_in_request_data( $request_data, $field_key, $file_index ) {
		$request_data = is_array( $request_data ) ? $request_data : CRPCRM_Helpers::maybe_json_decode( $request_data, true );
		if ( ! is_array( $request_data ) ) {
			return array();
		}

		if ( $field_key && isset( $request_data[ $field_key ] ) ) {
			return $this->find_file_in_value( $request_data[ $field_key ], $file_index );
		}

		foreach ( $request_data as $value ) {
			$file = $this->find_file_in_value( $value, $file_index, false );
			if ( ! empty( $file ) ) {
				return $file;
			}
		}

		return array();
	}

	private function find_file_in_value( $value, $file_index, $strict_index = true ) {
		$files = CRPCRM_Dynamic_Form_Renderer::get_uploaded_file_payload( $value );
		if ( empty( $files ) ) {
			return array();
		}

		if ( isset( $files[ $file_index ] ) ) {
			return CRPCRM_Dynamic_Form_Renderer::normalize_uploaded_file_meta( $files[ $file_index ] );
		}

		if ( ! $strict_index && isset( $files[0] ) ) {
			return CRPCRM_Dynamic_Form_Renderer::normalize_uploaded_file_meta( $files[0] );
		}

		return array();
	}

	private function build_file_from_payload_meta( $payload ) {
		$relative_path = sanitize_text_field( $payload['relative_path'] ?? '' );
		if ( '' === $relative_path ) {
			return array();
		}

		$file = array(
			'relative_path' => $relative_path,
			'filename'      => sanitize_file_name( $payload['filename'] ?? '' ),
			'original_name' => sanitize_file_name( $payload['original_name'] ?? '' ),
			'stored_name'   => sanitize_file_name( $payload['stored_name'] ?? '' ),
			'mime_type'     => sanitize_text_field( $payload['mime_type'] ?? '' ),
			'is_image'      => ! empty( $payload['is_image'] ) ? 1 : 0,
			'is_pdf'        => ! empty( $payload['is_pdf'] ) ? 1 : 0,
		);

		return CRPCRM_Dynamic_Form_Renderer::normalize_uploaded_file_meta( $file );
	}

	private function stream_file( $path, $file, $mode ) {
		$normalized = CRPCRM_Dynamic_Form_Renderer::normalize_uploaded_file_meta( $file );
		$mime_type  = sanitize_text_field( $normalized['mime_type'] ?? '' );
		$filename   = sanitize_file_name( $normalized['filename'] ?? basename( $path ) );

		if ( '' === $mime_type && function_exists( 'wp_check_filetype' ) ) {
			$mime_type = wp_check_filetype( $filename )['type'] ?? 'application/octet-stream';
		}
		if ( '' === $mime_type ) {
			$mime_type = 'application/octet-stream';
		}

		while ( ob_get_level() ) {
			ob_end_clean();
		}

		nocache_headers();
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Type: ' . $mime_type );
		header( 'Content-Length: ' . (string) filesize( $path ) );
		header( 'Accept-Ranges: bytes' );

		if ( 'download' === $mode ) {
			header( 'Content-Disposition: attachment; filename="' . str_replace( '"', '', $filename ) . '"; filename*=UTF-8\'\'' . rawurlencode( $filename ) );
		} else {
			header( 'Cache-Control: private, max-age=300' );
		}

		readfile( $path );
		exit;
	}

	private function verify_reference( $reference, $signature ) {
		$reference = is_string( $reference ) ? trim( $reference ) : '';
		$signature = is_string( $signature ) ? trim( $signature ) : '';

		if ( '' === $reference || '' === $signature ) {
			return array();
		}

		if ( ! hash_equals( self::sign_reference( $reference ), $signature ) ) {
			return array();
		}

		$decoded = self::decode_reference( $reference );
		if ( ! is_array( $decoded ) ) {
			return array();
		}

		$expires_at = absint( $decoded['exp'] ?? 0 );
		if ( $expires_at && $expires_at < CRPCRM_Helpers::current_timestamp() ) {
			return new WP_Error( 'crpcrm_file_expired', __( 'مهلت دسترسی به این فایل به پایان رسیده است. لطفاً دوباره از داخل پنل فایل را باز کنید.', 'customer-request-portal-crm' ) );
		}

		return $decoded;
	}

	private static function encode_reference( $payload ) {
		$json = wp_json_encode( $payload );
		if ( ! is_string( $json ) || '' === $json ) {
			return '';
		}

		return rtrim( strtr( base64_encode( $json ), '+/', '-_' ), '=' );
	}

	private static function decode_reference( $reference ) {
		$reference = strtr( $reference, '-_', '+/' );
		$padding   = strlen( $reference ) % 4;
		if ( $padding ) {
			$reference .= str_repeat( '=', 4 - $padding );
		}

		$json = base64_decode( $reference, true );
		if ( false === $json || '' === $json ) {
			return array();
		}

		$decoded = json_decode( $json, true );
		return is_array( $decoded ) ? $decoded : array();
	}

	private static function sign_reference( $reference ) {
		return hash_hmac( 'sha256', (string) $reference, wp_salt( 'auth' ) );
	}
}

<?php
/**
 * Safe cleanup service for protected request uploads.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_Request_File_Cleanup_Service {
	public function cleanup_request_files( $request ) {
		$request = is_array( $request ) ? $request : array();
		$files   = $this->extract_request_files( $request );

		return $this->cleanup_files( $files );
	}

	public function cleanup_files( array $files ) {
		$result = array(
			'deleted' => 0,
			'missing' => 0,
			'failed'  => 0,
		);

		foreach ( $files as $file ) {
			$status = $this->delete_file( $file );
			if ( isset( $result[ $status ] ) ) {
				++$result[ $status ];
			}
		}

		return $result;
	}

	public function cleanup_request_rows( array $requests ) {
		$summary = array(
			'deleted' => 0,
			'missing' => 0,
			'failed'  => 0,
		);

		foreach ( $requests as $request ) {
			$result = $this->cleanup_request_files( $request );
			foreach ( $summary as $key => $count ) {
				$summary[ $key ] += absint( $result[ $key ] ?? 0 );
			}
		}

		return $summary;
	}

	public function delete_protected_upload_directories() {
		$root = realpath( CRPCRM_Dynamic_Form_Renderer::get_protected_upload_root_dir() );
		if ( $root ) {
			$this->delete_directory_tree( $root, $root );
		}
	}

	private function extract_request_files( $request ) {
		$request_data = array();

		if ( isset( $request['request_data'] ) ) {
			$request_data = is_string( $request['request_data'] ) ? CRPCRM_Helpers::maybe_json_decode( $request['request_data'], true ) : $request['request_data'];
		}

		$request_data = is_array( $request_data ) ? $request_data : array();

		if ( ! empty( $request ) ) {
			$request_data = CRPCRM_Request_Repository::get_merged_request_data( $request );
		}

		$files = array();
		$this->collect_files_recursive( $request_data, $files );

		return $files;
	}

	private function collect_files_recursive( $value, array &$files ) {
		if ( ! is_array( $value ) ) {
			return;
		}

		if ( $this->looks_like_file_payload( $value ) ) {
			foreach ( CRPCRM_Dynamic_Form_Renderer::get_uploaded_file_payload( $value ) as $file ) {
				if ( is_array( $file ) ) {
					$files[] = $file;
				}
			}
			return;
		}

		foreach ( $value as $item ) {
			$this->collect_files_recursive( $item, $files );
		}
	}

	private function looks_like_file_payload( $value ) {
		if ( ! is_array( $value ) || empty( $value ) ) {
			return false;
		}

		if ( $this->looks_like_file_meta( $value ) ) {
			return true;
		}

		foreach ( $value as $item ) {
			if ( is_array( $item ) && $this->looks_like_file_meta( $item ) ) {
				return true;
			}
		}

		return false;
	}

	private function looks_like_file_meta( $value ) {
		if ( ! is_array( $value ) ) {
			return false;
		}

		foreach ( array( 'relative_path', 'attachment_id', 'download_url', 'original_name', 'stored_name', 'upload_token' ) as $key ) {
			if ( ! empty( $value[ $key ] ) ) {
				return true;
			}
		}

		return false;
	}

	private function delete_file( $file ) {
		$file = is_array( $file ) ? $file : array();
		$path = CRPCRM_Dynamic_Form_Renderer::resolve_uploaded_file_real_path( $file );

		if ( ! $path ) {
			return 'missing';
		}

		return $this->delete_file_by_path( $path, sanitize_text_field( $file['relative_path'] ?? '' ) );
	}

	public static function delete_uploaded_file_path( $path, $relative_path = '' ) {
		$service = new self();
		return $service->delete_file_by_path( $path, $relative_path );
	}

	public static function is_allowed_uploaded_path( $path ) {
		$service = new self();

		return '' !== $service->resolve_allowed_root( $path );
	}

	private function delete_file_by_path( $path, $relative_path = '' ) {
		$path = $this->normalize_real_path( $path );
		if ( ! $path ) {
			return 'missing';
		}

		$root = $this->resolve_allowed_root( $path );
		if ( ! $root ) {
			$this->debug_log( 'request_file_cleanup_outside_root', array( 'relative_path' => sanitize_text_field( $relative_path ) ) );
			return 'failed';
		}

		if ( ! file_exists( $path ) ) {
			return 'missing';
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		wp_delete_file( $path );
		if ( file_exists( $path ) ) {
			@unlink( $path );
		}

		if ( file_exists( $path ) ) {
			$this->debug_log( 'request_file_cleanup_delete_failed', array( 'relative_path' => sanitize_text_field( $relative_path ) ) );
			return 'failed';
		}

		$this->remove_empty_directories( dirname( $path ), $root );

		return 'deleted';
	}

	private function get_allowed_roots() {
		$uploads = wp_get_upload_dir();
		$roots   = array(
			$this->normalize_root_path( CRPCRM_Dynamic_Form_Renderer::get_protected_upload_root_dir() ),
			$this->normalize_root_path( trailingslashit( $uploads['basedir'] ) . 'crpcrm-request-files' ),
		);

		return array_values( array_filter( array_unique( $roots ) ) );
	}

	private function resolve_allowed_root( $path ) {
		$path = $this->normalize_real_path( $path );
		if ( ! $path ) {
			return '';
		}

		foreach ( $this->get_allowed_roots() as $root ) {
			if ( $root && 0 === strpos( $path, $root ) ) {
				return $root;
			}
		}

		return '';
	}

	private function remove_empty_directories( $dir, $root ) {
		$dir  = $this->normalize_real_path( $dir );
		$root = $this->normalize_root_path( $root );

		while ( $dir && $root && $dir !== untrailingslashit( $root ) && 0 === strpos( trailingslashit( $dir ), $root ) ) {
			$items = array_diff( scandir( $dir ), array( '.', '..' ) );
			if ( ! empty( $items ) ) {
				break;
			}

			@rmdir( $dir );
			$dir = realpath( dirname( $dir ) );
		}
	}

	private function delete_directory_tree( $path, $root ) {
		$path = $this->normalize_real_path( $path );
		$root = $this->normalize_root_path( $root );

		if ( ! $path || ! $root || 0 !== strpos( trailingslashit( $path ), $root ) || ! is_dir( $path ) ) {
			return;
		}

		$items = scandir( $path );
		if ( false === $items ) {
			return;
		}

		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}

			$child = $path . DIRECTORY_SEPARATOR . $item;
			if ( is_dir( $child ) ) {
				$this->delete_directory_tree( $child, $root );
				continue;
			}

			@unlink( $child );
		}

		@rmdir( $path );
	}

	private function normalize_root_path( $path ) {
		$path = $this->normalize_real_path( $path );

		return $path ? trailingslashit( $path ) : '';
	}

	private function normalize_real_path( $path ) {
		$real = is_string( $path ) && '' !== $path ? realpath( $path ) : false;

		return $real ? wp_normalize_path( $real ) : '';
	}

	private function debug_log( $event, array $context ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			CRPCRM_Logger::warning( $event, 'request_file_cleanup', $context );
		}
	}
}

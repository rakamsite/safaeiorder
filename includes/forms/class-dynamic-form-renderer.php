<?php
/**
 * Shared rendering, validation, and display helpers for dynamic request forms.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_Dynamic_Form_Renderer {
	const ALLOWED_TYPES = array( 'text', 'textarea', 'select', 'product_search', 'file_upload', 'display_html' );
	const ALLOWED_WIDTHS = array( '25', '33', '50', '100' );
	const MAX_FILE_SIZE = 10485760;

	public static function render_fields( $form, $context = 'public' ) {
		$form    = is_array( $form ) ? $form : array();
		$context = 'admin' === $context ? 'admin' : 'public';

		foreach ( self::get_enabled_fields( $form ) as $field ) {
			self::render_field( $field, sanitize_key( $form['id'] ?? $form['form_id'] ?? 'form' ), $context );
		}
	}

	public static function sanitize_and_validate( $form, $posted, $files = array() ) {
		$posted = is_array( $posted ) ? $posted : array();
		$files  = is_array( $files ) ? $files : array();
		$data   = array();
		$errors = array();

		foreach ( self::get_enabled_fields( $form ) as $field ) {
			$name = sanitize_key( $field['name'] ?? $field['key'] ?? '' );
			$type = self::normalize_type( $field['type'] ?? 'text' );

			if ( 'display_html' === $type ) {
				$content = trim( wp_strip_all_tags( $field['content'] ?? '' ) );
				if ( '' !== $content ) {
					$data[ $name ] = $content;
				}
				continue;
			}

			if ( 'product_search' === $type ) {
				$product_ids = self::sanitize_product_ids( $posted[ $name ] ?? '' );
				if ( ! empty( $field['required'] ) && empty( $product_ids ) ) {
					$errors[] = self::field_error( $field, 'این فیلد الزامی است.' );
					continue;
				}
				if ( ! empty( $product_ids ) ) {
					$products = self::get_selected_products( $product_ids );
					if ( count( $products ) !== count( $product_ids ) ) {
						$errors[] = self::field_error( $field, 'یک یا چند محصول انتخاب‌شده معتبر نیست.');
						continue;
					}
					$data[ $name ] = $products;
				} else {
					$data[ $name ] = array();
				}
				continue;
			}

			if ( 'file_upload' === $type ) {
				$uploaded_files = self::parse_uploaded_files_payload( $posted[ $name . '__uploaded' ] ?? '' );
				if ( is_wp_error( $uploaded_files ) ) {
					$errors[] = self::field_error( $field, $uploaded_files->get_error_message() );
					continue;
				}
				$new_uploaded_files = self::handle_uploaded_files( $files[ $name ] ?? null );
				if ( is_wp_error( $new_uploaded_files ) ) {
					$errors[] = self::field_error( $field, $new_uploaded_files->get_error_message() );
					continue;
				}
				$uploaded_files = array_merge( $uploaded_files, $new_uploaded_files );
				if ( ! empty( $field['required'] ) && empty( $uploaded_files ) ) {
					$errors[] = self::field_error( $field, 'این فیلد الزامی است.' );
					continue;
				}
				$data[ $name ] = $uploaded_files;
				continue;
			}

			$value = isset( $posted[ $name ] ) ? wp_unslash( $posted[ $name ] ) : '';
			$value = self::sanitize_value( $type, $value );

			if ( ! empty( $field['required'] ) && '' === $value ) {
				$errors[] = self::field_error( $field, 'این فیلد الزامی است.' );
				continue;
			}

			if ( 'select' === $type && '' !== $value && ! array_key_exists( $value, self::get_field_options( $field ) ) ) {
				$errors[] = self::field_error( $field, 'مقدار واردشده معتبر نیست.' );
				continue;
			}

			$data[ $name ] = $value;
		}

		return array(
			'data'   => $data,
			'errors' => array_values( array_unique( $errors ) ),
		);
	}

	public static function get_field_options( $field ) {
		$options = isset( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : array();
		$clean   = array();

		foreach ( $options as $value => $label ) {
			if ( is_int( $value ) ) {
				$value = $label;
			}
			$value = sanitize_text_field( $value );
			$label = sanitize_text_field( $label );
			if ( '' !== $value ) {
				$clean[ $value ] = $label;
			}
		}

		return $clean;
	}

	public static function get_field_labels( $form ) {
		$labels = array();
		foreach ( self::get_enabled_fields( $form ) as $field ) {
			$name = sanitize_key( $field['name'] ?? $field['key'] ?? '' );
			if ( $name ) {
				$labels[ $name ] = sanitize_text_field( $field['label'] ?? $name );
			}
		}
		return $labels;
	}

	public static function get_submission_snapshot( $form ) {
		$form         = is_array( $form ) ? $form : array();
		$form_id      = sanitize_key( $form['id'] ?? $form['form_id'] ?? '' );
		$form_version = sanitize_text_field( $form['version'] ?? '1' );
		$field_schema = array();

		foreach ( self::get_enabled_fields( $form ) as $field ) {
			$name = sanitize_key( $field['name'] ?? $field['key'] ?? '' );
			if ( ! $name ) {
				continue;
			}
			$field_schema[ $name ] = array(
				'label' => sanitize_text_field( $field['label'] ?? $name ),
				'type'  => self::normalize_type( $field['type'] ?? 'text' ),
			);
		}

		return array(
			'_submitted_form_title'   => sanitize_text_field( $form['title'] ?? '' ),
			'_submitted_form_id'      => $form_id,
			'_submitted_form_version' => $form_version ? $form_version : '1',
			'_submitted_field_labels' => self::get_field_labels( $form ),
			'_submitted_field_schema' => $field_schema,
		);
	}

	public static function get_display_items( $form, $request_data ) {
		$request_data = is_array( $request_data ) ? $request_data : array();
		$schema       = isset( $request_data['_submitted_field_schema'] ) && is_array( $request_data['_submitted_field_schema'] ) ? $request_data['_submitted_field_schema'] : array();
		$labels       = $form ? self::get_field_labels( $form ) : array();
		$snapshot     = isset( $request_data['_submitted_field_labels'] ) && is_array( $request_data['_submitted_field_labels'] ) ? $request_data['_submitted_field_labels'] : array();
		$items        = array();

		if ( empty( $schema ) && $form ) {
			foreach ( self::get_enabled_fields( $form ) as $field ) {
				$name = sanitize_key( $field['name'] ?? $field['key'] ?? '' );
				if ( $name ) {
					$schema[ $name ] = array(
						'label' => sanitize_text_field( $field['label'] ?? $name ),
						'type'  => self::normalize_type( $field['type'] ?? 'text' ),
					);
				}
			}
		}

		foreach ( $schema as $key => $meta ) {
			$key = sanitize_key( $key );
			if ( ! array_key_exists( $key, $request_data ) ) {
				continue;
			}
			$items[ $key ] = array(
				'label' => sanitize_text_field( $meta['label'] ?? $key ),
				'value' => self::display_value( $request_data[ $key ], $meta['type'] ?? 'text' ),
			);
		}

		foreach ( $labels as $key => $label ) {
			if ( array_key_exists( $key, $request_data ) && ! isset( $items[ $key ] ) ) {
				$items[ $key ] = array( 'label' => $label, 'value' => self::display_value( $request_data[ $key ] ) );
			}
		}

		foreach ( $request_data as $key => $value ) {
			$key = sanitize_key( $key );
			if ( ! $key || isset( $items[ $key ] ) || 0 === strpos( $key, '_submitted_' ) || in_array( $key, array( 'form_id', 'form_version', 'request_type', 'fields', 'submitted_fields' ), true ) ) {
				continue;
			}
			if ( is_array( $value ) && empty( $value ) ) {
				continue;
			}
			$label = isset( $snapshot[ $key ] ) ? sanitize_text_field( $snapshot[ $key ] ) : $key;
			$items[ $key ] = array( 'label' => $label, 'value' => self::display_value( $value ) );
		}

		return $items;
	}

	public static function get_enabled_fields( $form ) {
		$fields = array();
		foreach ( (array) ( $form['fields'] ?? array() ) as $field ) {
			if ( ! is_array( $field ) || ( isset( $field['enabled'] ) && ! $field['enabled'] ) ) {
				continue;
			}
			$field['type']  = self::normalize_type( $field['type'] ?? 'text' );
			$field['width'] = self::normalize_width( $field['width'] ?? '100' );
			$fields[]       = $field;
		}
		usort( $fields, function ( $a, $b ) {
			return absint( $a['sort_order'] ?? 0 ) <=> absint( $b['sort_order'] ?? 0 );
		} );
		return $fields;
	}

	private static function render_field( $field, $form_id, $context ) {
		$type        = self::normalize_type( $field['type'] ?? 'text' );
		$name        = sanitize_key( $field['name'] ?? $field['key'] ?? '' );
		$label       = sanitize_text_field( $field['label'] ?? $name );
		$field_id    = 'crpcrm-field-' . $form_id . '-' . $name;
		$required    = ! empty( $field['required'] );
		$placeholder = sanitize_text_field( $field['placeholder'] ?? '' );
		$help        = sanitize_textarea_field( $field['help'] ?? $field['help_text'] ?? '' );
		$default     = self::sanitize_value( $type, $field['default'] ?? '' );
		$width_class = 'crpcrm-width-' . self::normalize_width( $field['width'] ?? '100' );
		$wrapper     = 'crpcrm-field crpcrm-field-' . sanitize_html_class( $type ) . ' ' . $width_class;
		$control_class = 'admin' === $context ? '' : ' class="crpcrm-' . esc_attr( $type ) . '"';

		if ( 'display_html' === $type ) {
			echo '<div class="' . esc_attr( $wrapper . ' crpcrm-display-field' ) . '">';
			if ( $label ) {
				echo '<div class="crpcrm-field-label">' . esc_html( $label ) . '</div>';
			}
			echo '<div class="crpcrm-display-content">' . wp_kses_post( $field['content'] ?? '' ) . '</div>';
			if ( $help ) {
				echo '<div class="crpcrm-help-text">' . esc_html( $help ) . '</div>';
			}
			echo '</div>';
			return;
		}

		echo '<div class="' . esc_attr( $wrapper ) . '"><label class="crpcrm-field-label" for="' . esc_attr( $field_id ) . '">' . esc_html( $label ) . '</label>';
		if ( 'textarea' === $type ) {
			echo '<textarea' . $control_class . ' id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $name ) . '" placeholder="' . esc_attr( $placeholder ) . '"' . ( $required ? ' required' : '' ) . '>' . esc_textarea( $default ) . '</textarea>';
		} elseif ( 'select' === $type ) {
			echo '<select' . $control_class . ' id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $name ) . '"' . ( $required ? ' required' : '' ) . '><option value="">' . esc_html( 'انتخاب کنید' ) . '</option>';
			foreach ( self::get_field_options( $field ) as $value => $option_label ) {
				echo '<option value="' . esc_attr( $value ) . '"' . selected( $default, $value, false ) . '>' . esc_html( $option_label ) . '</option>';
			}
			echo '</select>';
		} elseif ( 'product_search' === $type ) {
			echo '<div class="crpcrm-product-search" data-field-name="' . esc_attr( $name ) . '">';
			echo '<input type="hidden" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $name ) . '" value="" class="crpcrm-product-search-value">';
			echo '<input type="search" class="crpcrm-input crpcrm-product-search-input" placeholder="' . esc_attr( $placeholder ? $placeholder : 'نام محصول را تایپ کنید' ) . '"' . ( $required ? ' data-required="1"' : '' ) . ' autocomplete="off">';
			echo '<div class="crpcrm-product-search-results" hidden></div>';
			echo '<div class="crpcrm-product-search-selected"></div>';
			echo '</div>';
		} elseif ( 'file_upload' === $type ) {
			$accept = '.jpg,.jpeg,.png,.webp,.gif,.pdf';
			echo '<div class="crpcrm-file-upload" data-field-name="' . esc_attr( $name ) . '">';
			echo '<input type="hidden" class="crpcrm-uploaded-files-store" name="' . esc_attr( $name . '__uploaded' ) . '" value="">';
			echo '<div class="crpcrm-file-upload-list">';
			echo self::render_file_input_row( $name, $accept, $required );
			echo '</div>';
			echo '<button type="button" class="crpcrm-file-upload-add">+</button>';
			echo '</div>';
		} else {
			$input_class = 'admin' === $context ? '' : ' class="crpcrm-input"';
			echo '<input' . $input_class . ' id="' . esc_attr( $field_id ) . '" type="text" name="' . esc_attr( $name ) . '" value="' . esc_attr( $default ) . '" placeholder="' . esc_attr( $placeholder ) . '"' . ( $required ? ' required' : '' ) . '>';
		}
		if ( $help ) {
			echo '<div class="crpcrm-help-text">' . esc_html( $help ) . '</div>';
		}
		echo '</div>';
	}

	private static function render_file_input_row( $name, $accept, $required ) {
		return '<div class="crpcrm-file-upload-row"><input type="file" name="' . esc_attr( $name ) . '[]" accept="' . esc_attr( $accept ) . '"' . ( $required ? ' data-required="1"' : '' ) . '><button type="button" class="crpcrm-file-upload-remove" aria-label="' . esc_attr__( 'حذف', 'customer-request-portal-crm' ) . '">&times;</button></div>';
	}

	private static function normalize_type( $type ) {
		$type = sanitize_key( $type );
		return in_array( $type, self::ALLOWED_TYPES, true ) ? $type : 'text';
	}

	private static function normalize_width( $width ) {
		$width = (string) $width;
		return in_array( $width, self::ALLOWED_WIDTHS, true ) ? $width : '100';
	}

	private static function sanitize_value( $type, $value ) {
		if ( is_array( $value ) || is_object( $value ) ) {
			return '';
		}
		return 'textarea' === $type ? trim( sanitize_textarea_field( $value ) ) : trim( sanitize_text_field( $value ) );
	}

	private static function field_error( $field, $fallback ) {
		return ! empty( $field['required_message'] ) ? sanitize_text_field( $field['required_message'] ) : $fallback;
	}

	private static function display_value( $value, $type = 'text' ) {
		if ( 'product_search' === $type && is_array( $value ) ) {
			$names = array();
			foreach ( $value as $product ) {
				if ( ! empty( $product['name'] ) ) {
					$names[] = sanitize_text_field( $product['name'] );
				}
			}
			return implode( '، ', $names );
		}

		if ( 'file_upload' === $type && is_array( $value ) ) {
			$names = array();
			foreach ( $value as $file ) {
				if ( ! empty( $file['name'] ) ) {
					$names[] = sanitize_text_field( $file['name'] );
				}
			}
			return implode( '، ', $names );
		}

		if ( is_array( $value ) ) {
			$flattened = array();
			foreach ( $value as $item ) {
				if ( is_scalar( $item ) ) {
					$flattened[] = (string) $item;
				}
			}
			return implode( '، ', $flattened );
		}

		return is_scalar( $value ) ? (string) $value : '';
	}

	private static function sanitize_product_ids( $value ) {
		$value = is_array( $value ) ? implode( ',', $value ) : (string) $value;
		$parts = preg_split( '/[^0-9]+/', $value );
		$parts = array_map( 'absint', $parts );
		$parts = array_values( array_unique( array_filter( $parts ) ) );
		return $parts;
	}

	private static function get_selected_products( $product_ids ) {
		$products = array();
		if ( empty( $product_ids ) || ! post_type_exists( 'product' ) ) {
			return $products;
		}

		foreach ( $product_ids as $product_id ) {
			$product_post = get_post( $product_id );
			if ( ! $product_post || 'product' !== $product_post->post_type || 'publish' !== $product_post->post_status ) {
				continue;
			}
			$products[] = array(
				'id'        => absint( $product_id ),
				'name'      => sanitize_text_field( get_the_title( $product_id ) ),
				'thumbnail' => get_the_post_thumbnail_url( $product_id, 'thumbnail' ) ? esc_url_raw( get_the_post_thumbnail_url( $product_id, 'thumbnail' ) ) : '',
			);
		}

		return $products;
	}

	private static function handle_uploaded_files( $file_group ) {
		if ( empty( $file_group ) || empty( $file_group['name'] ) ) {
			return array();
		}

		$normalized = self::normalize_uploaded_file_group( $file_group );
		$uploaded   = array();

		foreach ( $normalized as $file ) {
			if ( empty( $file['name'] ) || UPLOAD_ERR_NO_FILE === (int) $file['error'] ) {
				continue;
			}
			if ( (int) $file['error'] !== UPLOAD_ERR_OK ) {
				return new WP_Error( 'crpcrm_upload_error', 'بارگذاری فایل انجام نشد.' );
			}
			if ( empty( $file['size'] ) || (int) $file['size'] > self::MAX_FILE_SIZE ) {
				return new WP_Error( 'crpcrm_upload_size', 'هر فایل باید حداکثر 10 مگابایت باشد.' );
			}

			$stored = self::store_uploaded_file( $file );
			if ( is_wp_error( $stored ) ) {
				return $stored;
			}
			$uploaded[] = $stored;
		}

		return $uploaded;
	}

	private static function normalize_uploaded_file_group( $file_group ) {
		if ( ! is_array( $file_group['name'] ) ) {
			return array( $file_group );
		}

		$normalized = array();
		foreach ( $file_group['name'] as $index => $name ) {
			$normalized[] = array(
				'name'     => $name,
				'type'     => $file_group['type'][ $index ] ?? '',
				'tmp_name' => $file_group['tmp_name'][ $index ] ?? '',
				'error'    => $file_group['error'][ $index ] ?? UPLOAD_ERR_NO_FILE,
				'size'     => $file_group['size'][ $index ] ?? 0,
			);
		}
		return $normalized;
	}

	private static function store_uploaded_file( $file ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';

		add_filter( 'upload_dir', array( __CLASS__, 'filter_upload_dir' ) );
		$result = wp_handle_upload(
			$file,
			array(
				'test_form' => false,
				'mimes'     => self::get_allowed_upload_mimes(),
			)
		);
		remove_filter( 'upload_dir', array( __CLASS__, 'filter_upload_dir' ) );

		if ( ! empty( $result['error'] ) ) {
			return new WP_Error( 'crpcrm_upload_failed', sanitize_text_field( $result['error'] ) );
		}

		$base = wp_upload_dir();
		$path = str_replace( trailingslashit( $base['basedir'] ), '', $result['file'] );

		return array(
			'name'          => sanitize_file_name( wp_basename( $result['file'] ) ),
			'url'           => esc_url_raw( $result['url'] ),
			'relative_path' => sanitize_text_field( $path ),
			'type'          => sanitize_text_field( $result['type'] ?? '' ),
			'size'          => file_exists( $result['file'] ) ? absint( filesize( $result['file'] ) ) : 0,
		);
	}

	public static function filter_upload_dir( $uploads ) {
		$year  = current_time( 'Y' );
		$month = current_time( 'm' );
		$subdir = '/crpcrm-request-files/' . $year . '/' . $month;
		$uploads['subdir'] = $subdir;
		$uploads['path']   = $uploads['basedir'] . $subdir;
		$uploads['url']    = $uploads['baseurl'] . $subdir;
		return $uploads;
	}

	private static function get_allowed_upload_mimes() {
		return array(
			'jpg|jpeg|jpe' => 'image/jpeg',
			'png'          => 'image/png',
			'gif'          => 'image/gif',
			'webp'         => 'image/webp',
			'pdf'          => 'application/pdf',
		);
	}

	public static function handle_async_upload( $file ) {
		if ( empty( $file ) || ! is_array( $file ) ) {
			return new WP_Error( 'crpcrm_upload_missing', 'ÙØ§ÛŒÙ„ÛŒ Ø¨Ø±Ø§ÛŒ Ø¨Ø§Ø±Ú¯Ø°Ø§Ø±ÛŒ Ø§Ø±Ø³Ø§Ù„ Ù†Ø´Ø¯Ù‡ Ø§Ø³Øª.' );
		}

		$uploaded = self::handle_uploaded_files( $file );
		if ( is_wp_error( $uploaded ) ) {
			return $uploaded;
		}

		if ( empty( $uploaded ) ) {
			return new WP_Error( 'crpcrm_upload_missing', 'ÙØ§ÛŒÙ„ÛŒ Ø¨Ø±Ø§ÛŒ Ø¨Ø§Ø±Ú¯Ø°Ø§Ø±ÛŒ Ø§Ø±Ø³Ø§Ù„ Ù†Ø´Ø¯Ù‡ Ø§Ø³Øª.' );
		}

		return $uploaded[0];
	}

	private static function parse_uploaded_files_payload( $payload ) {
		if ( empty( $payload ) ) {
			return array();
		}

		if ( is_string( $payload ) ) {
			$decoded = json_decode( wp_unslash( $payload ), true );
		} elseif ( is_array( $payload ) ) {
			$decoded = $payload;
		} else {
			$decoded = array();
		}

		if ( ! is_array( $decoded ) ) {
			return new WP_Error( 'crpcrm_upload_invalid', 'Ø§Ø·Ù„Ø§Ø¹Ø§Øª ÙØ§ÛŒÙ„ Ø§Ø±Ø³Ø§Ù„ÛŒ Ù…Ø¹ØªØ¨Ø± Ù†ÛŒØ³Øª.' );
		}

		$files = array();
		foreach ( $decoded as $file ) {
			if ( ! is_array( $file ) || ! self::is_valid_uploaded_file_meta( $file ) ) {
				return new WP_Error( 'crpcrm_upload_invalid', 'Ø§Ø·Ù„Ø§Ø¹Ø§Øª ÙØ§ÛŒÙ„ Ø§Ø±Ø³Ø§Ù„ÛŒ Ù…Ø¹ØªØ¨Ø± Ù†ÛŒØ³Øª.' );
			}

			$files[] = array(
				'name'          => sanitize_file_name( $file['name'] ),
				'url'           => esc_url_raw( $file['url'] ),
				'relative_path' => sanitize_text_field( $file['relative_path'] ),
				'type'          => sanitize_text_field( $file['type'] ?? '' ),
				'size'          => absint( $file['size'] ?? 0 ),
			);
		}

		return $files;
	}

	private static function is_valid_uploaded_file_meta( $file ) {
		if ( empty( $file['name'] ) || empty( $file['url'] ) || empty( $file['relative_path'] ) ) {
			return false;
		}

		$extension = strtolower( pathinfo( (string) $file['name'], PATHINFO_EXTENSION ) );
		$allowed   = array_keys( self::get_allowed_upload_mimes() );
		$is_valid  = false;

		foreach ( $allowed as $group ) {
			$extensions = array_map( 'trim', explode( '|', $group ) );
			if ( in_array( $extension, $extensions, true ) ) {
				$is_valid = true;
				break;
			}
		}

		return $is_valid && absint( $file['size'] ?? 0 ) <= self::MAX_FILE_SIZE;
	}
}

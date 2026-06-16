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
	const MAX_FILE_SIZE = 5242880;
	const PENDING_UPLOAD_TTL = 14400;
	const PENDING_UPLOAD_OPTION = 'crpcrm_pending_upload_tokens';

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
				$uploaded_files = self::parse_uploaded_files_payload( $posted[ $name . '__uploaded' ] ?? '', $name );
				if ( is_wp_error( $uploaded_files ) ) {
					$errors[] = self::field_error( $field, $uploaded_files->get_error_message() );
					continue;
				}
				$new_uploaded_files = self::handle_uploaded_files( $files[ $name ] ?? null, $name );
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
				if ( 'display_html' === self::normalize_type( $field['type'] ?? 'text' ) ) {
					continue;
				}
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
			if ( 'display_html' === self::normalize_type( $field['type'] ?? 'text' ) ) {
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
		$field_types  = array();
		$items        = array();

		if ( $form ) {
			foreach ( self::get_enabled_fields( $form ) as $field ) {
				$name = sanitize_key( $field['name'] ?? $field['key'] ?? '' );
				if ( $name ) {
					$field_types[ $name ] = self::normalize_type( $field['type'] ?? 'text' );
				}
			}
		}

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
			if ( 'display_html' === ( $meta['type'] ?? '' ) ) {
				continue;
			}
			$items[ $key ] = array(
				'label' => sanitize_text_field( $meta['label'] ?? $key ),
				'value' => self::display_value( $request_data[ $key ], $meta['type'] ?? 'text' ),
				'type'  => sanitize_key( $meta['type'] ?? 'text' ),
				'raw'   => $request_data[ $key ],
			);
		}

		foreach ( $labels as $key => $label ) {
			if ( array_key_exists( $key, $request_data ) && ! isset( $items[ $key ] ) ) {
				$type = isset( $field_types[ $key ] ) ? $field_types[ $key ] : '';
				if ( 'display_html' === $type ) {
					continue;
				}
				$items[ $key ] = array(
					'label' => $label,
					'value' => self::display_value( $request_data[ $key ], $type ? $type : 'text' ),
					'type'  => $type ? $type : 'text',
					'raw'   => $request_data[ $key ],
				);
			}
		}

		foreach ( $request_data as $key => $value ) {
			$key = sanitize_key( $key );
			if ( ! $key || isset( $items[ $key ] ) || 0 === strpos( $key, '_submitted_' ) || in_array( $key, array( 'form_id', 'form_version', 'request_type', 'fields', 'submitted_fields', 'display_html' ), true ) ) {
				continue;
			}
			if ( is_array( $value ) && empty( $value ) ) {
				continue;
			}
			$label = isset( $snapshot[ $key ] ) ? sanitize_text_field( $snapshot[ $key ] ) : $key;
			$items[ $key ] = array(
				'label' => $label,
				'value' => self::display_value( $value ),
				'type'  => 'text',
				'raw'   => $value,
			);
		}

		return $items;
	}

	public static function get_summary_items( $form, $request_data ) {
		$items = self::get_display_items( $form, $request_data );
		$summary = array();

		foreach ( $items as $key => $item ) {
			$type = self::normalize_type( $item['type'] ?? 'text' );
			if ( 'display_html' === $type || 'file_upload' === $type ) {
				continue;
			}

			$value = self::display_value( $item['raw'] ?? ( $item['value'] ?? '' ), $type );
			if ( '' === trim( wp_strip_all_tags( (string) $value ) ) ) {
				continue;
			}

			$item['value'] = $value;
			$summary[ $key ] = $item;
		}

		return $summary;
	}

	public static function get_uploaded_file_items( $form, $request_data ) {
		$items = self::get_display_items( $form, $request_data );
		$files = array();

		foreach ( $items as $key => $item ) {
			if ( 'file_upload' !== self::normalize_type( $item['type'] ?? 'text' ) ) {
				continue;
			}
			$normalized = self::normalize_uploaded_file_payload( $item['raw'] ?? array() );
			if ( empty( $normalized ) ) {
				continue;
			}
			$item['raw']   = $normalized;
			$item['value'] = self::display_value( $normalized, 'file_upload' );
			$files[ $key ] = $item;
		}

		return $files;
	}

	public static function render_display_item( $item, $context = 'admin' ) {
		$item    = is_array( $item ) ? $item : array();
		$label   = sanitize_text_field( $item['label'] ?? '' );
		$type    = self::normalize_type( $item['type'] ?? 'text' );
		$raw     = array_key_exists( 'raw', $item ) ? $item['raw'] : ( $item['value'] ?? '' );
		$content = self::render_display_value_html( $raw, $type, $context, $item );

		return '<dt>' . esc_html( $label ) . '</dt><dd>' . $content . '</dd>';
	}

	public static function render_uploaded_file_value( $value, $field = array(), $context = 'admin' ) {
		$files = self::normalize_uploaded_file_payload( $value );
		if ( empty( $files ) ) {
			return '<span class="crpcrm-file-empty">' . esc_html__( 'فایلی برای بارگذاری ارسال نشده است.', 'customer-request-portal-crm' ) . '</span>';
		}

		$context = 'public' === $context ? 'public' : 'admin';
		$out     = array();
		foreach ( $files as $file ) {
			$out[] = self::render_single_uploaded_file( $file, $field, $context );
		}

		return '<div class="crpcrm-file-upload-display">' . implode( '', $out ) . '</div>';
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
			foreach ( self::normalize_uploaded_file_payload( $value ) as $file ) {
				if ( ! empty( $file['filename'] ) ) {
					$names[] = sanitize_text_field( $file['filename'] );
				} elseif ( ! empty( $file['name'] ) ) {
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

	private static function handle_uploaded_files( $file_group, $field_key = '' ) {
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
			if ( empty( $file['size'] ) || (int) $file['size'] > self::get_max_file_size() ) {
				return new WP_Error( 'crpcrm_upload_size', 'هر فایل باید حداکثر ' . size_format( self::get_max_file_size(), 0 ) . ' باشد.' );
			}

			$stored = self::store_uploaded_file( $file, $field_key );
			if ( is_wp_error( $stored ) ) {
				return $stored;
			}
			$uploaded[] = $stored;
		}

		return $uploaded;
	}

	private static function normalize_uploaded_file_payload( $value ) {
		$value = is_string( $value ) ? trim( $value ) : $value;
		if ( '' === $value || null === $value ) {
			return array();
		}

		if ( is_string( $value ) ) {
			$decoded = json_decode( wp_unslash( $value ), true );
			if ( null === $decoded && JSON_ERROR_NONE !== json_last_error() ) {
				$decoded = $value;
			}
			$value = $decoded;
		}

		if ( is_string( $value ) ) {
			return self::normalize_uploaded_file_payload( array( $value ) );
		}

		if ( ! is_array( $value ) ) {
			return array();
		}

		if ( empty( $value ) ) {
			return array();
		}

		$is_assoc = array_keys( $value ) !== range( 0, count( $value ) - 1 );
		if ( $is_assoc ) {
			return array( self::normalize_single_uploaded_file_meta( $value ) );
		}

		$files = array();
		foreach ( $value as $file ) {
			if ( is_string( $file ) ) {
				$files[] = self::normalize_single_uploaded_file_meta( array( 'url' => $file, 'name' => wp_basename( $file ) ) );
				continue;
			}
			if ( is_array( $file ) ) {
				$files[] = self::normalize_single_uploaded_file_meta( $file );
			}
		}

		return array_values( array_filter( $files ) );
	}

	private static function get_request_upload_subdir() {
		return '/crpcrm-request-files/' . current_time( 'Y' ) . '/' . current_time( 'm' );
	}

	private static function get_request_upload_dir() {
		$uploads = wp_get_upload_dir();
		$subdir  = self::get_request_upload_subdir();

		return array(
			'basedir' => $uploads['basedir'],
			'baseurl' => $uploads['baseurl'],
			'subdir'  => $subdir,
			'path'    => trailingslashit( $uploads['basedir'] ) . ltrim( $subdir, '/' ),
			'url'     => trailingslashit( $uploads['baseurl'] ) . ltrim( $subdir, '/' ),
		);
	}

	private static function normalize_single_uploaded_file_meta( $file ) {
		if ( ! is_array( $file ) ) {
			return array();
		}

		$attachment_id = absint( $file['attachment_id'] ?? 0 );
		$url           = '';
		if ( $attachment_id > 0 ) {
			$url = wp_get_attachment_url( $attachment_id );
		}
		if ( '' === $url && ! empty( $file['url'] ) ) {
			$url = esc_url_raw( (string) $file['url'] );
		}
		if ( '' === $url && ! empty( $file['relative_path'] ) ) {
			$uploads = wp_get_upload_dir();
			$url     = trailingslashit( $uploads['baseurl'] ) . ltrim( (string) $file['relative_path'], '/' );
		}

		$name = '';
		if ( ! empty( $file['filename'] ) ) {
			$name = sanitize_file_name( $file['filename'] );
		} elseif ( ! empty( $file['name'] ) ) {
			$name = sanitize_file_name( $file['name'] );
		} elseif ( $url ) {
			$name = sanitize_file_name( wp_basename( wp_parse_url( $url, PHP_URL_PATH ) ) );
		}

		if ( '' === $url || ! self::is_safe_internal_file_url( $url ) ) {
			$url = '';
		}

		$mime_type = '';
		if ( $attachment_id > 0 ) {
			$mime_type = get_post_mime_type( $attachment_id );
		}
		if ( '' === $mime_type && ! empty( $file['mime_type'] ) ) {
			$mime_type = sanitize_text_field( $file['mime_type'] );
		}
		if ( '' === $mime_type && ! empty( $file['type'] ) ) {
			$mime_type = sanitize_text_field( $file['type'] );
		}

		if ( '' === $mime_type ) {
			$mime_type = self::guess_mime_type_from_filename( $name );
		}

		$is_image = self::is_image_mime_type( $mime_type, $name );
		$is_pdf   = self::is_pdf_mime_type( $mime_type, $name );
		$file_type = $is_image ? 'image' : ( $is_pdf ? 'pdf' : 'file' );

		$relative_path = ! empty( $file['relative_path'] ) ? sanitize_text_field( $file['relative_path'] ) : '';
		if ( '' === $relative_path && $url ) {
			$uploads      = wp_get_upload_dir();
			$baseurl_path = wp_parse_url( $uploads['baseurl'], PHP_URL_PATH );
			$url_path     = wp_parse_url( $url, PHP_URL_PATH );
			if ( $baseurl_path && $url_path && 0 === strpos( $url_path, $baseurl_path ) ) {
				$relative_path = ltrim( substr( $url_path, strlen( $baseurl_path ) ), '/' );
			}
		}

		$size = absint( $file['size'] ?? 0 );
		if ( empty( $size ) && $url ) {
			$size = self::get_file_size_from_url( $url );
		}

		$created = self::extract_upload_date_from_relative_path( $relative_path );
		$download_url = $url;

		return array(
			'attachment_id' => $attachment_id > 0 ? $attachment_id : 0,
			'url'           => $url,
			'download_url'  => $download_url,
			'filename'      => $name,
			'name'          => $name,
			'mime_type'     => $mime_type,
			'type'          => $mime_type,
			'file_type'     => $file_type,
			'is_image'      => $is_image ? 1 : 0,
			'is_pdf'        => $is_pdf ? 1 : 0,
			'relative_path' => $relative_path,
			'upload_year'   => $created['year'],
			'upload_month'  => $created['month'],
			'size'          => $size,
			'upload_token'  => ! empty( $file['upload_token'] ) ? sanitize_text_field( $file['upload_token'] ) : '',
			'field_key'     => ! empty( $file['field_key'] ) ? sanitize_key( $file['field_key'] ) : '',
		);
	}

	private static function render_display_value_html( $value, $type = 'text', $context = 'admin', $field = array() ) {
		if ( 'file_upload' === $type || self::looks_like_uploaded_file_payload( $value ) ) {
			return self::render_uploaded_file_value( $value, $field, $context );
		}

		$text = self::display_value( $value, $type );
		if ( '' === $text ) {
			return '<span class="crpcrm-empty-value">—</span>';
		}

		return nl2br( esc_html( $text ) );
	}

	private static function render_single_uploaded_file( $file, $field = array(), $context = 'admin' ) {
		$file = self::normalize_single_uploaded_file_meta( $file );
		if ( empty( $file['url'] ) && empty( $file['download_url'] ) ) {
			return '';
		}

		$filename      = ! empty( $file['filename'] ) ? $file['filename'] : ( ! empty( $file['name'] ) ? $file['name'] : '' );
		$download_url  = ! empty( $file['download_url'] ) ? $file['download_url'] : $file['url'];
		$download_url  = self::get_safe_download_url( $download_url );
		$full_url      = self::get_safe_download_url( $file['url'] );
		$filename_attr  = esc_attr( $filename );
		$mime_type     = esc_attr( $file['mime_type'] ?? '' );

		if ( ! $download_url ) {
			return '<span class="crpcrm-file-empty">' . esc_html__( 'اطلاعات فایل ارسالی معتبر نیست.', 'customer-request-portal-crm' ) . '</span>';
		}

		if ( ! empty( $file['is_image'] ) ) {
			$thumb = '';
			if ( ! empty( $file['attachment_id'] ) ) {
				$thumb = wp_get_attachment_image(
					absint( $file['attachment_id'] ),
					'thumbnail',
					false,
					array(
						'class'   => 'crpcrm-file-thumb-image',
						'loading' => 'lazy',
						'alt'     => $filename_attr,
					)
				);
			}
			if ( '' === $thumb ) {
				$thumb = '<img class="crpcrm-file-thumb-image" src="' . esc_url( $full_url ? $full_url : $download_url ) . '" alt="' . $filename_attr . '" loading="lazy">';
			}

			return '<div class="crpcrm-file-item crpcrm-file-item-image"><button type="button" class="crpcrm-file-thumb" data-crpcrm-file-preview="1" data-full-url="' . esc_url( $full_url ? $full_url : $download_url ) . '" data-download-url="' . esc_url( $download_url ) . '" data-filename="' . $filename_attr . '" data-mime-type="' . $mime_type . '">' . $thumb . '</button><a class="crpcrm-file-download-link" href="' . esc_url( $download_url ) . '" download="' . $filename_attr . '">' . esc_html__( 'دانلود فایل', 'customer-request-portal-crm' ) . '</a></div>';
		}

		if ( ! empty( $file['is_pdf'] ) ) {
			return '<div class="crpcrm-file-item crpcrm-file-item-pdf"><a class="crpcrm-file-pdf" href="' . esc_url( $download_url ) . '" download="' . $filename_attr . '" data-download-url="' . esc_url( $download_url ) . '" data-filename="' . $filename_attr . '"><span class="crpcrm-file-pdf-icon">PDF</span><span class="crpcrm-file-name">' . esc_html( $filename ) . '</span></a><a class="crpcrm-file-download-link" href="' . esc_url( $download_url ) . '" download="' . $filename_attr . '">' . esc_html__( 'دانلود فایل', 'customer-request-portal-crm' ) . '</a></div>';
		}

		$label = $filename ? $filename : __( 'دانلود فایل', 'customer-request-portal-crm' );
		return '<div class="crpcrm-file-item crpcrm-file-item-generic"><span class="crpcrm-file-generic-icon">فایل</span><a class="crpcrm-file-download-link" href="' . esc_url( $download_url ) . '" download="' . $filename_attr . '">' . esc_html( $label ) . '</a></div>';
	}

	private static function get_safe_download_url( $url ) {
		$url = is_string( $url ) ? trim( $url ) : '';
		if ( '' === $url ) {
			return '';
		}

		if ( ! self::is_safe_internal_file_url( $url ) ) {
			return '';
		}

		return esc_url_raw( $url );
	}

	private static function is_safe_internal_file_url( $url ) {
		$url = is_string( $url ) ? trim( $url ) : '';
		if ( '' === $url ) {
			return false;
		}

		$uploads   = wp_get_upload_dir();
		$home_host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		$url_host  = wp_parse_url( $url, PHP_URL_HOST );

		if ( $url_host && $home_host && strtolower( $url_host ) !== strtolower( $home_host ) ) {
			return false;
		}

		if ( 0 === strpos( $url, $uploads['baseurl'] ) ) {
			return true;
		}

		if ( 0 === strpos( $url, admin_url() ) || 0 === strpos( $url, home_url( '/' ) ) ) {
			return true;
		}

		return false;
	}

	private static function guess_mime_type_from_filename( $filename ) {
		$extension = strtolower( pathinfo( (string) $filename, PATHINFO_EXTENSION ) );
		$map       = array(
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'jpe'  => 'image/jpeg',
			'png'  => 'image/png',
			'gif'  => 'image/gif',
			'webp' => 'image/webp',
			'pdf'  => 'application/pdf',
		);

		return isset( $map[ $extension ] ) ? $map[ $extension ] : '';
	}

	private static function is_image_mime_type( $mime_type, $filename = '' ) {
		$mime_type = strtolower( (string) $mime_type );
		if ( in_array( $mime_type, array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ), true ) ) {
			return true;
		}

		$guessed = self::guess_mime_type_from_filename( $filename );
		return in_array( $guessed, array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ), true );
	}

	private static function is_pdf_mime_type( $mime_type, $filename = '' ) {
		$mime_type = strtolower( (string) $mime_type );
		if ( 'application/pdf' === $mime_type ) {
			return true;
		}

		return 'application/pdf' === self::guess_mime_type_from_filename( $filename );
	}

	private static function looks_like_uploaded_file_payload( $value ) {
		if ( ! is_array( $value ) ) {
			return false;
		}

		foreach ( $value as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			if ( ! empty( $item['attachment_id'] ) || ! empty( $item['download_url'] ) || ! empty( $item['relative_path'] ) || ! empty( $item['mime_type'] ) || ! empty( $item['is_image'] ) || ! empty( $item['is_pdf'] ) || ! empty( $item['upload_token'] ) ) {
				return true;
			}
		}

		return false;
	}

	private static function get_file_size_from_url( $url ) {
		return 0;
	}

	private static function extract_upload_date_from_relative_path( $relative_path ) {
		$relative_path = trim( (string) $relative_path, '/' );
		if ( preg_match( '#crpcrm-request-files/(\d{4})/(\d{2})#', $relative_path, $matches ) ) {
			return array(
				'year'  => $matches[1],
				'month' => $matches[2],
			);
		}

		return array(
			'year'  => '',
			'month' => '',
		);
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

	private static function store_uploaded_file( $file, $field_key = '' ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';

		$upload_dir = self::get_request_upload_dir();
		if ( empty( $upload_dir['path'] ) || ! wp_mkdir_p( $upload_dir['path'] ) ) {
			error_log( '[CRPCRM] request_file_upload_dir_failed: ' . wp_json_encode( $upload_dir ) );
			return new WP_Error( 'crpcrm_upload_dir_failed', 'بارگذاری فایل انجام نشد.' );
		}

		add_filter( 'upload_dir', array( __CLASS__, 'filter_upload_dir' ) );
		try {
			$result = wp_handle_upload(
				$file,
				array(
					'test_form' => false,
					'mimes'     => self::get_allowed_upload_mimes(),
				)
			);
		} finally {
			remove_filter( 'upload_dir', array( __CLASS__, 'filter_upload_dir' ) );
		}

		if ( ! empty( $result['error'] ) ) {
			return new WP_Error( 'crpcrm_upload_failed', sanitize_text_field( $result['error'] ) );
		}

		$base = wp_get_upload_dir();
		$path = str_replace( trailingslashit( $base['basedir'] ), '', $result['file'] );
		$year  = current_time( 'Y' );
		$month = current_time( 'm' );
		$mime  = sanitize_text_field( $result['type'] ?? '' );
		$name  = sanitize_file_name( wp_basename( $result['file'] ) );
		$file_type = self::is_pdf_mime_type( $mime, $name ) ? 'pdf' : ( self::is_image_mime_type( $mime, $name ) ? 'image' : 'file' );

		return self::register_pending_upload(
			array(
			'attachment_id' => 0,
			'name'          => $name,
			'filename'      => $name,
			'url'           => esc_url_raw( $result['url'] ),
			'download_url'  => esc_url_raw( $result['url'] ),
			'relative_path' => sanitize_text_field( $path ),
			'upload_year'   => $year,
			'upload_month'  => $month,
			'mime_type'     => $mime,
			'type'          => $mime,
			'file_type'     => $file_type,
			'is_image'      => 'image' === $file_type ? 1 : 0,
			'is_pdf'        => 'pdf' === $file_type ? 1 : 0,
			'size'          => file_exists( $result['file'] ) ? absint( filesize( $result['file'] ) ) : absint( $file['size'] ?? 0 ),
			'field_key'     => sanitize_key( $field_key ),
			)
		);
	}

	public static function filter_upload_dir( $uploads ) {
		$subdir = self::get_request_upload_subdir();
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

	public static function handle_async_upload( $file, $field_key = '' ) {
		if ( empty( $file ) || ! is_array( $file ) ) {
			return new WP_Error( 'crpcrm_upload_missing', 'فایلی برای بارگذاری ارسال نشده است.' );
		}

		$uploaded = self::handle_uploaded_files( $file, $field_key );
		if ( is_wp_error( $uploaded ) ) {
			return $uploaded;
		}

		if ( empty( $uploaded ) ) {
			return new WP_Error( 'crpcrm_upload_missing', 'فایلی برای بارگذاری ارسال نشده است.' );
		}

		return $uploaded[0];
	}

	private static function parse_uploaded_files_payload( $payload, $field_key = '' ) {
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
			return new WP_Error( 'crpcrm_upload_invalid', 'اطلاعات فایل ارسالی معتبر نیست.' );
		}

		$normalized = array();
		foreach ( self::normalize_uploaded_file_payload( $decoded ) as $file ) {
			if ( ! is_array( $file ) ) {
				return new WP_Error( 'crpcrm_upload_invalid', 'اطلاعات فایل ارسالی معتبر نیست.' );
			}

			$token = ! empty( $file['upload_token'] ) ? sanitize_text_field( $file['upload_token'] ) : '';
			if ( $token ) {
				$pending = self::get_pending_upload( $token );
				if ( ! $pending ) {
					return new WP_Error( 'crpcrm_upload_invalid', 'اطلاعات فایل ارسالی معتبر نیست.' );
				}
				if ( absint( $pending['current_user_id'] ?? 0 ) !== get_current_user_id() ) {
					return new WP_Error( 'crpcrm_upload_invalid', 'اطلاعات فایل ارسالی معتبر نیست.' );
				}
				$pending_field_key   = sanitize_key( $pending['field_key'] ?? '' );
				$requested_field_key = sanitize_key( $field_key );
				if ( $pending_field_key && $requested_field_key && $pending_field_key !== $requested_field_key ) {
					return new WP_Error( 'crpcrm_upload_invalid', 'اطلاعات فایل ارسالی معتبر نیست.' );
				}

				$file = $pending;
				$file['field_key'] = $requested_field_key ? $requested_field_key : $pending_field_key;
				$normalized[] = self::normalize_single_uploaded_file_meta( $file );
				continue;
			}

			if ( ! self::is_valid_legacy_uploaded_file_meta( $file ) ) {
				return new WP_Error( 'crpcrm_upload_invalid', 'اطلاعات فایل ارسالی معتبر نیست.' );
			}

			$file['field_key'] = sanitize_key( $field_key );
			$normalized[] = self::normalize_single_uploaded_file_meta( $file );
		}

		return $normalized;
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

		return $is_valid && absint( $file['size'] ?? 0 ) <= self::get_max_file_size();
	}

	private static function is_valid_legacy_uploaded_file_meta( $file ) {
		$file = is_array( $file ) ? $file : array();

		if ( ! empty( $file['attachment_id'] ) ) {
			$attachment_url = wp_get_attachment_url( absint( $file['attachment_id'] ) );
			return $attachment_url && self::is_safe_internal_file_url( $attachment_url );
		}

		if ( empty( $file['url'] ) || ! self::is_safe_internal_file_url( $file['url'] ) ) {
			return false;
		}

		$uploads      = wp_get_upload_dir();
		$baseurl_path = wp_parse_url( $uploads['baseurl'], PHP_URL_PATH );
		$url_path     = wp_parse_url( $file['url'], PHP_URL_PATH );
		if ( ! $baseurl_path || ! $url_path || 0 !== strpos( $url_path, $baseurl_path ) ) {
			return false;
		}

		if ( ! empty( $file['size'] ) && absint( $file['size'] ) > self::get_max_file_size() ) {
			return false;
		}

		return ! empty( $file['relative_path'] ) || ! empty( $file['filename'] ) || ! empty( $file['name'] );
	}

	private static function register_pending_upload( $file ) {
		$file = is_array( $file ) ? $file : array();
		$token = wp_generate_password( 32, false, false );
		$now   = current_time( 'timestamp' );

		$pending = $file;
		$pending['upload_token']    = $token;
		$pending['created_at']      = CRPCRM_Helpers::current_datetime();
		$pending['expires_at']      = wp_date( 'Y-m-d H:i:s', $now + self::PENDING_UPLOAD_TTL );
		$pending['current_user_id'] = get_current_user_id();
		$pending['status']          = 'pending';

		set_transient( self::pending_upload_transient_key( $token ), $pending, self::PENDING_UPLOAD_TTL );
		self::index_pending_upload( $token, $pending );

		$file['upload_token'] = $token;
		$file['field_key']    = sanitize_key( $file['field_key'] ?? '' );
		return $file;
	}

	private static function index_pending_upload( $token, $file ) {
		$token = sanitize_text_field( $token );
		if ( '' === $token ) {
			return;
		}

		$index = get_option( self::PENDING_UPLOAD_OPTION, array() );
		$index = is_array( $index ) ? $index : array();
		$index[ $token ] = array(
			'expires_at'      => strtotime( $file['expires_at'] ?? '' ) ? strtotime( $file['expires_at'] ) : ( current_time( 'timestamp' ) + self::PENDING_UPLOAD_TTL ),
			'current_user_id' => absint( $file['current_user_id'] ?? 0 ),
			'field_key'      => sanitize_key( $file['field_key'] ?? '' ),
			'relative_path'  => sanitize_text_field( $file['relative_path'] ?? '' ),
		);
		update_option( self::PENDING_UPLOAD_OPTION, $index, false );
	}

	private static function get_pending_upload( $token ) {
		$token = sanitize_text_field( $token );
		if ( '' === $token ) {
			return false;
		}

		$pending = get_transient( self::pending_upload_transient_key( $token ) );
		return is_array( $pending ) ? $pending : false;
	}

	private static function finalize_pending_upload( $token ) {
		$token = sanitize_text_field( $token );
		if ( '' === $token ) {
			return;
		}

		delete_transient( self::pending_upload_transient_key( $token ) );
		$index = get_option( self::PENDING_UPLOAD_OPTION, array() );
		$index = is_array( $index ) ? $index : array();
		if ( isset( $index[ $token ] ) ) {
			unset( $index[ $token ] );
			update_option( self::PENDING_UPLOAD_OPTION, $index, false );
		}
	}

	private static function pending_upload_transient_key( $token ) {
		return 'crpcrm_pending_upload_' . sanitize_key( $token );
	}

	private static function get_max_file_size() {
		$limit = self::MAX_FILE_SIZE;
		if ( function_exists( 'wp_max_upload_size' ) ) {
			$wp_limit = absint( wp_max_upload_size() );
			if ( $wp_limit > 0 ) {
				$limit = min( $limit, $wp_limit );
			}
		}

		return max( 1, $limit );
	}

	public static function finalize_request_data_uploads( $request_data, $request_id ) {
		$request_data = is_array( $request_data ) ? $request_data : array();
		$request_id   = absint( $request_id );

		if ( ! $request_id ) {
			return $request_data;
		}

		return self::finalize_request_data_uploads_recursive( $request_data, $request_id );
	}

	public static function schedule_pending_upload_cleanup() {
		if ( ! wp_next_scheduled( 'crpcrm_pending_upload_cleanup' ) ) {
			wp_schedule_event( current_time( 'timestamp' ) + HOUR_IN_SECONDS, 'twicedaily', 'crpcrm_pending_upload_cleanup' );
		}
	}

	public static function clear_pending_upload_cleanup() {
		wp_clear_scheduled_hook( 'crpcrm_pending_upload_cleanup' );
	}

	public static function cleanup_pending_uploads() {
		require_once ABSPATH . 'wp-admin/includes/file.php';

		$index = get_option( self::PENDING_UPLOAD_OPTION, array() );
		$index = is_array( $index ) ? $index : array();
		$now   = current_time( 'timestamp' );

		foreach ( $index as $token => $item ) {
			$token = sanitize_text_field( $token );
			if ( '' === $token ) {
				unset( $index[ $token ] );
				continue;
			}

			$pending = self::get_pending_upload( $token );
			$expires = absint( is_array( $item ) && isset( $item['expires_at'] ) ? $item['expires_at'] : 0 );
			if ( $pending && ( ! $expires || $expires > $now ) ) {
				continue;
			}

			$path = self::resolve_uploaded_file_path( is_array( $pending ) ? $pending : $item );
			if ( $path && file_exists( $path ) ) {
				if ( false === wp_delete_file( $path ) ) {
					error_log( '[CRPCRM] pending_upload_cleanup_file_delete_failed: ' . wp_json_encode( array( 'token' => $token, 'path' => $path ) ) );
				}
			}

			$attachment_id = absint( is_array( $pending ) && ! empty( $pending['attachment_id'] ) ? $pending['attachment_id'] : ( is_array( $item ) && ! empty( $item['attachment_id'] ) ? $item['attachment_id'] : 0 ) );
			if ( $attachment_id && function_exists( 'wp_delete_attachment' ) ) {
				wp_delete_attachment( $attachment_id, true );
			}

			delete_transient( self::pending_upload_transient_key( $token ) );
			unset( $index[ $token ] );
		}

		update_option( self::PENDING_UPLOAD_OPTION, $index, false );
	}

	private static function finalize_request_data_uploads_recursive( $value, $request_id ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}

		if ( self::looks_like_uploaded_file_payload( $value ) ) {
			return self::finalize_uploaded_file_payload( $value, $request_id );
		}

		foreach ( $value as $key => $item ) {
			$value[ $key ] = self::finalize_request_data_uploads_recursive( $item, $request_id );
		}

		return $value;
	}

	private static function finalize_uploaded_file_payload( $value, $request_id ) {
		$files = self::normalize_uploaded_file_payload( $value );
		$final = array();

		foreach ( $files as $file ) {
			$file  = is_array( $file ) ? $file : array();
			$token = ! empty( $file['upload_token'] ) ? sanitize_text_field( $file['upload_token'] ) : '';
			if ( $token ) {
				$pending = self::get_pending_upload( $token );
				if ( $pending && absint( $pending['current_user_id'] ?? 0 ) === get_current_user_id() ) {
					$file = self::normalize_single_uploaded_file_meta( $pending );
					self::finalize_pending_upload( $token );
				}
			}

			$file['request_id'] = absint( $request_id );
			if ( empty( $file['field_key'] ) && isset( $value['field_key'] ) ) {
				$file['field_key'] = sanitize_key( $value['field_key'] );
			}
			unset( $file['upload_token'], $file['status'], $file['current_user_id'], $file['created_at'], $file['expires_at'] );
			$normalized = self::normalize_single_uploaded_file_meta( $file );
			$normalized['request_id'] = absint( $request_id );
			$final[] = $normalized;
		}

		return $final;
	}

	private static function resolve_uploaded_file_path( $file ) {
		$file = is_array( $file ) ? $file : array();
		$uploads = wp_get_upload_dir();
		$base    = realpath( $uploads['basedir'] );

		if ( ! empty( $file['relative_path'] ) ) {
			$path = trailingslashit( $uploads['basedir'] ) . ltrim( sanitize_text_field( $file['relative_path'] ), '/' );
			$real = realpath( $path );
			if ( $real && $base && 0 === strpos( $real, $base ) ) {
				return $real;
			}
		}

		if ( ! empty( $file['url'] ) ) {
			$baseurl_path = wp_parse_url( $uploads['baseurl'], PHP_URL_PATH );
			$url_path     = wp_parse_url( $file['url'], PHP_URL_PATH );
			if ( $baseurl_path && $url_path && 0 === strpos( $url_path, $baseurl_path ) ) {
				$relative = ltrim( substr( $url_path, strlen( $baseurl_path ) ), '/' );
				$path     = trailingslashit( $uploads['basedir'] ) . $relative;
				$real     = realpath( $path );
				if ( $real && $base && 0 === strpos( $real, $base ) ) {
					return $real;
				}
			}
		}

		return '';
	}
}

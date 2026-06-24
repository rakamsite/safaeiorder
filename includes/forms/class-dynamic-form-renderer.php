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
	const MAX_FILES_PER_FIELD = 5;
	const MAX_TOTAL_REQUEST_SIZE = 26214400;
	const DAILY_UPLOAD_FILE_LIMIT = 50;
	const DAILY_UPLOAD_SIZE_LIMIT = 104857600;
	const PENDING_UPLOAD_TTL = 14400;
	const PENDING_UPLOAD_OPTION = 'crpcrm_pending_upload_tokens';
	const DAILY_UPLOAD_OPTION = 'crpcrm_daily_upload_usage';

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
		$total_uploaded_size = 0;

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
				$uploaded_files = self::collect_submitted_uploaded_files( $posted[ $name . '__uploaded' ] ?? '', $files[ $name ] ?? null, $name );
				if ( is_wp_error( $uploaded_files ) ) {
					$errors[] = self::field_error( $field, self::normalize_upload_error_message( $uploaded_files->get_error_message() ) );
					continue;
				}
				if ( ! empty( $field['required'] ) && empty( $uploaded_files ) ) {
					$errors[] = self::field_error( $field, 'این فیلد الزامی است.' );
					continue;
				}
				$total_uploaded_size += self::sum_uploaded_files_size( $uploaded_files );
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

		if ( $total_uploaded_size > self::MAX_TOTAL_REQUEST_SIZE ) {
			$errors[] = sprintf(
				__( "\u{0645}\u{062C}\u{0645}\u{0648}\u{0639} \u{062D}\u{062C}\u{0645} \u{0641}\u{0627}\u{06CC}\u{0644}\u{200C}\u{0647}\u{0627}\u{06CC} \u{0627}\u{0631}\u{0633}\u{0627}\u{0644}\u{06CC} \u{0646}\u{0628}\u{0627}\u{06CC}\u{062F} \u{0628}\u{06CC}\u{0634}\u{062A}\u{0631} \u{0627}\u{0632} %s \u{0628}\u{0627}\u{0634}\u{062F}.", 'customer-request-portal-crm' ),
				size_format( self::MAX_TOTAL_REQUEST_SIZE, 0 )
			);
		}

		return array(
			'data'   => $data,
			'errors' => array_values(
				array_unique(
					array_map(
						array( __CLASS__, 'normalize_upload_error_message' ),
						$errors
					)
				)
			),
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
				'key'   => $key,
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
					'key'   => $key,
					'raw'   => $request_data[ $key ],
				);
			}
		}

		foreach ( $request_data as $key => $value ) {
			$key = sanitize_key( $key );
			if ( ! $key || isset( $items[ $key ] ) || 0 === strpos( $key, '_submitted_' ) || in_array( $key, array( 'form_id', 'form_version', 'request_type', 'fields', 'submitted_fields', 'display_html', '_landing_attribution' ), true ) ) {
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
				'key'   => $key,
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
		foreach ( array_values( $files ) as $index => $file ) {
			$field['file_index'] = absint( $index );
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
			echo self::render_file_upload_field( $name, $required, $accept );
		} else {
			$input_class = 'admin' === $context ? '' : ' class="crpcrm-input"';
			echo '<input' . $input_class . ' id="' . esc_attr( $field_id ) . '" type="text" name="' . esc_attr( $name ) . '" value="' . esc_attr( $default ) . '" placeholder="' . esc_attr( $placeholder ) . '"' . ( $required ? ' required' : '' ) . '>';
		}
		if ( $help ) {
			echo '<div class="crpcrm-help-text">' . esc_html( $help ) . '</div>';
		}
		echo '</div>';
	}

	public static function render_file_upload_field( $name, $required = false, $accept = '.jpg,.jpeg,.png,.webp,.gif,.pdf' ) {
		$name     = sanitize_key( $name );
		$required = (bool) $required;
		$accept   = is_string( $accept ) && '' !== trim( $accept ) ? $accept : '.jpg,.jpeg,.png,.webp,.gif,.pdf';

		return '<div class="crpcrm-file-upload" data-field-name="' . esc_attr( $name ) . '" data-field-required="' . ( $required ? '1' : '0' ) . '" data-max-files="' . esc_attr( (string) self::MAX_FILES_PER_FIELD ) . '" data-max-total-size="' . esc_attr( (string) self::MAX_TOTAL_REQUEST_SIZE ) . '"><input type="hidden" class="crpcrm-uploaded-files-store" name="' . esc_attr( $name . '__uploaded' ) . '" value=""><div class="crpcrm-file-upload-list">' . self::render_file_input_row( $name, $accept, $required ) . '</div><button type="button" class="crpcrm-file-upload-add" aria-label="' . esc_attr__( 'افزودن فایل', 'customer-request-portal-crm' ) . '" title="' . esc_attr__( 'افزودن فایل', 'customer-request-portal-crm' ) . '">+</button></div>';
	}

	private static function render_file_input_row( $name, $accept, $required ) {
		return '<div class="crpcrm-file-upload-row"><input type="file" name="' . esc_attr( $name ) . '[]" accept="' . esc_attr( $accept ) . '"' . ( $required ? ' required data-required="1"' : '' ) . ' aria-label="' . esc_attr__( 'انتخاب فایل', 'customer-request-portal-crm' ) . '"><button type="button" class="crpcrm-file-upload-remove" aria-label="' . esc_attr__( 'حذف', 'customer-request-portal-crm' ) . '" title="' . esc_attr__( 'حذف', 'customer-request-portal-crm' ) . '">&times;</button></div>';
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
		return self::handle_uploaded_files_with_context( $file_group, $field_key );
	}

	public static function collect_submitted_uploaded_files( $payload, $file_group = null, $field_key = '' ) {
		$existing = self::parse_uploaded_files_payload( $payload, $field_key );
		if ( is_wp_error( $existing ) ) {
			return $existing;
		}

		$new_files = self::handle_uploaded_files_with_context(
			$file_group,
			$field_key,
			array(
				'current_file_count' => count( $existing ),
				'current_total_size' => self::sum_uploaded_files_size( $existing ),
			)
		);
		if ( is_wp_error( $new_files ) ) {
			return $new_files;
		}

		$merged = array_merge( $existing, $new_files );
		$validation = self::validate_uploaded_file_collection( $merged );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		return $merged;
	}

	private static function handle_uploaded_files_with_context( $file_group, $field_key = '', $context = array() ) {
		if ( empty( $file_group ) || empty( $file_group['name'] ) ) {
			return array();
		}

		$normalized = self::normalize_uploaded_file_group( $file_group );
		$uploaded   = array();
		$current_file_count = max( 0, absint( $context['current_file_count'] ?? 0 ) );
		$current_total_size = max( 0, absint( $context['current_total_size'] ?? 0 ) );

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

			$limits = self::validate_incoming_upload_limits(
				absint( $file['size'] ),
				$current_file_count + count( $uploaded ),
				$current_total_size + self::sum_uploaded_files_size( $uploaded )
			);
			if ( is_wp_error( $limits ) ) {
				return $limits;
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
		return '/crpcrm-protected/request-files/' . CRPCRM_Helpers::now()->format( 'Y' ) . '/' . CRPCRM_Helpers::now()->format( 'm' );
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

	public static function get_protected_upload_root_dir() {
		$uploads = wp_get_upload_dir();
		return trailingslashit( $uploads['basedir'] ) . 'crpcrm-protected';
	}

	private static function ensure_request_upload_root_protection() {
		$root_dir = self::get_protected_upload_root_dir();
		if ( empty( $root_dir ) || ( ! file_exists( $root_dir ) && ! wp_mkdir_p( $root_dir ) ) ) {
			return;
		}

		$index_file = trailingslashit( $root_dir ) . 'index.php';
		if ( ! file_exists( $index_file ) ) {
			self::safe_write_file( $index_file, "<?php\n// Silence is golden.\n" );
		}

		$htaccess_file = trailingslashit( $root_dir ) . '.htaccess';
		if ( ! file_exists( $htaccess_file ) ) {
			$rules = "Options -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n";
			self::safe_write_file( $htaccess_file, $rules );
		}
	}

	private static function safe_write_file( $path, $contents ) {
		$path = is_string( $path ) ? trim( $path ) : '';
		if ( '' === $path ) {
			return false;
		}

		$dir = dirname( $path );
		if ( ! $dir || ( ! file_exists( $dir ) && ! wp_mkdir_p( $dir ) ) || ! is_writable( $dir ) ) {
			return false;
		}

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$written = false;
		if ( function_exists( 'WP_Filesystem' ) && WP_Filesystem() ) {
			global $wp_filesystem;
			if ( is_object( $wp_filesystem ) && method_exists( $wp_filesystem, 'put_contents' ) ) {
				$written = (bool) $wp_filesystem->put_contents( $path, $contents, FS_CHMOD_FILE );
			}
		}

		if ( ! $written ) {
			$written = false !== @file_put_contents( $path, $contents, LOCK_EX );
		}

		return $written;
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
		$stored_name = '';
		if ( ! empty( $file['stored_filename'] ) ) {
			$stored_name = sanitize_file_name( $file['stored_filename'] );
		} elseif ( ! empty( $file['stored_name'] ) ) {
			$stored_name = sanitize_file_name( $file['stored_name'] );
		}

		$original_name = '';
		if ( ! empty( $file['original_name'] ) ) {
			$original_name = sanitize_file_name( $file['original_name'] );
		} elseif ( ! empty( $file['display_name'] ) ) {
			$original_name = sanitize_file_name( $file['display_name'] );
		}

		$name = '';
		if ( $original_name ) {
			$name = $original_name;
		} elseif ( ! empty( $file['filename'] ) ) {
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
		if ( empty( $size ) ) {
			$size = self::get_file_size_from_meta( $file );
		}

		$created = self::extract_upload_date_from_relative_path( $relative_path );

		return array(
			'attachment_id' => $attachment_id > 0 ? $attachment_id : 0,
			'url'           => $url,
			'download_url'  => $url,
			'filename'      => $name,
			'name'          => $name,
			'original_name' => $name,
			'display_name'  => $name,
			'stored_name'   => $stored_name,
			'stored_filename' => $stored_name,
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
		$file          = self::normalize_single_uploaded_file_meta( $file );
		$download_url  = CRPCRM_Request_File_Access_Service::build_file_url( $file, $field, 'download' );
		$full_url      = CRPCRM_Request_File_Access_Service::build_file_url( $file, $field, 'preview' );
		$filename      = ! empty( $file['filename'] ) ? $file['filename'] : ( ! empty( $file['name'] ) ? $file['name'] : '' );
		$filename_attr = esc_attr( $filename );
		$mime_type     = esc_attr( $file['mime_type'] ?? '' );

		if ( ! $download_url ) {
			return '<span class="crpcrm-file-empty">' . esc_html__( 'اطلاعات فایل ارسالی معتبر نیست.', 'customer-request-portal-crm' ) . '</span>';
		}

		if ( ! empty( $file['is_image'] ) ) {
			$thumb = '<img class="crpcrm-file-thumb-image" src="' . esc_url( $full_url ? $full_url : $download_url ) . '" alt="' . $filename_attr . '" loading="lazy">';

			return '<div class="crpcrm-file-item crpcrm-file-item-image"><button type="button" class="crpcrm-file-thumb" aria-label="' . esc_attr__( 'مشاهده فایل', 'customer-request-portal-crm' ) . '" title="' . esc_attr__( 'مشاهده فایل', 'customer-request-portal-crm' ) . '" data-crpcrm-file-preview="1" data-full-url="' . esc_url( $full_url ? $full_url : $download_url ) . '" data-download-url="' . esc_url( $download_url ) . '" data-filename="' . $filename_attr . '" data-mime-type="' . $mime_type . '">' . $thumb . '</button><a class="crpcrm-file-download-link" href="' . esc_url( $download_url ) . '" download="' . $filename_attr . '" aria-label="' . esc_attr__( 'دانلود فایل', 'customer-request-portal-crm' ) . '">' . esc_html__( 'دانلود فایل', 'customer-request-portal-crm' ) . '</a></div>';
		}

		if ( ! empty( $file['is_pdf'] ) ) {
			return '<div class="crpcrm-file-item crpcrm-file-item-pdf"><a class="crpcrm-file-pdf" href="' . esc_url( $download_url ) . '" download="' . $filename_attr . '" aria-label="' . esc_attr__( 'دانلود فایل PDF', 'customer-request-portal-crm' ) . '" title="' . esc_attr__( 'دانلود فایل PDF', 'customer-request-portal-crm' ) . '" data-download-url="' . esc_url( $download_url ) . '" data-filename="' . $filename_attr . '"><span class="crpcrm-file-pdf-icon">PDF</span><span class="crpcrm-file-name">' . esc_html( $filename ) . '</span></a><a class="crpcrm-file-download-link" href="' . esc_url( $download_url ) . '" download="' . $filename_attr . '" aria-label="' . esc_attr__( 'دانلود فایل', 'customer-request-portal-crm' ) . '">' . esc_html__( 'دانلود فایل', 'customer-request-portal-crm' ) . '</a></div>';
		}

		$label = $filename ? $filename : __( 'دانلود فایل', 'customer-request-portal-crm' );
		return '<div class="crpcrm-file-item crpcrm-file-item-generic"><span class="crpcrm-file-generic-icon">فایل</span><a class="crpcrm-file-download-link" href="' . esc_url( $download_url ) . '" download="' . $filename_attr . '" aria-label="' . esc_attr__( 'دانلود فایل', 'customer-request-portal-crm' ) . '">' . esc_html( $label ) . '</a></div>';
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

	private static function get_file_size_from_meta( $file ) {
		$path = self::resolve_uploaded_file_path( $file );
		if ( '' === $path || ! file_exists( $path ) || ! is_readable( $path ) ) {
			return 0;
		}

		return absint( filesize( $path ) );
	}

	private static function extract_upload_date_from_relative_path( $relative_path ) {
		$relative_path = trim( (string) $relative_path, '/' );
		if ( preg_match( '#(?:crpcrm-request-files|crpcrm-protected/request-files)/(\d{4})/(\d{2})#', $relative_path, $matches ) ) {
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
		self::ensure_request_upload_root_protection();
		if ( empty( $upload_dir['path'] ) || ! wp_mkdir_p( $upload_dir['path'] ) ) {
			error_log( '[CRPCRM] request_file_upload_dir_failed: ' . wp_json_encode( $upload_dir ) );
			return new WP_Error( 'crpcrm_upload_dir_failed', 'بارگذاری فایل انجام نشد.' );
		}

		add_filter( 'upload_dir', array( __CLASS__, 'filter_upload_dir' ) );
		add_filter( 'sanitize_file_name', array( __CLASS__, 'filter_request_upload_filename' ), 10, 2 );
		try {
			$result = wp_handle_upload(
				$file,
				array(
					'test_form' => false,
					'mimes'     => self::get_allowed_upload_mimes(),
				)
			);
		} finally {
			remove_filter( 'sanitize_file_name', array( __CLASS__, 'filter_request_upload_filename' ), 10 );
			remove_filter( 'upload_dir', array( __CLASS__, 'filter_upload_dir' ) );
		}

		if ( ! empty( $result['error'] ) ) {
			return new WP_Error( 'crpcrm_upload_failed', sanitize_text_field( $result['error'] ) );
		}

		$base          = wp_get_upload_dir();
		$path          = str_replace( trailingslashit( $base['basedir'] ), '', $result['file'] );
		$year          = CRPCRM_Helpers::now()->format( 'Y' );
		$month         = CRPCRM_Helpers::now()->format( 'm' );
		$mime          = sanitize_text_field( $result['type'] ?? '' );
		$stored_name   = sanitize_file_name( wp_basename( $result['file'] ) );
		$original_name = sanitize_file_name( wp_basename( (string) ( $file['name'] ?? '' ) ) );
		if ( '' === $original_name ) {
			$original_name = $stored_name;
		}
		$file_type = self::is_pdf_mime_type( $mime, $original_name ) ? 'pdf' : ( self::is_image_mime_type( $mime, $original_name ) ? 'image' : 'file' );

		$stored_file = self::register_pending_upload(
			array(
			'attachment_id' => 0,
			'name'          => $original_name,
			'filename'      => $original_name,
			'original_name' => $original_name,
			'display_name'  => $original_name,
			'stored_name'   => $stored_name,
			'stored_filename' => $stored_name,
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
		if ( ! is_wp_error( $stored_file ) ) {
			self::record_daily_upload_usage( absint( $stored_file['size'] ?? 0 ) );
		}

		return $stored_file;
	}

	public static function filter_upload_dir( $uploads ) {
		$subdir = self::get_request_upload_subdir();
		$uploads['subdir'] = $subdir;
		$uploads['path']   = $uploads['basedir'] . $subdir;
		$uploads['url']    = $uploads['baseurl'] . $subdir;
		return $uploads;
	}

	public static function filter_request_upload_filename( $filename, $raw_filename = '' ) {
		$extension = strtolower( pathinfo( (string) $raw_filename, PATHINFO_EXTENSION ) );
		$random    = wp_generate_password( 24, false, false );
		$extension = preg_replace( '/[^a-z0-9]+/i', '', $extension );
		return $random . ( $extension ? '.' . $extension : '' );
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

	public static function handle_async_upload( $file, $field_key = '', $context = array() ) {
		if ( empty( $file ) || ! is_array( $file ) ) {
			return new WP_Error( 'crpcrm_upload_missing', 'فایلی برای بارگذاری ارسال نشده است.' );
		}

		$uploaded = self::handle_uploaded_files_with_context( $file, $field_key, $context );
		if ( is_wp_error( $uploaded ) ) {
			return $uploaded;
		}

		if ( empty( $uploaded ) ) {
			return new WP_Error( 'crpcrm_upload_missing', 'فایلی برای بارگذاری ارسال نشده است.' );
		}

		$file = self::normalize_single_uploaded_file_meta( $uploaded[0] );
		$file['url'] = CRPCRM_Request_File_Access_Service::build_file_url(
			$file,
			array(
				'source_type'  => 'pending',
				'source_field' => sanitize_key( $field_key ),
			),
			'preview'
		);
		$file['download_url'] = CRPCRM_Request_File_Access_Service::build_file_url(
			$file,
			array(
				'source_type'  => 'pending',
				'source_field' => sanitize_key( $field_key ),
			),
			'download'
		);

		return $file;
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
			if ( '' === $token ) {
				return new WP_Error( 'crpcrm_upload_invalid', 'اطلاعات فایل ارسالی معتبر نیست. لطفاً فایل را دوباره بارگذاری کنید.' );
			}
			if ( $token ) {
				$pending = self::get_pending_upload( $token );
				if ( ! $pending ) {
					return new WP_Error( 'crpcrm_upload_invalid', 'اطلاعات فایل ارسالی معتبر نیست.' );
				}
				if ( ! self::pending_upload_belongs_to_actor( $pending, sanitize_key( $field_key ) ) ) {
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

			return new WP_Error( 'crpcrm_upload_invalid', 'اطلاعات فایل ارسالی معتبر نیست. لطفاً فایل را دوباره بارگذاری کنید.' );
		}

		return $normalized;
	}

	private static function register_pending_upload( $file ) {
		$file = is_array( $file ) ? $file : array();
		$token = wp_generate_password( 32, false, false );
		$pending = $file;
		$pending['upload_token']    = $token;
		$pending['created_at']      = CRPCRM_Helpers::current_datetime();
		$pending['expires_at']      = CRPCRM_Helpers::add_seconds_to_now( self::PENDING_UPLOAD_TTL );
		$pending['current_user_id'] = get_current_user_id();
		$pending['client_hash']     = self::get_upload_client_hash();
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
			'expires_at'      => CRPCRM_Helpers::is_valid_datetime( $file['expires_at'] ?? '' ) ? CRPCRM_Helpers::datetime_to_timestamp( $file['expires_at'] ) : ( CRPCRM_Helpers::current_timestamp() + self::PENDING_UPLOAD_TTL ),
			'current_user_id' => absint( $file['current_user_id'] ?? 0 ),
			'client_hash'     => sanitize_text_field( $file['client_hash'] ?? '' ),
			'field_key'       => sanitize_key( $file['field_key'] ?? '' ),
			'relative_path'   => sanitize_text_field( $file['relative_path'] ?? '' ),
			'size'            => absint( $file['size'] ?? 0 ),
		);
		update_option( self::PENDING_UPLOAD_OPTION, $index, false );
	}

	private static function get_pending_upload( $token ) {
		$token = sanitize_text_field( $token );
		if ( '' === $token ) {
			return false;
		}

		$pending = get_transient( self::pending_upload_transient_key( $token ) );
		if ( ! is_array( $pending ) ) {
			return false;
		}

		$expires_at = ! empty( $pending['expires_at'] ) && CRPCRM_Helpers::is_valid_datetime( $pending['expires_at'] ) ? CRPCRM_Helpers::datetime_to_timestamp( $pending['expires_at'] ) : 0;
		if ( $expires_at && $expires_at < CRPCRM_Helpers::current_timestamp() ) {
			self::finalize_pending_upload( $token );
			return false;
		}

		return $pending;
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

	public static function delete_pending_upload( $token, $field_key = '', $user_id = 0 ) {
		$token   = sanitize_text_field( $token );
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();

		if ( '' === $token ) {
			return new WP_Error( 'crpcrm_pending_upload_invalid', __( 'فایل موقت معتبر نیست.', 'customer-request-portal-crm' ) );
		}

		$pending = self::get_pending_upload( $token );
		if ( ! $pending ) {
			self::finalize_pending_upload( $token );
			return new WP_Error( 'crpcrm_pending_upload_missing', __( 'فایل موقت پیدا نشد یا منقضی شده است.', 'customer-request-portal-crm' ) );
		}

		if ( ! self::pending_upload_belongs_to_actor( $pending, $field_key, $user_id ) ) {
			return new WP_Error( 'crpcrm_pending_upload_forbidden', __( 'اجازه حذف این فایل را ندارید.', 'customer-request-portal-crm' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$path = self::resolve_uploaded_file_path( $pending );
		if ( $path && file_exists( $path ) ) {
			$deleted = false !== wp_delete_file( $path );
			if ( ! $deleted && file_exists( $path ) ) {
				$deleted = @unlink( $path );
			}
			if ( ! $deleted && file_exists( $path ) ) {
				return new WP_Error( 'crpcrm_pending_upload_delete_failed', __( 'حذف فایل موقت انجام نشد.', 'customer-request-portal-crm' ) );
			}
		}

		self::finalize_pending_upload( $token );

		return true;
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
			wp_schedule_event( CRPCRM_Helpers::current_timestamp() + HOUR_IN_SECONDS, 'twicedaily', 'crpcrm_pending_upload_cleanup' );
		}
	}

	public static function clear_pending_upload_cleanup() {
		wp_clear_scheduled_hook( 'crpcrm_pending_upload_cleanup' );
	}

	public static function cleanup_pending_uploads() {
		require_once ABSPATH . 'wp-admin/includes/file.php';

		$index = get_option( self::PENDING_UPLOAD_OPTION, array() );
		$index = is_array( $index ) ? $index : array();
		$now   = CRPCRM_Helpers::current_timestamp();

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

			$deleted = self::delete_local_uploaded_file( is_array( $pending ) ? $pending : $item );
			if ( ! $deleted && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[CRPCRM] pending_upload_cleanup_file_delete_failed: ' . wp_json_encode( array( 'token' => $token ) ) );
			}

			$attachment_id = absint( is_array( $pending ) && ! empty( $pending['attachment_id'] ) ? $pending['attachment_id'] : ( is_array( $item ) && ! empty( $item['attachment_id'] ) ? $item['attachment_id'] : 0 ) );
			if ( $attachment_id && function_exists( 'wp_delete_attachment' ) ) {
				wp_delete_attachment( $attachment_id, true );
			}

			delete_transient( self::pending_upload_transient_key( $token ) );
			unset( $index[ $token ] );
		}

		update_option( self::PENDING_UPLOAD_OPTION, $index, false );
		self::cleanup_daily_upload_usage();
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
				if ( $pending && self::pending_upload_belongs_to_actor( $pending ) ) {
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

	public static function finalize_record_uploaded_files( $value, $source_type = '', $source_id = 0, $field_key = '', $owner_user_id = 0 ) {
		$files   = self::normalize_uploaded_file_payload( $value );
		$final   = array();
		$user_id = $owner_user_id ? absint( $owner_user_id ) : get_current_user_id();

		foreach ( $files as $file ) {
			$file  = is_array( $file ) ? $file : array();
			$token = ! empty( $file['upload_token'] ) ? sanitize_text_field( $file['upload_token'] ) : '';
			if ( $token ) {
				$pending = self::get_pending_upload( $token );
				if ( ! $pending || ! self::pending_upload_belongs_to_actor( $pending, $field_key, $user_id ) ) {
					continue;
				}

				$file = self::normalize_single_uploaded_file_meta( $pending );
				self::finalize_pending_upload( $token );
			}

			unset( $file['upload_token'], $file['status'], $file['current_user_id'], $file['created_at'], $file['expires_at'] );
			if ( $field_key ) {
				$file['field_key'] = sanitize_key( $field_key );
			}
			if ( $source_type ) {
				$file['source_type'] = sanitize_key( $source_type );
			}
			if ( $source_id ) {
				$file['source_id'] = absint( $source_id );
			}
			$final[] = self::normalize_single_uploaded_file_meta( $file );
		}

		return $final;
	}

	public static function finalize_record_uploaded_files_strict( $value, $source_type = '', $source_id = 0, $field_key = '', $owner_user_id = 0 ) {
		$files          = self::normalize_uploaded_file_payload( $value );
		$final          = array();
		$finalize_tokens = array();
		$user_id        = $owner_user_id ? absint( $owner_user_id ) : get_current_user_id();

		foreach ( $files as $file ) {
			$file  = is_array( $file ) ? $file : array();
			$token = ! empty( $file['upload_token'] ) ? sanitize_text_field( $file['upload_token'] ) : '';
			if ( $token ) {
				$pending = self::get_pending_upload( $token );
				if ( ! $pending ) {
					return new WP_Error( 'crpcrm_pending_upload_missing', __( 'فایل انتخاب‌شده پیدا نشد یا منقضی شده است. لطفاً دوباره فایل را بارگذاری کنید.', 'customer-request-portal-crm' ) );
				}

				if ( ! self::pending_upload_belongs_to_actor( $pending, $field_key, $user_id ) ) {
					return new WP_Error( 'crpcrm_pending_upload_forbidden', __( 'اجازه استفاده از این فایل موقت را ندارید.', 'customer-request-portal-crm' ) );
				}

				$file = self::normalize_single_uploaded_file_meta( $pending );
				$finalize_tokens[] = $token;
			}

			unset( $file['upload_token'], $file['status'], $file['current_user_id'], $file['created_at'], $file['expires_at'] );
			if ( $field_key ) {
				$file['field_key'] = sanitize_key( $field_key );
			}
			if ( $source_type ) {
				$file['source_type'] = sanitize_key( $source_type );
			}
			if ( $source_id ) {
				$file['source_id'] = absint( $source_id );
			}

			$final[] = self::normalize_single_uploaded_file_meta( $file );
		}

		foreach ( $finalize_tokens as $token ) {
			self::finalize_pending_upload( $token );
		}

		return $final;
	}

	public static function get_uploaded_file_payload( $value ) {
		return self::normalize_uploaded_file_payload( $value );
	}

	public static function validate_uploaded_file_payload( $files ) {
		return self::validate_uploaded_file_collection( $files );
	}

	public static function normalize_uploaded_file_meta( $file ) {
		return self::normalize_single_uploaded_file_meta( $file );
	}

	public static function get_pending_upload_meta( $token ) {
		return self::get_pending_upload( $token );
	}

	public static function get_pending_upload_health_stats() {
		$index   = get_option( self::PENDING_UPLOAD_OPTION, array() );
		$index   = is_array( $index ) ? $index : array();
		$now     = CRPCRM_Helpers::current_timestamp();
		$expired = 0;

		foreach ( $index as $item ) {
			$expires = absint( is_array( $item ) && isset( $item['expires_at'] ) ? $item['expires_at'] : 0 );
			if ( $expires && $expires <= $now ) {
				++$expired;
			}
		}

		$usage = self::get_daily_upload_usage_option();

		return array(
			'pending_total'       => count( $index ),
			'expired_pending'     => $expired,
			'daily_usage_days'    => count( $usage ),
			'daily_usage_entries' => strlen( wp_json_encode( $usage ) ),
		);
	}

	public static function pending_upload_belongs_to_actor( $pending, $field_key = '', $user_id = 0 ) {
		$pending = is_array( $pending ) ? $pending : array();
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		$current_user_id = absint( $pending['current_user_id'] ?? 0 );
		$current_hash    = sanitize_text_field( $pending['client_hash'] ?? '' );
		$pending_field   = sanitize_key( $pending['field_key'] ?? '' );
		$field_key       = sanitize_key( $field_key );

		if ( $current_user_id > 0 && $current_user_id !== $user_id ) {
			return false;
		}

		if ( 0 === $current_user_id && $current_hash && $current_hash !== self::get_upload_client_hash() ) {
			return false;
		}

		if ( $field_key && $pending_field && $field_key !== $pending_field ) {
			return false;
		}

		return true;
	}

	public static function resolve_uploaded_file_real_path( $file ) {
		return self::resolve_uploaded_file_path( $file );
	}

	private static function validate_uploaded_file_collection( $files ) {
		$files = self::normalize_uploaded_file_payload( $files );
		if ( count( $files ) > self::MAX_FILES_PER_FIELD ) {
			return new WP_Error( 'crpcrm_upload_count', sprintf( __( 'حداکثر %d فایل مجاز است.', 'customer-request-portal-crm' ), self::MAX_FILES_PER_FIELD ) );
		}

		if ( self::sum_uploaded_files_size( $files ) > self::MAX_TOTAL_REQUEST_SIZE ) {
			return new WP_Error( 'crpcrm_upload_total_size', sprintf( __( 'مجموع حجم فایل‌ها نباید بیشتر از %s باشد.', 'customer-request-portal-crm' ), size_format( self::MAX_TOTAL_REQUEST_SIZE, 0 ) ) );
		}

		return true;
	}

	private static function validate_incoming_upload_limits( $incoming_size, $current_file_count, $current_total_size ) {
		if ( ( $current_file_count + 1 ) > self::MAX_FILES_PER_FIELD ) {
			return new WP_Error( 'crpcrm_upload_count', sprintf( __( 'حداکثر %d فایل مجاز است.', 'customer-request-portal-crm' ), self::MAX_FILES_PER_FIELD ) );
		}

		if ( ( $current_total_size + $incoming_size ) > self::MAX_TOTAL_REQUEST_SIZE ) {
			return new WP_Error( 'crpcrm_upload_total_size', sprintf( __( 'مجموع حجم فایل‌ها نباید بیشتر از %s باشد.', 'customer-request-portal-crm' ), size_format( self::MAX_TOTAL_REQUEST_SIZE, 0 ) ) );
		}

		return self::validate_daily_upload_limits( $incoming_size );
	}

	private static function validate_daily_upload_limits( $incoming_size ) {
		$usage = self::get_daily_upload_usage();
		foreach ( self::get_daily_upload_actor_keys() as $actor_key ) {
			$stats = $usage[ $actor_key ] ?? array( 'count' => 0, 'size' => 0 );
			if ( ( absint( $stats['count'] ?? 0 ) + 1 ) > self::DAILY_UPLOAD_FILE_LIMIT ) {
				return new WP_Error( 'crpcrm_upload_daily_count', __( 'تعداد بارگذاری‌های امروز از حد مجاز بیشتر شده است. لطفاً بعداً دوباره تلاش کنید.', 'customer-request-portal-crm' ) );
			}
			if ( ( absint( $stats['size'] ?? 0 ) + $incoming_size ) > self::DAILY_UPLOAD_SIZE_LIMIT ) {
				return new WP_Error( 'crpcrm_upload_daily_size', sprintf( __( 'حجم بارگذاری‌های امروز به حد مجاز %s رسیده است.', 'customer-request-portal-crm' ), size_format( self::DAILY_UPLOAD_SIZE_LIMIT, 0 ) ) );
			}
		}

		return true;
	}

	private static function record_daily_upload_usage( $size ) {
		$size  = max( 0, absint( $size ) );
		$usage = self::get_daily_upload_usage_option();
		$today = CRPCRM_Helpers::now()->format( 'Y-m-d' );
		if ( ! isset( $usage[ $today ] ) || ! is_array( $usage[ $today ] ) ) {
			$usage[ $today ] = array();
		}

		foreach ( self::get_daily_upload_actor_keys() as $actor_key ) {
			if ( ! isset( $usage[ $today ][ $actor_key ] ) || ! is_array( $usage[ $today ][ $actor_key ] ) ) {
				$usage[ $today ][ $actor_key ] = array(
					'count' => 0,
					'size'  => 0,
				);
			}
			$usage[ $today ][ $actor_key ]['count'] = absint( $usage[ $today ][ $actor_key ]['count'] ?? 0 ) + 1;
			$usage[ $today ][ $actor_key ]['size']  = absint( $usage[ $today ][ $actor_key ]['size'] ?? 0 ) + $size;
		}

		update_option( self::DAILY_UPLOAD_OPTION, $usage, false );
	}

	private static function get_daily_upload_usage() {
		$usage = self::get_daily_upload_usage_option();
		$today = CRPCRM_Helpers::now()->format( 'Y-m-d' );
		return isset( $usage[ $today ] ) && is_array( $usage[ $today ] ) ? $usage[ $today ] : array();
	}

	private static function get_daily_upload_usage_option() {
		$usage = get_option( self::DAILY_UPLOAD_OPTION, array() );
		$usage = is_array( $usage ) ? $usage : array();
		$today = CRPCRM_Helpers::now()->setTime( 0, 0, 0 );

		foreach ( $usage as $date => $stats ) {
			try {
				$entry_date = new DateTimeImmutable( $date . ' 00:00:00', wp_timezone() );
			} catch ( Exception $exception ) {
				unset( $usage[ $date ] );
				continue;
			}

			if ( $entry_date < $today->sub( new DateInterval( 'P2D' ) ) ) {
				unset( $usage[ $date ] );
			}
		}

		return $usage;
	}

	private static function cleanup_daily_upload_usage() {
		update_option( self::DAILY_UPLOAD_OPTION, self::get_daily_upload_usage_option(), false );
	}

	private static function get_daily_upload_actor_keys() {
		$keys = array();
		$user_id = get_current_user_id();
		if ( $user_id > 0 ) {
			$keys[] = 'user:' . absint( $user_id );
		}

		$client_hash = self::get_upload_client_hash();
		if ( '' !== $client_hash ) {
			$keys[] = 'ip:' . $client_hash;
		}

		return array_values( array_unique( array_filter( $keys ) ) );
	}

	private static function get_upload_client_hash() {
		$ip = self::get_request_upload_ip();
		return '' !== $ip ? hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) ) : '';
	}

	private static function get_request_upload_ip() {
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	}

	private static function sum_uploaded_files_size( $files ) {
		$total = 0;
		foreach ( self::normalize_uploaded_file_payload( $files ) as $file ) {
			$total += absint( $file['size'] ?? 0 );
		}

		return $total;
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

	private static function delete_local_uploaded_file( $file ) {
		$path = self::resolve_uploaded_file_path( $file );
		if ( ! $path || ! file_exists( $path ) ) {
			return true;
		}

		$deleted = false !== wp_delete_file( $path );
		if ( ! $deleted && file_exists( $path ) ) {
			$deleted = @unlink( $path );
		}

		return $deleted || ! file_exists( $path );
	}

	private static function normalize_upload_error_message( $message ) {
		$message = is_string( $message ) ? trim( $message ) : '';
		if ( '' === $message ) {
			return __( 'خطایی در بارگذاری فایل رخ داد.', 'customer-request-portal-crm' );
		}


		return $message;
	}
}

<?php
/**
 * Public-facing bootstrap for future portal features.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_Public {
	private $portal_shortcode;
	private $landing_tracking_service;

	public function __construct() {
		$this->portal_shortcode = new CRPCRM_Portal_Shortcode();
		$this->landing_tracking_service = new CRPCRM_Landing_Tracking_Service();
	}

	public function register_hooks() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_crpcrm_search_products', array( $this, 'handle_search_products' ) );
		add_action( 'wp_ajax_crpcrm_upload_request_file', array( $this, 'handle_upload_request_file' ) );
		add_action( 'wp_ajax_crpcrm_delete_pending_request_file', array( $this, 'handle_delete_pending_request_file' ) );
		$this->portal_shortcode->register_hooks();
		$this->landing_tracking_service->register_hooks();
	}

	public function enqueue_assets() {
		if ( ! CRPCRM_Feature_Manager::is_enabled( 'portal' ) ) {
			return;
		}

		$public_css_path = CRPCRM_PLUGIN_DIR . 'assets/css/public.css';
		$public_js_path  = CRPCRM_PLUGIN_DIR . 'assets/js/public.js';
		$request_preview_css_path = CRPCRM_PLUGIN_DIR . 'assets/css/request-file-preview.css';
		$request_preview_js_path  = CRPCRM_PLUGIN_DIR . 'assets/js/request-file-preview.js';
		wp_register_style( 'crpcrm-public', CRPCRM_PLUGIN_URL . 'assets/css/public.css', array(), file_exists( $public_css_path ) ? filemtime( $public_css_path ) : CRPCRM_VERSION );
		wp_register_script( 'crpcrm-public', CRPCRM_PLUGIN_URL . 'assets/js/public.js', array(), file_exists( $public_js_path ) ? filemtime( $public_js_path ) : CRPCRM_VERSION, true );
		wp_register_style( 'crpcrm-request-file-preview', CRPCRM_PLUGIN_URL . 'assets/css/request-file-preview.css', array(), file_exists( $request_preview_css_path ) ? filemtime( $request_preview_css_path ) : CRPCRM_VERSION );
		wp_register_script( 'crpcrm-request-file-preview', CRPCRM_PLUGIN_URL . 'assets/js/request-file-preview.js', array(), file_exists( $request_preview_js_path ) ? filemtime( $request_preview_js_path ) : CRPCRM_VERSION, true );
		wp_localize_script(
			'crpcrm-public',
			'crpcrmPublic',
			array(
				'ajaxUrl'              => admin_url( 'admin-ajax.php' ),
				'productSearchNonce'   => wp_create_nonce( 'crpcrm_search_products' ),
				'fileUploadNonce'      => wp_create_nonce( 'crpcrm_upload_request_file' ),
				'fileDeletePendingNonce' => wp_create_nonce( 'crpcrm_delete_pending_request_file' ),
				'fileDeletePendingError' => 'حذف فایل انجام نشد.',
				'fileDeletePendingFallback' => 'حذف این فایل در این مرورگر انجام نمی‌شود.',
				'productSearchMin'     => 2,
				'productSearchEmpty'   => 'محصولی پیدا نشد.',
				'productSearchLoading' => 'در حال جستجو...',
				'productRemoveLabel'   => 'حذف محصول',
				'fileUploadLoading'    => 'در حال بارگذاری...',
				'fileUploadFallback'   => 'بارگذاری خودکار در این مرورگر پشتیبانی نمی‌شود. فایل را همراه فرم ارسال کنید.',
				'fileUploadInvalid'    => 'نوع فایل مجاز نیست.',
				'fileUploadTooLarge'   => 'حجم فایل بیش از حد مجاز است.',
				'fileUploadNetwork'    => 'ارتباط با سرور برقرار نشد.',
				'fileUploadMaxSize'    => CRPCRM_Dynamic_Form_Renderer::MAX_FILE_SIZE,
				'fileUploadMaxFiles'   => CRPCRM_Dynamic_Form_Renderer::MAX_FILES_PER_FIELD,
				'fileUploadMaxTotalSize' => CRPCRM_Dynamic_Form_Renderer::MAX_TOTAL_REQUEST_SIZE,
				'fileUploadError'      => 'بارگذاری فایل انجام نشد.',
				'fileUploadedLabel'    => 'بارگذاری شده',
				'filePreviewLabel'     => 'مشاهده فایل',
				'fileDownloadLabel'    => 'دانلود فایل',
			)
		);
	}

	public function handle_search_products() {
		check_ajax_referer( 'crpcrm_search_products', 'nonce' );

		if ( ! is_user_logged_in() || ! $this->current_user_can_access_request_ajax() ) {
			wp_send_json_error( array( 'message' => 'unauthorized' ), 403 );
		}

		$term        = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';
		$term_length = function_exists( 'mb_strlen' ) ? mb_strlen( $term ) : strlen( $term );
		if ( $term_length < 2 || ! post_type_exists( 'product' ) ) {
			wp_send_json_success( array() );
		}

		$products = get_posts(
			array(
				'post_type'              => 'product',
				'post_status'            => 'publish',
				'posts_per_page'         => 10,
				's'                      => $term,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'suppress_filters'       => false,
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'update_post_meta_cache' => false,
			)
		);

		$results = array();
		foreach ( $products as $product ) {
			$results[] = array(
				'id'        => absint( $product->ID ),
				'name'      => sanitize_text_field( get_the_title( $product->ID ) ),
				'thumbnail' => get_the_post_thumbnail_url( $product->ID, 'thumbnail' ) ? esc_url_raw( get_the_post_thumbnail_url( $product->ID, 'thumbnail' ) ) : '',
			);
		}

		wp_send_json_success( $results );
	}

	public function handle_upload_request_file() {
		check_ajax_referer( 'crpcrm_upload_request_file', 'nonce' );

		if ( ! is_user_logged_in() || ! $this->current_user_can_upload_request_files() ) {
			wp_send_json_error( array( 'message' => 'unauthorized' ), 403 );
		}

		if ( empty( $_FILES['file'] ) || ! is_array( $_FILES['file'] ) ) {
			wp_send_json_error( array( 'message' => 'missing_file' ), 400 );
		}

		$field_key = isset( $_POST['field_key'] ) ? sanitize_key( wp_unslash( $_POST['field_key'] ) ) : '';
		$file      = CRPCRM_Dynamic_Form_Renderer::handle_async_upload(
			$_FILES['file'],
			$field_key,
			array(
				'current_file_count' => isset( $_POST['current_file_count'] ) ? absint( $_POST['current_file_count'] ) : 0,
				'current_total_size' => isset( $_POST['current_total_size'] ) ? absint( $_POST['current_total_size'] ) : 0,
			)
		);
		if ( is_wp_error( $file ) ) {
			wp_send_json_error( array( 'message' => $file->get_error_message() ), 400 );
		}

		wp_send_json_success( $file );
	}

	public function handle_delete_pending_request_file() {
		check_ajax_referer( 'crpcrm_delete_pending_request_file', 'nonce' );

		if ( ! is_user_logged_in() || ! $this->current_user_can_upload_request_files() ) {
			wp_send_json_error( array( 'message' => 'unauthorized' ), 403 );
		}

		$token     = isset( $_POST['upload_token'] ) ? sanitize_text_field( wp_unslash( $_POST['upload_token'] ) ) : '';
		$field_key = isset( $_POST['field_key'] ) ? sanitize_key( wp_unslash( $_POST['field_key'] ) ) : '';

		$deleted = CRPCRM_Dynamic_Form_Renderer::delete_pending_upload( $token, $field_key, get_current_user_id() );
		if ( is_wp_error( $deleted ) ) {
			wp_send_json_error( array( 'message' => $deleted->get_error_message() ), 400 );
		}

		wp_send_json_success( array( 'deleted' => true ) );
	}

	private function current_user_can_upload_request_files() {
		return $this->current_user_can_access_request_ajax();
	}

	private function current_user_can_access_request_ajax() {
		if ( current_user_can( 'manage_options' ) || current_user_can( 'crpcrm_manage_plugin' ) || current_user_can( 'crpcrm_manage_settings' ) || current_user_can( 'crpcrm_manage_requests' ) || current_user_can( 'crpcrm_assign_requests' ) || current_user_can( 'crpcrm_view_all_requests' ) || current_user_can( 'crpcrm_manage_staff_portal' ) ) {
			return true;
		}

		if ( current_user_can( 'crpcrm_use_staff_portal' ) ) {
			return CRPCRM_Feature_Manager::is_enabled( 'staff' );
		}

		if ( current_user_can( 'crpcrm_use_customer_portal' ) ) {
			return CRPCRM_Feature_Manager::is_enabled( 'portal' );
		}

		if ( current_user_can( 'crpcrm_create_requests' ) ) {
			return CRPCRM_Feature_Manager::is_enabled( 'form_builder' );
		}

		return false;
	}
}

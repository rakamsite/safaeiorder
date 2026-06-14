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

	public function __construct() {
		$this->portal_shortcode = new CRPCRM_Portal_Shortcode();
	}

	public function register_hooks() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_crpcrm_search_products', array( $this, 'handle_search_products' ) );
		$this->portal_shortcode->register_hooks();
	}

	public function enqueue_assets() {
		if ( ! CRPCRM_Feature_Manager::is_enabled( 'portal' ) ) {
			return;
		}

		$public_css_path = CRPCRM_PLUGIN_DIR . 'assets/css/public.css';
		$public_js_path  = CRPCRM_PLUGIN_DIR . 'assets/js/public.js';
		wp_register_style( 'crpcrm-public', CRPCRM_PLUGIN_URL . 'assets/css/public.css', array(), file_exists( $public_css_path ) ? filemtime( $public_css_path ) : CRPCRM_VERSION );
		wp_register_script( 'crpcrm-public', CRPCRM_PLUGIN_URL . 'assets/js/public.js', array(), file_exists( $public_js_path ) ? filemtime( $public_js_path ) : CRPCRM_VERSION, true );
		wp_localize_script(
			'crpcrm-public',
			'crpcrmPublic',
			array(
				'ajaxUrl'              => admin_url( 'admin-ajax.php' ),
				'productSearchNonce'   => wp_create_nonce( 'crpcrm_search_products' ),
				'productSearchMin'     => 2,
				'productSearchEmpty'   => 'محصولی پیدا نشد.',
				'productSearchLoading' => 'در حال جستجو...',
				'productRemoveLabel'   => 'حذف محصول',
			)
		);
	}

	public function handle_search_products() {
		check_ajax_referer( 'crpcrm_search_products', 'nonce' );

		if ( ! is_user_logged_in() && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'unauthorized' ), 403 );
		}

		$term = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';
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
}

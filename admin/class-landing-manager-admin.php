<?php
/**
 * Admin UI for landing links.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_Landing_Manager_Admin {
	private $manager;

	public function __construct( CRPCRM_Landing_Manager $manager = null ) {
		$this->manager = $manager ? $manager : new CRPCRM_Landing_Manager();
	}

	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 25 );
		add_action( 'admin_init', array( $this, 'guard_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_crpcrm_save_landing_link', array( $this, 'handle_save' ) );
		add_action( 'admin_post_crpcrm_landing_action', array( $this, 'handle_action' ) );
		add_action( 'wp_ajax_crpcrm_search_landing_targets', array( $this, 'handle_search_targets' ) );
	}

	public function register_menu() {
		if ( ! CRPCRM_Feature_Manager::is_enabled( 'landing_manager' ) ) {
			return;
		}

		$parent_slug = CRPCRM_Feature_Manager::is_crm_ui_enabled() ? 'crpcrm-dashboard' : 'crpcrm-settings';
		if ( ! current_user_can( 'crpcrm_use_staff_portal' ) && current_user_can( 'crpcrm_manage_landings' ) ) {
			$parent_slug = 'crpcrm-settings';
		}
		$capability  = current_user_can( 'crpcrm_manage_landings' ) ? 'crpcrm_manage_landings' : 'manage_options';

		add_submenu_page(
			$parent_slug,
			'مدیریت لندینگ‌ها',
			'مدیریت لندینگ‌ها',
			$capability,
			'crpcrm-landings',
			array( $this, 'render_page' )
		);
	}

	public function guard_page() {
		if ( ! is_admin() || wp_doing_ajax() || ! isset( $_GET['page'] ) || 'crpcrm-landings' !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
			return;
		}

		if ( ! CRPCRM_Feature_Manager::is_enabled( 'landing_manager' ) ) {
			wp_die( esc_html( CRPCRM_Feature_Manager::disabled_message() ) );
		}

		if ( ! $this->can_manage_landings() ) {
			wp_die( esc_html( 'شما اجازه مدیریت لندینگ‌ها را ندارید.' ) );
		}
	}

	public function enqueue_assets( $hook_suffix ) {
		if ( false === strpos( $hook_suffix, 'crpcrm-landings' ) ) {
			return;
		}

		$css_path = CRPCRM_PLUGIN_DIR . 'assets/css/landing-manager.css';
		$js_path  = CRPCRM_PLUGIN_DIR . 'assets/js/landing-manager.js';

		wp_enqueue_style( 'crpcrm-admin-ui', CRPCRM_PLUGIN_URL . 'assets/css/admin-ui.css', array( 'crpcrm-admin' ), file_exists( CRPCRM_PLUGIN_DIR . 'assets/css/admin-ui.css' ) ? filemtime( CRPCRM_PLUGIN_DIR . 'assets/css/admin-ui.css' ) : CRPCRM_VERSION );
		wp_enqueue_style( 'crpcrm-landing-manager', CRPCRM_PLUGIN_URL . 'assets/css/landing-manager.css', array( 'crpcrm-admin-ui' ), file_exists( $css_path ) ? filemtime( $css_path ) : CRPCRM_VERSION );
		wp_enqueue_script( 'crpcrm-landing-manager', CRPCRM_PLUGIN_URL . 'assets/js/landing-manager.js', array( 'jquery' ), file_exists( $js_path ) ? filemtime( $js_path ) : CRPCRM_VERSION, true );
		wp_localize_script(
			'crpcrm-landing-manager',
			'crpcrmLandings',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'crpcrm_search_landing_targets' ),
				'copyLabel'      => 'کپی لینک',
				'copiedLabel'    => 'کپی شد',
				'searchingLabel' => 'در حال جستجو...',
				'emptyLabel'     => 'نتیجه‌ای پیدا نشد.',
				'selectLabel'    => 'انتخاب',
				'previewLabel'   => 'لینک نهایی',
				'debug'          => defined( 'WP_DEBUG' ) && WP_DEBUG,
			)
		);
	}

	public function render_page() {
		if ( ! $this->can_manage_landings() ) {
			wp_die( esc_html( 'شما اجازه مدیریت لندینگ‌ها را ندارید.' ) );
		}

		$page     = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$per_page = 20;
		$status   = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		if ( ! in_array( $status, array( '', 'active', 'inactive', 'archived' ), true ) ) {
			$status = '';
		}

		$list = $this->manager->list_landings(
			array(
				'status' => $status,
				'limit'  => $per_page,
				'offset' => ( $page - 1 ) * $per_page,
			)
		);

		$stats_map       = $this->manager->get_landing_stats_for_items( $list['items'] );
		$overview_stats  = $this->manager->get_overview_stats();
		$landing_id      = isset( $_GET['landing_id'] ) ? absint( $_GET['landing_id'] ) : 0;
		$landing         = $landing_id ? $this->manager->get( $landing_id ) : null;

		if ( $landing_id && ! $landing ) {
			$landing_id = 0;
		}

		$landing_stats = $landing_id ? $this->manager->get_landing_stats( $landing_id ) : array();

		include CRPCRM_PLUGIN_DIR . 'admin/views/landing-manager.php';
	}

	public function handle_save() {
		$this->verify_action( 'crpcrm_save_landing_link', 'crpcrm_landing_nonce' );

		$input      = isset( $_POST['crpcrm_landing'] ) && is_array( $_POST['crpcrm_landing'] ) ? wp_unslash( $_POST['crpcrm_landing'] ) : array();
		$landing_id = isset( $_POST['landing_id'] ) ? absint( $_POST['landing_id'] ) : 0;

		$result = $landing_id ? $this->manager->update( $landing_id, $input ) : $this->manager->create( $input );
		if ( is_wp_error( $result ) ) {
			$this->redirect(
				array(
					'landing_id'      => $landing_id,
					'landing-error'   => $result->get_error_code(),
					'landing-message' => $result->get_error_message(),
				)
			);
		}

		$this->redirect(
			array(
				'landing_id'    => absint( $result ),
				'landing-saved' => '1',
			)
		);
	}

	public function handle_action() {
		$this->verify_action( 'crpcrm_landing_action', 'crpcrm_landing_action_nonce' );

		$action_type = isset( $_POST['landing_action_type'] ) ? sanitize_key( wp_unslash( $_POST['landing_action_type'] ) ) : '';
		$landing_id  = isset( $_POST['landing_id'] ) ? absint( $_POST['landing_id'] ) : 0;

		if ( ! $landing_id ) {
			$this->redirect( array( 'landing-error' => 'missing-id' ) );
		}

		if ( 'archive' === $action_type ) {
			$result = $this->manager->delete_or_archive( $landing_id, get_current_user_id() );
		} elseif ( 'toggle' === $action_type ) {
			$landing    = $this->manager->get( $landing_id );
			$new_status = 'active';
			if ( $landing && 'active' === ( $landing['status'] ?? '' ) ) {
				$new_status = 'inactive';
			} elseif ( $landing && 'archived' === ( $landing['status'] ?? '' ) ) {
				$new_status = 'active';
			}
			$result = $this->manager->set_status( $landing_id, $new_status, get_current_user_id() );
		} else {
			$result = new WP_Error( 'invalid_action', 'عملیات نامعتبر است.' );
		}

		if ( is_wp_error( $result ) ) {
			$this->redirect(
				array(
					'landing-error'   => $result->get_error_code(),
					'landing-message' => $result->get_error_message(),
				)
			);
		}

		$this->redirect( array( 'landing-updated' => '1' ) );
	}

	public function handle_search_targets() {
		check_ajax_referer( 'crpcrm_search_landing_targets', 'nonce' );

		if ( ! $this->can_manage_landings() ) {
			wp_send_json_error( array( 'message' => 'unauthorized' ), 403 );
		}

		$term = isset( $_GET['term'] ) ? wp_unslash( $_GET['term'] ) : '';
		wp_send_json_success( $this->manager->search_destinations( $term ) );
	}

	private function verify_action( $action, $nonce_name ) {
		if ( ! $this->can_manage_landings() ) {
			wp_die( esc_html( 'شما اجازه مدیریت لندینگ‌ها را ندارید.' ) );
		}

		check_admin_referer( $action, $nonce_name );

		if ( ! CRPCRM_Feature_Manager::is_enabled( 'landing_manager' ) ) {
			wp_die( esc_html( CRPCRM_Feature_Manager::disabled_message() ) );
		}
	}

	private function can_manage_landings() {
		return current_user_can( 'crpcrm_manage_landings' ) || current_user_can( 'manage_options' );
	}

	private function redirect( $args = array() ) {
		wp_safe_redirect( add_query_arg( array_merge( array( 'page' => 'crpcrm-landings' ), $args ), admin_url( 'admin.php' ) ) );
		exit;
	}
}

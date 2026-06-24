<?php
/**
 * Lightweight wp-admin CRUD for custom forms.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_Form_Builder_Admin {
	private $repository;

	public function __construct( CRPCRM_Form_Builder_Repository $repository = null ) {
		$this->repository = $repository ? $repository : new CRPCRM_Form_Builder_Repository();
	}

	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 20 );
		add_action( 'admin_init', array( $this, 'guard_page' ) );
		add_action( 'admin_post_crpcrm_save_custom_form', array( $this, 'handle_save' ) );
		add_action( 'admin_post_crpcrm_delete_custom_form', array( $this, 'handle_delete' ) );
		add_action( 'admin_post_crpcrm_restore_default_forms', array( $this, 'handle_restore_defaults' ) );
	}

	public function register_menu() {
		if ( ! CRPCRM_Feature_Manager::is_enabled( 'form_builder' ) ) {
			return;
		}
		$parent_slug = CRPCRM_Feature_Manager::is_crm_ui_enabled() ? 'crpcrm-dashboard' : 'crpcrm-settings';
		add_submenu_page( $parent_slug, 'فرم‌ساز', 'فرم‌ساز', 'manage_options', 'crpcrm-form-builder', array( $this, 'render_page' ) );
	}

	public function guard_page() {
		if ( ! is_admin() || wp_doing_ajax() || ! isset( $_GET['page'] ) || 'crpcrm-form-builder' !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
			return;
		}
		if ( ! CRPCRM_Feature_Manager::is_enabled( 'form_builder' ) ) {
			wp_die( esc_html( CRPCRM_Feature_Manager::disabled_message() ) );
		}
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html( 'شما اجازه مدیریت فرم‌ها را ندارید.' ) );
		}
		$form_id = isset( $_GET['form_id'] ) ? sanitize_key( wp_unslash( $_GET['form_id'] ) ) : '';
		$form    = $form_id ? $this->repository->get_form( $form_id ) : null;
		$forms   = $this->repository->get_forms();
		include CRPCRM_PLUGIN_DIR . 'admin/views/form-builder.php';
	}

	public function handle_save() {
		$this->verify_action( 'crpcrm_save_custom_form', 'crpcrm_form_builder_nonce' );
		$input       = isset( $_POST['crpcrm_form'] ) && is_array( $_POST['crpcrm_form'] ) ? wp_unslash( $_POST['crpcrm_form'] ) : array();
		$original_id = isset( $_POST['original_form_id'] ) ? sanitize_key( wp_unslash( $_POST['original_form_id'] ) ) : '';
		$raw_form_id = isset( $input['form_id'] ) ? trim( (string) $input['form_id'] ) : '';
		if ( '' === $raw_form_id || ! preg_match( '/^[A-Za-z0-9_-]+$/', $raw_form_id ) ) {
			$this->redirect( array( 'form_id' => $original_id, 'form-error' => 'invalid-form-id' ) );
		}
		$form        = $this->repository->sanitize_form( $input );
		$existing    = $this->repository->get_form( $form['form_id'] );
		if ( $existing && $original_id !== $form['form_id'] ) {
			$this->redirect( array( 'form-error' => 'duplicate-id' ) );
		}
		if ( $original_id && $original_id !== $form['form_id'] ) {
			$this->redirect( array( 'form_id' => $original_id, 'form-error' => 'immutable-id' ) );
		}
		$result = $this->repository->save_form( $form, $original_id );
		if ( is_wp_error( $result ) ) {
			$this->redirect( array( 'form_id' => $original_id, 'form-error' => 'invalid-schema', 'form-error-message' => $result->get_error_message() ) );
		}
		$this->redirect( array( 'form_id' => $form['form_id'], 'form-saved' => '1' ) );
	}

	public function handle_delete() {
		$this->verify_action( 'crpcrm_delete_custom_form', 'crpcrm_form_builder_nonce' );
		$form_id     = isset( $_POST['form_id'] ) ? sanitize_key( wp_unslash( $_POST['form_id'] ) ) : '';
		$was_default = isset( CRPCRM_Default_Form_Definitions::get_forms()[ $form_id ] );
		$result      = $this->repository->delete_form( $form_id );
		if ( is_wp_error( $result ) ) {
			$this->redirect( array( 'form-error' => 'delete-failed', 'form-error-message' => $result->get_error_message() ) );
		}
		$this->redirect( array( $was_default ? 'form-disabled' : 'form-deleted' => '1' ) );
	}

	public function handle_restore_defaults() {
		$this->verify_action( 'crpcrm_restore_default_forms', 'crpcrm_form_builder_nonce' );
		$restored = $this->repository->restore_default_forms();
		$this->redirect( array( 'form-defaults-restored' => $restored > 0 ? '1' : '0' ) );
	}

	private function verify_action( $action, $nonce_name ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html( 'شما اجازه مدیریت فرم‌ها را ندارید.' ) );
		}
		check_admin_referer( $action, $nonce_name );
		if ( ! CRPCRM_Feature_Manager::is_enabled( 'form_builder' ) ) {
			wp_die( esc_html( CRPCRM_Feature_Manager::disabled_message() ) );
		}
	}

	private function redirect( $args = array() ) {
		wp_safe_redirect( add_query_arg( array_merge( array( 'page' => 'crpcrm-form-builder' ), $args ), admin_url( 'admin.php' ) ) );
		exit;
	}
}

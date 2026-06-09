<?php
/**
 * Keeps CRM customer data in sync with WordPress user deletion.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_Customer_Deletion_Service {
	private $customer_repository;

	public function __construct( CRPCRM_Customer_Repository $customer_repository = null ) {
		$this->customer_repository = $customer_repository ? $customer_repository : new CRPCRM_Customer_Repository();
	}

	public function register_hooks() {
		add_action( 'deleted_user', array( $this, 'delete_customer_for_user' ), 10, 1 );
	}

	public function delete_customer_for_user( $user_id ) {
		$customer = $this->customer_repository->find_by_user_id( $user_id );
		if ( ! $customer ) {
			return;
		}

		$result = $this->customer_repository->delete_permanently( $customer['id'] );
		if ( is_wp_error( $result ) ) {
			CRPCRM_Logger::error( 'customer_cleanup_after_user_deletion_failed', 'customer_cleanup_after_user_deletion_failed', array( 'customer_id' => absint( $customer['id'] ), 'deleted_user_id' => absint( $user_id ), 'reason' => $result->get_error_code() ) );
			return;
		}

		CRPCRM_Logger::info( 'customer_deleted_after_wordpress_user_deletion', 'customer_deleted_after_wordpress_user_deletion', array( 'customer_id' => absint( $customer['id'] ), 'deleted_user_id' => absint( $user_id ) ) );
	}
}

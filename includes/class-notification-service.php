<?php
/**
 * Central plugin notification service.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_Notification_Service {
	private $table;

	public function __construct() {
		$this->table = CRPCRM_DB::table( 'notifications' );
	}

	public function register_hooks() {
		add_action( 'admin_post_crpcrm_notification_action', array( $this, 'handle_action' ) );
	}

	public static function is_enabled() {
		return CRPCRM_Feature_Manager::is_enabled( 'notifications' );
	}

	public function create_for_user( $user_id, $type, $title, $message = '', $target_url = '', $object_type = '', $object_id = 0 ) {
		global $wpdb;

		if ( ! self::is_enabled() ) {
			return 0;
		}

		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return 0;
		}

		$wpdb->insert(
			$this->table,
			array(
				'user_id'     => $user_id,
				'type'        => sanitize_key( $type ),
				'title'       => sanitize_text_field( $title ),
				'message'     => sanitize_textarea_field( $message ),
				'target_url'  => esc_url_raw( $target_url ),
				'object_type' => sanitize_key( $object_type ),
				'object_id'   => absint( $object_id ),
				'created_at'  => CRPCRM_Helpers::current_datetime(),
			)
		);

		return absint( $wpdb->insert_id );
	}

	public function create_for_users( $user_ids, $type, $title, $message = '', $target_url = '', $object_type = '', $object_id = 0 ) {
		foreach ( array_values( array_unique( array_map( 'absint', (array) $user_ids ) ) ) as $user_id ) {
			$this->create_for_user( $user_id, $type, $title, $message, $target_url, $object_type, $object_id );
		}
	}

	public function list_for_user( $user_id, $args = array() ) {
		global $wpdb;

		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return array();
		}

		$args   = wp_parse_args(
			$args,
			array(
				'limit'  => 50,
				'offset' => 0,
				'unread' => null,
			)
		);
		$where  = array( 'user_id = %d' );
		$values = array( $user_id );

		if ( null !== $args['unread'] ) {
			$where[]  = 'is_read = %d';
			$values[] = $args['unread'] ? 0 : 1;
		}

		$values[] = max( 1, absint( $args['limit'] ) );
		$values[] = max( 0, absint( $args['offset'] ) );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY created_at DESC LIMIT %d OFFSET %d',
				$values
			),
			ARRAY_A
		);
	}

	public function count_unread_for_user( $user_id ) {
		global $wpdb;

		$user_id = absint( $user_id );
		if ( ! $user_id || ! self::is_enabled() ) {
			return 0;
		}

		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->table} WHERE user_id = %d AND is_read = 0", $user_id ) );
	}

	public function mark_read( $notification_id, $user_id ) {
		global $wpdb;

		return false !== $wpdb->update(
			$this->table,
			array(
				'is_read' => 1,
				'read_at' => CRPCRM_Helpers::current_datetime(),
			),
			array(
				'id'      => absint( $notification_id ),
				'user_id' => absint( $user_id ),
			)
		);
	}

	public function mark_all_read( $user_id ) {
		global $wpdb;

		return false !== $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->table} SET is_read = 1, read_at = %s WHERE user_id = %d AND is_read = 0",
				CRPCRM_Helpers::current_datetime(),
				absint( $user_id )
			)
		);
	}

	public function get( $notification_id, $user_id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d AND user_id = %d", absint( $notification_id ), absint( $user_id ) ),
			ARRAY_A
		);
	}

	public function handle_action() {
		if ( ! is_user_logged_in() || ! self::is_enabled() ) {
			wp_die( esc_html__( 'دسترسی نامعتبر است.', 'customer-request-portal-crm' ) );
		}

		check_admin_referer( 'crpcrm_notification_action', 'crpcrm_notification_nonce' );

		$user_id         = get_current_user_id();
		$action_type     = isset( $_POST['notification_action_type'] ) ? sanitize_key( wp_unslash( $_POST['notification_action_type'] ) ) : '';
		$notification_id = isset( $_POST['notification_id'] ) ? absint( $_POST['notification_id'] ) : 0;

		if ( 'mark_all_read' === $action_type ) {
			$this->mark_all_read( $user_id );
			wp_safe_redirect( admin_url( 'admin.php?page=crpcrm-notifications' ) );
			exit;
		}

		$notification = $this->get( $notification_id, $user_id );
		if ( ! $notification ) {
			wp_safe_redirect( admin_url( 'admin.php?page=crpcrm-notifications' ) );
			exit;
		}

		$this->mark_read( $notification_id, $user_id );
		$target = ! empty( $notification['target_url'] ) ? $notification['target_url'] : admin_url( 'admin.php?page=crpcrm-notifications' );
		wp_safe_redirect( $target );
		exit;
	}
}

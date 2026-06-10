<?php
/**
 * Request access policy helpers.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_Request_Access_Service {
	public static function can_access_admin_requests( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		return user_can( $user_id, 'crpcrm_view_all_requests' ) || user_can( $user_id, 'crpcrm_view_assigned_requests' ) || user_can( $user_id, 'crpcrm_manage_requests' );
	}

	public static function can_view_all( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		return user_can( $user_id, 'crpcrm_view_all_requests' ) || user_can( $user_id, 'crpcrm_manage_requests' ) || user_can( $user_id, 'manage_options' );
	}

	public static function can_view_request( $request, $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		if ( ! $request || ! self::can_access_admin_requests( $user_id ) ) {
			return false;
		}
		if ( self::can_view_all( $user_id ) ) {
			return true;
		}
		$owner_id = isset( $request['owner_id'] ) ? absint( $request['owner_id'] ) : 0;
		return 0 === $owner_id || $owner_id === $user_id;
	}

	public static function can_claim_request( $request, $user_id = 0 ) {
		if ( ! CRPCRM_Feature_Manager::is_enabled( 'staff' ) ) {
			return false;
		}

		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		if ( ! $request || ! user_can( $user_id, 'crpcrm_claim_requests' ) || ! self::can_view_request( $request, $user_id ) ) {
			return false;
		}
		$owner_id = isset( $request['owner_id'] ) ? absint( $request['owner_id'] ) : 0;
		return 0 === $owner_id && empty( $request['closed_at'] );
	}

	public static function can_manage_request( $request = null, $user_id = 0 ) {
		if ( ! CRPCRM_Feature_Manager::is_enabled( 'staff' ) ) {
			return false;
		}

		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		return user_can( $user_id, 'crpcrm_assign_requests' ) || user_can( $user_id, 'crpcrm_manage_requests' ) || user_can( $user_id, 'manage_options' );
	}

	public static function can_create_request( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		return user_can( $user_id, 'crpcrm_create_requests' ) || user_can( $user_id, 'manage_options' );
	}

	public static function can_delete_request( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		return user_can( $user_id, 'manage_options' );
	}
}

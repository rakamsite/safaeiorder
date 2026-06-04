<?php
/**
 * Role and capability management.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_Roles {
	public static function get_capabilities() {
		return array(
			'crpcrm_use_customer_portal',
			'crpcrm_view_assigned_requests',
			'crpcrm_claim_requests',
			'crpcrm_update_own_requests',
			'crpcrm_use_staff_portal',
			'crpcrm_view_all_requests',
			'crpcrm_manage_requests',
			'crpcrm_assign_requests',
			'crpcrm_view_reports',
			'crpcrm_manage_plugin',
			'crpcrm_manage_settings',
			'crpcrm_manage_staff_portal',
		);
	}

	public static function get_roles() {
		return array(
			'customer'          => array(
				'label' => 'مشتری پرتال',
				'caps'  => array( 'read', 'crpcrm_use_customer_portal' ),
			),
			'sales_agent'       => array(
				'label' => 'کارشناس فروش',
				'caps'  => array( 'read', 'crpcrm_view_assigned_requests', 'crpcrm_claim_requests', 'crpcrm_update_own_requests', 'crpcrm_use_staff_portal' ),
			),
			'sales_manager'     => array(
				'label' => 'مدیر فروش',
				'caps'  => array( 'read', 'crpcrm_view_all_requests', 'crpcrm_claim_requests', 'crpcrm_manage_requests', 'crpcrm_assign_requests', 'crpcrm_view_reports', 'crpcrm_use_staff_portal' ),
			),
			'internal_employee' => array(
				'label' => 'کارمند داخلی',
				'caps'  => array( 'read', 'crpcrm_use_staff_portal' ),
			),
			'crm_admin'         => array(
				'label' => 'مدیر CRM',
				'caps'  => array( 'read', 'crpcrm_manage_plugin', 'crpcrm_manage_settings', 'crpcrm_view_all_requests', 'crpcrm_claim_requests', 'crpcrm_manage_requests', 'crpcrm_assign_requests', 'crpcrm_view_reports', 'crpcrm_use_staff_portal', 'crpcrm_manage_staff_portal' ),
			),
		);
	}

	public static function add_roles_and_caps() {
		foreach ( self::get_roles() as $role_key => $role_config ) {
			$caps = array();
			foreach ( $role_config['caps'] as $cap ) {
				$caps[ $cap ] = true;
			}

			if ( get_role( $role_key ) ) {
				$role = get_role( $role_key );
				foreach ( $caps as $cap => $grant ) {
					$role->add_cap( $cap, $grant );
				}
			} else {
				add_role( $role_key, $role_config['label'], $caps );
			}
		}

		$administrator = get_role( 'administrator' );
		if ( $administrator ) {
			foreach ( self::get_capabilities() as $capability ) {
				$administrator->add_cap( $capability );
			}
		}
	}
}

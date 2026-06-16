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

	public static function register_hooks() {
		add_filter( 'user_has_cap', array( __CLASS__, 'ensure_staff_admin_capabilities' ), 1, 4 );
		add_action( 'admin_init', array( __CLASS__, 'restrict_staff_admin_pages' ), 1 );
		add_action( 'admin_menu', array( __CLASS__, 'restrict_staff_admin_menu' ), 999 );
		add_filter( 'show_admin_bar', array( __CLASS__, 'hide_staff_admin_bar' ) );
	}

	public static function is_staff_user( $user = null ) {
		if ( null === $user ) {
			$user = wp_get_current_user();
		} elseif ( is_numeric( $user ) ) {
			$user = get_userdata( absint( $user ) );
		}

		if ( ! $user instanceof WP_User ) {
			return false;
		}

		return (bool) array_intersect( array( 'sales_agent', 'sales_manager', 'internal_employee', 'crm_admin' ), (array) $user->roles );
	}

	public static function ensure_staff_admin_capabilities( $allcaps, $caps, $args, $user ) {
		if ( ! self::is_staff_user( $user ) ) {
			return $allcaps;
		}

		$role_configs = self::get_roles();
		foreach ( (array) $user->roles as $role_key ) {
			if ( empty( $role_configs[ $role_key ]['caps'] ) ) {
				continue;
			}
			foreach ( $role_configs[ $role_key ]['caps'] as $capability ) {
				$allcaps[ $capability ] = true;
			}
		}

		// Some sites redirect every user without this core capability away from wp-admin.
		// It is granted only during normal admin requests; non-CRM admin screens are blocked below.
		if ( is_admin() && ! wp_doing_ajax() ) {
			$allcaps['edit_posts'] = true;
		}

		return $allcaps;
	}

	public static function restrict_staff_admin_pages() {
		if ( ! self::is_staff_user() || wp_doing_ajax() ) {
			return;
		}

		global $pagenow;
		if ( 'admin-post.php' === $pagenow || self::is_crm_admin_page() ) {
			return;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=crpcrm-dashboard' ) );
		exit;
	}

	public static function restrict_staff_admin_menu() {
		if ( ! self::is_staff_user() ) {
			return;
		}

		global $menu, $submenu;
		$menu = array_values(
			array_filter(
				(array) $menu,
				function( $item ) {
					return isset( $item[2] ) && 'crpcrm-dashboard' === $item[2];
				}
			)
		);

		foreach ( array_keys( (array) $submenu ) as $parent_slug ) {
			if ( 'crpcrm-dashboard' !== $parent_slug ) {
				unset( $submenu[ $parent_slug ] );
			}
		}
	}

	public static function hide_staff_admin_bar( $show ) {
		return self::is_staff_user() ? false : $show;
	}

	private static function is_crm_admin_page() {
		global $pagenow;
		if ( 'admin.php' !== $pagenow ) {
			return false;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		return 0 === strpos( $page, 'crpcrm-' );
	}

	public static function get_capabilities() {
		return array(
			'crpcrm_use_customer_portal',
			'crpcrm_view_assigned_requests',
			'crpcrm_claim_requests',
			'crpcrm_create_requests',
			'crpcrm_update_own_requests',
			'crpcrm_use_staff_portal',
			'crpcrm_view_all_requests',
			'crpcrm_manage_requests',
			'crpcrm_assign_requests',
			'crpcrm_view_reports',
			'crpcrm_view_notifications',
			'crpcrm_manage_landings',
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
				'caps'  => array( 'read', 'crpcrm_view_assigned_requests', 'crpcrm_claim_requests', 'crpcrm_create_requests', 'crpcrm_update_own_requests', 'crpcrm_use_staff_portal', 'crpcrm_view_notifications' ),
			),
			'sales_manager'     => array(
				'label' => 'مدیر فروش',
				'caps'  => array( 'read', 'crpcrm_view_all_requests', 'crpcrm_claim_requests', 'crpcrm_create_requests', 'crpcrm_manage_requests', 'crpcrm_assign_requests', 'crpcrm_view_reports', 'crpcrm_use_staff_portal', 'crpcrm_manage_staff_portal', 'crpcrm_view_notifications', 'crpcrm_manage_landings' ),
			),
			'internal_employee' => array(
				'label' => 'کارمند داخلی',
				'caps'  => array( 'read', 'crpcrm_use_staff_portal', 'crpcrm_view_notifications' ),
			),
			'crm_admin'         => array(
				'label' => 'مدیر CRM',
				'caps'  => array( 'read', 'crpcrm_manage_plugin', 'crpcrm_manage_settings', 'crpcrm_view_all_requests', 'crpcrm_claim_requests', 'crpcrm_create_requests', 'crpcrm_manage_requests', 'crpcrm_assign_requests', 'crpcrm_view_reports', 'crpcrm_use_staff_portal', 'crpcrm_manage_staff_portal', 'crpcrm_view_notifications', 'crpcrm_manage_landings' ),
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

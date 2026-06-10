<?php
/**
 * Plugin Name: پرتال درخواست و CRM مشتریان
 * Plugin URI: https://example.com/
 * Description: اسکلت پایه پرتال مشتریان، ثبت درخواست، CRM فروش و پنل داخلی کارکنان.
 * Version: 0.1.5
 * Author: Safaei
 * Text Domain: customer-request-portal-crm
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CRPCRM_VERSION', '0.1.5' );
define( 'CRPCRM_DB_VERSION', '0.4.0' );
define( 'CRPCRM_PLUGIN_FILE', __FILE__ );
define( 'CRPCRM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CRPCRM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once CRPCRM_PLUGIN_DIR . 'includes/business-profiles/interface-business-profile.php';
require_once CRPCRM_PLUGIN_DIR . 'includes/business-profiles/class-safaei-business-profile.php';
require_once CRPCRM_PLUGIN_DIR . 'includes/business-profiles/class-business-profile-manager.php';
require_once CRPCRM_PLUGIN_DIR . 'includes/class-public-theme.php';
require_once CRPCRM_PLUGIN_DIR . 'includes/registries/class-form-registry.php';
require_once CRPCRM_PLUGIN_DIR . 'includes/registries/class-request-type-registry.php';
require_once CRPCRM_PLUGIN_DIR . 'includes/registries/class-system-request-types.php';
require_once CRPCRM_PLUGIN_DIR . 'includes/class-labels.php';
require_once CRPCRM_PLUGIN_DIR . 'includes/class-helpers.php';
require_once CRPCRM_PLUGIN_DIR . 'includes/class-db.php';
require_once CRPCRM_PLUGIN_DIR . 'includes/class-roles.php';
require_once CRPCRM_PLUGIN_DIR . 'includes/class-activator.php';
require_once CRPCRM_PLUGIN_DIR . 'includes/class-deactivator.php';
require_once CRPCRM_PLUGIN_DIR . 'includes/class-logger.php';
require_once CRPCRM_PLUGIN_DIR . 'includes/class-activity.php';
require_once CRPCRM_PLUGIN_DIR . 'includes/class-request-access-service.php';
require_once CRPCRM_PLUGIN_DIR . 'includes/class-request-workflow-service.php';
require_once CRPCRM_PLUGIN_DIR . 'includes/class-lead-follow-up-service.php';
require_once CRPCRM_PLUGIN_DIR . 'includes/class-settings.php';
require_once CRPCRM_PLUGIN_DIR . 'includes/class-csv-exporter.php';
require_once CRPCRM_PLUGIN_DIR . 'includes/class-health-check-service.php';
require_once CRPCRM_PLUGIN_DIR . 'includes/class-maintenance-service.php';
require_once CRPCRM_PLUGIN_DIR . 'includes/class-admin-tools.php';
require_once CRPCRM_PLUGIN_DIR . 'includes/repositories/class-customer-repository.php';
require_once CRPCRM_PLUGIN_DIR . 'includes/class-customer-deletion-service.php';
require_once CRPCRM_PLUGIN_DIR . 'includes/repositories/class-otp-repository.php';
require_once CRPCRM_PLUGIN_DIR . 'includes/sms/class-sms-provider-interface.php';
require_once CRPCRM_PLUGIN_DIR . 'includes/sms/class-melipayamak-provider.php';
require_once CRPCRM_PLUGIN_DIR . 'includes/sms/class-sms-ir-provider.php';
require_once CRPCRM_PLUGIN_DIR . 'includes/sms/class-sms-provider-registry.php';
require_once CRPCRM_PLUGIN_DIR . 'includes/class-otp-service.php';
require_once CRPCRM_PLUGIN_DIR . 'includes/repositories/class-customer-attribution-repository.php';
require_once CRPCRM_PLUGIN_DIR . 'includes/repositories/class-reports-repository.php';
require_once CRPCRM_PLUGIN_DIR . 'includes/repositories/class-staff-repository.php';
require_once CRPCRM_PLUGIN_DIR . 'includes/class-sales-daily-stats-service.php';
require_once CRPCRM_PLUGIN_DIR . 'includes/class-attribution-service.php';
require_once CRPCRM_PLUGIN_DIR . 'includes/class-admin-menu.php';
require_once CRPCRM_PLUGIN_DIR . 'admin/class-admin-pages.php';
require_once CRPCRM_PLUGIN_DIR . 'public/class-request-forms.php';
require_once CRPCRM_PLUGIN_DIR . 'public/class-portal-shortcode.php';
require_once CRPCRM_PLUGIN_DIR . 'public/class-public.php';
require_once CRPCRM_PLUGIN_DIR . 'includes/repositories/class-request-repository.php';
require_once CRPCRM_PLUGIN_DIR . 'includes/repositories/class-activity-repository.php';
require_once CRPCRM_PLUGIN_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'CRPCRM_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'CRPCRM_Deactivator', 'deactivate' ) );

function crpcrm_run_plugin() {
	$plugin = new CRPCRM_Plugin();
	$plugin->run();
}
crpcrm_run_plugin();

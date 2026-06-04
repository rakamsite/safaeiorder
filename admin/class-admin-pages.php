<?php
/**
 * Admin page renderer.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_Admin_Pages {
	public function dashboard() {
		$this->render( 'dashboard.php' );
	}

	public function requests() {
		$this->render( 'requests-placeholder.php' );
	}

	public function customers() {
		$this->render( 'customers-placeholder.php' );
	}

	public function reports() {
		$this->render( 'reports-placeholder.php' );
	}

	public function staff() {
		$this->render( 'staff-placeholder.php' );
	}

	public function settings() {
		$settings            = CRPCRM_Settings::get();
		$attribution_service = class_exists( 'CRPCRM_Attribution_Service' ) ? new CRPCRM_Attribution_Service() : null;
		$current_attribution = $attribution_service ? $attribution_service->get_current_attribution() : null;

		$this->render(
			'settings.php',
			array(
				'settings'            => $settings,
				'current_attribution' => $current_attribution,
			)
		);
	}

	private function render( $view, $vars = array() ) {
		$view_path = CRPCRM_PLUGIN_DIR . 'admin/views/' . $view;

		if ( ! file_exists( $view_path ) ) {
			wp_die( esc_html__( 'فایل نمایشی پیدا نشد.', 'customer-request-portal-crm' ) );
		}

		extract( $vars, EXTR_SKIP );
		include $view_path;
	}
}

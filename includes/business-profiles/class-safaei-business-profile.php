<?php
/**
 * Default Safaei business profile.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_Safaei_Business_Profile implements CRPCRM_Business_Profile_Interface {
	public function get_id() {
		return 'safaei';
	}

	public function get_label() {
		return 'بازرگانی صفایی';
	}

	public function get_request_types() {
		return array(
			'car_registration' => 'ثبت‌نام خودرو',
			'parts_request'    => 'درخواست قطعات',
			'repair_booking'   => 'درخواست تعمیرات',
			'lead_follow_up'   => 'پیگیری سرنخ',
		);
	}

	public function get_forms() {
		return array(
			'new_car_registration' => array(
				'id'               => 'new_car_registration',
				'page'             => 'new_car_registration',
				'title'            => 'ثبت‌نام خودرو',
				'label'            => 'فرم ثبت‌نام خودرو',
				'icon'             => 'car',
				'description'      => '',
				'request_type'     => 'car_registration',
				'version'          => '1.0.0',
				'enabled'          => true,
				'submit_label'     => 'ثبت درخواست خودرو',
				'summary_template' => '',
				'fields'           => array(
					array(
						'name'             => 'desired_vehicle',
						'type'             => 'select',
						'label'            => 'خودروی موردنظر',
						'placeholder'      => '',
						'required'         => true,
						'required_message' => 'خودروی موردنظر الزامی است.',
						'options'          => CRPCRM_Settings::get_active_vehicle_options( 'new_car_registration' ),
						'default'          => '',
						'help'             => '',
					),
				),
			),
			'new_parts_request' => array(
				'id'               => 'new_parts_request',
				'page'             => 'new_parts_request',
				'title'            => 'درخواست قطعات',
				'label'            => 'فرم درخواست قطعات',
				'icon'             => 'parts',
				'description'      => '',
				'request_type'     => 'parts_request',
				'version'          => '1.0.0',
				'enabled'          => true,
				'submit_label'     => 'ثبت درخواست قطعه',
				'summary_template' => '',
				'fields'           => array(
					array( 'name' => 'part_name', 'type' => 'text', 'label' => 'نام قطعه موردنیاز', 'placeholder' => 'مثلاً چراغ جلو، سپر، لنت، فیلتر روغن', 'required' => true, 'required_message' => 'نام قطعه موردنیاز الزامی است.', 'options' => array(), 'default' => '', 'help' => '' ),
					array( 'name' => 'vehicle_model', 'type' => 'select', 'label' => 'مدل خودرو', 'placeholder' => '', 'required' => true, 'required_message' => 'مدل خودرو الزامی است.', 'options' => CRPCRM_Settings::get_active_vehicle_options( 'new_parts_request' ), 'default' => '', 'help' => '' ),
					array( 'name' => 'description', 'type' => 'textarea', 'label' => 'توضیحات', 'placeholder' => 'توضیحات بیشتر درباره قطعه موردنیاز را وارد کنید.', 'required' => true, 'required_message' => 'توضیحات الزامی است.', 'options' => array(), 'default' => '', 'help' => '' ),
				),
			),
			'new_repair_booking' => array(
				'id'               => 'new_repair_booking',
				'page'             => 'new_repair_booking',
				'title'            => 'درخواست تعمیرات',
				'label'            => 'فرم درخواست تعمیرات',
				'icon'             => 'repair',
				'description'      => '',
				'request_type'     => 'repair_booking',
				'version'          => '1.0.0',
				'enabled'          => true,
				'submit_label'     => 'ثبت درخواست تعمیرات',
				'summary_template' => '',
				'fields'           => array(
					array( 'name' => 'vehicle_model', 'type' => 'select', 'label' => 'مدل خودرو', 'placeholder' => '', 'required' => true, 'required_message' => 'مدل خودرو الزامی است.', 'options' => CRPCRM_Settings::get_active_vehicle_options( 'new_repair_booking' ), 'default' => '', 'help' => '' ),
					array( 'name' => 'service_type', 'type' => 'select', 'label' => 'نوع سرویس یا مشکل', 'placeholder' => '', 'required' => true, 'required_message' => 'نوع سرویس یا مشکل الزامی است.', 'options' => array( 'سرویس دوره‌ای', 'تعمیر موتور', 'گیربکس', 'برق خودرو', 'جلوبندی', 'صافکاری و بدنه', 'عیب‌یابی', 'تعویض قطعه', 'سایر' ), 'default' => '', 'help' => '' ),
					array( 'name' => 'problem_description', 'type' => 'textarea', 'label' => 'شرح مشکل', 'placeholder' => 'لطفاً مشکل خودرو یا سرویس موردنظر را توضیح دهید.', 'required' => true, 'required_message' => 'شرح مشکل الزامی است.', 'options' => array(), 'default' => '', 'help' => '' ),
				),
			),
		);
	}

	public function get_default_sms_templates() {
		return array();
	}

	public function get_theme_tokens() {
		return array(
			'primary'       => '#2563eb',
			'primary_hover' => '#1d4ed8',
			'secondary'     => '#64748b',
			'bg'            => '#f8fafc',
			'card_bg'       => '#ffffff',
			'text'          => '#111827',
			'muted_text'    => '#4b5563',
			'border'        => '#e5e7eb',
			'field_bg'      => '#ffffff',
			'field_border'  => '#d1d5db',
			'error'         => '#dc2626',
			'success'       => '#059669',
			'warning'       => '#d97706',
			'radius'        => '14px',
			'radius_sm'     => '10px',
		);
	}
}

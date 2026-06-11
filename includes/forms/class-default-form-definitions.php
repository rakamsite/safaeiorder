<?php
/**
 * Temporary default request form definitions until Form Builder is available.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_Default_Form_Definitions {
	const SETTINGS_KEY = 'default';

	public static function get_aliases() {
		return array(
			'new_car_registration' => 'safaei_car_registration',
			'new_parts_request'    => 'safaei_parts_request',
			'new_repair_booking'   => 'safaei_repair_booking',
		);
	}

	public static function get_forms() {
		$vehicle_options = array( 'فونیکس FX', 'تیگو ۷', 'تیگو ۸', 'آریزو ۵', 'آریزو ۶', 'X22', 'X55' );
		$forms           = array(
			// Legacy form IDs are preserved so existing links and shortcodes keep working.
			'safaei_car_registration' => array(
				'id'               => 'safaei_car_registration',
				'page'             => 'safaei_car_registration',
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
					array( 'name' => 'desired_vehicle', 'type' => 'select', 'label' => 'خودروی موردنظر', 'placeholder' => '', 'required' => true, 'required_message' => 'خودروی موردنظر الزامی است.', 'options' => $vehicle_options, 'default' => '', 'help' => '' ),
				),
			),
			'safaei_parts_request' => array(
				'id'               => 'safaei_parts_request',
				'page'             => 'safaei_parts_request',
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
					array( 'name' => 'vehicle_model', 'type' => 'select', 'label' => 'مدل خودرو', 'placeholder' => '', 'required' => true, 'required_message' => 'مدل خودرو الزامی است.', 'options' => $vehicle_options, 'default' => '', 'help' => '' ),
					array( 'name' => 'description', 'type' => 'textarea', 'label' => 'توضیحات', 'placeholder' => 'توضیحات بیشتر درباره قطعه موردنیاز را وارد کنید.', 'required' => true, 'required_message' => 'توضیحات الزامی است.', 'options' => array(), 'default' => '', 'help' => '' ),
				),
			),
			'safaei_repair_booking' => array(
				'id'               => 'safaei_repair_booking',
				'page'             => 'safaei_repair_booking',
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
					array( 'name' => 'vehicle_model', 'type' => 'select', 'label' => 'مدل خودرو', 'placeholder' => '', 'required' => true, 'required_message' => 'مدل خودرو الزامی است.', 'options' => $vehicle_options, 'default' => '', 'help' => '' ),
					array( 'name' => 'service_type', 'type' => 'select', 'label' => 'نوع سرویس یا مشکل', 'placeholder' => '', 'required' => true, 'required_message' => 'نوع سرویس یا مشکل الزامی است.', 'options' => array( 'سرویس دوره‌ای', 'تعمیر موتور', 'گیربکس', 'برق خودرو', 'جلوبندی', 'صافکاری و بدنه', 'عیب‌یابی', 'تعویض قطعه', 'سایر' ), 'default' => '', 'help' => '' ),
					array( 'name' => 'problem_description', 'type' => 'textarea', 'label' => 'شرح مشکل', 'placeholder' => 'لطفاً مشکل خودرو یا سرویس موردنظر را توضیح دهید.', 'required' => true, 'required_message' => 'شرح مشکل الزامی است.', 'options' => array(), 'default' => '', 'help' => '' ),
				),
			),
		);

		return apply_filters( 'crpcrm_default_forms', $forms );
	}
}

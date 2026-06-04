<?php
/**
 * Customer portal request form definitions and helpers.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_Request_Forms {
	public static function get_forms() {
		return array(
			'new_car_registration' => array(
				'page'         => 'new_car_registration',
				'request_type' => 'car_registration',
				'title'        => 'ثبت‌نام خودرو',
				'submit_label' => 'ثبت درخواست خودرو',
				'fields'       => array(
					array( 'name' => 'desired_vehicle', 'type' => 'text', 'required' => true, 'label' => 'خودروی موردنظر', 'placeholder' => 'مثلاً فونیکس FX، تیگو ۷، آریزو ۶', 'required_message' => 'خودروی موردنظر الزامی است.' ),
					array( 'name' => 'request_kind', 'type' => 'select', 'required' => true, 'label' => 'نوع درخواست', 'required_message' => 'نوع درخواست الزامی است.', 'options' => array( 'ثبت‌نام', 'خرید نقدی', 'خرید اقساطی', 'اطلاع از شرایط فروش', 'مشاوره' ) ),
					array( 'name' => 'budget_range', 'type' => 'select', 'required' => true, 'label' => 'بودجه حدودی', 'required_message' => 'بودجه حدودی الزامی است.', 'options' => array( 'مشخص نیست', 'تا ۱ میلیارد', '۱ تا ۱.۵ میلیارد', '۱.۵ تا ۲ میلیارد', 'بیشتر از ۲ میلیارد' ) ),
					array( 'name' => 'description', 'type' => 'textarea', 'required' => true, 'label' => 'توضیحات', 'placeholder' => 'اگر توضیح بیشتری درباره درخواست خود دارید، بنویسید.', 'required_message' => 'توضیحات الزامی است.' ),
				),
			),
			'new_parts_request' => array(
				'page'         => 'new_parts_request',
				'request_type' => 'parts_request',
				'title'        => 'درخواست قطعات',
				'submit_label' => 'ثبت درخواست قطعه',
				'fields'       => array(
					array( 'name' => 'part_name', 'type' => 'text', 'required' => true, 'label' => 'نام قطعه موردنیاز', 'placeholder' => 'مثلاً چراغ جلو، سپر، لنت، فیلتر روغن', 'required_message' => 'نام قطعه موردنیاز الزامی است.' ),
					array( 'name' => 'vehicle_model', 'type' => 'text', 'required' => true, 'label' => 'مدل خودرو', 'placeholder' => 'مثلاً تیگو ۷، آریزو ۵، X22', 'required_message' => 'مدل خودرو الزامی است.' ),
					array( 'name' => 'vehicle_year', 'type' => 'select', 'required' => true, 'label' => 'سال خودرو', 'required_message' => 'سال خودرو الزامی است.', 'options' => array( '۱۴۰۴', '۱۴۰۳', '۱۴۰۲', '۱۴۰۱', '۱۴۰۰', '۱۳۹۹', '۱۳۹۸', '۱۳۹۷', '۱۳۹۶', '۱۳۹۵', 'قبل از ۱۳۹۵', 'نمی‌دانم' ) ),
					array( 'name' => 'description', 'type' => 'textarea', 'required' => true, 'label' => 'توضیحات', 'placeholder' => 'توضیحات بیشتر درباره قطعه موردنیاز را وارد کنید.', 'required_message' => 'توضیحات الزامی است.' ),
				),
			),
			'new_repair_booking' => array(
				'page'         => 'new_repair_booking',
				'request_type' => 'repair_booking',
				'title'        => 'درخواست تعمیرات',
				'submit_label' => 'ثبت درخواست تعمیرات',
				'fields'       => array(
					array( 'name' => 'vehicle_model', 'type' => 'text', 'required' => true, 'label' => 'مدل خودرو', 'placeholder' => 'مثلاً تیگو ۸، آریزو ۶، X55', 'required_message' => 'مدل خودرو الزامی است.' ),
					array( 'name' => 'service_type', 'type' => 'select', 'required' => true, 'label' => 'نوع سرویس یا مشکل', 'required_message' => 'نوع سرویس یا مشکل الزامی است.', 'options' => array( 'سرویس دوره‌ای', 'تعمیر موتور', 'گیربکس', 'برق خودرو', 'جلوبندی', 'صافکاری و بدنه', 'عیب‌یابی', 'تعویض قطعه', 'سایر' ) ),
					array( 'name' => 'problem_description', 'type' => 'textarea', 'required' => true, 'label' => 'شرح مشکل', 'placeholder' => 'لطفاً مشکل خودرو یا سرویس موردنظر را توضیح دهید.', 'required_message' => 'شرح مشکل الزامی است.' ),
					array( 'name' => 'preferred_date', 'type' => 'date', 'required' => true, 'label' => 'تاریخ پیشنهادی مراجعه', 'required_message' => 'تاریخ پیشنهادی مراجعه الزامی است.' ),
					array( 'name' => 'preferred_call_time', 'type' => 'select', 'required' => true, 'label' => 'زمان مناسب تماس', 'required_message' => 'زمان مناسب تماس الزامی است.', 'options' => array( 'صبح', 'ظهر', 'عصر', 'شب', 'فرقی ندارد' ) ),
				),
			),
		);
	}

	public static function get_form( $page ) {
		$forms = self::get_forms();
		$page  = sanitize_key( $page );
		return isset( $forms[ $page ] ) ? $forms[ $page ] : null;
	}

	public static function get_form_pages() {
		return array_keys( self::get_forms() );
	}

	public static function get_type_label( $request_type ) {
		$labels = array(
			'car_registration' => 'ثبت‌نام خودرو',
			'parts_request'    => 'درخواست قطعات',
			'repair_booking'   => 'درخواست تعمیرات',
		);
		return isset( $labels[ $request_type ] ) ? $labels[ $request_type ] : $request_type;
	}

	public static function get_customer_status_label( $status ) {
		$labels = array(
			'new'         => 'ثبت شده',
			'in_progress' => 'در حال بررسی',
			'no_answer'   => 'در انتظار تماس',
			'follow_up'   => 'در حال پیگیری',
			'won'         => 'تکمیل شده',
			'lost'        => 'بسته شده',
			'invalid'     => 'بسته شده',
		);
		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}

	public static function sanitize_and_validate( $form, $posted ) {
		$data   = array();
		$errors = array();

		foreach ( $form['fields'] as $field ) {
			$name  = $field['name'];
			$value = isset( $posted[ $name ] ) ? wp_unslash( $posted[ $name ] ) : '';
			$value = 'textarea' === $field['type'] ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
			$value = trim( $value );

			if ( ! empty( $field['required'] ) && '' === $value ) {
				$errors[] = $field['required_message'];
				continue;
			}

			if ( 'select' === $field['type'] && '' !== $value && ! in_array( $value, $field['options'], true ) ) {
				$errors[] = $field['required_message'];
				continue;
			}

			$data[ $name ] = $value;
		}

		if ( ! empty( $errors ) ) {
			array_unshift( $errors, 'لطفاً فیلدهای الزامی را تکمیل کنید.' );
		}

		return array( 'data' => $data, 'errors' => array_values( array_unique( $errors ) ) );
	}

	public static function build_summary( $form, $data ) {
		$parts = array();
		foreach ( $form['fields'] as $field ) {
			$name = $field['name'];
			if ( isset( $data[ $name ] ) && '' !== $data[ $name ] ) {
				$parts[] = $field['label'] . ': ' . $data[ $name ];
			}
		}

		return implode( ' | ', $parts );
	}
}

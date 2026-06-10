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
		);
	}

	public function get_form_aliases() {
		return array(
			'new_car_registration' => 'safaei_car_registration',
			'new_parts_request'    => 'safaei_parts_request',
			'new_repair_booking'   => 'safaei_repair_booking',
		);
	}

	public function get_vehicle_form_labels() {
		return array(
			'safaei_car_registration' => 'فرم ثبت‌نام خودرو',
			'safaei_parts_request'    => 'فرم درخواست قطعات',
			'safaei_repair_booking'   => 'فرم درخواست تعمیرات',
		);
	}

	public function get_default_vehicle_options() {
		return array(
			array( 'label' => 'فونیکس FX', 'priority' => 10, 'enabled' => 'yes' ),
			array( 'label' => 'تیگو ۷', 'priority' => 20, 'enabled' => 'yes' ),
			array( 'label' => 'تیگو ۸', 'priority' => 30, 'enabled' => 'yes' ),
			array( 'label' => 'آریزو ۵', 'priority' => 40, 'enabled' => 'yes' ),
			array( 'label' => 'آریزو ۶', 'priority' => 50, 'enabled' => 'yes' ),
			array( 'label' => 'X22', 'priority' => 60, 'enabled' => 'yes' ),
			array( 'label' => 'X55', 'priority' => 70, 'enabled' => 'yes' ),
		);
	}

	public function get_active_vehicle_options( $form_id ) {
		$form_id = sanitize_key( $form_id );
		$catalog = $this->get_vehicle_catalog();
		$items   = isset( $catalog[ $form_id ] ) && is_array( $catalog[ $form_id ] ) ? $catalog[ $form_id ] : $this->get_default_vehicle_options();
		$items   = array_filter( $items, function ( $item ) {
			return is_array( $item ) && ! empty( $item['label'] ) && 'yes' === ( $item['enabled'] ?? 'no' );
		} );

		usort( $items, function ( $a, $b ) {
			return absint( $a['priority'] ?? 999 ) <=> absint( $b['priority'] ?? 999 );
		} );

		return array_values( array_unique( wp_list_pluck( $items, 'label' ) ) );
	}

	public function get_vehicle_catalog() {
		$settings = get_option( 'crpcrm_profile_settings_' . $this->get_id(), array() );
		$settings = is_array( $settings ) ? $settings : array();
		return isset( $settings['vehicle_catalog'] ) && is_array( $settings['vehicle_catalog'] )
			? $settings['vehicle_catalog']
			: $this->get_profile_default_settings()['vehicle_catalog'];
	}

	private function sanitize_vehicle_catalog( $input, $current ) {
		$clean   = array();
		foreach ( array_keys( $this->get_vehicle_form_labels() ) as $form_id ) {
			$items = isset( $input[ $form_id ] ) && is_array( $input[ $form_id ] ) ? $input[ $form_id ] : ( $current[ $form_id ] ?? array() );
			foreach ( $items as $item ) {
				$label = isset( $item['label'] ) ? trim( sanitize_text_field( $item['label'] ) ) : '';
				if ( '' === $label ) {
					continue;
				}
				$clean[ $form_id ][] = array(
					'label'    => $label,
					'priority' => absint( $item['priority'] ?? 999 ),
					'enabled'  => isset( $item['enabled'] ) && 'yes' === $item['enabled'] ? 'yes' : 'no',
				);
			}
			if ( empty( $clean[ $form_id ] ) ) {
				$clean[ $form_id ] = $this->get_default_vehicle_options();
			}
		}
		return $clean;
	}

	public function has_profile_settings() {
		return true;
	}

	public function get_profile_default_settings() {
		$catalog = array();
		foreach ( array_keys( $this->get_vehicle_form_labels() ) as $form_id ) {
			$catalog[ $form_id ] = $this->get_default_vehicle_options();
		}

		return array( 'vehicle_catalog' => $catalog );
	}

	public function sanitize_profile_settings( $input, $current ) {
		$current_catalog = isset( $current['vehicle_catalog'] ) && is_array( $current['vehicle_catalog'] ) ? $current['vehicle_catalog'] : array();
		$input_catalog   = isset( $input['vehicle_catalog'] ) && is_array( $input['vehicle_catalog'] ) ? $input['vehicle_catalog'] : array();
		return array( 'vehicle_catalog' => $this->sanitize_vehicle_catalog( $input_catalog, $current_catalog ) );
	}

	public function render_profile_settings( $settings ) {
		$catalog = isset( $settings['vehicle_catalog'] ) && is_array( $settings['vehicle_catalog'] ) ? $settings['vehicle_catalog'] : array();
		$groups  = $this->get_vehicle_form_labels();
		foreach ( $groups as $form_id => $form_label ) {
			$items = isset( $catalog[ $form_id ] ) && is_array( $catalog[ $form_id ] ) ? $catalog[ $form_id ] : array();
			$count = count( $items );
			for ( $i = $count; $i < $count + 5; $i++ ) {
				$items[] = array( 'label' => '', 'priority' => ( $i + 1 ) * 10, 'enabled' => 'yes' );
			}
			?>
			<div class="crpcrm-profile-catalog-group">
				<h3><?php echo esc_html( $form_label ); ?></h3>
				<table class="widefat striped">
					<thead><tr><th><?php echo esc_html( 'نام خودرو' ); ?></th><th><?php echo esc_html( 'اولویت نمایش' ); ?></th><th><?php echo esc_html( 'فعال' ); ?></th></tr></thead>
					<tbody>
						<?php foreach ( $items as $index => $item ) : ?>
							<tr>
								<td><input class="regular-text" name="crpcrm_profile_settings[vehicle_catalog][<?php echo esc_attr( $form_id ); ?>][<?php echo esc_attr( $index ); ?>][label]" type="text" value="<?php echo esc_attr( $item['label'] ?? '' ); ?>" placeholder="<?php echo esc_attr( 'مثلاً تیگو ۸' ); ?>" /></td>
								<td><input name="crpcrm_profile_settings[vehicle_catalog][<?php echo esc_attr( $form_id ); ?>][<?php echo esc_attr( $index ); ?>][priority]" type="number" min="0" value="<?php echo esc_attr( absint( $item['priority'] ?? 999 ) ); ?>" /></td>
								<td><label><input name="crpcrm_profile_settings[vehicle_catalog][<?php echo esc_attr( $form_id ); ?>][<?php echo esc_attr( $index ); ?>][enabled]" type="checkbox" value="yes" <?php checked( $item['enabled'] ?? 'yes', 'yes' ); ?> /> <?php echo esc_html( 'نمایش داده شود' ); ?></label></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php
		}
		echo '<p class="description">' . esc_html( 'ردیف‌های خالی ذخیره نمی‌شوند. تنظیمات هر فرم مستقل ذخیره می‌شود.' ) . '</p>';
	}

	public function get_forms() {
		return array(
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
					array(
						'name'             => 'desired_vehicle',
						'type'             => 'select',
						'label'            => 'خودروی موردنظر',
						'placeholder'      => '',
						'required'         => true,
						'required_message' => 'خودروی موردنظر الزامی است.',
						'options'          => $this->get_active_vehicle_options( 'safaei_car_registration' ),
						'default'          => '',
						'help'             => '',
					),
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
					array( 'name' => 'vehicle_model', 'type' => 'select', 'label' => 'مدل خودرو', 'placeholder' => '', 'required' => true, 'required_message' => 'مدل خودرو الزامی است.', 'options' => $this->get_active_vehicle_options( 'safaei_parts_request' ), 'default' => '', 'help' => '' ),
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
					array( 'name' => 'vehicle_model', 'type' => 'select', 'label' => 'مدل خودرو', 'placeholder' => '', 'required' => true, 'required_message' => 'مدل خودرو الزامی است.', 'options' => $this->get_active_vehicle_options( 'safaei_repair_booking' ), 'default' => '', 'help' => '' ),
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

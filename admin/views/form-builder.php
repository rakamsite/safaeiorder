<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$editing      = is_array( $form );
$icon_choices = CRPCRM_Form_Icon_Pack::get_icons();
$field_types  = array(
	'text'           => 'متن کوتاه',
	'textarea'       => 'متن چندخطی',
	'select'         => 'انتخابی',
	'product_search' => 'جستجوی محصول',
	'file_upload'    => 'بارگذاری فایل',
	'display_html'   => 'متن یا HTML نمایشی',
);
$field_widths = array(
	'25'  => '25%',
	'33'  => '33%',
	'50'  => '50%',
	'100' => '100%',
);

$form = $editing ? $form : array(
	'form_id'      => '',
	'title'        => '',
	'description'  => '',
	'icon'         => CRPCRM_Form_Icon_Pack::DEFAULT_ICON,
	'submit_label' => 'ثبت درخواست',
	'enabled'      => true,
	'sort_order'   => 0,
	'version'      => '1.0.0',
	'fields'       => array(
		array(
			'key'         => '',
			'label'       => '',
			'type'        => 'text',
			'required'    => false,
			'placeholder' => '',
			'help_text'   => '',
			'options'     => array(),
			'content'     => '',
			'width'       => '100',
			'enabled'     => true,
			'sort_order'  => 0,
		),
	),
);

$fields = is_array( $form['fields'] ) && ! empty( $form['fields'] ) ? array_values( $form['fields'] ) : array();
if ( empty( $fields ) ) {
	$fields[] = array(
		'key'         => '',
		'label'       => '',
		'type'        => 'text',
		'required'    => false,
		'placeholder' => '',
		'help_text'   => '',
		'options'     => array(),
		'content'     => '',
		'width'       => '100',
		'enabled'     => true,
		'sort_order'  => 0,
	);
}
?>
<div class="wrap crpcrm-admin-wrap" dir="rtl">
	<h1><?php echo esc_html( 'فرم‌ساز' ); ?></h1>
	<p class="description"><?php echo esc_html( 'فرم‌ها از اینجا مدیریت می‌شوند و هر فرم می‌تواند فیلدهای پویا، آیکن و چیدمان اختصاصی خودش را داشته باشد.' ); ?></p>
	<?php if ( isset( $_GET['form-saved'] ) ) : ?><div class="notice notice-success"><p><?php echo esc_html( 'فرم ذخیره شد.' ); ?></p></div><?php endif; ?>
	<?php if ( isset( $_GET['form-deleted'] ) ) : ?><div class="notice notice-success"><p><?php echo esc_html( 'فرم حذف شد.' ); ?></p></div><?php endif; ?>
	<?php if ( isset( $_GET['form-disabled'] ) ) : ?><div class="notice notice-success"><p><?php echo esc_html( 'فرم پیش‌فرض غیرفعال شد و هر زمان خواستید می‌توانید آن را بازگردانید.' ); ?></p></div><?php endif; ?>
	<?php if ( isset( $_GET['form-defaults-restored'] ) ) : ?><div class="notice notice-success"><p><?php echo esc_html( 'فرم‌های پیش‌فرض بازگردانی شدند.' ); ?></p></div><?php endif; ?>
	<?php if ( isset( $_GET['form-error'] ) ) : ?>
		<?php $form_error_message = isset( $_GET['form-error-message'] ) ? sanitize_text_field( wp_unslash( $_GET['form-error-message'] ) ) : ( 'invalid-form-id' === sanitize_key( wp_unslash( $_GET['form-error'] ) ) ? 'شناسه فرم الزامی است و فقط می‌تواند شامل حروف انگلیسی، عدد، خط تیره و آندرلاین باشد.' : 'ذخیره فرم انجام نشد. تنظیمات فرم را بررسی کنید.' ); ?>
		<div class="notice notice-error"><p><?php echo esc_html( $form_error_message ); ?></p></div>
	<?php endif; ?>

	<div class="crpcrm-card">
		<h2><?php echo esc_html( 'فرم‌های سفارشی' ); ?></h2>
		<p><a class="button button-primary" href="<?php echo esc_url( add_query_arg( array( 'page' => 'crpcrm-form-builder' ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( 'افزودن فرم جدید' ); ?></a></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:0 0 12px;">
			<input type="hidden" name="action" value="crpcrm_restore_default_forms">
			<?php wp_nonce_field( 'crpcrm_restore_default_forms', 'crpcrm_form_builder_nonce' ); ?>
			<button class="button" type="submit"><?php echo esc_html( 'بازگردانی فرم‌های پیش‌فرض' ); ?></button>
		</form>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php echo esc_html( 'عنوان' ); ?></th>
					<th><?php echo esc_html( 'آیکن' ); ?></th>
					<th><?php echo esc_html( 'شناسه' ); ?></th>
					<th><?php echo esc_html( 'تعداد فیلد' ); ?></th>
					<th><?php echo esc_html( 'وضعیت' ); ?></th>
					<th><?php echo esc_html( 'عملیات' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $forms ) ) : ?>
					<tr><td colspan="6"><?php echo esc_html( 'هنوز فرم سفارشی ساخته نشده است.' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $forms as $item ) : ?>
					<tr>
						<td><?php echo esc_html( $item['title'] ); ?></td>
						<td><span class="crpcrm-form-icon-preview" title="<?php echo esc_attr( CRPCRM_Form_Icon_Pack::get_icon_label( $item['icon'] ?? '' ) ); ?>"><?php echo CRPCRM_Form_Icon_Pack::get_icon_markup( $item['icon'] ?? '' ); ?></span></td>
						<td><code><?php echo esc_html( $item['form_id'] ); ?></code><div class="description"><?php echo esc_html( 'نوع: ' . ( $item['request_type'] ?? $item['form_id'] ) ); ?></div></td>
						<td><?php echo esc_html( number_format_i18n( count( $item['fields'] ?? array() ) ) ); ?></td>
						<td><?php echo esc_html( ! empty( $item['enabled'] ) ? 'فعال' : 'غیرفعال' ); ?></td>
						<td><a class="button button-small" href="<?php echo esc_url( add_query_arg( array( 'page' => 'crpcrm-form-builder', 'form_id' => $item['form_id'] ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( 'ویرایش' ); ?></a> <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline" onsubmit="return confirm('<?php echo esc_attr( 'این فرم حذف شود؟' ); ?>');"><input type="hidden" name="action" value="crpcrm_delete_custom_form"><input type="hidden" name="form_id" value="<?php echo esc_attr( $item['form_id'] ); ?>"><?php wp_nonce_field( 'crpcrm_delete_custom_form', 'crpcrm_form_builder_nonce' ); ?><button class="button button-small" type="submit"><?php echo esc_html( 'حذف' ); ?></button></form></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<div class="crpcrm-card">
		<h2><?php echo esc_html( $editing ? 'ویرایش فرم' : 'افزودن فرم جدید' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="crpcrm-form-builder-editor">
			<input type="hidden" name="action" value="crpcrm_save_custom_form">
			<input type="hidden" name="original_form_id" value="<?php echo esc_attr( $editing ? $form['form_id'] : '' ); ?>">
			<?php wp_nonce_field( 'crpcrm_save_custom_form', 'crpcrm_form_builder_nonce' ); ?>

			<table class="form-table">
				<tbody>
					<tr><th><label for="crpcrm-form-id"><?php echo esc_html( 'شناسه فرم' ); ?></label></th><td><input id="crpcrm-form-id" class="regular-text" name="crpcrm_form[form_id]" required pattern="[A-Za-z0-9_-]+" value="<?php echo esc_attr( $form['form_id'] ); ?>" <?php echo $editing ? 'readonly' : ''; ?>><p class="description"><?php echo esc_html( 'فقط حروف انگلیسی، عدد، خط تیره و آندرلاین.' ); ?></p></td></tr>
					<tr><th><label for="crpcrm-request-type"><?php echo esc_html( 'request_type' ); ?></label></th><td><input id="crpcrm-request-type" class="regular-text" name="crpcrm_form[request_type]" value="<?php echo esc_attr( $form['request_type'] ?? $form['form_id'] ); ?>"><p class="description"><?php echo esc_html( 'اگر خالی بماند، همان شناسه فرم به‌عنوان نوع درخواست استفاده می‌شود. تکراری بودن مجاز است و می‌تواند چند فرم را زیر یک نوع درخواست گروه‌بندی کند.' ); ?></p></td></tr>
					<tr><th><label for="crpcrm-form-title"><?php echo esc_html( 'عنوان' ); ?></label></th><td><input id="crpcrm-form-title" class="regular-text" name="crpcrm_form[title]" required value="<?php echo esc_attr( $form['title'] ); ?>"></td></tr>
					<tr><th><?php echo esc_html( 'توضیح' ); ?></th><td><textarea class="large-text" rows="3" name="crpcrm_form[description]"><?php echo esc_textarea( $form['description'] ); ?></textarea></td></tr>
					<tr><th><?php echo esc_html( 'متن دکمه ثبت' ); ?></th><td><input class="regular-text" name="crpcrm_form[submit_label]" value="<?php echo esc_attr( $form['submit_label'] ); ?>"></td></tr>
					<tr>
						<th><?php echo esc_html( 'آیکن فرم' ); ?></th>
						<td>
							<fieldset class="crpcrm-icon-picker">
								<?php foreach ( $icon_choices as $icon_key => $icon_data ) : ?>
									<label class="crpcrm-icon-option">
										<input type="radio" name="crpcrm_form[icon]" value="<?php echo esc_attr( $icon_key ); ?>" <?php checked( $form['icon'], $icon_key ); ?>>
										<span class="crpcrm-icon-option-preview" aria-hidden="true"><?php echo CRPCRM_Form_Icon_Pack::get_icon_markup( $icon_key ); ?></span>
										<span class="crpcrm-icon-option-label"><?php echo esc_html( $icon_data['label'] ); ?></span>
									</label>
								<?php endforeach; ?>
							</fieldset>
						</td>
					</tr>
					<tr><th><?php echo esc_html( 'نسخه / ترتیب' ); ?></th><td><input name="crpcrm_form[version]" value="<?php echo esc_attr( $form['version'] ); ?>"> <input type="number" name="crpcrm_form[sort_order]" value="<?php echo esc_attr( $form['sort_order'] ); ?>"></td></tr>
					<tr><th><?php echo esc_html( 'فعال' ); ?></th><td><label><input type="hidden" name="crpcrm_form[enabled]" value="0"><input type="checkbox" name="crpcrm_form[enabled]" value="1" <?php checked( ! empty( $form['enabled'] ) ); ?>> <?php echo esc_html( 'فرم فعال باشد' ); ?></label></td></tr>
				</tbody>
			</table>

			<div class="crpcrm-form-builder-fields" data-field-count="<?php echo esc_attr( count( $fields ) ); ?>">
				<div class="crpcrm-form-builder-fields-header">
					<h3><?php echo esc_html( 'فیلدها' ); ?></h3>
					<button type="button" class="button button-secondary crpcrm-add-field-button">+ <?php echo esc_html( 'افزودن فیلد' ); ?></button>
				</div>
				<div class="crpcrm-form-builder-field-list">
					<?php foreach ( $fields as $index => $field ) : ?>
						<?php
						$field = wp_parse_args(
							$field,
							array(
								'key'         => '',
								'label'       => '',
								'type'        => 'text',
								'required'    => false,
								'placeholder' => '',
								'help_text'   => '',
								'options'     => array(),
								'content'     => '',
								'width'       => '100',
								'enabled'     => true,
								'sort_order'  => $index,
							)
						);
						?>
						<div class="crpcrm-form-builder-field-card" data-field-index="<?php echo esc_attr( $index ); ?>">
							<div class="crpcrm-form-builder-field-card-header">
								<strong><?php echo esc_html( 'فیلد' ); ?> <span class="crpcrm-field-order-label"><?php echo esc_html( $index + 1 ); ?></span></strong>
								<button type="button" class="button-link-delete crpcrm-remove-field-button"><?php echo esc_html( 'حذف' ); ?></button>
							</div>
							<div class="crpcrm-form-builder-field-grid">
								<label><span><?php echo esc_html( 'کلید' ); ?></span><input name="crpcrm_form[fields][<?php echo esc_attr( $index ); ?>][key]" value="<?php echo esc_attr( $field['key'] ); ?>" placeholder="field_key"></label>
								<label><span><?php echo esc_html( 'عنوان' ); ?></span><input name="crpcrm_form[fields][<?php echo esc_attr( $index ); ?>][label]" value="<?php echo esc_attr( $field['label'] ); ?>" placeholder="<?php echo esc_attr( 'عنوان فیلد' ); ?>"></label>
								<label><span><?php echo esc_html( 'نوع فیلد' ); ?></span><select class="crpcrm-form-builder-field-type" name="crpcrm_form[fields][<?php echo esc_attr( $index ); ?>][type]"><?php foreach ( $field_types as $type_key => $type_label ) : ?><option value="<?php echo esc_attr( $type_key ); ?>" <?php selected( $field['type'], $type_key ); ?>><?php echo esc_html( $type_label ); ?></option><?php endforeach; ?></select></label>
								<label><span><?php echo esc_html( 'عرض' ); ?></span><select name="crpcrm_form[fields][<?php echo esc_attr( $index ); ?>][width]"><?php foreach ( $field_widths as $width_key => $width_label ) : ?><option value="<?php echo esc_attr( $width_key ); ?>" <?php selected( (string) $field['width'], $width_key ); ?>><?php echo esc_html( $width_label ); ?></option><?php endforeach; ?></select></label>
								<label><span><?php echo esc_html( 'Placeholder' ); ?></span><input name="crpcrm_form[fields][<?php echo esc_attr( $index ); ?>][placeholder]" value="<?php echo esc_attr( $field['placeholder'] ); ?>" placeholder="<?php echo esc_attr( 'متن راهنما' ); ?>"></label>
								<label><span><?php echo esc_html( 'توضیح کوتاه' ); ?></span><textarea rows="2" name="crpcrm_form[fields][<?php echo esc_attr( $index ); ?>][help_text]"><?php echo esc_textarea( $field['help_text'] ); ?></textarea></label>
								<label class="crpcrm-field-type-options<?php echo 'select' === $field['type'] ? '' : ' is-hidden'; ?>"><span><?php echo esc_html( 'گزینه‌ها' ); ?></span><textarea rows="4" name="crpcrm_form[fields][<?php echo esc_attr( $index ); ?>][options]" placeholder="<?php echo esc_attr( 'هر گزینه در یک خط' ); ?>"><?php echo esc_textarea( implode( "\n", (array) $field['options'] ) ); ?></textarea></label>
								<label class="crpcrm-field-type-content<?php echo 'display_html' === $field['type'] ? '' : ' is-hidden'; ?>"><span><?php echo esc_html( 'محتوای نمایشی' ); ?></span><textarea rows="5" name="crpcrm_form[fields][<?php echo esc_attr( $index ); ?>][content]" placeholder="<?php echo esc_attr( '<p>متن یا HTML</p>' ); ?>"><?php echo esc_textarea( $field['content'] ); ?></textarea></label>
							</div>
							<div class="crpcrm-form-builder-field-flags">
								<label><input type="hidden" name="crpcrm_form[fields][<?php echo esc_attr( $index ); ?>][required]" value="0"><input type="checkbox" name="crpcrm_form[fields][<?php echo esc_attr( $index ); ?>][required]" value="1" <?php checked( ! empty( $field['required'] ) ); ?>> <?php echo esc_html( 'اجباری' ); ?></label>
								<label><input type="hidden" name="crpcrm_form[fields][<?php echo esc_attr( $index ); ?>][enabled]" value="0"><input type="checkbox" name="crpcrm_form[fields][<?php echo esc_attr( $index ); ?>][enabled]" value="1" <?php checked( ! empty( $field['enabled'] ) ); ?>> <?php echo esc_html( 'فعال' ); ?></label>
								<label><span><?php echo esc_html( 'ترتیب' ); ?></span><input type="number" class="small-text crpcrm-field-sort-order" name="crpcrm_form[fields][<?php echo esc_attr( $index ); ?>][sort_order]" value="<?php echo esc_attr( $field['sort_order'] ); ?>"></label>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>

			<script type="text/template" id="tmpl-crpcrm-form-builder-field">
				<div class="crpcrm-form-builder-field-card" data-field-index="__INDEX__">
					<div class="crpcrm-form-builder-field-card-header">
						<strong><?php echo esc_html( 'فیلد' ); ?> <span class="crpcrm-field-order-label">__ORDER__</span></strong>
						<button type="button" class="button-link-delete crpcrm-remove-field-button"><?php echo esc_html( 'حذف' ); ?></button>
					</div>
					<div class="crpcrm-form-builder-field-grid">
						<label><span><?php echo esc_html( 'کلید' ); ?></span><input name="crpcrm_form[fields][__INDEX__][key]" value="" placeholder="field_key"></label>
						<label><span><?php echo esc_html( 'عنوان' ); ?></span><input name="crpcrm_form[fields][__INDEX__][label]" value="" placeholder="<?php echo esc_attr( 'عنوان فیلد' ); ?>"></label>
						<label><span><?php echo esc_html( 'نوع فیلد' ); ?></span><select class="crpcrm-form-builder-field-type" name="crpcrm_form[fields][__INDEX__][type]"><?php foreach ( $field_types as $type_key => $type_label ) : ?><option value="<?php echo esc_attr( $type_key ); ?>"><?php echo esc_html( $type_label ); ?></option><?php endforeach; ?></select></label>
						<label><span><?php echo esc_html( 'عرض' ); ?></span><select name="crpcrm_form[fields][__INDEX__][width]"><?php foreach ( $field_widths as $width_key => $width_label ) : ?><option value="<?php echo esc_attr( $width_key ); ?>" <?php selected( '100', $width_key ); ?>><?php echo esc_html( $width_label ); ?></option><?php endforeach; ?></select></label>
						<label><span><?php echo esc_html( 'Placeholder' ); ?></span><input name="crpcrm_form[fields][__INDEX__][placeholder]" value="" placeholder="<?php echo esc_attr( 'متن راهنما' ); ?>"></label>
						<label><span><?php echo esc_html( 'توضیح کوتاه' ); ?></span><textarea rows="2" name="crpcrm_form[fields][__INDEX__][help_text]"></textarea></label>
						<label class="crpcrm-field-type-options is-hidden"><span><?php echo esc_html( 'گزینه‌ها' ); ?></span><textarea rows="4" name="crpcrm_form[fields][__INDEX__][options]" placeholder="<?php echo esc_attr( 'هر گزینه در یک خط' ); ?>"></textarea></label>
						<label class="crpcrm-field-type-content is-hidden"><span><?php echo esc_html( 'محتوای نمایشی' ); ?></span><textarea rows="5" name="crpcrm_form[fields][__INDEX__][content]" placeholder="<?php echo esc_attr( '<p>متن یا HTML</p>' ); ?>"></textarea></label>
					</div>
					<div class="crpcrm-form-builder-field-flags">
						<label><input type="hidden" name="crpcrm_form[fields][__INDEX__][required]" value="0"><input type="checkbox" name="crpcrm_form[fields][__INDEX__][required]" value="1"> <?php echo esc_html( 'اجباری' ); ?></label>
						<label><input type="hidden" name="crpcrm_form[fields][__INDEX__][enabled]" value="0"><input type="checkbox" name="crpcrm_form[fields][__INDEX__][enabled]" value="1" checked> <?php echo esc_html( 'فعال' ); ?></label>
						<label><span><?php echo esc_html( 'ترتیب' ); ?></span><input type="number" class="small-text crpcrm-field-sort-order" name="crpcrm_form[fields][__INDEX__][sort_order]" value="__INDEX__"></label>
					</div>
				</div>
			</script>

			<?php submit_button( 'ذخیره فرم' ); ?>
		</form>
	</div>
</div>

<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$editing = is_array( $form );
$form    = $editing ? $form : array(
	'form_id'      => '',
	'title'        => '',
	'description'  => '',
	'request_type' => '',
	'submit_label' => 'ثبت درخواست',
	'enabled'      => true,
	'sort_order'   => 0,
	'version'      => '1.0.0',
	'fields'       => array(),
);
$fields = $form['fields'];
for ( $i = count( $fields ); $i < 10; $i++ ) {
	$fields[] = array( 'key' => '', 'label' => '', 'type' => 'text', 'required' => false, 'placeholder' => '', 'help_text' => '', 'options' => array(), 'enabled' => true, 'sort_order' => $i );
}
?>
<div class="wrap crpcrm-admin-wrap" dir="rtl">
	<h1><?php echo esc_html( 'فرم‌ساز' ); ?></h1>
	<p class="description"><?php echo esc_html( 'فرم‌های ذخیره‌شده منبع مرکزی فرم‌ها هستند. خاموش کردن قابلیت فرم‌ساز فقط دسترسی به مدیریت فرم‌ها را متوقف می‌کند.' ); ?></p>
	<?php if ( isset( $_GET['form-saved'] ) ) : ?><div class="notice notice-success"><p><?php echo esc_html( 'فرم ذخیره شد.' ); ?></p></div><?php endif; ?>
	<?php if ( isset( $_GET['form-deleted'] ) ) : ?><div class="notice notice-success"><p><?php echo esc_html( 'فرم حذف شد.' ); ?></p></div><?php endif; ?>
	<?php if ( isset( $_GET['form-error'] ) ) : ?><div class="notice notice-error"><p><?php echo esc_html( 'ذخیره فرم انجام نشد. شناسه‌ها و فیلدهای فرم را بررسی کنید.' ); ?></p></div><?php endif; ?>

	<div class="crpcrm-card">
		<h2><?php echo esc_html( 'فرم‌های سفارشی' ); ?></h2>
		<p><a class="button button-primary" href="<?php echo esc_url( add_query_arg( array( 'page' => 'crpcrm-form-builder' ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( 'افزودن فرم جدید' ); ?></a></p>
		<table class="widefat striped"><thead><tr><th><?php echo esc_html( 'عنوان' ); ?></th><th><?php echo esc_html( 'شناسه' ); ?></th><th><?php echo esc_html( 'نوع درخواست' ); ?></th><th><?php echo esc_html( 'وضعیت' ); ?></th><th><?php echo esc_html( 'عملیات' ); ?></th></tr></thead><tbody>
		<?php if ( empty( $forms ) ) : ?><tr><td colspan="5"><?php echo esc_html( 'هنوز فرم سفارشی ساخته نشده است.' ); ?></td></tr><?php endif; ?>
		<?php foreach ( $forms as $item ) : ?><tr><td><?php echo esc_html( $item['title'] ); ?></td><td><code><?php echo esc_html( $item['form_id'] ); ?></code></td><td><code><?php echo esc_html( $item['request_type'] ); ?></code></td><td><?php echo esc_html( $item['enabled'] ? 'فعال' : 'غیرفعال' ); ?></td><td><a class="button button-small" href="<?php echo esc_url( add_query_arg( array( 'page' => 'crpcrm-form-builder', 'form_id' => $item['form_id'] ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( 'ویرایش' ); ?></a> <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline" onsubmit="return confirm('<?php echo esc_attr( 'این فرم حذف شود؟' ); ?>');"><input type="hidden" name="action" value="crpcrm_delete_custom_form"><input type="hidden" name="form_id" value="<?php echo esc_attr( $item['form_id'] ); ?>"><?php wp_nonce_field( 'crpcrm_delete_custom_form', 'crpcrm_form_builder_nonce' ); ?><button class="button button-small" type="submit"><?php echo esc_html( 'حذف' ); ?></button></form></td></tr><?php endforeach; ?>
		</tbody></table>
	</div>

	<div class="crpcrm-card">
		<h2><?php echo esc_html( $editing ? 'ویرایش فرم' : 'افزودن فرم جدید' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="crpcrm_save_custom_form">
			<input type="hidden" name="original_form_id" value="<?php echo esc_attr( $editing ? $form['form_id'] : '' ); ?>">
			<?php wp_nonce_field( 'crpcrm_save_custom_form', 'crpcrm_form_builder_nonce' ); ?>
			<table class="form-table"><tbody>
				<tr><th><label for="crpcrm-form-id"><?php echo esc_html( 'شناسه فرم' ); ?></label></th><td><input id="crpcrm-form-id" class="regular-text" name="crpcrm_form[form_id]" value="<?php echo esc_attr( $form['form_id'] ); ?>" <?php echo $editing ? 'readonly' : ''; ?>><p class="description"><?php echo esc_html( 'خالی بماند، از عنوان ساخته می‌شود.' ); ?></p></td></tr>
				<tr><th><label for="crpcrm-form-title"><?php echo esc_html( 'عنوان' ); ?></label></th><td><input id="crpcrm-form-title" class="regular-text" name="crpcrm_form[title]" required value="<?php echo esc_attr( $form['title'] ); ?>"></td></tr>
				<tr><th><?php echo esc_html( 'توضیح' ); ?></th><td><textarea class="large-text" rows="3" name="crpcrm_form[description]"><?php echo esc_textarea( $form['description'] ); ?></textarea></td></tr>
				<tr><th><?php echo esc_html( 'نوع درخواست' ); ?></th><td><input class="regular-text" name="crpcrm_form[request_type]" required value="<?php echo esc_attr( $form['request_type'] ); ?>"></td></tr>
				<tr><th><?php echo esc_html( 'متن دکمه ثبت' ); ?></th><td><input class="regular-text" name="crpcrm_form[submit_label]" value="<?php echo esc_attr( $form['submit_label'] ); ?>"></td></tr>
				<tr><th><?php echo esc_html( 'نسخه / ترتیب' ); ?></th><td><input name="crpcrm_form[version]" value="<?php echo esc_attr( $form['version'] ); ?>"> <input type="number" name="crpcrm_form[sort_order]" value="<?php echo esc_attr( $form['sort_order'] ); ?>"></td></tr>
				<tr><th><?php echo esc_html( 'فعال' ); ?></th><td><label><input type="checkbox" name="crpcrm_form[enabled]" value="1" <?php checked( $form['enabled'] ); ?>> <?php echo esc_html( 'فرم فعال باشد' ); ?></label></td></tr>
			</tbody></table>
			<h3><?php echo esc_html( 'فیلدها' ); ?></h3>
			<table class="widefat striped"><thead><tr><th><?php echo esc_html( 'کلید / عنوان' ); ?></th><th><?php echo esc_html( 'نوع' ); ?></th><th><?php echo esc_html( 'placeholder / راهنما' ); ?></th><th><?php echo esc_html( 'گزینه‌های select' ); ?></th><th><?php echo esc_html( 'وضعیت / ترتیب' ); ?></th></tr></thead><tbody>
			<?php foreach ( $fields as $index => $field ) : ?><tr>
				<td><input name="crpcrm_form[fields][<?php echo esc_attr( $index ); ?>][key]" placeholder="<?php echo esc_attr( 'field_key' ); ?>" value="<?php echo esc_attr( $field['key'] ); ?>"><br><input name="crpcrm_form[fields][<?php echo esc_attr( $index ); ?>][label]" placeholder="<?php echo esc_attr( 'عنوان فیلد' ); ?>" value="<?php echo esc_attr( $field['label'] ); ?>"></td>
				<td><select name="crpcrm_form[fields][<?php echo esc_attr( $index ); ?>][type]"><?php foreach ( array( 'text' => 'متن', 'textarea' => 'متن چندخطی', 'select' => 'انتخابی' ) as $type => $label ) : ?><option value="<?php echo esc_attr( $type ); ?>" <?php selected( $field['type'], $type ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></td>
				<td><input name="crpcrm_form[fields][<?php echo esc_attr( $index ); ?>][placeholder]" placeholder="<?php echo esc_attr( 'متن راهنما' ); ?>" value="<?php echo esc_attr( $field['placeholder'] ); ?>"><br><textarea name="crpcrm_form[fields][<?php echo esc_attr( $index ); ?>][help_text]" placeholder="<?php echo esc_attr( 'توضیح فیلد' ); ?>"><?php echo esc_textarea( $field['help_text'] ); ?></textarea></td>
				<td><textarea name="crpcrm_form[fields][<?php echo esc_attr( $index ); ?>][options]" placeholder="<?php echo esc_attr( 'هر گزینه در یک خط' ); ?>"><?php echo esc_textarea( implode( "\n", $field['options'] ) ); ?></textarea></td>
				<td><label><input type="checkbox" name="crpcrm_form[fields][<?php echo esc_attr( $index ); ?>][required]" value="1" <?php checked( $field['required'] ); ?>> <?php echo esc_html( 'اجباری' ); ?></label><br><label><input type="checkbox" name="crpcrm_form[fields][<?php echo esc_attr( $index ); ?>][enabled]" value="1" <?php checked( $field['enabled'] ); ?>> <?php echo esc_html( 'فعال' ); ?></label><br><input type="number" name="crpcrm_form[fields][<?php echo esc_attr( $index ); ?>][sort_order]" value="<?php echo esc_attr( $field['sort_order'] ); ?>"></td>
			</tr><?php endforeach; ?>
			</tbody></table>
			<?php submit_button( 'ذخیره فرم' ); ?>
		</form>
	</div>
</div>

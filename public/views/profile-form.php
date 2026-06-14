<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_embedded = ! empty( $is_embedded );
?>
<?php if ( ! $is_embedded ) : ?>
<div class="crpcrm-portal <?php echo esc_attr( $portal_theme_class ); ?>" style="<?php echo esc_attr( $portal_theme_style ); ?>" dir="rtl">
<?php endif; ?>
	<div class="crpcrm-portal-card crpcrm-card crpcrm-profile-card">
		<?php if ( ! $is_embedded && ! empty( $notice ) ) : ?>
			<div class="crpcrm-notice crpcrm-notice-<?php echo esc_attr( $notice['type'] ); ?> crpcrm-alert crpcrm-alert-<?php echo esc_attr( $notice['type'] ); ?>"><?php echo esc_html( $notice['message'] ); ?></div>
		<?php endif; ?>

		<header class="crpcrm-card-header">
			<h2><?php echo esc_html( $is_edit ? 'پروفایل من' : 'تکمیل اطلاعات پایه' ); ?></h2>
			<p><?php echo esc_html( $is_edit ? 'اطلاعات پایه پروفایل خود را ویرایش کنید.' : 'برای استفاده از پرتال، لطفاً اطلاعات پایه خود را تکمیل کنید.' ); ?></p>
		</header>

		<div class="crpcrm-card-body">
			<form class="crpcrm-profile-form crpcrm-form" method="post" action="<?php echo esc_url( $admin_post_url ); ?>">
				<input type="hidden" name="action" value="crpcrm_save_profile" />
				<input type="hidden" name="crpcrm_redirect_to" value="<?php echo esc_url( $portal_redirect_to ); ?>" />
				<?php wp_nonce_field( 'crpcrm_save_profile', 'crpcrm_profile_nonce' ); ?>

				<div class="crpcrm-field crpcrm-field-mobile">
					<label class="crpcrm-field-label" for="crpcrm-profile-phone"><?php echo esc_html( 'شماره موبایل' ); ?></label>
					<div class="crpcrm-field-control"><input class="crpcrm-input" id="crpcrm-profile-phone" type="text" value="<?php echo esc_attr( $phone_display ); ?>" readonly /></div>
				</div>
				<?php foreach ( $registration_fields as $field_id => $field ) : ?>
					<?php $field_value = isset( $registration_values[ $field_id ] ) ? $registration_values[ $field_id ] : ''; ?>
					<div class="crpcrm-field crpcrm-field-<?php echo esc_attr( sanitize_html_class( $field['type'] ) ); ?>">
						<label class="crpcrm-field-label" for="crpcrm-registration-<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
						<div class="crpcrm-field-control">
							<?php if ( 'province' === $field['type'] ) : ?>
								<select class="crpcrm-select" id="crpcrm-registration-<?php echo esc_attr( $field_id ); ?>" name="crpcrm_registration[<?php echo esc_attr( $field_id ); ?>]" <?php echo ! empty( $field['required'] ) ? 'required' : ''; ?>>
									<option value=""><?php echo esc_html( 'استان را انتخاب کنید' ); ?></option>
									<?php foreach ( $provinces as $province ) : ?><option value="<?php echo esc_attr( $province ); ?>" <?php selected( $field_value, $province ); ?>><?php echo esc_html( $province ); ?></option><?php endforeach; ?>
								</select>
							<?php elseif ( 'select' === $field['type'] ) : ?>
								<select class="crpcrm-select" id="crpcrm-registration-<?php echo esc_attr( $field_id ); ?>" name="crpcrm_registration[<?php echo esc_attr( $field_id ); ?>]" <?php echo ! empty( $field['required'] ) ? 'required' : ''; ?>>
									<option value=""><?php echo esc_html( 'انتخاب کنید' ); ?></option>
									<?php foreach ( $field['options'] as $option ) : ?><option value="<?php echo esc_attr( $option ); ?>" <?php selected( $field_value, $option ); ?>><?php echo esc_html( $option ); ?></option><?php endforeach; ?>
								</select>
							<?php elseif ( 'textarea' === $field['type'] ) : ?>
								<textarea class="crpcrm-input" id="crpcrm-registration-<?php echo esc_attr( $field_id ); ?>" name="crpcrm_registration[<?php echo esc_attr( $field_id ); ?>]" <?php echo ! empty( $field['required'] ) ? 'required' : ''; ?>><?php echo esc_textarea( $field_value ); ?></textarea>
							<?php elseif ( 'date' === $field['type'] ) : ?>
								<?php echo CRPCRM_Helpers::jalali_date_input( 'crpcrm_registration[' . $field_id . ']', $field_value, array( 'required' => ! empty( $field['required'] ), 'id' => 'crpcrm-registration-' . $field_id ) ); ?>
							<?php else : ?>
								<input class="crpcrm-input" id="crpcrm-registration-<?php echo esc_attr( $field_id ); ?>" name="crpcrm_registration[<?php echo esc_attr( $field_id ); ?>]" type="<?php echo esc_attr( 'email' === $field['type'] ? 'email' : 'text' ); ?>" value="<?php echo esc_attr( $field_value ); ?>" <?php echo ! empty( $field['required'] ) ? 'required' : ''; ?> />
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
				<div class="crpcrm-form-actions crpcrm-profile-save-actions"><button class="crpcrm-button crpcrm-button-primary" type="submit"><?php echo esc_html( 'ذخیره اطلاعات' ); ?></button></div>
			</form>
		</div>

		<?php if ( ! $is_embedded ) : ?>
			<footer class="crpcrm-card-footer crpcrm-profile-logout"><a class="crpcrm-logout-link" href="<?php echo esc_url( $logout_url ); ?>"><?php echo esc_html( 'خروج از حساب کاربری' ); ?></a></footer>
		<?php endif; ?>
	</div>
<?php if ( ! $is_embedded ) : ?>
</div>
<?php endif; ?>

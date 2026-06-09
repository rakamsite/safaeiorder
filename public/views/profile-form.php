<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_embedded = ! empty( $is_embedded );
?>
<?php if ( ! $is_embedded ) : ?>
<div class="crpcrm-portal <?php echo esc_attr( $portal_profile_class ); ?>" style="<?php echo esc_attr( $portal_theme_style ); ?>" dir="rtl">
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
				<div class="crpcrm-field crpcrm-field-text">
					<label class="crpcrm-field-label" for="crpcrm-full-name"><?php echo esc_html( 'نام و نام خانوادگی' ); ?></label>
					<div class="crpcrm-field-control"><input class="crpcrm-input" id="crpcrm-full-name" name="crpcrm_full_name" type="text" value="<?php echo esc_attr( isset( $customer['full_name'] ) ? $customer['full_name'] : '' ); ?>" placeholder="<?php echo esc_attr( 'مثلاً علی رضایی' ); ?>" required /></div>
				</div>
				<div class="crpcrm-field crpcrm-field-select">
					<label class="crpcrm-field-label" for="crpcrm-province"><?php echo esc_html( 'استان' ); ?></label>
					<div class="crpcrm-field-control"><select class="crpcrm-select" id="crpcrm-province" name="crpcrm_province" required>
						<option value=""><?php echo esc_html( 'استان را انتخاب کنید' ); ?></option>
						<?php foreach ( $provinces as $province ) : ?>
							<option value="<?php echo esc_attr( $province ); ?>" <?php selected( isset( $customer['province'] ) ? $customer['province'] : '', $province ); ?>><?php echo esc_html( $province ); ?></option>
						<?php endforeach; ?>
					</select></div>
				</div>
				<div class="crpcrm-field crpcrm-field-text">
					<label class="crpcrm-field-label" for="crpcrm-city"><?php echo esc_html( 'شهر' ); ?></label>
					<div class="crpcrm-field-control"><input class="crpcrm-input" id="crpcrm-city" name="crpcrm_city" type="text" value="<?php echo esc_attr( isset( $customer['city'] ) ? $customer['city'] : '' ); ?>" required /></div>
				</div>
				<div class="crpcrm-form-actions"><button class="crpcrm-button crpcrm-button-primary" type="submit"><?php echo esc_html( 'ذخیره اطلاعات' ); ?></button></div>
			</form>
		</div>

		<?php if ( ! $is_embedded ) : ?>
			<footer class="crpcrm-card-footer"><a class="crpcrm-logout-link crpcrm-button crpcrm-button-secondary" href="<?php echo esc_url( $logout_url ); ?>"><?php echo esc_html( 'خروج از حساب کاربری' ); ?></a></footer>
		<?php endif; ?>
	</div>
<?php if ( ! $is_embedded ) : ?>
</div>
<?php endif; ?>

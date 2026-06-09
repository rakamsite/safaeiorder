<div class="crpcrm-portal <?php echo esc_attr( $portal_profile_class ); ?>" style="<?php echo esc_attr( $portal_theme_style ); ?>" dir="rtl">
	<div class="crpcrm-portal-card crpcrm-card">
		<?php if ( ! empty( $notice ) ) : ?>
			<div class="crpcrm-notice crpcrm-notice-<?php echo esc_attr( $notice['type'] ); ?> crpcrm-alert crpcrm-alert-<?php echo esc_attr( $notice['type'] ); ?>"><?php echo esc_html( $notice['message'] ); ?></div>
		<?php endif; ?>

		<header class="crpcrm-card-header">
			<h2><?php echo esc_html( 'ورود یا ثبت‌نام' ); ?></h2>
			<p><?php echo esc_html( 'برای ورود، شماره موبایل خود را وارد کنید.' ); ?></p>
		</header>
		<div class="crpcrm-card-body">
			<form method="post" action="<?php echo esc_url( $admin_post_url ); ?>" class="crpcrm-otp-form crpcrm-form">
				<input type="hidden" name="action" value="crpcrm_request_otp" />
				<input type="hidden" name="crpcrm_redirect_to" value="<?php echo esc_attr( $portal_redirect_to ); ?>" />
				<?php wp_nonce_field( 'crpcrm_request_otp', 'crpcrm_otp_nonce' ); ?>
				<div class="crpcrm-field crpcrm-field-mobile">
					<label class="crpcrm-field-label" for="crpcrm_phone"><?php echo esc_html( 'شماره موبایل' ); ?></label>
					<div class="crpcrm-field-control"><input class="crpcrm-input" id="crpcrm_phone" name="crpcrm_phone" type="tel" inputmode="tel" autocomplete="tel" required placeholder="<?php echo esc_attr( 'مثلاً 09123456789' ); ?>" /></div>
				</div>
				<div class="crpcrm-form-actions"><button type="submit" class="crpcrm-button crpcrm-button-primary"><?php echo esc_html( 'دریافت کد ورود' ); ?></button></div>
			</form>
		</div>
	</div>
</div>

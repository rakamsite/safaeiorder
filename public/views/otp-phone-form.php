<div class="crpcrm-portal" dir="rtl">
	<div class="crpcrm-portal-card">
		<?php if ( ! empty( $notice ) ) : ?>
			<div class="crpcrm-notice crpcrm-notice-<?php echo esc_attr( $notice['type'] ); ?>"><?php echo esc_html( $notice['message'] ); ?></div>
		<?php endif; ?>

		<h2><?php echo esc_html( 'ورود یا ثبت‌نام' ); ?></h2>
		<p><?php echo esc_html( 'برای ورود، شماره موبایل خود را وارد کنید.' ); ?></p>

		<form method="post" action="<?php echo esc_url( $admin_post_url ); ?>" class="crpcrm-otp-form">
			<input type="hidden" name="action" value="crpcrm_request_otp" />
			<input type="hidden" name="crpcrm_redirect_to" value="<?php echo esc_attr( $portal_redirect_to ); ?>" />
			<?php wp_nonce_field( 'crpcrm_request_otp', 'crpcrm_otp_nonce' ); ?>

			<input id="crpcrm_phone" name="crpcrm_phone" type="tel" inputmode="tel" autocomplete="tel" required placeholder="<?php echo esc_attr( 'مثلاً 09123456789' ); ?>" />

			<button type="submit" class="crpcrm-button"><?php echo esc_html( 'دریافت کد ورود' ); ?></button>
		</form>
	</div>
</div>

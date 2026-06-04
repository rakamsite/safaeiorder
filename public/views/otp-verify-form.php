<div class="crpcrm-portal" dir="rtl">
	<div class="crpcrm-portal-card">
		<?php if ( ! empty( $notice ) ) : ?>
			<div class="crpcrm-notice crpcrm-notice-<?php echo esc_attr( $notice['type'] ); ?>"><?php echo esc_html( $notice['message'] ); ?></div>
		<?php endif; ?>

		<h2><?php echo esc_html( 'تأیید شماره موبایل' ); ?></h2>
		<p><?php echo esc_html( 'کد تأیید ارسال‌شده را وارد کنید.' ); ?></p>
		<p class="crpcrm-muted"><?php echo esc_html( 'کد تأیید به شماره ' . ( 0 === strpos( $phone_normalized, '98' ) ? '0' . substr( $phone_normalized, 2 ) : $phone_normalized ) . ' ارسال شد.' ); ?></p>

		<form method="post" action="<?php echo esc_url( $admin_post_url ); ?>" class="crpcrm-otp-form">
			<input type="hidden" name="action" value="crpcrm_verify_otp" />
			<input type="hidden" name="crpcrm_redirect_to" value="<?php echo esc_attr( $portal_redirect_to ); ?>" />
			<input type="hidden" name="crpcrm_otp_state" value="<?php echo esc_attr( $state_token ); ?>" />
			<?php wp_nonce_field( 'crpcrm_verify_otp', 'crpcrm_otp_nonce' ); ?>

			<label for="crpcrm_otp_code"><?php echo esc_html( 'کد تأیید' ); ?></label>
			<input id="crpcrm_otp_code" name="crpcrm_otp_code" type="text" inputmode="numeric" pattern="[0-9۰-۹٠-٩]{5,6}" maxlength="6" autocomplete="one-time-code" required />

			<button type="submit" class="crpcrm-button"><?php echo esc_html( 'ورود به پرتال' ); ?></button>
		</form>

		<div class="crpcrm-otp-actions">
			<form method="post" action="<?php echo esc_url( $admin_post_url ); ?>">
				<input type="hidden" name="action" value="crpcrm_change_otp_phone" />
				<input type="hidden" name="crpcrm_redirect_to" value="<?php echo esc_attr( $portal_redirect_to ); ?>" />
				<?php wp_nonce_field( 'crpcrm_change_otp_phone', 'crpcrm_otp_nonce' ); ?>
				<button type="submit" class="crpcrm-link-button"><?php echo esc_html( 'تغییر شماره موبایل' ); ?></button>
			</form>

			<form method="post" action="<?php echo esc_url( $admin_post_url ); ?>">
				<input type="hidden" name="action" value="crpcrm_resend_otp" />
				<input type="hidden" name="crpcrm_redirect_to" value="<?php echo esc_attr( $portal_redirect_to ); ?>" />
				<input type="hidden" name="crpcrm_otp_state" value="<?php echo esc_attr( $state_token ); ?>" />
				<?php wp_nonce_field( 'crpcrm_resend_otp', 'crpcrm_otp_nonce' ); ?>
				<button type="submit" class="crpcrm-link-button"><?php echo esc_html( 'ارسال مجدد کد' ); ?></button>
				<span class="crpcrm-muted"><?php echo esc_html( 'فاصله مجاز ارسال مجدد: ' . absint( $resend_seconds ) . ' ثانیه' ); ?></span>
			</form>
		</div>
	</div>
</div>

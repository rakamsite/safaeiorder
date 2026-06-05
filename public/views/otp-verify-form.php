<div class="crpcrm-portal" dir="rtl">
	<div class="crpcrm-portal-card">
		<?php if ( ! empty( $notice ) ) : ?>
			<div class="crpcrm-notice crpcrm-notice-<?php echo esc_attr( $notice['type'] ); ?>"><?php echo esc_html( $notice['message'] ); ?></div>
		<?php endif; ?>

		<p><?php echo esc_html( 'کد تأیید ارسال‌شده را وارد کنید.' ); ?></p>

		<form method="post" action="<?php echo esc_url( $admin_post_url ); ?>" class="crpcrm-otp-form">
			<input type="hidden" name="action" value="crpcrm_verify_otp" />
			<input type="hidden" name="crpcrm_redirect_to" value="<?php echo esc_attr( $portal_redirect_to ); ?>" />
			<input type="hidden" name="crpcrm_otp_state" value="<?php echo esc_attr( $state_token ); ?>" />
			<?php wp_nonce_field( 'crpcrm_verify_otp', 'crpcrm_otp_nonce' ); ?>

			<div class="crpcrm-otp-code-boxes" dir="ltr" data-otp-length="3">
				<input class="crpcrm-otp-code-box" type="text" inputmode="numeric" pattern="[0-9۰-۹٠-٩]*" maxlength="3" autocomplete="one-time-code" aria-label="<?php echo esc_attr( 'رقم اول کد تأیید' ); ?>" required />
				<input class="crpcrm-otp-code-box" type="text" inputmode="numeric" pattern="[0-9۰-۹٠-٩]*" maxlength="1" autocomplete="off" aria-label="<?php echo esc_attr( 'رقم دوم کد تأیید' ); ?>" required />
				<input class="crpcrm-otp-code-box" type="text" inputmode="numeric" pattern="[0-9۰-۹٠-٩]*" maxlength="1" autocomplete="off" aria-label="<?php echo esc_attr( 'رقم سوم کد تأیید' ); ?>" required />
			</div>
			<input id="crpcrm_otp_code" name="crpcrm_otp_code" type="hidden" value="" />

			<button type="submit" class="crpcrm-button"><?php echo esc_html( 'ورود' ); ?></button>
		</form>

		<div class="crpcrm-otp-actions">
			<form method="post" action="<?php echo esc_url( $admin_post_url ); ?>" class="crpcrm-change-phone-form">
				<input type="hidden" name="action" value="crpcrm_change_otp_phone" />
				<input type="hidden" name="crpcrm_redirect_to" value="<?php echo esc_attr( $portal_redirect_to ); ?>" />
				<?php wp_nonce_field( 'crpcrm_change_otp_phone', 'crpcrm_otp_nonce' ); ?>
				<button type="submit" class="crpcrm-text-link"><?php echo esc_html( 'تغییر شماره موبایل' ); ?></button>
			</form>

			<form method="post" action="<?php echo esc_url( $admin_post_url ); ?>" class="crpcrm-resend-otp-form" data-resend-seconds="<?php echo esc_attr( absint( $resend_seconds ) ); ?>">
				<input type="hidden" name="action" value="crpcrm_resend_otp" />
				<input type="hidden" name="crpcrm_redirect_to" value="<?php echo esc_attr( $portal_redirect_to ); ?>" />
				<input type="hidden" name="crpcrm_otp_state" value="<?php echo esc_attr( $state_token ); ?>" />
				<?php wp_nonce_field( 'crpcrm_resend_otp', 'crpcrm_otp_nonce' ); ?>
				<span class="crpcrm-resend-countdown"><?php echo esc_html( 'تا ارسال مجدد کد تأیید ' . absint( $resend_seconds ) . ' ثانیه' ); ?></span>
				<button type="submit" class="crpcrm-link-button crpcrm-resend-button" hidden><?php echo esc_html( 'ارسال مجدد کد تأیید' ); ?></button>
			</form>
		</div>
	</div>
</div>

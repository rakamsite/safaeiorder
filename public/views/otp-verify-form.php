<div class="crpcrm-portal <?php echo esc_attr( $portal_profile_class ); ?>" style="<?php echo esc_attr( $portal_theme_style ); ?>" dir="rtl">
	<div class="crpcrm-portal-card crpcrm-card">
		<?php if ( ! empty( $notice ) ) : ?>
			<div class="crpcrm-notice crpcrm-notice-<?php echo esc_attr( $notice['type'] ); ?> crpcrm-alert crpcrm-alert-<?php echo esc_attr( $notice['type'] ); ?>"><?php echo esc_html( $notice['message'] ); ?></div>
		<?php endif; ?>
		<header class="crpcrm-card-header"><p><?php echo esc_html( 'کد تأیید ارسال‌شده را وارد کنید.' ); ?></p></header>
		<div class="crpcrm-card-body">
			<form method="post" action="<?php echo esc_url( $admin_post_url ); ?>" class="crpcrm-otp-form crpcrm-form">
				<input type="hidden" name="action" value="crpcrm_verify_otp" />
				<input type="hidden" name="crpcrm_redirect_to" value="<?php echo esc_attr( $portal_redirect_to ); ?>" />
				<input type="hidden" name="crpcrm_otp_state" value="<?php echo esc_attr( $state_token ); ?>" />
				<?php wp_nonce_field( 'crpcrm_verify_otp', 'crpcrm_otp_nonce' ); ?>
				<div class="crpcrm-field crpcrm-field-number">
					<div class="crpcrm-field-control crpcrm-otp-code-boxes" dir="ltr" data-otp-length="3">
						<input class="crpcrm-otp-code-box crpcrm-input" type="text" inputmode="numeric" pattern="[0-9۰-۹٠-٩]*" maxlength="3" autocomplete="one-time-code" aria-label="<?php echo esc_attr( 'رقم اول کد تأیید' ); ?>" required />
						<input class="crpcrm-otp-code-box crpcrm-input" type="text" inputmode="numeric" pattern="[0-9۰-۹٠-٩]*" maxlength="1" autocomplete="off" aria-label="<?php echo esc_attr( 'رقم دوم کد تأیید' ); ?>" required />
						<input class="crpcrm-otp-code-box crpcrm-input" type="text" inputmode="numeric" pattern="[0-9۰-۹٠-٩]*" maxlength="1" autocomplete="off" aria-label="<?php echo esc_attr( 'رقم سوم کد تأیید' ); ?>" required />
					</div>
				</div>
				<input id="crpcrm_otp_code" name="crpcrm_otp_code" type="hidden" value="" />
				<div class="crpcrm-form-actions"><button type="submit" class="crpcrm-button crpcrm-button-primary"><?php echo esc_html( 'ورود' ); ?></button></div>
			</form>
		</div>

		<footer class="crpcrm-card-footer crpcrm-otp-actions">
			<form method="post" action="<?php echo esc_url( $admin_post_url ); ?>" class="crpcrm-change-phone-form">
				<input type="hidden" name="action" value="crpcrm_change_otp_phone" />
				<input type="hidden" name="crpcrm_redirect_to" value="<?php echo esc_attr( $portal_redirect_to ); ?>" />
				<?php wp_nonce_field( 'crpcrm_change_otp_phone', 'crpcrm_otp_nonce' ); ?>
				<button type="submit" class="crpcrm-text-link crpcrm-button crpcrm-button-secondary"><?php echo esc_html( 'تغییر شماره موبایل' ); ?></button>
			</form>
			<form method="post" action="<?php echo esc_url( $admin_post_url ); ?>" class="crpcrm-resend-otp-form" data-resend-seconds="<?php echo esc_attr( absint( $resend_seconds ) ); ?>">
				<input type="hidden" name="action" value="crpcrm_resend_otp" />
				<input type="hidden" name="crpcrm_redirect_to" value="<?php echo esc_attr( $portal_redirect_to ); ?>" />
				<input type="hidden" name="crpcrm_otp_state" value="<?php echo esc_attr( $state_token ); ?>" />
				<?php wp_nonce_field( 'crpcrm_resend_otp', 'crpcrm_otp_nonce' ); ?>
				<span class="crpcrm-resend-countdown"><?php echo esc_html( 'تا ارسال مجدد کد تأیید ' . absint( $resend_seconds ) . ' ثانیه' ); ?></span>
				<button type="submit" class="crpcrm-link-button crpcrm-resend-button crpcrm-button crpcrm-button-secondary" hidden><?php echo esc_html( 'ارسال مجدد کد تأیید' ); ?></button>
			</form>
		</footer>
	</div>
</div>

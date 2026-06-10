<div class="crpcrm-portal <?php echo esc_attr( $portal_profile_class ); ?>" style="<?php echo esc_attr( $portal_theme_style ); ?>" dir="rtl">
	<div class="crpcrm-portal-card crpcrm-card crpcrm-otp-phone-card">
		<?php if ( ! empty( $notice ) ) : ?>
			<div class="crpcrm-notice crpcrm-notice-<?php echo esc_attr( $notice['type'] ); ?> crpcrm-alert crpcrm-alert-<?php echo esc_attr( $notice['type'] ); ?>"><?php echo esc_html( $notice['message'] ); ?></div>
		<?php endif; ?>

		<header class="crpcrm-card-header">
			<h2><?php echo esc_html( 'ورود/ثبت نام با موبایل' ); ?></h2>
		</header>
		<div class="crpcrm-card-body">
			<form method="post" action="<?php echo esc_url( $admin_post_url ); ?>" class="crpcrm-otp-form crpcrm-form">
				<input type="hidden" name="action" value="crpcrm_request_otp" />
				<input type="hidden" name="crpcrm_redirect_to" value="<?php echo esc_attr( $portal_redirect_to ); ?>" />
				<?php wp_nonce_field( 'crpcrm_request_otp', 'crpcrm_otp_nonce' ); ?>
				<div class="crpcrm-field crpcrm-field-mobile">
					<div class="crpcrm-field-control crpcrm-phone-entry" dir="ltr">
						<span class="crpcrm-phone-prefix" aria-hidden="true">09</span>
						<div class="crpcrm-phone-digits" aria-hidden="true">
							<?php for ( $digit_index = 0; $digit_index < 9; $digit_index++ ) : ?>
								<span class="crpcrm-phone-digit"></span>
							<?php endfor; ?>
						</div>
						<input class="crpcrm-phone-suffix" id="crpcrm_phone_suffix" type="tel" inputmode="numeric" autocomplete="tel-national" maxlength="9" required aria-label="<?php echo esc_attr( 'ادامه شماره موبایل پس از 09' ); ?>" />
						<input id="crpcrm_phone" name="crpcrm_phone" type="hidden" value="" />
					</div>
				</div>
				<div class="crpcrm-form-actions"><button type="submit" class="crpcrm-button crpcrm-button-primary"><?php echo esc_html( 'دریافت کد ورود' ); ?></button></div>
			</form>
		</div>
	</div>
</div>

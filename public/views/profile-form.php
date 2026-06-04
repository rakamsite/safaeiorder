<div class="crpcrm-portal" dir="rtl">
	<div class="crpcrm-portal-card crpcrm-profile-card">
		<?php if ( ! empty( $notice ) ) : ?>
			<div class="crpcrm-notice crpcrm-notice-<?php echo esc_attr( $notice['type'] ); ?>"><?php echo esc_html( $notice['message'] ); ?></div>
		<?php endif; ?>

		<h2><?php echo esc_html( $is_edit ? 'ویرایش پروفایل' : 'تکمیل اطلاعات پایه' ); ?></h2>
		<p><?php echo esc_html( $is_edit ? 'اطلاعات پایه پروفایل خود را ویرایش کنید.' : 'برای استفاده از پرتال، لطفاً اطلاعات پایه خود را تکمیل کنید.' ); ?></p>

		<form class="crpcrm-profile-form" method="post" action="<?php echo esc_url( $admin_post_url ); ?>">
			<input type="hidden" name="action" value="crpcrm_save_profile" />
			<input type="hidden" name="crpcrm_redirect_to" value="<?php echo esc_url( $portal_redirect_to ); ?>" />
			<?php wp_nonce_field( 'crpcrm_save_profile', 'crpcrm_profile_nonce' ); ?>

			<label for="crpcrm-profile-phone"><?php echo esc_html( 'شماره موبایل' ); ?></label>
			<input id="crpcrm-profile-phone" type="text" value="<?php echo esc_attr( $phone_display ); ?>" readonly />

			<label for="crpcrm-full-name"><?php echo esc_html( 'نام و نام خانوادگی' ); ?></label>
			<input id="crpcrm-full-name" name="crpcrm_full_name" type="text" value="<?php echo esc_attr( isset( $customer['full_name'] ) ? $customer['full_name'] : '' ); ?>" placeholder="<?php echo esc_attr( 'مثلاً علی رضایی' ); ?>" required />

			<label for="crpcrm-province"><?php echo esc_html( 'استان' ); ?></label>
			<select id="crpcrm-province" name="crpcrm_province" required>
				<option value=""><?php echo esc_html( 'استان را انتخاب کنید' ); ?></option>
				<?php foreach ( $provinces as $province ) : ?>
					<option value="<?php echo esc_attr( $province ); ?>" <?php selected( isset( $customer['province'] ) ? $customer['province'] : '', $province ); ?>><?php echo esc_html( $province ); ?></option>
				<?php endforeach; ?>
			</select>

			<label for="crpcrm-city"><?php echo esc_html( 'شهر' ); ?></label>
			<input id="crpcrm-city" name="crpcrm_city" type="text" value="<?php echo esc_attr( isset( $customer['city'] ) ? $customer['city'] : '' ); ?>" required />

			<button class="crpcrm-button" type="submit"><?php echo esc_html( 'ذخیره اطلاعات' ); ?></button>
		</form>

		<a class="crpcrm-logout-link" href="<?php echo esc_url( $logout_url ); ?>"><?php echo esc_html( 'خروج از حساب کاربری' ); ?></a>
	</div>
</div>

<div class="crpcrm-portal <?php echo esc_attr( $portal_theme_class ); ?>" style="<?php echo esc_attr( $portal_theme_style ); ?>" dir="rtl">
	<div class="crpcrm-portal-card crpcrm-card crpcrm-portal-placeholder">
		<?php if ( ! empty( $notice ) ) : ?>
			<div class="crpcrm-notice crpcrm-notice-<?php echo esc_attr( $notice['type'] ); ?> crpcrm-alert crpcrm-alert-<?php echo esc_attr( $notice['type'] ); ?>"><?php echo esc_html( $notice['message'] ); ?></div>
		<?php endif; ?>

		<h2><?php echo esc_html( 'پرتال مشتری' ); ?></h2>
		<p><?php echo esc_html( ! empty( $placeholder_message ) ? $placeholder_message : 'پروفایل شما تکمیل شده است. داشبورد مشتری در فازهای بعدی اضافه می‌شود.' ); ?></p>
		<?php if ( ! empty( $profile_url ) ) : ?>
			<p><a class="crpcrm-profile-link crpcrm-button crpcrm-button-secondary" href="<?php echo esc_url( $profile_url ); ?>"><?php echo esc_html( 'ویرایش پروفایل' ); ?></a></p>
		<?php endif; ?>
		<a class="crpcrm-logout-link crpcrm-button crpcrm-button-secondary" href="<?php echo esc_url( $logout_url ); ?>"><?php echo esc_html( 'خروج از حساب کاربری' ); ?></a>
	</div>
</div>

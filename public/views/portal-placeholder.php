<div class="crpcrm-portal" dir="rtl">
	<div class="crpcrm-portal-card crpcrm-portal-placeholder">
		<?php if ( ! empty( $notice ) ) : ?>
			<div class="crpcrm-notice crpcrm-notice-<?php echo esc_attr( $notice['type'] ); ?>"><?php echo esc_html( $notice['message'] ); ?></div>
		<?php endif; ?>

		<h2><?php echo esc_html( 'پرتال مشتری' ); ?></h2>
		<p><?php echo esc_html( ! empty( $placeholder_message ) ? $placeholder_message : 'پروفایل شما تکمیل شده است. داشبورد مشتری در فازهای بعدی اضافه می‌شود.' ); ?></p>
		<?php if ( ! empty( $profile_url ) ) : ?>
			<p><a class="crpcrm-profile-link" href="<?php echo esc_url( $profile_url ); ?>"><?php echo esc_html( 'ویرایش پروفایل' ); ?></a></p>
		<?php endif; ?>
		<a class="crpcrm-logout-link" href="<?php echo esc_url( $logout_url ); ?>"><?php echo esc_html( 'خروج از حساب کاربری' ); ?></a>
	</div>
</div>

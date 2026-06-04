<div class="crpcrm-portal" dir="rtl">
	<div class="crpcrm-portal-card crpcrm-portal-placeholder">
		<?php if ( ! empty( $notice ) ) : ?>
			<div class="crpcrm-notice crpcrm-notice-<?php echo esc_attr( $notice['type'] ); ?>"><?php echo esc_html( $notice['message'] ); ?></div>
		<?php endif; ?>

		<h2><?php echo esc_html( 'پرتال مشتری' ); ?></h2>
		<p><?php echo esc_html( 'شما با موفقیت وارد پرتال شدید. تکمیل اطلاعات پایه و داشبورد در فازهای بعدی اضافه می‌شود.' ); ?></p>
		<a class="crpcrm-logout-link" href="<?php echo esc_url( $logout_url ); ?>"><?php echo esc_html( 'خروج از حساب کاربری' ); ?></a>
	</div>
</div>

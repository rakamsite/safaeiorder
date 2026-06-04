<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<div class="wrap crpcrm-admin-wrap" dir="rtl">
	<h1><?php echo esc_html( 'داشبورد پرتال و CRM' ); ?></h1>
	<p class="crpcrm-admin-intro"><?php echo esc_html( 'خلاصه مدیریتی وضعیت درخواست‌ها، پیگیری‌ها و پنل کارکنان.' ); ?></p>
	<div class="crpcrm-dashboard-grid">
		<?php foreach ( (array) $cards as $card ) : ?>
			<a class="crpcrm-dashboard-card" href="<?php echo esc_url( $card['url'] ); ?>">
				<span class="crpcrm-dashboard-card-title"><?php echo esc_html( $card['title'] ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( absint( $card['count'] ) ) ); ?></strong>
				<span class="crpcrm-dashboard-card-link"><?php echo esc_html( 'مشاهده جزئیات' ); ?></span>
			</a>
		<?php endforeach; ?>
	</div>
</div>

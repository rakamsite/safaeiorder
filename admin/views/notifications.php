<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap crpcrm-admin-wrap" dir="rtl">
	<h1>اعلانات</h1>

	<div class="crpcrm-card">
		<div class="crpcrm-notifications-header">
			<p>تعداد اعلان‌های خوانده‌نشده: <strong><?php echo esc_html( number_format_i18n( $unread_count ) ); ?></strong></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="crpcrm_notification_action">
				<input type="hidden" name="notification_action_type" value="mark_all_read">
				<?php wp_nonce_field( 'crpcrm_notification_action', 'crpcrm_notification_nonce' ); ?>
				<button type="submit" class="button">خواندن همه</button>
			</form>
		</div>

		<?php if ( empty( $notifications ) ) : ?>
			<p>هنوز اعلانی برای شما ثبت نشده است.</p>
		<?php else : ?>
			<div class="crpcrm-table-scroll">
				<table class="widefat striped">
					<thead>
						<tr>
							<th>عنوان</th>
							<th>متن</th>
							<th>زمان</th>
							<th>وضعیت</th>
							<th>عملیات</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $notifications as $notification ) : ?>
							<tr class="<?php echo empty( $notification['is_read'] ) ? 'crpcrm-notification-unread' : ''; ?>">
								<td><strong><?php echo esc_html( $notification['title'] ); ?></strong></td>
								<td><?php echo esc_html( $notification['message'] ? $notification['message'] : '—' ); ?></td>
								<td><?php echo esc_html( CRPCRM_Helpers::format_jalali_datetime( $notification['created_at'] ) ); ?></td>
								<td><?php echo esc_html( empty( $notification['is_read'] ) ? 'جدید' : 'خوانده شده' ); ?></td>
								<td>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
										<input type="hidden" name="action" value="crpcrm_notification_action">
										<input type="hidden" name="notification_action_type" value="open">
										<input type="hidden" name="notification_id" value="<?php echo esc_attr( absint( $notification['id'] ) ); ?>">
										<?php wp_nonce_field( 'crpcrm_notification_action', 'crpcrm_notification_nonce' ); ?>
										<button type="submit" class="button button-primary button-small">مشاهده</button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</div>
</div>

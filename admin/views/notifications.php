<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$page        = max( 1, absint( $page ?? 1 ) );
$per_page    = max( 1, absint( $per_page ?? 20 ) );
$total       = absint( $total ?? 0 );
$total_pages = max( 1, absint( $total_pages ?? 1 ) );
$base_url    = isset( $base_url ) ? $base_url : admin_url( 'admin.php?page=crpcrm-notifications' );
$page_url    = static function ( $target_page ) use ( $base_url, $total_pages ) {
	$target_page = min( max( 1, absint( $target_page ) ), max( 1, $total_pages ) );
	return add_query_arg( array( 'paged' => $target_page ), $base_url );
};
?>
<div class="wrap crpcrm-admin-wrap" dir="rtl">
	<h1><?php echo esc_html( 'اعلانات' ); ?></h1>

	<div class="crpcrm-card">
		<div class="crpcrm-notifications-header">
			<p>
				<?php echo esc_html( 'اعلان‌های خوانده‌نشده:' ); ?>
				<strong><?php echo esc_html( number_format_i18n( $unread_count ) ); ?></strong>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="crpcrm_notification_action">
				<input type="hidden" name="notification_action_type" value="mark_all_read">
				<?php wp_nonce_field( 'crpcrm_notification_action', 'crpcrm_notification_nonce' ); ?>
				<button type="submit" class="button button-primary"><?php echo esc_html( 'خواندن همه' ); ?></button>
			</form>
		</div>

		<?php if ( empty( $notifications ) ) : ?>
			<p><?php echo esc_html( 'هنوز اعلانی برای شما ثبت نشده است.' ); ?></p>
		<?php else : ?>
			<div class="crpcrm-table-scroll">
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php echo esc_html( 'عنوان' ); ?></th>
							<th><?php echo esc_html( 'نوع' ); ?></th>
							<th><?php echo esc_html( 'پیام' ); ?></th>
							<th><?php echo esc_html( 'زمان ایجاد' ); ?></th>
							<th><?php echo esc_html( 'دیده شد' ); ?></th>
							<th><?php echo esc_html( 'خوانده شد' ); ?></th>
							<th><?php echo esc_html( 'عملیات' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $notifications as $notification ) : ?>
							<?php
							$is_seen = ! empty( $notification['is_seen'] );
							$is_read = ! empty( $notification['is_read'] );
							$created = ! empty( $notification['created_at'] ) ? CRPCRM_Helpers::format_jalali_datetime( $notification['created_at'] ) : '';
							?>
							<tr class="<?php echo $is_read ? '' : 'crpcrm-notification-unread'; ?>">
								<td><strong><?php echo esc_html( $notification['title'] ?? '' ); ?></strong></td>
								<td><?php echo esc_html( $notification['type'] ?? '' ); ?></td>
								<td><?php echo esc_html( '' !== ( $notification['message'] ?? '' ) ? $notification['message'] : '—' ); ?></td>
								<td><?php echo esc_html( $created ); ?></td>
								<td><?php echo esc_html( $is_seen ? 'بله' : 'خیر' ); ?></td>
								<td><?php echo esc_html( $is_read ? 'بله' : 'خیر' ); ?></td>
								<td>
									<div class="crpcrm-actions">
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="crpcrm-inline-form">
											<input type="hidden" name="action" value="crpcrm_notification_action">
											<input type="hidden" name="notification_action_type" value="open">
											<input type="hidden" name="notification_id" value="<?php echo esc_attr( absint( $notification['id'] ) ); ?>">
											<?php wp_nonce_field( 'crpcrm_notification_action', 'crpcrm_notification_nonce' ); ?>
											<button type="submit" class="button button-primary button-small"><?php echo esc_html( 'مشاهده' ); ?></button>
										</form>

										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="crpcrm-inline-form">
											<input type="hidden" name="action" value="crpcrm_notification_action">
											<input type="hidden" name="notification_action_type" value="mark_read">
											<input type="hidden" name="notification_id" value="<?php echo esc_attr( absint( $notification['id'] ) ); ?>">
											<?php wp_nonce_field( 'crpcrm_notification_action', 'crpcrm_notification_nonce' ); ?>
											<button type="submit" class="button button-secondary button-small"><?php echo esc_html( 'خوانده شد' ); ?></button>
										</form>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<?php if ( $total_pages > 1 ) : ?>
				<div class="tablenav bottom">
					<div class="tablenav-pages">
						<span class="displaying-num"><?php echo esc_html( sprintf( '%s مورد', number_format_i18n( $total ) ) ); ?></span>
						<span class="pagination-links">
							<?php if ( $page > 1 ) : ?>
								<a class="button" href="<?php echo esc_url( $page_url( 1 ) ); ?>"><?php echo esc_html( 'اول' ); ?></a>
								<a class="button" href="<?php echo esc_url( $page_url( $page - 1 ) ); ?>"><?php echo esc_html( 'قبلی' ); ?></a>
							<?php endif; ?>

							<span class="paging-input">
								<?php echo esc_html( number_format_i18n( $page ) . ' از ' . number_format_i18n( $total_pages ) ); ?>
							</span>

							<?php if ( $page < $total_pages ) : ?>
								<a class="button" href="<?php echo esc_url( $page_url( $page + 1 ) ); ?>"><?php echo esc_html( 'بعدی' ); ?></a>
								<a class="button" href="<?php echo esc_url( $page_url( $total_pages ) ); ?>"><?php echo esc_html( 'آخر' ); ?></a>
							<?php endif; ?>
						</span>
					</div>
				</div>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</div>

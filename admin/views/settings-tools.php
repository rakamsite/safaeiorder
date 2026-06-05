<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<?php if ( isset( $_GET['logs-cleaned'] ) ) : $deleted_logs = absint( $_GET['logs-cleaned'] ); ?>
	<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $deleted_logs ? sprintf( 'پاکسازی انجام شد. تعداد %d لاگ حذف شد.', $deleted_logs ) : 'لاگ قدیمی برای حذف وجود نداشت.' ); ?></p></div>
<?php endif; ?>
<?php if ( isset( $_GET['tables-repaired'] ) ) : ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html( 'بررسی جدول‌ها انجام شد.' ); ?></p></div><?php endif; ?>
<?php if ( isset( $_GET['request-codes-fixed'] ) ) : ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html( sprintf( 'تعداد %d درخواست فاقد کد پیگیری بروزرسانی شد.', absint( $_GET['request-codes-fixed'] ) ) ); ?></p></div><?php endif; ?>

<?php if ( CRPCRM_Admin_Tools::can_maintain() ) : ?>
<div class="crpcrm-card">
	<h2><?php echo esc_html( 'وضعیت سلامت افزونه' ); ?></h2>
	<table class="widefat striped"><thead><tr><th><?php echo esc_html( 'مورد' ); ?></th><th><?php echo esc_html( 'وضعیت' ); ?></th><th><?php echo esc_html( 'پیام' ); ?></th></tr></thead><tbody>
	<?php foreach ( $health_status as $check ) : ?>
		<?php $status_labels = array( 'ok' => 'سالم', 'warning' => 'نیازمند بررسی', 'error' => 'خطا' ); ?>
		<tr><td><?php echo esc_html( $check['label'] ); ?></td><td><strong class="crpcrm-status-<?php echo esc_attr( $check['status'] ); ?>"><?php echo esc_html( isset( $status_labels[ $check['status'] ] ) ? $status_labels[ $check['status'] ] : $check['status'] ); ?></strong></td><td><?php echo esc_html( $check['message'] ); ?></td></tr>
	<?php endforeach; ?>
	</tbody></table>
</div>
<?php endif; ?>

<?php if ( CRPCRM_Admin_Tools::can_export() ) : ?>
<div class="crpcrm-card">
	<h2><?php echo esc_html( 'خروجی گرفتن' ); ?></h2>
	<div class="crpcrm-tools-grid">
	<?php
	$export_forms = array(
		array( 'action' => 'crpcrm_export_requests', 'nonce' => 'crpcrm_export_requests', 'title' => 'خروجی درخواست‌ها', 'fields' => array( 'date_from' => 'تاریخ از', 'date_to' => 'تاریخ تا', 'request_type' => 'نوع درخواست', 'status' => 'وضعیت', 'source' => 'منبع', 'campaign' => 'کمپین', 'owner_id' => 'کارشناس مسئول' ) ),
		array( 'action' => 'crpcrm_export_customers', 'nonce' => 'crpcrm_export_customers', 'title' => 'خروجی مشتریان', 'fields' => array( 'date_from' => 'تاریخ ایجاد از', 'date_to' => 'تاریخ ایجاد تا', 'province' => 'استان', 'city' => 'شهر', 'first_source' => 'منبع اولین ورود', 'last_source' => 'منبع آخرین ورود', 'profile_completed' => 'پروفایل تکمیل شده / ناقص' ) ),
		array( 'action' => 'crpcrm_export_daily_reports', 'nonce' => 'crpcrm_export_daily_reports', 'title' => 'خروجی گزارش‌های روزانه کارکنان', 'fields' => array( 'date_from' => 'تاریخ از', 'date_to' => 'تاریخ تا', 'user_id' => 'کارمند', 'status' => 'وضعیت', 'needs_manager_attention' => 'نیازمند توجه مدیر' ) ),
		array( 'action' => 'crpcrm_export_staff_requests', 'nonce' => 'crpcrm_export_staff_requests', 'title' => 'خروجی درخواست‌های کارکنان از مدیریت', 'fields' => array( 'date_from' => 'تاریخ از', 'date_to' => 'تاریخ تا', 'user_id' => 'کارمند', 'category' => 'دسته‌بندی', 'priority' => 'اولویت', 'status' => 'وضعیت' ) ),
		array( 'action' => 'crpcrm_export_staff_issues', 'nonce' => 'crpcrm_export_staff_issues', 'title' => 'خروجی مشکلات و موانع کارکنان', 'fields' => array( 'date_from' => 'تاریخ از', 'date_to' => 'تاریخ تا', 'user_id' => 'کارمند', 'severity' => 'شدت', 'status' => 'وضعیت', 'needs_manager_decision' => 'نیازمند تصمیم مدیر' ) ),
	);
	?>
	<?php foreach ( $export_forms as $form ) : ?>
		<form method="get" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="crpcrm-export-form">
			<h3><?php echo esc_html( $form['title'] ); ?></h3>
			<input type="hidden" name="action" value="<?php echo esc_attr( $form['action'] ); ?>" />
			<?php wp_nonce_field( $form['nonce'], 'crpcrm_export_nonce' ); ?>
			<?php foreach ( $form['fields'] as $field_key => $field_label ) : ?>
				<label><?php echo esc_html( $field_label ); ?>
				<?php if ( in_array( $field_key, array( 'date_from', 'date_to' ), true ) ) : ?>
					<?php echo CRPCRM_Helpers::jalali_date_input( $field_key ); ?>
				<?php elseif ( 'user_id' === $field_key || 'owner_id' === $field_key ) : ?>
					<select name="<?php echo esc_attr( $field_key ); ?>"><option value=""><?php echo esc_html( 'همه' ); ?></option><?php foreach ( $staff_users as $staff_user ) : ?><option value="<?php echo esc_attr( $staff_user->ID ); ?>"><?php echo esc_html( $staff_user->display_name ); ?></option><?php endforeach; ?></select>
				<?php elseif ( in_array( $field_key, array( 'profile_completed', 'needs_manager_attention', 'needs_manager_decision' ), true ) ) : ?>
					<select name="<?php echo esc_attr( $field_key ); ?>"><option value=""><?php echo esc_html( 'همه' ); ?></option><option value="1"><?php echo esc_html( 'بله' ); ?></option><option value="0"><?php echo esc_html( 'خیر' ); ?></option></select>
				<?php else : ?>
					<input type="text" name="<?php echo esc_attr( $field_key ); ?>" />
				<?php endif; ?>
				</label>
			<?php endforeach; ?>
			<?php submit_button( 'دانلود CSV', 'secondary', 'submit', false ); ?>
		</form>
	<?php endforeach; ?>
	</div>
</div>
<?php endif; ?>

<?php if ( CRPCRM_Admin_Tools::can_maintain() ) : ?>
<div class="crpcrm-card">
	<h2><?php echo esc_html( 'پاکسازی لاگ‌ها' ); ?></h2>
	<p><?php echo esc_html( sprintf( 'لاگ‌های قدیمی‌تر از %d روز حذف می‌شوند.', absint( $settings['log_retention_days'] ) ) ); ?></p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="crpcrm_cleanup_logs" /><?php wp_nonce_field( 'crpcrm_cleanup_logs', 'crpcrm_tools_nonce' ); ?><?php submit_button( 'پاکسازی لاگ‌های قدیمی', 'delete', 'submit', false ); ?></form>
</div>
<div class="crpcrm-card">
	<h2><?php echo esc_html( 'ابزارهای بازسازی و ترمیم' ); ?></h2>
	<p><?php echo esc_html( 'ترمیم جدول‌ها با dbDelta اجرا می‌شود و داده‌های موجود حذف نمی‌شوند.' ); ?></p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="crpcrm_tools_rebuild_roles" /><?php wp_nonce_field( 'crpcrm_tools_rebuild_roles', 'crpcrm_tools_nonce' ); ?><?php submit_button( 'بازسازی نقش‌ها و دسترسی‌ها', 'secondary', 'submit', false ); ?></form>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="crpcrm_repair_tables" /><?php wp_nonce_field( 'crpcrm_repair_tables', 'crpcrm_tools_nonce' ); ?><?php submit_button( 'بررسی و ترمیم جدول‌ها', 'secondary', 'submit', false ); ?></form>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="crpcrm_fix_request_codes" /><?php wp_nonce_field( 'crpcrm_fix_request_codes', 'crpcrm_tools_nonce' ); ?><?php submit_button( 'بروزرسانی کد پیگیری درخواست‌های فاقد کد', 'secondary', 'submit', false ); ?></form>
	<?php if ( 'yes' === $settings['delete_data_on_uninstall'] ) : ?><p class="description crpcrm-danger-text"><?php echo esc_html( 'هشدار: گزینه حذف داده‌ها هنگام حذف افزونه فعال است.' ); ?></p><?php endif; ?>
</div>
<?php endif; ?>

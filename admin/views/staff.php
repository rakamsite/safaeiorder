<?php if ( ! defined( 'ABSPATH' ) ) { exit; }
$tabs = array( 'dashboard' => 'داشبورد کارکنان', 'daily_reports' => 'گزارش روزانه', 'requests' => 'درخواست از مدیریت', 'issues' => 'مشکلات و موانع', 'tasks' => 'وظایف', 'announcements' => 'اطلاعیه‌ها' );
$report_statuses = array( 'submitted' => 'ارسال شده', 'seen' => 'دیده شد', 'responded' => 'پاسخ داده شد', 'closed' => 'بسته شد' );
$request_statuses = array( 'new' => 'جدید', 'seen' => 'دیده شد', 'in_review' => 'در حال بررسی', 'done' => 'انجام شد', 'rejected' => 'رد شد' );
$issue_statuses = array( 'new' => 'جدید', 'seen' => 'دیده شد', 'in_review' => 'در حال بررسی', 'resolved' => 'حل شد', 'rejected' => 'رد شد' );
$task_statuses = array( 'new' => 'جدید', 'in_progress' => 'در حال انجام', 'done' => 'انجام شد', 'blocked' => 'متوقف شده', 'cancelled' => 'لغو شد' );
$employee_task_statuses = array( 'new' => 'جدید', 'in_progress' => 'در حال انجام', 'done' => 'انجام شد', 'blocked' => 'متوقف شده' );
$priorities = array( 'low' => 'کم', 'normal' => 'معمولی', 'high' => 'زیاد' );
$severities = array( 'low' => 'کم', 'medium' => 'متوسط', 'high' => 'زیاد' );
$categories = array( 'manager_decision' => 'نیاز به تصمیم مدیر', 'purchase_or_supply' => 'نیاز به خرید یا تأمین', 'customer_problem' => 'مشکل با مشتری', 'internal_process_problem' => 'مشکل فرایندی داخلی', 'improvement_suggestion' => 'پیشنهاد بهبود', 'error_or_bug_report' => 'گزارش خطا یا مشکل', 'other' => 'سایر' );
$audiences = array( 'all' => 'همه کارکنان', 'selected_roles' => 'نقش‌های منتخب', 'selected_users' => 'کاربران منتخب' );
$role_labels = array( 'sales_agent' => 'کارشناس فروش', 'sales_manager' => 'مدیر فروش', 'internal_employee' => 'کارمند داخلی', 'crm_admin' => 'مدیر CRM', 'administrator' => 'مدیر کل' );
$user_label = function( $id ) { $u = get_userdata( absint( $id ) ); return $u ? $u->display_name : 'کاربر حذف‌شده'; };
$badge = function( $value, $labels ) { return '<span class="crpcrm-badge crpcrm-status-badge crpcrm-status-' . esc_attr( $value ) . '">' . esc_html( isset( $labels[ $value ] ) ? $labels[ $value ] : $value ) . '</span>'; };
$selected = function( $a, $b ) { selected( (string) $a, (string) $b ); };
$edit_id = isset( $_GET['staff_item_id'] ) ? absint( $_GET['staff_item_id'] ) : 0;
$notice_labels = array( 'saved' => 'اطلاعات با موفقیت ذخیره شد.', 'access_denied' => 'شما اجازه انجام این عملیات را ندارید.', 'validation_error' => 'لطفاً فیلدهای الزامی گزارش فروش را تکمیل کنید.' );
$sales_stat_labels = isset( $sales_stats_service ) ? $sales_stats_service->get_labels() : array();
$sales_form_keys = array( 'claimed_today', 'current_owned_requests', 'actions_today', 'call_answered_today', 'call_no_answer_today', 'whatsapp_sent_today', 'internal_notes_today', 'followups_scheduled_today', 'followups_due_today', 'overdue_followups', 'won_today', 'lost_today', 'invalid_today', 'open_requests', 'closed_today' );
$sales_snapshot_keys = array( 'claimed_today', 'actions_today', 'call_answered_today', 'call_no_answer_today', 'whatsapp_sent_today', 'followups_due_today', 'overdue_followups', 'won_today', 'lost_today', 'invalid_today', 'open_requests', 'generated_at' );
$get_snapshot = function( $item ) use ( $sales_stats_service ) { return ( isset( $sales_stats_service ) && ! empty( $item['sales_crm_snapshot'] ) ) ? $sales_stats_service->decode_snapshot( $item['sales_crm_snapshot'] ) : array(); };
?>
<div class="wrap crpcrm-admin-wrap crpcrm-staff-admin" dir="rtl">
	<h1><?php echo esc_html( 'پنل کارکنان' ); ?></h1>
	<?php if ( $notice && isset( $notice_labels[ $notice ] ) ) : ?>
		<div class="notice <?php echo in_array( $notice, array( 'access_denied', 'validation_error' ), true ) ? 'notice-error' : 'notice-success'; ?> is-dismissible"><p><?php echo esc_html( $notice_labels[ $notice ] ); ?></p></div>
	<?php endif; ?>
	<?php if ( 'dashboard' === $tab && 'yes' === CRPCRM_Settings::get( 'daily_report_required', 'no' ) && empty( $today_report ) ) : ?>
		<div class="notice notice-warning"><p><?php echo esc_html( 'گزارش روزانه امروز هنوز ثبت نشده است.' ); ?></p></div>
	<?php endif; ?>
	<nav class="nav-tab-wrapper">
		<?php foreach ( $tabs as $key => $label ) : ?>
			<a class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => 'crpcrm-staff', 'staff_tab' => $key ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( $label ); ?></a>
		<?php endforeach; ?>
	</nav>

<?php if ( 'dashboard' === $tab ) : ?>
	<div class="crpcrm-summary-cards">
		<div><strong><?php echo esc_html( $dashboard_counts['today_report'] ); ?></strong><span>گزارش امروز من</span></div>
		<div><strong><?php echo esc_html( $dashboard_counts['open_requests'] ); ?></strong><span>درخواست‌های باز من از مدیریت</span></div>
		<div><strong><?php echo esc_html( $dashboard_counts['open_issues'] ); ?></strong><span>مشکلات/موانع باز من</span></div>
		<div><strong><?php echo esc_html( $dashboard_counts['open_tasks'] ); ?></strong><span>وظایف باز من</span></div>
		<div><strong><?php echo esc_html( $dashboard_counts['overdue_tasks'] ); ?></strong><span>وظایف عقب‌افتاده من</span></div>
		<div><strong><?php echo esc_html( $dashboard_counts['unread_announcements'] ); ?></strong><span>اطلاعیه‌های خوانده‌نشده</span></div>
	</div>
	<?php if ( $can_manage ) : ?>
		<div class="crpcrm-summary-cards">
			<div><strong><?php echo esc_html( count( $dashboard_counts['reported_users'] ) ); ?></strong><span>افرادی که امروز گزارش داده‌اند</span></div>
			<div><strong><?php echo esc_html( count( $dashboard_counts['not_reported_users'] ) ); ?></strong><span>افرادی که امروز گزارش نداده‌اند</span></div>
			<div><strong><?php echo esc_html( $dashboard_counts['attention_reports'] ); ?></strong><span>گزارش‌های نیازمند توجه مدیر</span></div>
			<div><strong><?php echo esc_html( $dashboard_counts['new_requests'] ); ?></strong><span>درخواست‌های جدید از مدیریت</span></div>
			<div><strong><?php echo esc_html( $dashboard_counts['all_open_issues'] ); ?></strong><span>مشکلات باز</span></div>
			<div><strong><?php echo esc_html( $dashboard_counts['all_overdue_tasks'] ); ?></strong><span>وظایف عقب‌افتاده</span></div>
			<div><strong><?php echo esc_html( $dashboard_counts['all_unread_announcements'] ); ?></strong><span>اطلاعیه‌های خوانده‌نشده توسط کارکنان</span></div>
		</div>
		<div class="crpcrm-detail-grid">
			<div class="crpcrm-card"><h2>گزارش داده‌اند</h2><p><?php echo esc_html( implode( '، ', array_map( $user_label, $dashboard_counts['reported_users'] ) ) ?: 'موردی نیست' ); ?></p></div>
			<div class="crpcrm-card"><h2>گزارش نداده‌اند</h2><p><?php echo esc_html( implode( '، ', array_map( $user_label, $dashboard_counts['not_reported_users'] ) ) ?: 'موردی نیست' ); ?></p></div>
		</div>
		<div class="crpcrm-card">
			<h2>خلاصه فروش امروز</h2>
			<table class="widefat striped"><thead><tr><th>کارشناس</th><th>گزارش امروز</th><th>اقدامات امروز</th><th>تماس پاسخ داده</th><th>تماس پاسخ نداد</th><th>پیگیری عقب‌افتاده</th><th>موفق امروز</th><th>ناموفق امروز</th></tr></thead><tbody>
			<?php foreach ( $dashboard_counts['sales_today_summary'] as $row ) : $stats = $row['stats']; ?>
				<tr><td><?php echo esc_html( $row['display_name'] ); ?></td><td><?php echo esc_html( $row['reported_today'] ? 'ثبت شده' : 'ثبت نشده' ); ?></td><td><?php echo esc_html( absint( $stats['actions_today'] ?? 0 ) ); ?></td><td><?php echo esc_html( absint( $stats['call_answered_today'] ?? 0 ) ); ?></td><td><?php echo esc_html( absint( $stats['call_no_answer_today'] ?? 0 ) ); ?></td><td><?php echo esc_html( absint( $stats['overdue_followups'] ?? 0 ) ); ?></td><td><?php echo esc_html( absint( $stats['won_today'] ?? 0 ) ); ?></td><td><?php echo esc_html( absint( $stats['lost_today'] ?? 0 ) ); ?></td></tr>
			<?php endforeach; ?>
			<?php if ( empty( $dashboard_counts['sales_today_summary'] ) ) : ?><tr><td colspan="8">فروشنده‌ای یافت نشد.</td></tr><?php endif; ?>
			</tbody></table>
		</div>
	<?php endif; ?>
<?php endif; ?>

<?php if ( 'daily_reports' === $tab ) : ?>
	<div class="crpcrm-card"><h2><?php echo $today_report ? 'ویرایش گزارش امروز' : 'ثبت گزارش روزانه'; ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'crpcrm_staff_save_daily_report' ); ?>
			<input type="hidden" name="action" value="crpcrm_staff_action">
			<input type="hidden" name="staff_action_type" value="save_daily_report">

			<?php if ( ! empty( $is_sales_user ) ) : ?>
				<div class="crpcrm-card">
					<h3>آمار امروز شما در CRM</h3>
					<?php if ( $sales_stats_service->all_zero( $current_sales_stats ) ) : ?>
						<p class="description">برای امروز فعالیتی در CRM ثبت نشده است.</p>
					<?php endif; ?>
					<?php if ( ! empty( $current_sales_stats['overdue_followups'] ) ) : ?>
						<div class="notice notice-warning inline"><p>شما پیگیری عقب‌افتاده دارید. لطفاً در توضیح گزارش روزانه وضعیت آن‌ها را مشخص کنید.</p></div>
					<?php endif; ?>
					<table class="widefat striped"><tbody>
						<?php foreach ( $sales_form_keys as $key ) : ?>
							<tr><th><?php echo esc_html( $sales_stat_labels[ $key ] ?? $key ); ?></th><td><?php echo esc_html( absint( $current_sales_stats[ $key ] ?? 0 ) ); ?></td></tr>
						<?php endforeach; ?>
					</tbody></table>
				</div>
			<?php endif; ?>

			<textarea name="completed_work" required placeholder="کارهای انجام‌شده امروز" rows="4" class="large-text"><?php echo esc_textarea( $today_report['completed_work'] ?? '' ); ?></textarea><br><br>
			<textarea name="unfinished_work" required placeholder="کارهای نیمه‌تمام" rows="4" class="large-text"><?php echo esc_textarea( $today_report['unfinished_work'] ?? '' ); ?></textarea><br><br>
			<textarea name="problems" required placeholder="مشکلات امروز" rows="4" class="large-text"><?php echo esc_textarea( $today_report['problems'] ?? '' ); ?></textarea><br><br>
			<textarea name="tomorrow_plan" required placeholder="برنامه فردا" rows="4" class="large-text"><?php echo esc_textarea( $today_report['tomorrow_plan'] ?? '' ); ?></textarea><br><br>
			<label><input type="checkbox" name="needs_manager_attention" value="1" <?php checked( ! empty( $today_report['needs_manager_attention'] ) ); ?>> نیاز به توجه مدیر دارد؟</label>
			<?php if ( ! empty( $is_sales_user ) ) : ?>
				<p><label><strong>توضیح تکمیلی فروش</strong><br>
					<textarea name="sales_comment" required rows="4" class="large-text" placeholder="اگر درباره کیفیت لیدها، مشکلات پیگیری، کمپین‌ها یا نتیجه تماس‌های امروز نکته‌ای دارید، اینجا بنویسید."><?php echo esc_textarea( $today_report['sales_comment'] ?? '' ); ?></textarea>
				</label></p>
				<p class="description">اگر درباره کیفیت لیدها، مشکلات پیگیری، کمپین‌ها یا نتیجه تماس‌های امروز نکته‌ای دارید، اینجا بنویسید.</p>
			<?php endif; ?>
			<?php submit_button( 'ثبت گزارش روزانه' ); ?>
		</form>
		<?php if ( ! empty( $today_report['manager_response'] ) ) : ?><p><strong>پاسخ مدیر:</strong> <?php echo esc_html( $today_report['manager_response'] ); ?></p><?php endif; ?>
	</div>
	<?php include __DIR__ . '/staff-parts/filters-daily.php'; ?>
	<table class="widefat striped crpcrm-requests-table"><thead><tr><th>تاریخ</th><th>کارمند</th><th>نیازمند توجه مدیر</th><th>وضعیت</th><?php if ( $can_manage ) : ?><th>آمار CRM</th><?php endif; ?><th>تاریخ ثبت</th><th>عملیات</th></tr></thead><tbody>
	<?php foreach ( $items as $item ) : $snapshot = $get_snapshot( $item ); ?>
		<tr>
			<td><?php echo esc_html( $item['report_date'] ); ?></td>
			<td><?php echo esc_html( $user_label( $item['user_id'] ) ); ?></td>
			<td><?php echo esc_html( $item['needs_manager_attention'] ? 'بله' : 'خیر' ); ?></td>
			<td><?php echo $badge( $item['status'], $report_statuses ); ?></td>
			<?php if ( $can_manage ) : ?><td><?php echo $snapshot ? esc_html( 'اقدام: ' . absint( $snapshot['actions_today'] ) . ' | موفق: ' . absint( $snapshot['won_today'] ) . ' | عقب‌افتاده: ' . absint( $snapshot['overdue_followups'] ) ) : ( $sales_stats_service->is_sales_user( $item['user_id'] ) ? esc_html( 'ندارد' ) : esc_html( '—' ) ); ?></td><?php endif; ?>
			<td><?php echo esc_html( $item['created_at'] ); ?></td>
			<td>
				<?php if ( ! empty( $item['manager_response'] ) ) echo '<p><strong>پاسخ:</strong> ' . esc_html( $item['manager_response'] ) . '</p>'; ?>
				<?php if ( $can_manage ) : ?>
					<div class="crpcrm-card"><h3>آمار CRM ثبت‌شده در زمان ارسال گزارش</h3>
						<?php if ( $snapshot ) : ?>
							<table class="widefat striped"><tbody><?php foreach ( $sales_snapshot_keys as $key ) : ?><tr><th><?php echo esc_html( $sales_stat_labels[ $key ] ?? $key ); ?></th><td><?php echo esc_html( 'generated_at' === $key ? $snapshot[ $key ] : absint( $snapshot[ $key ] ?? 0 ) ); ?></td></tr><?php endforeach; ?></tbody></table>
						<?php else : ?>
							<p>برای این گزارش، آمار CRM ثبت نشده است.</p>
						<?php endif; ?>
					</div>
					<?php if ( ! empty( $item['sales_comment'] ) ) : ?><p><strong>توضیح تکمیلی فروش:</strong> <?php echo esc_html( $item['sales_comment'] ); ?></p><?php endif; ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php wp_nonce_field( 'crpcrm_staff_manage_daily_report' ); ?><input type="hidden" name="action" value="crpcrm_staff_action"><input type="hidden" name="staff_action_type" value="manage_daily_report"><input type="hidden" name="report_id" value="<?php echo esc_attr( $item['id'] ); ?>"><select name="manager_action"><option value="seen">دیده شد</option><option value="responded">پاسخ داده شد</option><option value="closed">بسته شد</option></select><textarea name="manager_response" rows="2" class="large-text" placeholder="پاسخ مدیریتی"><?php echo esc_textarea( $item['manager_response'] ); ?></textarea><?php submit_button( 'ثبت عملیات', 'secondary small', '', false ); ?></form>
				<?php endif; ?>
			</td>
		</tr>
	<?php endforeach; ?>
	</tbody></table>
<?php endif; ?>

<?php if ( 'requests' === $tab ) : $edit = $edit_id ? $repository->get_staff_request( $edit_id ) : null; if ( $edit && ( absint( $edit['user_id'] ) !== get_current_user_id() || 'new' !== $edit['status'] ) ) { $edit = null; } ?>
	<div class="crpcrm-card"><h2><?php echo $edit ? 'ویرایش درخواست از مدیریت' : 'ثبت درخواست از مدیریت'; ?></h2><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php wp_nonce_field( 'crpcrm_staff_save_staff_request' ); ?><input type="hidden" name="action" value="crpcrm_staff_action"><input type="hidden" name="staff_action_type" value="save_staff_request"><input type="hidden" name="request_id" value="<?php echo esc_attr( $edit['id'] ?? 0 ); ?>"><p><select name="category" required><?php foreach ( $categories as $k=>$v ) : ?><option value="<?php echo esc_attr($k); ?>" <?php selected($edit['category'] ?? '', $k); ?>><?php echo esc_html($v); ?></option><?php endforeach; ?></select></p><p><input type="text" name="title" required class="regular-text" placeholder="عنوان" value="<?php echo esc_attr( $edit['title'] ?? '' ); ?>"></p><p><textarea name="description" required rows="5" class="large-text" placeholder="توضیحات"><?php echo esc_textarea( $edit['description'] ?? '' ); ?></textarea></p><p><select name="priority" required><?php foreach ( $priorities as $k=>$v ) : ?><option value="<?php echo esc_attr($k); ?>" <?php selected($edit['priority'] ?? 'normal', $k); ?>><?php echo esc_html($v); ?></option><?php endforeach; ?></select></p><?php submit_button( $edit ? 'به‌روزرسانی درخواست' : 'ثبت درخواست' ); ?></form></div>
	<?php include __DIR__ . '/staff-parts/filters-requests.php'; ?>
	<table class="widefat striped"><thead><tr><th>تاریخ</th><th>کارمند</th><th>دسته‌بندی</th><th>عنوان</th><th>اولویت</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody><?php foreach ( $items as $item ) : ?><tr><td><?php echo esc_html($item['created_at']); ?></td><td><?php echo esc_html($user_label($item['user_id'])); ?></td><td><?php echo esc_html($categories[$item['category']] ?? $item['category']); ?></td><td><strong><?php echo esc_html($item['title']); ?></strong><p><?php echo esc_html($item['description']); ?></p><?php if($item['manager_response']) echo '<p><strong>پاسخ مدیر:</strong> '.esc_html($item['manager_response']).'</p>'; ?></td><td><?php echo $badge($item['priority'],$priorities); ?></td><td><?php echo $badge($item['status'],$request_statuses); ?></td><td><?php if(!$can_manage && absint($item['user_id'])===get_current_user_id() && 'new'===$item['status']) : ?><a class="button" href="<?php echo esc_url(add_query_arg(array('page'=>'crpcrm-staff','staff_tab'=>'requests','staff_item_id'=>$item['id']),admin_url('admin.php'))); ?>">ویرایش</a><?php endif; if($can_manage): ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('crpcrm_staff_manage_staff_request'); ?><input type="hidden" name="action" value="crpcrm_staff_action"><input type="hidden" name="staff_action_type" value="manage_staff_request"><input type="hidden" name="request_id" value="<?php echo esc_attr($item['id']); ?>"><select name="status"><?php foreach($request_statuses as $k=>$v): ?><option value="<?php echo esc_attr($k); ?>" <?php selected($item['status'],$k); ?>><?php echo esc_html($v); ?></option><?php endforeach; ?></select><textarea name="manager_response" rows="2" class="large-text" placeholder="پاسخ مدیریتی"><?php echo esc_textarea($item['manager_response']); ?></textarea><?php submit_button('ثبت پاسخ','secondary small','',false); ?></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table>
<?php endif; ?>

<?php if ( 'issues' === $tab ) : $edit = $edit_id ? $repository->get_issue( $edit_id ) : null; if ( $edit && ( absint( $edit['user_id'] ) !== get_current_user_id() || 'new' !== $edit['status'] ) ) { $edit = null; } ?>
	<div class="crpcrm-card"><h2><?php echo $edit ? 'ویرایش مشکل یا مانع' : 'ثبت مشکل یا مانع'; ?></h2><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('crpcrm_staff_save_issue'); ?><input type="hidden" name="action" value="crpcrm_staff_action"><input type="hidden" name="staff_action_type" value="save_issue"><input type="hidden" name="issue_id" value="<?php echo esc_attr($edit['id'] ?? 0); ?>"><p><input type="text" name="title" required class="regular-text" placeholder="عنوان" value="<?php echo esc_attr($edit['title'] ?? ''); ?>"></p><p><input type="text" name="related_department" required class="regular-text" placeholder="واحد مرتبط" value="<?php echo esc_attr($edit['related_department'] ?? ''); ?>"></p><p><select name="severity" required><?php foreach($severities as $k=>$v): ?><option value="<?php echo esc_attr($k); ?>" <?php selected($edit['severity'] ?? 'medium',$k); ?>><?php echo esc_html($v); ?></option><?php endforeach; ?></select></p><p><textarea name="description" required rows="4" class="large-text" placeholder="توضیحات"><?php echo esc_textarea($edit['description'] ?? ''); ?></textarea></p><p><textarea name="suggested_solution" required rows="4" class="large-text" placeholder="پیشنهاد راه‌حل"><?php echo esc_textarea($edit['suggested_solution'] ?? ''); ?></textarea></p><label><input type="checkbox" name="needs_manager_decision" value="1" <?php checked(!empty($edit['needs_manager_decision'])); ?>> نیاز به تصمیم مدیر؟</label><?php submit_button($edit ? 'به‌روزرسانی مشکل' : 'ثبت مشکل'); ?></form></div>
	<?php include __DIR__ . '/staff-parts/filters-issues.php'; ?>
	<table class="widefat striped"><thead><tr><th>تاریخ</th><th>کارمند</th><th>عنوان</th><th>واحد مرتبط</th><th>شدت</th><th>نیاز به تصمیم مدیر</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody><?php foreach($items as $item): ?><tr><td><?php echo esc_html($item['created_at']); ?></td><td><?php echo esc_html($user_label($item['user_id'])); ?></td><td><strong><?php echo esc_html($item['title']); ?></strong><p><?php echo esc_html($item['description']); ?></p><p><strong>راه‌حل:</strong> <?php echo esc_html($item['suggested_solution']); ?></p><?php if($item['manager_response']) echo '<p><strong>پاسخ مدیر:</strong> '.esc_html($item['manager_response']).'</p>'; ?></td><td><?php echo esc_html($item['related_department']); ?></td><td><?php echo $badge($item['severity'],$severities); ?></td><td><?php echo $item['needs_manager_decision']?'بله':'خیر'; ?></td><td><?php echo $badge($item['status'],$issue_statuses); ?></td><td><?php if(!$can_manage && absint($item['user_id'])===get_current_user_id() && 'new'===$item['status']): ?><a class="button" href="<?php echo esc_url(add_query_arg(array('page'=>'crpcrm-staff','staff_tab'=>'issues','staff_item_id'=>$item['id']),admin_url('admin.php'))); ?>">ویرایش</a><?php endif; if($can_manage): ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('crpcrm_staff_manage_issue'); ?><input type="hidden" name="action" value="crpcrm_staff_action"><input type="hidden" name="staff_action_type" value="manage_issue"><input type="hidden" name="issue_id" value="<?php echo esc_attr($item['id']); ?>"><select name="status"><?php foreach($issue_statuses as $k=>$v): ?><option value="<?php echo esc_attr($k); ?>" <?php selected($item['status'],$k); ?>><?php echo esc_html($v); ?></option><?php endforeach; ?></select><textarea name="manager_response" rows="2" class="large-text" placeholder="پاسخ مدیریتی"><?php echo esc_textarea($item['manager_response']); ?></textarea><?php submit_button('ثبت پاسخ','secondary small','',false); ?></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table>
<?php endif; ?>

<?php if ( 'tasks' === $tab ) : $edit = ( $can_manage && $edit_id ) ? $repository->get_task( $edit_id ) : null; ?>
	<?php if ( $can_manage ) : ?><div class="crpcrm-card"><h2><?php echo $edit ? 'ویرایش وظیفه' : 'ایجاد وظیفه'; ?></h2><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('crpcrm_staff_save_task'); ?><input type="hidden" name="action" value="crpcrm_staff_action"><input type="hidden" name="staff_action_type" value="save_task"><input type="hidden" name="task_id" value="<?php echo esc_attr($edit['id'] ?? 0); ?>"><p><input type="text" name="title" required class="regular-text" placeholder="عنوان" value="<?php echo esc_attr($edit['title'] ?? ''); ?>"></p><p><textarea name="description" required rows="4" class="large-text" placeholder="توضیحات"><?php echo esc_textarea($edit['description'] ?? ''); ?></textarea></p><p><select name="assigned_to" required><?php foreach($staff_users as $u): ?><option value="<?php echo esc_attr($u->ID); ?>" <?php selected($edit['assigned_to'] ?? '',$u->ID); ?>><?php echo esc_html($u->display_name); ?></option><?php endforeach; ?></select></p><p><input type="date" name="due_date" required value="<?php echo esc_attr($edit['due_date'] ?? $today); ?>"></p><p><select name="priority" required><?php foreach($priorities as $k=>$v): ?><option value="<?php echo esc_attr($k); ?>" <?php selected($edit['priority'] ?? 'normal',$k); ?>><?php echo esc_html($v); ?></option><?php endforeach; ?></select> <select name="status"><?php foreach($task_statuses as $k=>$v): ?><option value="<?php echo esc_attr($k); ?>" <?php selected($edit['status'] ?? 'new',$k); ?>><?php echo esc_html($v); ?></option><?php endforeach; ?></select></p><p><textarea name="manager_note" rows="3" class="large-text" placeholder="یادداشت مدیر"><?php echo esc_textarea($edit['manager_note'] ?? ''); ?></textarea></p><?php submit_button($edit ? 'به‌روزرسانی وظیفه' : 'ایجاد وظیفه'); ?></form></div><?php endif; ?>
	<?php include __DIR__ . '/staff-parts/filters-tasks.php'; ?>
	<table class="widefat striped"><thead><tr><th>عنوان</th><th>مسئول</th><th>مهلت</th><th>اولویت</th><th>وضعیت</th><th>ایجادکننده</th><th>تاریخ ایجاد</th><th>عملیات</th></tr></thead><tbody><?php foreach($items as $item): ?><tr><td><strong><?php echo esc_html($item['title']); ?></strong><p><?php echo esc_html($item['description']); ?></p><?php if($item['employee_update']) echo '<p><strong>گزارش کارمند:</strong> '.esc_html($item['employee_update']).'</p>'; if($item['manager_note']) echo '<p><strong>یادداشت مدیر:</strong> '.esc_html($item['manager_note']).'</p>'; ?></td><td><?php echo esc_html($user_label($item['assigned_to'])); ?></td><td><?php echo esc_html($item['due_date']); ?><?php if($item['due_date'] < $today && !in_array($item['status'],array('done','cancelled'),true)) echo ' <span class="crpcrm-badge crpcrm-status-new">عقب‌افتاده</span>'; ?></td><td><?php echo $badge($item['priority'],$priorities); ?></td><td><?php echo $badge($item['status'],$task_statuses); ?></td><td><?php echo esc_html($user_label($item['created_by'])); ?></td><td><?php echo esc_html($item['created_at']); ?></td><td><?php if($can_manage): ?><a class="button" href="<?php echo esc_url(add_query_arg(array('page'=>'crpcrm-staff','staff_tab'=>'tasks','staff_item_id'=>$item['id']),admin_url('admin.php'))); ?>">ویرایش</a><?php endif; ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('crpcrm_staff_update_task_status'); ?><input type="hidden" name="action" value="crpcrm_staff_action"><input type="hidden" name="staff_action_type" value="update_task_status"><input type="hidden" name="task_id" value="<?php echo esc_attr($item['id']); ?>"><select name="status"><?php foreach(($can_manage?$task_statuses:$employee_task_statuses) as $k=>$v): ?><option value="<?php echo esc_attr($k); ?>" <?php selected($item['status'],$k); ?>><?php echo esc_html($v); ?></option><?php endforeach; ?></select><textarea name="note" rows="2" class="large-text" placeholder="توضیح وضعیت"><?php echo esc_textarea($can_manage?$item['manager_note']:$item['employee_update']); ?></textarea><?php submit_button('تغییر وضعیت','secondary small','',false); ?></form></td></tr><?php endforeach; ?></tbody></table>
<?php endif; ?>

<?php if ( 'announcements' === $tab ) : ?>
	<?php if ( $can_manage ) : ?><div class="crpcrm-card"><h2>ثبت اطلاعیه</h2><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('crpcrm_staff_create_announcement'); ?><input type="hidden" name="action" value="crpcrm_staff_action"><input type="hidden" name="staff_action_type" value="create_announcement"><p><input type="text" name="title" required class="regular-text" placeholder="عنوان"></p><p><textarea name="body" required rows="5" class="large-text" placeholder="متن اطلاعیه"></textarea></p><p><select name="audience_type" required><option value="all">همه کارکنان</option><option value="selected_roles">نقش‌های منتخب</option><option value="selected_users">کاربران منتخب</option></select></p><p><strong>نقش‌های منتخب:</strong><br><?php foreach($role_labels as $role=>$label): ?><label><input type="checkbox" name="audience_roles[]" value="<?php echo esc_attr($role); ?>"> <?php echo esc_html($label); ?></label> <?php endforeach; ?></p><p><strong>کاربران منتخب:</strong><br><?php foreach($staff_users as $u): ?><label><input type="checkbox" name="audience_users[]" value="<?php echo esc_attr($u->ID); ?>"> <?php echo esc_html($u->display_name); ?></label> <?php endforeach; ?></p><?php submit_button('ثبت اطلاعیه'); ?></form></div><?php endif; ?>
	<?php include __DIR__ . '/staff-parts/filters-announcements.php'; ?>
	<table class="widefat striped"><thead><tr><th>عنوان</th><th>مخاطب</th><th>ایجادکننده</th><th>تاریخ ایجاد</th><th>تعداد مشاهده‌شده</th><th>عملیات</th></tr></thead><tbody><?php foreach($items as $item): $stats=$repository->get_announcement_read_stats($item['id']); ?><tr><td><strong><?php echo esc_html($item['title']); ?></strong><p><?php echo esc_html($item['body']); ?></p></td><td><?php echo esc_html($audiences[$item['audience_type']] ?? $item['audience_type']); ?></td><td><?php echo esc_html($user_label($item['created_by'])); ?></td><td><?php echo esc_html($item['created_at']); ?></td><td><?php echo esc_html($stats['seen']); ?> / <?php echo esc_html($stats['seen']+$stats['unseen']); ?></td><td><?php if($can_manage): ?><p><strong>دیده‌اند:</strong> <?php echo esc_html(implode('، ', array_map($user_label,$stats['seen_ids'])) ?: 'هیچ‌کس'); ?></p><p><strong>ندیده‌اند:</strong> <?php echo esc_html(implode('، ', array_map($user_label,$stats['unseen_ids'])) ?: 'هیچ‌کس'); ?></p><?php else: $read=$repository->user_has_read_announcement($item['id'],get_current_user_id()); if($read): ?><span class="crpcrm-badge crpcrm-status-badge">مشاهده شده</span><?php else: ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('crpcrm_staff_mark_announcement_read'); ?><input type="hidden" name="action" value="crpcrm_staff_action"><input type="hidden" name="staff_action_type" value="mark_announcement_read"><input type="hidden" name="announcement_id" value="<?php echo esc_attr($item['id']); ?>"><?php submit_button('مشاهده شد','secondary small','',false); ?></form><?php endif; endif; ?></td></tr><?php endforeach; ?></tbody></table>
<?php endif; ?>
</div>

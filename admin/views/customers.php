<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'crpcrm_admin_customers_url' ) ) {
function crpcrm_admin_customers_url( $args = array() ) {
	return add_query_arg( array_merge( array( 'page' => 'crpcrm-customers' ), $args ), admin_url( 'admin.php' ) );
}
}

if ( ! function_exists( 'crpcrm_admin_customer_profile_url' ) ) {
function crpcrm_admin_customer_profile_url( $customer_id, $args = array() ) {
	return add_query_arg( array_merge( array( 'page' => 'crpcrm-customer-profile', 'customer_id' => absint( $customer_id ) ), $args ), admin_url( 'admin.php' ) );
}
}

if ( ! function_exists( 'crpcrm_admin_request_detail_url' ) ) {
function crpcrm_admin_request_detail_url( $request_id ) {
	return add_query_arg( array( 'page' => 'crpcrm-requests', 'request_id' => absint( $request_id ) ), admin_url( 'admin.php' ) );
}
}

if ( ! function_exists( 'crpcrm_display_value' ) ) {
function crpcrm_display_value( $value ) {
	return '' !== (string) $value && null !== $value ? $value : 'ثبت نشده';
}
}

if ( ! function_exists( 'crpcrm_customer_agent_name' ) ) {
function crpcrm_customer_agent_name( $user_id ) {
	$user = get_userdata( absint( $user_id ) );
	return $user ? $user->display_name : 'کاربر حذف‌شده';
}
}

$sources = array( 'direct', 'instagram', 'whatsapp', 'telegram', 'google', 'bing' );
$notice_messages = array(
	'customer_deleted'       => array( 'success', 'مشتری و تمام درخواست‌ها و اطلاعات CRM مرتبط با او حذف شد. حساب وردپرس مشتری، در صورت وجود، حذف نشده است.' ),
	'customer_delete_denied' => array( 'error', 'شما اجازه حذف مشتری را ندارید.' ),
	'customer_not_found'     => array( 'error', 'مشتری موردنظر یافت نشد.' ),
	'customer_delete_failed' => array( 'error', 'حذف کامل مشتری انجام نشد. لطفاً دوباره تلاش کنید.' ),
);
$notice = isset( $_GET['crpcrm_notice'] ) ? sanitize_key( wp_unslash( $_GET['crpcrm_notice'] ) ) : '';
?>
<div class="wrap crpcrm-admin-wrap crpcrm-customers-admin" dir="rtl">
	<?php if ( 'list' === $mode ) : ?>
		<h1><?php echo esc_html( 'مشتریان' ); ?></h1>
		<?php if ( isset( $notice_messages[ $notice ] ) ) : ?><div class="notice notice-<?php echo esc_attr( $notice_messages[ $notice ][0] ); ?> is-dismissible"><p><?php echo esc_html( $notice_messages[ $notice ][1] ); ?></p></div><?php endif; ?>
		<p><?php echo esc_html( 'لیست ساده مشتریان CRM با خلاصه منابع ورود و وضعیت درخواست‌ها.' ); ?></p>
		<form method="get" class="crpcrm-request-filters">
			<input type="hidden" name="page" value="crpcrm-customers">
			<div class="crpcrm-filter-grid">
				<label><?php echo esc_html( 'منبع اولین ورود' ); ?>
					<select name="first_source"><option value=""><?php echo esc_html( 'همه' ); ?></option><?php foreach ( $sources as $source ) : ?><option value="<?php echo esc_attr( $source ); ?>" <?php selected( $filters['first_source'], $source ); ?>><?php echo esc_html( CRPCRM_Helpers::get_source_label( $source ) ); ?></option><?php endforeach; ?></select>
				</label>
				<label><?php echo esc_html( 'منبع آخرین ورود' ); ?>
					<select name="last_source"><option value=""><?php echo esc_html( 'همه' ); ?></option><?php foreach ( $sources as $source ) : ?><option value="<?php echo esc_attr( $source ); ?>" <?php selected( $filters['last_source'], $source ); ?>><?php echo esc_html( CRPCRM_Helpers::get_source_label( $source ) ); ?></option><?php endforeach; ?></select>
				</label>
				<label><?php echo esc_html( 'استان' ); ?><input type="text" name="province" value="<?php echo esc_attr( $filters['province'] ); ?>"></label>
				<label><?php echo esc_html( 'شهر' ); ?><input type="text" name="city" value="<?php echo esc_attr( $filters['city'] ); ?>"></label>
				<label><?php echo esc_html( 'درخواست باز' ); ?>
					<select name="open_filter"><option value=""><?php echo esc_html( 'همه' ); ?></option><option value="has_open" <?php selected( $filters['open_filter'], 'has_open' ); ?>><?php echo esc_html( 'دارای درخواست باز' ); ?></option><option value="no_open" <?php selected( $filters['open_filter'], 'no_open' ); ?>><?php echo esc_html( 'بدون درخواست باز' ); ?></option></select>
				</label>
				<label><?php echo esc_html( 'جستجو' ); ?><input type="search" name="search" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="<?php echo esc_attr( 'نام یا موبایل' ); ?>"></label>
			</div>
			<p class="submit"><button type="submit" class="button button-primary"><?php echo esc_html( 'اعمال فیلتر' ); ?></button> <a class="button" href="<?php echo esc_url( crpcrm_admin_customers_url() ); ?>"><?php echo esc_html( 'پاک کردن فیلترها' ); ?></a></p>
		</form>

		<table class="widefat fixed striped crpcrm-customers-table">
			<thead><tr><th><?php echo esc_html( 'نام و نام خانوادگی' ); ?></th><th><?php echo esc_html( 'شماره موبایل' ); ?></th><th><?php echo esc_html( 'استان' ); ?></th><th><?php echo esc_html( 'شهر' ); ?></th><th><?php echo esc_html( 'منبع اولین ورود' ); ?></th><th><?php echo esc_html( 'منبع آخرین ورود' ); ?></th><th><?php echo esc_html( 'تعداد درخواست‌ها' ); ?></th><th><?php echo esc_html( 'درخواست‌های باز' ); ?></th><th><?php echo esc_html( 'آخرین درخواست' ); ?></th><th><?php echo esc_html( 'تاریخ ثبت‌نام / ایجاد پروفایل' ); ?></th><th><?php echo esc_html( 'عملیات' ); ?></th></tr></thead>
			<tbody>
			<?php if ( empty( $customers ) ) : ?>
				<tr><td colspan="11"><?php echo esc_html( 'مشتری‌ای یافت نشد.' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $customers as $item ) : ?>
					<tr>
						<td><?php echo esc_html( $item['full_name'] ? $item['full_name'] : '—' ); ?></td>
						<td><?php echo esc_html( $item['phone'] ? $item['phone'] : $item['phone_normalized'] ); ?></td>
						<td><?php echo esc_html( $item['province'] ? $item['province'] : '—' ); ?></td>
						<td><?php echo esc_html( $item['city'] ? $item['city'] : '—' ); ?></td>
						<td><span class="crpcrm-badge crpcrm-source-badge"><?php echo esc_html( CRPCRM_Helpers::get_source_label( $item['first_source'] ) ); ?></span></td>
						<td><span class="crpcrm-badge crpcrm-source-badge"><?php echo esc_html( CRPCRM_Helpers::get_source_label( $item['last_source'] ) ); ?></span></td>
						<td><?php echo esc_html( number_format_i18n( absint( $item['requests_count'] ) ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( absint( $item['open_requests_count'] ) ) ); ?></td>
						<td><?php echo esc_html( $item['last_request_code'] ? $item['last_request_code'] . ' - ' . CRPCRM_Helpers::format_jalali_datetime( $item['last_request_at'] ) : '—' ); ?></td>
						<td><?php echo esc_html( CRPCRM_Helpers::format_jalali_datetime( $item['created_at'] ) ); ?></td>
						<td>
							<a class="button button-small" href="<?php echo esc_url( crpcrm_admin_customer_profile_url( $item['id'] ) ); ?>"><?php echo esc_html( 'مشاهده پروفایل' ); ?></a>
							<?php if ( current_user_can( 'manage_options' ) ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline" onsubmit="return confirm('<?php echo esc_js( 'این مشتری و تمام درخواست‌ها و اطلاعات CRM مرتبط با او برای همیشه حذف می‌شود. حساب وردپرس او حذف نخواهد شد. ادامه می‌دهید؟' ); ?>');">
									<input type="hidden" name="action" value="crpcrm_delete_customer">
									<input type="hidden" name="customer_id" value="<?php echo esc_attr( absint( $item['id'] ) ); ?>">
									<?php wp_nonce_field( 'crpcrm_delete_customer_' . absint( $item['id'] ) ); ?>
									<button type="submit" class="button button-small button-link-delete"><?php echo esc_html( 'حذف مشتری' ); ?></button>
								</form>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
			</tbody>
		</table>
		<?php if ( $total_pages > 1 ) : ?>
			<div class="tablenav"><div class="tablenav-pages">
				<?php $base_args = array_filter( $filters ); ?>
				<?php if ( $page > 1 ) : ?><a class="button" href="<?php echo esc_url( crpcrm_admin_customers_url( array_merge( $base_args, array( 'paged' => $page - 1 ) ) ) ); ?>"><?php echo esc_html( 'قبلی' ); ?></a><?php endif; ?>
				<span class="crpcrm-page-count"><?php echo esc_html( sprintf( 'صفحه %1$d از %2$d', $page, $total_pages ) ); ?></span>
				<?php if ( $page < $total_pages ) : ?><a class="button" href="<?php echo esc_url( crpcrm_admin_customers_url( array_merge( $base_args, array( 'paged' => $page + 1 ) ) ) ); ?>"><?php echo esc_html( 'بعدی' ); ?></a><?php endif; ?>
			</div></div>
		<?php endif; ?>
	<?php elseif ( 'profile' === $mode ) : ?>
		<h1><?php echo esc_html( 'پروفایل تحلیلی مشتری' ); ?></h1>
		<div class="crpcrm-profile-header"><h2><?php echo esc_html( $customer['full_name'] ? $customer['full_name'] : 'مشتری بدون نام' ); ?></h2><span><?php echo esc_html( $customer['phone'] ? $customer['phone'] : $customer['phone_normalized'] ); ?></span></div>
		<p><a class="button" href="<?php echo esc_url( crpcrm_admin_customers_url() ); ?>"><?php echo esc_html( 'بازگشت به مشتریان' ); ?></a><?php if ( ! empty( $return_request_id ) ) : ?> <a class="button" href="<?php echo esc_url( crpcrm_admin_request_detail_url( $return_request_id ) ); ?>"><?php echo esc_html( 'بازگشت به درخواست' ); ?></a><?php endif; ?></p>

		<div class="crpcrm-detail-grid">
			<div class="crpcrm-card"><h2><?php echo esc_html( 'اطلاعات پایه مشتری' ); ?></h2><dl><dt><?php echo esc_html( 'نام و نام خانوادگی' ); ?></dt><dd><?php echo esc_html( crpcrm_display_value( $customer['full_name'] ) ); ?></dd><dt><?php echo esc_html( 'شماره موبایل' ); ?></dt><dd><?php echo esc_html( crpcrm_display_value( $customer['phone'] ? $customer['phone'] : $customer['phone_normalized'] ) ); ?></dd><dt><?php echo esc_html( 'استان' ); ?></dt><dd><?php echo esc_html( crpcrm_display_value( $customer['province'] ) ); ?></dd><dt><?php echo esc_html( 'شهر' ); ?></dt><dd><?php echo esc_html( crpcrm_display_value( $customer['city'] ) ); ?></dd><dt><?php echo esc_html( 'وضعیت تکمیل پروفایل' ); ?></dt><dd><span class="crpcrm-badge crpcrm-status-badge"><?php echo esc_html( ! empty( $customer['profile_completed'] ) ? 'تکمیل شده' : 'ناقص' ); ?></span></dd><dt><?php echo esc_html( 'تاریخ ایجاد پروفایل' ); ?></dt><dd><?php echo esc_html( ( $customer['created_at'] ? CRPCRM_Helpers::format_jalali_datetime( $customer['created_at'] ) : 'ثبت نشده' ) ); ?></dd><dt><?php echo esc_html( 'آخرین بروزرسانی پروفایل' ); ?></dt><dd><?php echo esc_html( ( $customer['updated_at'] ? CRPCRM_Helpers::format_jalali_datetime( $customer['updated_at'] ) : 'ثبت نشده' ) ); ?></dd></dl></div>
			<?php if ( ! empty( array_filter( $registration_values ) ) ) : ?>
				<div class="crpcrm-card"><h2><?php echo esc_html( 'اطلاعات تکمیلی ثبت‌نام مشتری' ); ?></h2><dl>
					<?php foreach ( $registration_fields as $field_id => $field ) : ?><?php if ( isset( $registration_values[ $field_id ] ) && '' !== trim( (string) $registration_values[ $field_id ] ) ) : ?><dt><?php echo esc_html( $field['label'] ); ?></dt><dd><?php echo esc_html( $registration_values[ $field_id ] ); ?></dd><?php endif; ?><?php endforeach; ?>
				</dl></div>
			<?php endif; ?>
			<div class="crpcrm-card"><h2><?php echo esc_html( 'اولین ورود' ); ?></h2><dl><dt><?php echo esc_html( 'منبع' ); ?></dt><dd><?php echo esc_html( CRPCRM_Helpers::get_source_label( $customer['first_source'] ) ); ?></dd><dt><?php echo esc_html( 'مدیوم' ); ?></dt><dd><?php echo esc_html( CRPCRM_Helpers::get_medium_label( $customer['first_medium'] ) ); ?></dd><dt><?php echo esc_html( 'کمپین' ); ?></dt><dd><?php echo esc_html( crpcrm_display_value( $customer['first_campaign'] ) ); ?></dd><dt><?php echo esc_html( 'محتوا' ); ?></dt><dd><?php echo esc_html( crpcrm_display_value( $customer['first_content'] ) ); ?></dd><dt><?php echo esc_html( 'کلمه کلیدی' ); ?></dt><dd><?php echo esc_html( crpcrm_display_value( $customer['first_term'] ) ); ?></dd><dt><?php echo esc_html( 'صفحه ورود' ); ?></dt><dd><?php echo esc_html( crpcrm_display_value( $customer['first_landing_page'] ) ); ?></dd><dt><?php echo esc_html( 'ارجاع‌دهنده' ); ?></dt><dd><?php echo esc_html( crpcrm_display_value( $customer['first_referrer'] ) ); ?></dd><dt><?php echo esc_html( 'تاریخ اولین ورود' ); ?></dt><dd><?php echo esc_html( ( $customer['first_seen_at'] ? CRPCRM_Helpers::format_jalali_datetime( $customer['first_seen_at'] ) : 'ثبت نشده' ) ); ?></dd></dl></div>
			<div class="crpcrm-card"><h2><?php echo esc_html( 'آخرین ورود' ); ?></h2><dl><dt><?php echo esc_html( 'منبع' ); ?></dt><dd><?php echo esc_html( CRPCRM_Helpers::get_source_label( $customer['last_source'] ) ); ?></dd><dt><?php echo esc_html( 'مدیوم' ); ?></dt><dd><?php echo esc_html( CRPCRM_Helpers::get_medium_label( $customer['last_medium'] ) ); ?></dd><dt><?php echo esc_html( 'کمپین' ); ?></dt><dd><?php echo esc_html( crpcrm_display_value( $customer['last_campaign'] ) ); ?></dd><dt><?php echo esc_html( 'محتوا' ); ?></dt><dd><?php echo esc_html( crpcrm_display_value( $customer['last_content'] ) ); ?></dd><dt><?php echo esc_html( 'کلمه کلیدی' ); ?></dt><dd><?php echo esc_html( crpcrm_display_value( $customer['last_term'] ) ); ?></dd><dt><?php echo esc_html( 'صفحه ورود' ); ?></dt><dd><?php echo esc_html( crpcrm_display_value( $customer['last_landing_page'] ) ); ?></dd><dt><?php echo esc_html( 'ارجاع‌دهنده' ); ?></dt><dd><?php echo esc_html( crpcrm_display_value( $customer['last_referrer'] ) ); ?></dd><dt><?php echo esc_html( 'تاریخ آخرین ورود' ); ?></dt><dd><?php echo esc_html( ( $customer['last_seen_at'] ? CRPCRM_Helpers::format_jalali_datetime( $customer['last_seen_at'] ) : 'ثبت نشده' ) ); ?></dd></dl></div>
		</div>

		<div class="crpcrm-summary-cards crpcrm-profile-stats">
			<?php
			$cards               = array( 'total_requests' => 'تعداد کل درخواست‌ها', 'open_requests' => 'درخواست‌های باز', 'closed_requests' => 'درخواست‌های بسته‌شده', 'won' => 'موفق', 'lost' => 'ناموفق', 'invalid' => 'نامعتبر', 'open_followups' => 'پیگیری‌های باز' );
			$request_type_counts = isset( $stats['request_type_counts'] ) && is_array( $stats['request_type_counts'] ) ? $stats['request_type_counts'] : array();
			?>
			<?php foreach ( $cards as $key => $label ) : ?><div><strong><?php echo esc_html( number_format_i18n( isset( $stats[ $key ] ) ? absint( $stats[ $key ] ) : 0 ) ); ?></strong><span><?php echo esc_html( $label ); ?></span></div><?php endforeach; ?>
			<?php foreach ( $request_type_counts as $request_type => $request_type_count ) : ?><div><strong><?php echo esc_html( number_format_i18n( $request_type_count ) ); ?></strong><span><?php echo esc_html( CRPCRM_Request_Type_Registry::get_label( $request_type ) ); ?></span></div><?php endforeach; ?>
			<div><strong><?php echo esc_html( crpcrm_display_value( $stats['last_request_at'] ?? '' ) ); ?></strong><span><?php echo esc_html( 'آخرین تاریخ ثبت درخواست' ); ?></span></div>
			<div><strong><?php echo esc_html( crpcrm_display_value( $stats['last_activity_at'] ?? '' ) ); ?></strong><span><?php echo esc_html( 'آخرین تاریخ فعالیت' ); ?></span></div>
		</div>

		<div class="crpcrm-card"><h2><?php echo esc_html( 'درخواست‌های مشتری' ); ?></h2><table class="widefat fixed striped"><thead><tr><th><?php echo esc_html( 'کد پیگیری' ); ?></th><th><?php echo esc_html( 'نوع درخواست' ); ?></th><th><?php echo esc_html( 'خلاصه درخواست' ); ?></th><th><?php echo esc_html( 'وضعیت' ); ?></th><th><?php echo esc_html( 'منبع همان درخواست' ); ?></th><th><?php echo esc_html( 'کمپین همان درخواست' ); ?></th><th><?php echo esc_html( 'مسئول' ); ?></th><th><?php echo esc_html( 'تاریخ ثبت' ); ?></th><th><?php echo esc_html( 'آخرین فعالیت' ); ?></th><th><?php echo esc_html( 'عملیات' ); ?></th></tr></thead><tbody><?php if ( empty( $requests ) ) : ?><tr><td colspan="10"><?php echo esc_html( 'درخواستی برای نمایش وجود ندارد.' ); ?></td></tr><?php else : ?><?php foreach ( $requests as $request ) : ?><tr><td><strong><?php echo esc_html( $request['request_code'] ); ?></strong></td><td><?php echo esc_html( CRPCRM_Request_Type_Registry::get_label( $request['request_type'], $request ) ); ?></td><td><?php $request_data = CRPCRM_Request_Repository::get_merged_request_data( $request ); echo esc_html( wp_trim_words( CRPCRM_Request_Forms::build_display_summary( $request['request_type'], $request_data, $request['request_summary'] ), 16, '…' ) ); ?></td><td><span class="crpcrm-badge crpcrm-status-badge crpcrm-status-<?php echo esc_attr( sanitize_html_class( $request['status'] ) ); ?>"><?php echo esc_html( CRPCRM_Helpers::get_persian_status_label( $request['status'] ) ); ?></span></td><td><span class="crpcrm-badge crpcrm-source-badge"><?php echo esc_html( CRPCRM_Helpers::get_source_label( $request['request_source'] ) ); ?></span></td><td><?php echo esc_html( $request['request_campaign'] ? $request['request_campaign'] : '—' ); ?></td><td><?php echo esc_html( CRPCRM_Helpers::get_owner_label( $request['owner_id'] ) ); ?></td><td><?php echo esc_html( CRPCRM_Helpers::format_jalali_datetime( $request['created_at'] ) ); ?></td><td><?php echo esc_html( $request['last_activity_at'] ? CRPCRM_Helpers::format_jalali_datetime( $request['last_activity_at'] ) : 'ثبت نشده' ); ?></td><td><a class="button button-small" href="<?php echo esc_url( crpcrm_admin_request_detail_url( $request['id'] ) ); ?>"><?php echo esc_html( 'مشاهده درخواست' ); ?></a></td></tr><?php endforeach; ?><?php endif; ?></tbody></table></div>

		<?php if ( CRPCRM_Feature_Manager::is_enabled( 'staff' ) ) : ?>
			<div class="crpcrm-card"><h2><?php echo esc_html( 'کارشناسان مرتبط با مشتری' ); ?></h2><?php if ( empty( $agents ) ) : ?><p><?php echo esc_html( 'هنوز کارشناسی با این مشتری کار نکرده است.' ); ?></p><?php else : ?><table class="widefat fixed striped"><thead><tr><th><?php echo esc_html( 'نام کارشناس' ); ?></th><th><?php echo esc_html( 'تعداد درخواست‌هایی که owner بوده' ); ?></th><th><?php echo esc_html( 'تعداد فعالیت‌های ثبت‌شده' ); ?></th><th><?php echo esc_html( 'آخرین فعالیت' ); ?></th></tr></thead><tbody><?php foreach ( $agents as $agent ) : ?><tr><td><?php echo esc_html( crpcrm_customer_agent_name( $agent['user_id'] ) ); ?></td><td><?php echo esc_html( number_format_i18n( absint( $agent['owner_requests'] ) ) ); ?></td><td><?php echo esc_html( number_format_i18n( absint( $agent['activity_count'] ) ) ); ?></td><td><?php echo esc_html( $agent['last_activity_at'] ? CRPCRM_Helpers::format_jalali_datetime( $agent['last_activity_at'] ) : 'ثبت نشده' ); ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></div>
		<?php endif; ?>

		<div class="crpcrm-card"><h2><?php echo esc_html( 'آخرین فعالیت‌ها' ); ?></h2><?php if ( empty( $activities ) ) : ?><p><?php echo esc_html( 'هنوز فعالیتی ثبت نشده است.' ); ?></p><?php else : ?><table class="widefat fixed striped"><thead><tr><th><?php echo esc_html( 'زمان' ); ?></th><th><?php echo esc_html( 'کد درخواست' ); ?></th><th><?php echo esc_html( 'انجام‌دهنده' ); ?></th><th><?php echo esc_html( 'نوع فعالیت' ); ?></th><th><?php echo esc_html( 'توضیح' ); ?></th><th><?php echo esc_html( 'وضعیت قبلی' ); ?></th><th><?php echo esc_html( 'وضعیت جدید' ); ?></th></tr></thead><tbody><?php foreach ( $activities as $activity ) : ?><tr><td><?php echo esc_html( CRPCRM_Helpers::format_jalali_datetime( $activity['created_at'] ) ); ?></td><td><?php echo esc_html( $activity['request_code'] ? $activity['request_code'] : '—' ); ?></td><td><?php echo esc_html( ! empty( $activity['actor_user_id'] ) ? crpcrm_customer_agent_name( $activity['actor_user_id'] ) : $activity['actor_type'] ); ?></td><td><?php echo esc_html( CRPCRM_Helpers::get_activity_type_label( $activity['activity_type'] ) ); ?></td><td><?php echo esc_html( $activity['note'] ? $activity['note'] : '—' ); ?></td><td><?php echo esc_html( $activity['old_status'] ? CRPCRM_Helpers::get_persian_status_label( $activity['old_status'] ) : '—' ); ?></td><td><?php echo esc_html( $activity['new_status'] ? CRPCRM_Helpers::get_persian_status_label( $activity['new_status'] ) : '—' ); ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></div>

		<?php if ( CRPCRM_Feature_Manager::is_enabled( 'tracking' ) ) : ?>
			<div class="crpcrm-card"><h2><?php echo esc_html( 'تاریخچه ورودهای مهم' ); ?></h2><?php if ( empty( $attribution_events ) ) : ?><p><?php echo esc_html( 'تاریخچه ورود ثبت نشده است.' ); ?></p><?php else : ?><table class="widefat fixed striped"><thead><tr><th><?php echo esc_html( 'زمان' ); ?></th><th><?php echo esc_html( 'منبع' ); ?></th><th><?php echo esc_html( 'مدیوم' ); ?></th><th><?php echo esc_html( 'کمپین' ); ?></th><th><?php echo esc_html( 'محتوا' ); ?></th><th><?php echo esc_html( 'صفحه ورود' ); ?></th><th><?php echo esc_html( 'ارجاع‌دهنده' ); ?></th><th><?php echo esc_html( 'لاگین بوده؟' ); ?></th></tr></thead><tbody><?php foreach ( $attribution_events as $event ) : ?><tr><td><?php echo esc_html( CRPCRM_Helpers::format_jalali_datetime( $event['detected_at'] ) ); ?></td><td><?php echo esc_html( CRPCRM_Helpers::get_source_label( $event['source'] ) ); ?></td><td><?php echo esc_html( CRPCRM_Helpers::get_medium_label( $event['medium'] ) ); ?></td><td><?php echo esc_html( crpcrm_display_value( $event['campaign'] ) ); ?></td><td><?php echo esc_html( crpcrm_display_value( $event['content'] ) ); ?></td><td><?php echo esc_html( crpcrm_display_value( $event['landing_page'] ) ); ?></td><td><?php echo esc_html( crpcrm_display_value( $event['referrer'] ) ); ?></td><td><?php echo esc_html( ! empty( $event['is_logged_in'] ) ? 'بله' : 'خیر' ); ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></div>
		<?php endif; ?>
	<?php endif; ?>
</div>

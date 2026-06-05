<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'crpcrm_reports_url' ) ) {
function crpcrm_reports_url( $args = array(), $remove = array() ) {
	$base = array(
		'page'         => 'crpcrm-reports',
		'date_range'   => $GLOBALS['filters']['date_range'] ?? 'today',
		'date_from'    => $GLOBALS['filters']['date_from'] ?? '',
		'date_to'      => $GLOBALS['filters']['date_to'] ?? '',
		'request_type' => $GLOBALS['filters']['request_type'] ?? '',
		'source'       => $GLOBALS['filters']['source'] ?? '',
		'campaign'     => $GLOBALS['filters']['campaign'] ?? '',
		'content'      => $GLOBALS['filters']['content'] ?? '',
		'status'       => $GLOBALS['filters']['status'] ?? '',
		'owner_filter' => $GLOBALS['filters']['owner_filter'] ?? 'all',
	);
	$query = array_merge( $base, $args );
	foreach ( $remove as $key ) {
		unset( $query[ $key ] );
	}
	foreach ( $query as $key => $value ) {
		if ( '' === $value || null === $value ) {
			unset( $query[ $key ] );
		}
	}
	return add_query_arg( $query, admin_url( 'admin.php' ) );
}
}

if ( ! function_exists( 'crpcrm_reports_percent' ) ) {
function crpcrm_reports_percent( $part, $total ) {
	$total = absint( $total );
	return $total > 0 ? round( ( absint( $part ) / $total ) * 100, 1 ) . '%' : '۰٪';
}
}

if ( ! function_exists( 'crpcrm_reports_rate' ) ) {
function crpcrm_reports_rate( $won, $closed ) {
	$closed = absint( $closed );
	return $closed > 0 ? round( ( absint( $won ) / $closed ) * 100, 1 ) . '%' : 'قابل محاسبه نیست';
}
}

if ( ! function_exists( 'crpcrm_reports_duration' ) ) {
function crpcrm_reports_duration( $seconds ) {
	if ( null === $seconds || '' === $seconds ) {
		return '—';
	}
	$seconds = max( 0, (int) $seconds );
	$hours   = floor( $seconds / HOUR_IN_SECONDS );
	$minutes = floor( ( $seconds % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS );
	return $hours > 0 ? $hours . ' ساعت و ' . $minutes . ' دقیقه' : $minutes . ' دقیقه';
}
}

if ( ! function_exists( 'crpcrm_reports_owner_label' ) ) {
function crpcrm_reports_owner_label( $owner_id ) {
	$owner_id = absint( $owner_id );
	if ( ! $owner_id ) {
		return 'بدون مسئول';
	}
	$user = get_userdata( $owner_id );
	return $user ? $user->display_name : 'کاربر حذف‌شده';
}
}

if ( ! function_exists( 'crpcrm_reports_close_reason_label' ) ) {
function crpcrm_reports_close_reason_label( $reason ) {
	$labels = array(
		'price_not_suitable'              => 'قیمت مناسب نبود',
		'customer_cancelled'              => 'مشتری منصرف شد',
		'no_response'                     => 'پاسخگو نبود',
		'not_real_need'                   => 'نیاز واقعی نداشت',
		'wrong_time'                      => 'زمان خرید مناسب نبود',
		'unavailable_service_or_product'  => 'موجودی/خدمت قابل ارائه نبود',
		'duplicate_or_wrong'              => 'تکراری یا اشتباه ثبت شده',
		'other'                           => 'سایر',
	);
	return isset( $labels[ $reason ] ) ? $labels[ $reason ] : sanitize_text_field( $reason );
}
}

if ( ! function_exists( 'crpcrm_reports_invalid_reason_label' ) ) {
function crpcrm_reports_invalid_reason_label( $reason ) {
	$labels = array(
		'wrong_number'       => 'شماره اشتباه',
		'fake_data'          => 'اطلاعات غیرواقعی',
		'spam'               => 'اسپم',
		'irrelevant_request' => 'درخواست نامرتبط',
		'other'              => 'سایر',
	);
	return isset( $labels[ $reason ] ) ? $labels[ $reason ] : sanitize_text_field( $reason );
}
}

$GLOBALS['filters'] = $filters;
$date_ranges = array(
	'today'         => 'امروز',
	'yesterday'     => 'دیروز',
	'last_7_days'   => '۷ روز اخیر',
	'last_30_days'  => '۳۰ روز اخیر',
	'current_month' => 'ماه جاری',
	'last_month'    => 'ماه قبل',
	'custom'        => 'بازه دلخواه',
);
$request_types = array( '' => 'همه', 'car_registration' => 'ثبت‌نام خودرو', 'parts_request' => 'درخواست قطعات', 'repair_booking' => 'درخواست تعمیرات' );
$sources       = array( '' => 'همه', 'direct' => 'مستقیم', 'instagram' => 'اینستاگرام', 'whatsapp' => 'واتساپ', 'google' => 'گوگل', 'telegram' => 'تلگرام', 'other' => 'سایر' );
$statuses      = array( '' => 'همه', 'new' => 'جدید', 'in_progress' => 'در حال پیگیری', 'no_answer' => 'پاسخ نداد', 'follow_up' => 'پیگیری بعدی', 'won' => 'موفق', 'lost' => 'ناموفق', 'invalid' => 'نامعتبر' );
$total_for_percent = max( 1, absint( $kpis['total'] ) );
$known_source_keys = array( 'direct', 'instagram', 'whatsapp', 'google', 'telegram', 'bing' );
?>
<div class="wrap crpcrm-admin-wrap crpcrm-reports-admin" dir="rtl">
	<h1><?php echo esc_html( 'گزارش‌های مدیریتی' ); ?></h1>

	<form method="get" class="crpcrm-request-filters crpcrm-reports-filters">
		<input type="hidden" name="page" value="crpcrm-reports">
		<h2><?php echo esc_html( 'فیلترهای کلی' ); ?></h2>
		<div class="crpcrm-filter-grid">
			<label><?php echo esc_html( 'بازه زمانی' ); ?>
				<select name="date_range">
					<?php foreach ( $date_ranges as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $filters['date_range'], $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label><?php echo esc_html( 'از تاریخ' ); ?><?php echo CRPCRM_Helpers::jalali_date_input( 'date_from', $filters['date_from'] ); ?></label>
			<label><?php echo esc_html( 'تا تاریخ' ); ?><?php echo CRPCRM_Helpers::jalali_date_input( 'date_to', $filters['date_to'] ); ?></label>
			<label><?php echo esc_html( 'نوع درخواست' ); ?><select name="request_type"><?php foreach ( $request_types as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $filters['request_type'], $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
			<label><?php echo esc_html( 'منبع' ); ?><select name="source"><?php foreach ( $sources as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $filters['source'], $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
			<label><?php echo esc_html( 'کمپین' ); ?><input type="text" name="campaign" value="<?php echo esc_attr( $filters['campaign'] ); ?>" placeholder="request_campaign"></label>
			<label><?php echo esc_html( 'محتوا' ); ?><input type="text" name="content" value="<?php echo esc_attr( $filters['content'] ); ?>" placeholder="request_content"></label>
			<label><?php echo esc_html( 'وضعیت' ); ?><select name="status"><?php foreach ( $statuses as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $filters['status'], $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
			<label><?php echo esc_html( 'کارشناس' ); ?>
				<select name="owner_filter">
					<option value="all" <?php selected( $filters['owner_filter'], 'all' ); ?>><?php echo esc_html( 'همه' ); ?></option>
					<option value="unassigned" <?php selected( $filters['owner_filter'], 'unassigned' ); ?>><?php echo esc_html( 'بدون مسئول' ); ?></option>
					<?php foreach ( $assignable_users as $user ) : ?><option value="<?php echo esc_attr( $user->ID ); ?>" <?php selected( (string) $filters['owner_filter'], (string) $user->ID ); ?>><?php echo esc_html( $user->display_name ); ?></option><?php endforeach; ?>
				</select>
			</label>
		</div>
		<p class="submit">
			<button type="submit" class="button button-primary"><?php echo esc_html( 'اعمال فیلتر' ); ?></button>
			<a class="button" href="<?php echo esc_url( add_query_arg( array( 'page' => 'crpcrm-reports' ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( 'پاک کردن فیلترها' ); ?></a>
		</p>
	</form>

	<div class="crpcrm-summary-cards crpcrm-kpi-cards">
		<?php foreach ( array(
			'total' => 'تعداد کل درخواست‌ها', 'today' => 'درخواست‌های امروز', 'this_week' => 'درخواست‌های این هفته', 'this_month' => 'درخواست‌های این ماه', 'open_total' => 'درخواست‌های باز', 'closed_total' => 'درخواست‌های بسته‌شده', 'unassigned' => 'درخواست‌های بدون مسئول', 'followups_today' => 'پیگیری‌های امروز', 'overdue_followups' => 'پیگیری‌های عقب‌افتاده', 'won' => 'درخواست‌های موفق', 'lost' => 'درخواست‌های ناموفق', 'invalid' => 'درخواست‌های نامعتبر'
		) as $key => $label ) : ?>
			<div><strong><?php echo esc_html( number_format_i18n( absint( $kpis[ $key ] ) ) ); ?></strong><span><?php echo esc_html( $label ); ?></span></div>
		<?php endforeach; ?>
		<div><strong><?php echo esc_html( null === $kpis['success_rate'] ? 'قابل محاسبه نیست' : $kpis['success_rate'] . '%' ); ?></strong><span><?php echo esc_html( 'نرخ موفقیت' ); ?></span></div>
		<div><strong><?php echo esc_html( crpcrm_reports_duration( $kpis['avg_first_action_seconds'] ) ); ?></strong><span><?php echo esc_html( 'میانگین زمان اولین اقدام' ); ?></span></div>
	</div>

	<div class="crpcrm-report-section"><h2><?php echo esc_html( 'منابع ورودی' ); ?></h2>
		<table class="widefat striped"><thead><tr><th>منبع</th><th>تعداد درخواست</th><th>درصد از کل</th><th>موفق</th><th>ناموفق</th><th>نامعتبر</th><th>باز</th><th>نرخ موفقیت</th><th>میانگین زمان اولین اقدام</th><th>لینک</th></tr></thead><tbody>
		<?php if ( empty( $source_report ) ) : ?><tr><td colspan="10"><?php echo esc_html( 'داده‌ای یافت نشد.' ); ?></td></tr><?php endif; ?>
		<?php foreach ( $source_report as $row ) : $closed = absint( $row['won'] ) + absint( $row['lost'] ) + absint( $row['invalid'] ); ?>
			<tr><td><span class="crpcrm-badge crpcrm-source-badge"><?php echo esc_html( CRPCRM_Helpers::get_source_label( $row['request_source'] ) ); ?></span></td><td><?php echo esc_html( number_format_i18n( $row['total'] ) ); ?></td><td><?php echo esc_html( crpcrm_reports_percent( $row['total'], $kpis['total'] ) ); ?></td><td><?php echo esc_html( number_format_i18n( $row['won'] ) ); ?></td><td><?php echo esc_html( number_format_i18n( $row['lost'] ) ); ?></td><td><?php echo esc_html( number_format_i18n( $row['invalid'] ) ); ?></td><td><?php echo esc_html( number_format_i18n( $row['open_total'] ) ); ?></td><td><?php echo esc_html( crpcrm_reports_rate( $row['won'], $closed ) ); ?></td><td><?php echo esc_html( crpcrm_reports_duration( $row['avg_first_action_seconds'] ) ); ?></td><td><a href="<?php echo esc_url( crpcrm_reports_url( array( 'source' => in_array( $row['request_source'], $known_source_keys, true ) ? $row['request_source'] : 'other', 'paged' => 1 ) ) ); ?>"><?php echo esc_html( 'مشاهده درخواست‌ها' ); ?></a></td></tr>
		<?php endforeach; ?>
		</tbody></table>
	</div>

	<div class="crpcrm-report-section"><h2><?php echo esc_html( 'کمپین‌ها و محتوا' ); ?></h2>
		<h3><?php echo esc_html( 'گزارش کمپین‌ها' ); ?></h3>
		<table class="widefat striped"><thead><tr><th>کمپین</th><th>تعداد درخواست</th><th>ثبت‌نام خودرو</th><th>درخواست قطعات</th><th>درخواست تعمیرات</th><th>موفق</th><th>ناموفق</th><th>باز</th><th>نرخ موفقیت</th></tr></thead><tbody>
		<?php if ( empty( $campaign_report ) ) : ?><tr><td colspan="9"><?php echo esc_html( 'داده‌ای یافت نشد.' ); ?></td></tr><?php endif; ?>
		<?php foreach ( $campaign_report as $row ) : $campaign = $row['request_campaign']; $closed = absint( $row['won'] ) + absint( $row['lost'] ); ?>
			<tr><td><a href="<?php echo esc_url( crpcrm_reports_url( array( 'campaign' => $campaign, 'paged' => 1 ) ) ); ?>"><?php echo esc_html( $campaign ? $campaign : 'بدون کمپین' ); ?></a></td><td><?php echo esc_html( number_format_i18n( $row['total'] ) ); ?></td><td><?php echo esc_html( number_format_i18n( $row['car_registration'] ) ); ?></td><td><?php echo esc_html( number_format_i18n( $row['parts_request'] ) ); ?></td><td><?php echo esc_html( number_format_i18n( $row['repair_booking'] ) ); ?></td><td><?php echo esc_html( number_format_i18n( $row['won'] ) ); ?></td><td><?php echo esc_html( number_format_i18n( $row['lost'] ) ); ?></td><td><?php echo esc_html( number_format_i18n( $row['open_total'] ) ); ?></td><td><?php echo esc_html( crpcrm_reports_rate( $row['won'], $closed ) ); ?></td></tr>
		<?php endforeach; ?>
		</tbody></table>

		<h3><?php echo esc_html( 'گزارش محتوا' ); ?></h3>
		<table class="widefat striped"><thead><tr><th>محتوا</th><th>تعداد درخواست</th><th>منبع غالب</th><th>نوع درخواست غالب</th><th>موفق</th><th>ناموفق</th><th>باز</th><th>نرخ موفقیت</th></tr></thead><tbody>
		<?php if ( empty( $content_report ) ) : ?><tr><td colspan="8"><?php echo esc_html( 'داده‌ای یافت نشد.' ); ?></td></tr><?php endif; ?>
		<?php foreach ( $content_report as $row ) : $content = $row['request_content']; $closed = absint( $row['won'] ) + absint( $row['lost'] ); ?>
			<tr><td><a href="<?php echo esc_url( crpcrm_reports_url( array( 'content' => $content, 'paged' => 1 ) ) ); ?>"><?php echo esc_html( $content ? $content : 'بدون محتوا' ); ?></a></td><td><?php echo esc_html( number_format_i18n( $row['total'] ) ); ?></td><td><?php echo esc_html( CRPCRM_Helpers::get_source_label( $row['top_source'] ) ); ?></td><td><?php echo esc_html( CRPCRM_Helpers::get_request_type_label( $row['top_type'] ) ); ?></td><td><?php echo esc_html( number_format_i18n( $row['won'] ) ); ?></td><td><?php echo esc_html( number_format_i18n( $row['lost'] ) ); ?></td><td><?php echo esc_html( number_format_i18n( $row['open_total'] ) ); ?></td><td><?php echo esc_html( crpcrm_reports_rate( $row['won'], $closed ) ); ?></td></tr>
		<?php endforeach; ?>
		</tbody></table>
	</div>

	<div class="crpcrm-report-section"><h2><?php echo esc_html( 'نوع درخواست و ماتریس منبع' ); ?></h2>
		<table class="widefat striped"><thead><tr><th>نوع درخواست</th><th>تعداد کل</th><th>باز</th><th>موفق</th><th>ناموفق</th><th>نامعتبر</th><th>نرخ موفقیت</th><th>منبع اول</th><th>کمپین اول</th></tr></thead><tbody>
		<?php if ( empty( $request_type_report ) ) : ?><tr><td colspan="9"><?php echo esc_html( 'داده‌ای یافت نشد.' ); ?></td></tr><?php endif; ?>
		<?php foreach ( $request_type_report as $row ) : $closed = absint( $row['won'] ) + absint( $row['lost'] ) + absint( $row['invalid'] ); ?>
			<tr><td><a href="<?php echo esc_url( crpcrm_reports_url( array( 'request_type' => $row['request_type'], 'paged' => 1 ) ) ); ?>"><?php echo esc_html( CRPCRM_Helpers::get_request_type_label( $row['request_type'] ) ); ?></a></td><td><?php echo esc_html( number_format_i18n( $row['total'] ) ); ?></td><td><?php echo esc_html( number_format_i18n( $row['open_total'] ) ); ?></td><td><?php echo esc_html( number_format_i18n( $row['won'] ) ); ?></td><td><?php echo esc_html( number_format_i18n( $row['lost'] ) ); ?></td><td><?php echo esc_html( number_format_i18n( $row['invalid'] ) ); ?></td><td><?php echo esc_html( crpcrm_reports_rate( $row['won'], $closed ) ); ?></td><td><?php echo esc_html( CRPCRM_Helpers::get_source_label( $row['top_source'] ) ); ?></td><td><?php echo esc_html( $row['top_campaign'] ? $row['top_campaign'] : 'بدون کمپین' ); ?></td></tr>
		<?php endforeach; ?>
		</tbody></table>
		<h3><?php echo esc_html( 'Source × Request Type' ); ?></h3>
		<table class="widefat striped"><thead><tr><th>منبع</th><th>ثبت‌نام خودرو</th><th>درخواست قطعات</th><th>درخواست تعمیرات</th><th>جمع کل</th></tr></thead><tbody>
		<?php foreach ( $source_type_matrix as $row ) : ?><tr><td><?php echo esc_html( CRPCRM_Helpers::get_source_label( $row['request_source'] ) ); ?></td><td><?php echo esc_html( number_format_i18n( $row['car_registration'] ) ); ?></td><td><?php echo esc_html( number_format_i18n( $row['parts_request'] ) ); ?></td><td><?php echo esc_html( number_format_i18n( $row['repair_booking'] ) ); ?></td><td><?php echo esc_html( number_format_i18n( $row['total'] ) ); ?></td></tr><?php endforeach; ?>
		<?php if ( empty( $source_type_matrix ) ) : ?><tr><td colspan="5"><?php echo esc_html( 'داده‌ای یافت نشد.' ); ?></td></tr><?php endif; ?>
		</tbody></table>
	</div>

	<div class="crpcrm-report-section"><h2><?php echo esc_html( 'قیف فروش' ); ?></h2><div class="crpcrm-status-funnel">
		<?php foreach ( $status_funnel as $row ) : ?>
			<a class="crpcrm-funnel-card" href="<?php echo esc_url( crpcrm_reports_url( array( 'status' => $row['status'], 'paged' => 1 ) ) ); ?>"><strong><?php echo esc_html( number_format_i18n( $row['total'] ) ); ?></strong><span><?php echo esc_html( CRPCRM_Helpers::get_persian_status_label( $row['status'] ) ); ?></span><em><?php echo esc_html( crpcrm_reports_percent( $row['total'], $kpis['total'] ) ); ?></em></a>
		<?php endforeach; ?>
	</div></div>

	<div class="crpcrm-report-section"><h2><?php echo esc_html( 'عملکرد کارشناسان' ); ?></h2>
		<table class="widefat striped"><thead><tr><th>نام کارشناس</th><th>فعلی تحت مسئولیت</th><th>برداشته در بازه</th><th>اقدامات</th><th>تماس پاسخ داده</th><th>تماس پاسخ داده نشد</th><th>واتساپ</th><th>پیگیری تنظیم‌شده</th><th>موفق</th><th>ناموفق</th><th>نامعتبر</th><th>پیگیری عقب‌افتاده</th><th>میانگین اولین اقدام</th><th>نرخ موفقیت</th></tr></thead><tbody>
		<?php foreach ( $agent_performance as $row ) : ?><tr><td><?php echo esc_html( $row['display_name'] ); ?></td><td><?php echo esc_html( number_format_i18n( $row['current_owned'] ) ); ?></td><td><?php echo esc_html( number_format_i18n( $row['claimed'] ) ); ?></td><td><?php echo esc_html( number_format_i18n( $row['activities'] ) ); ?></td><td><?php echo esc_html( number_format_i18n( $row['call_answered'] ) ); ?></td><td><?php echo esc_html( number_format_i18n( $row['call_no_answer'] ) ); ?></td><td><?php echo esc_html( number_format_i18n( $row['whatsapp_sent'] ) ); ?></td><td><?php echo esc_html( number_format_i18n( $row['follow_up_scheduled'] ) ); ?></td><td><?php echo esc_html( number_format_i18n( $row['won'] ) ); ?></td><td><?php echo esc_html( number_format_i18n( $row['lost'] ) ); ?></td><td><?php echo esc_html( number_format_i18n( $row['invalid'] ) ); ?></td><td><?php echo esc_html( number_format_i18n( $row['overdue_followups'] ) ); ?></td><td><?php echo esc_html( crpcrm_reports_duration( $row['avg_first_action_seconds'] ) ); ?></td><td><?php echo esc_html( null === $row['success_rate'] ? 'قابل محاسبه نیست' : $row['success_rate'] . '%' ); ?></td></tr><?php endforeach; ?>
		</tbody></table>
	</div>

	<div class="crpcrm-report-section"><h2><?php echo esc_html( 'پیگیری‌ها و موارد عقب‌افتاده' ); ?></h2><div class="crpcrm-summary-cards">
		<?php foreach ( $followup_report as $item ) : ?><div><strong><?php echo esc_html( number_format_i18n( $item['total'] ) ); ?></strong><span><?php echo esc_html( $item['label'] ); ?></span><a href="<?php echo esc_url( crpcrm_reports_url( array( 'workflow_filter' => $item['key'], 'paged' => 1 ) ) ); ?>"><?php echo esc_html( 'مشاهده لیست' ); ?></a></div><?php endforeach; ?>
	</div></div>

	<div class="crpcrm-report-section"><h2><?php echo esc_html( 'دلایل ناموفق و نامعتبر' ); ?></h2><div class="crpcrm-two-columns">
		<div><h3><?php echo esc_html( 'دلایل ناموفق' ); ?></h3><table class="widefat striped"><thead><tr><th>دلیل</th><th>تعداد</th><th>درصد</th></tr></thead><tbody><?php $lost_total = array_sum( wp_list_pluck( $close_reason_report, 'total' ) ); foreach ( $close_reason_report as $row ) : ?><tr><td><?php echo esc_html( crpcrm_reports_close_reason_label( $row['reason'] ) ); ?></td><td><?php echo esc_html( number_format_i18n( $row['total'] ) ); ?></td><td><?php echo esc_html( crpcrm_reports_percent( $row['total'], $lost_total ) ); ?></td></tr><?php endforeach; ?><?php if ( empty( $close_reason_report ) ) : ?><tr><td colspan="3"><?php echo esc_html( 'داده‌ای یافت نشد.' ); ?></td></tr><?php endif; ?></tbody></table></div>
		<div><h3><?php echo esc_html( 'دلایل نامعتبر' ); ?></h3><table class="widefat striped"><thead><tr><th>دلیل</th><th>تعداد</th><th>درصد</th></tr></thead><tbody><?php $invalid_total = array_sum( wp_list_pluck( $invalid_reason_report, 'total' ) ); foreach ( $invalid_reason_report as $row ) : ?><tr><td><?php echo esc_html( crpcrm_reports_invalid_reason_label( $row['reason'] ) ); ?></td><td><?php echo esc_html( number_format_i18n( $row['total'] ) ); ?></td><td><?php echo esc_html( crpcrm_reports_percent( $row['total'], $invalid_total ) ); ?></td></tr><?php endforeach; ?><?php if ( empty( $invalid_reason_report ) ) : ?><tr><td colspan="3"><?php echo esc_html( 'داده‌ای یافت نشد.' ); ?></td></tr><?php endif; ?></tbody></table></div>
	</div></div>

	<div class="crpcrm-report-section"><h2><?php echo esc_html( 'جزئیات درخواست‌ها' ); ?></h2>
		<?php
		$csv_args = array(
			'action'       => 'crpcrm_reports_csv',
			'date_range'   => $filters['date_range'],
			'date_from'    => $filters['date_from'],
			'date_to'      => $filters['date_to'],
			'request_type' => $filters['request_type'],
			'source'       => $filters['source'],
			'campaign'     => $filters['campaign'],
			'content'      => $filters['content'],
			'status'       => $filters['status'],
			'owner_filter' => $filters['owner_filter'],
		);
		$csv_args = array_filter( $csv_args, static function( $value ) { return '' !== $value && null !== $value; } );
		?>
		<p><a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( $csv_args, admin_url( 'admin-post.php' ) ), 'crpcrm_reports_csv' ) ); ?>"><?php echo esc_html( 'خروجی CSV' ); ?></a></p>
		<table class="widefat striped crpcrm-requests-table"><thead><tr><th>کد پیگیری</th><th>تاریخ ثبت</th><th>مشتری</th><th>موبایل</th><th>نوع درخواست</th><th>خلاصه درخواست</th><th>منبع</th><th>کمپین</th><th>محتوا</th><th>وضعیت</th><th>مسئول</th><th>آخرین فعالیت</th><th>عملیات</th></tr></thead><tbody>
		<?php if ( empty( $request_details ) ) : ?><tr><td colspan="13"><?php echo esc_html( 'درخواستی مطابق فیلترها یافت نشد.' ); ?></td></tr><?php endif; ?>
		<?php foreach ( $request_details as $request ) : ?>
			<tr><td><?php echo esc_html( $request['request_code'] ); ?></td><td><?php echo esc_html( CRPCRM_Helpers::format_jalali_datetime( $request['created_at'] ) ); ?></td><td><?php echo esc_html( $request['customer_name'] ? $request['customer_name'] : '—' ); ?></td><td><?php echo esc_html( $request['customer_phone'] ? $request['customer_phone'] : '—' ); ?></td><td><?php echo esc_html( CRPCRM_Helpers::get_request_type_label( $request['request_type'] ) ); ?></td><td><?php echo esc_html( wp_trim_words( $request['request_summary'], 18, '…' ) ); ?></td><td><span class="crpcrm-badge crpcrm-source-badge"><?php echo esc_html( CRPCRM_Helpers::get_source_label( $request['request_source'] ) ); ?></span></td><td><?php echo esc_html( $request['request_campaign'] ? $request['request_campaign'] : '—' ); ?></td><td><?php echo esc_html( $request['request_content'] ? $request['request_content'] : '—' ); ?></td><td><span class="crpcrm-badge crpcrm-status-badge crpcrm-status-<?php echo esc_attr( $request['status'] ); ?>"><?php echo esc_html( CRPCRM_Helpers::get_persian_status_label( $request['status'] ) ); ?></span></td><td><?php echo esc_html( crpcrm_reports_owner_label( $request['owner_id'] ) ); ?></td><td><?php echo esc_html( $request['last_activity_at'] ? CRPCRM_Helpers::format_jalali_datetime( $request['last_activity_at'] ) : '—' ); ?></td><td><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'crpcrm-requests', 'request_id' => absint( $request['id'] ) ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( 'مشاهده درخواست' ); ?></a><br><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'crpcrm-customer-profile', 'customer_id' => absint( $request['customer_id'] ), 'return_request_id' => absint( $request['id'] ) ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( 'مشاهده پروفایل مشتری' ); ?></a></td></tr>
		<?php endforeach; ?>
		</tbody></table>
		<div class="tablenav"><div class="tablenav-pages"><span class="displaying-num"><?php echo esc_html( number_format_i18n( $total ) . ' مورد' ); ?></span><?php if ( $total_pages > 1 ) : ?><a class="button" href="<?php echo esc_url( crpcrm_reports_url( array( 'paged' => max( 1, $page - 1 ) ) ) ); ?>"><?php echo esc_html( 'قبلی' ); ?></a><span class="crpcrm-page-count"><?php echo esc_html( $page . ' / ' . $total_pages ); ?></span><a class="button" href="<?php echo esc_url( crpcrm_reports_url( array( 'paged' => min( $total_pages, $page + 1 ) ) ) ); ?>"><?php echo esc_html( 'بعدی' ); ?></a><?php endif; ?></div></div>
	</div>
</div>

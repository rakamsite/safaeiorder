<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'crpcrm_reports_url' ) ) {
	function crpcrm_reports_url( $args = array(), $remove = array() ) {
		$base = array(
			'page'         => 'crpcrm-reports',
			'date_range'   => $GLOBALS['filters']['date_range'] ?? 'last_30_days',
			'date_from'    => $GLOBALS['filters']['date_from'] ?? '',
			'date_to'      => $GLOBALS['filters']['date_to'] ?? '',
			'request_type' => $GLOBALS['filters']['request_type'] ?? '',
			'source'       => $GLOBALS['filters']['source'] ?? '',
			'campaign'     => $GLOBALS['filters']['campaign'] ?? '',
			'content'      => $GLOBALS['filters']['content'] ?? '',
			'landing'      => $GLOBALS['filters']['landing'] ?? '',
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
		return $total > 0 ? round( ( absint( $part ) / $total ) * 100, 1 ) . '%' : '—';
	}
}

if ( ! function_exists( 'crpcrm_reports_rate' ) ) {
	function crpcrm_reports_rate( $won, $closed ) {
		$closed = absint( $closed );
		return $closed > 0 ? round( ( absint( $won ) / $closed ) * 100, 1 ) . '%' : '—';
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

if ( ! function_exists( 'crpcrm_reports_label' ) ) {
	function crpcrm_reports_label( $value, $fallback = '—' ) {
		$value = trim( (string) $value );
		return '' !== $value ? $value : $fallback;
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

if ( ! function_exists( 'crpcrm_reports_status_class' ) ) {
	function crpcrm_reports_status_class( $status ) {
		$status = sanitize_key( (string) $status );
		$map    = array(
			'new'         => 'new',
			'in_progress' => 'pending',
			'no_answer'   => 'muted',
			'follow_up'   => 'pending',
			'won'         => 'success',
			'lost'        => 'failed',
			'invalid'     => 'failed',
			'closed'      => 'archived',
		);
		return isset( $map[ $status ] ) ? $map[ $status ] : 'muted';
	}
}

if ( ! function_exists( 'crpcrm_reports_chart_has_data' ) ) {
	function crpcrm_reports_chart_has_data( $chart ) {
		if ( empty( $chart ) || empty( $chart['datasets'] ) || ! is_array( $chart['datasets'] ) ) {
			return false;
		}
		foreach ( $chart['datasets'] as $dataset ) {
			$values = isset( $dataset['data'] ) && is_array( $dataset['data'] ) ? $dataset['data'] : array();
			foreach ( $values as $value ) {
				if ( absint( $value ) > 0 ) {
					return true;
				}
			}
		}
		return false;
	}
}

$dashboard          = isset( $dashboard ) && is_array( $dashboard ) ? $dashboard : array();
$overview           = isset( $dashboard['overview'] ) && is_array( $dashboard['overview'] ) ? $dashboard['overview'] : array();
$charts             = isset( $dashboard['charts'] ) && is_array( $dashboard['charts'] ) ? $dashboard['charts'] : array();
$campaign_summary   = isset( $dashboard['campaign_summary'] ) && is_array( $dashboard['campaign_summary'] ) ? $dashboard['campaign_summary'] : array();
$landing_performance = isset( $dashboard['landing_performance'] ) && is_array( $dashboard['landing_performance'] ) ? $dashboard['landing_performance'] : array();
$staff_performance  = isset( $dashboard['staff_performance'] ) && is_array( $dashboard['staff_performance'] ) ? $dashboard['staff_performance'] : array();
$request_details    = isset( $dashboard['request_details'] ) && is_array( $dashboard['request_details'] ) ? $dashboard['request_details'] : array();
$request_total      = isset( $dashboard['request_total'] ) ? absint( $dashboard['request_total'] ) : 0;
$landing_enabled    = ! empty( $landing_enabled );
$staff_enabled      = ! empty( $staff_enabled );
$GLOBALS['filters']  = isset( $filters ) && is_array( $filters ) ? $filters : array();

$date_ranges = array(
	'today'         => 'امروز',
	'yesterday'     => 'دیروز',
	'last_7_days'   => '۷ روز اخیر',
	'last_30_days'  => '۳۰ روز اخیر',
	'current_month' => 'ماه جاری',
	'current_year'  => 'سال جاری',
	'last_month'    => 'ماه قبل',
	'custom'        => 'بازه دلخواه',
);
$request_types = array( '' => 'همه' ) + CRPCRM_Request_Type_Registry::get_request_types();
$sources = array(
	''          => 'همه',
	'direct'    => 'مستقیم',
	'instagram' => 'اینستاگرام',
	'whatsapp'  => 'واتساپ',
	'google'    => 'گوگل',
	'telegram'  => 'تلگرام',
	'bing'      => 'بینگ',
	'other'     => 'سایر',
);
$statuses = array(
	''            => 'همه',
	'new'         => 'جدید',
	'in_progress' => 'در حال پیگیری',
	'no_answer'   => 'پاسخ داده نشده',
	'follow_up'   => 'پیگیری بعدی',
	'won'         => 'موفق',
	'lost'        => 'ناموفق',
	'invalid'     => 'نامعتبر',
);

$overview_cards = array(
	array( 'label' => 'کل درخواست‌ها', 'value' => absint( $overview['total_requests'] ?? 0 ), 'meta' => 'همه درخواست‌های ثبت‌شده' ),
	array( 'label' => 'درخواست‌های جدید', 'value' => absint( $overview['new_requests'] ?? 0 ), 'meta' => 'وضعیت جدید' ),
	array( 'label' => 'در حال پیگیری', 'value' => absint( $overview['in_progress'] ?? 0 ) + absint( $overview['follow_up'] ?? 0 ), 'meta' => 'در صف پیگیری تیم' ),
	array( 'label' => 'موفق', 'value' => absint( $overview['won_requests'] ?? 0 ), 'meta' => 'تبدیل‌های موفق' ),
	array( 'label' => 'ناموفق', 'value' => absint( $overview['lost_requests'] ?? 0 ) + absint( $overview['invalid_requests'] ?? 0 ), 'meta' => 'نتیجه نهایی ناموفق یا نامعتبر' ),
	array( 'label' => 'نرخ موفقیت', 'value' => isset( $overview['success_rate'] ) && null !== $overview['success_rate'] ? $overview['success_rate'] . '%' : '—', 'meta' => 'نسبت موفق به بسته‌شده' ),
	array( 'label' => 'پیگیری‌های امروز', 'value' => absint( $overview['followups_today'] ?? 0 ), 'meta' => 'موارد برنامه‌ریزی‌شده برای امروز' ),
);

$csv_args = array(
	'action'       => 'crpcrm_reports_csv',
	'date_range'   => $filters['date_range'],
	'date_from'    => $filters['date_from'],
	'date_to'      => $filters['date_to'],
	'request_type' => $filters['request_type'],
	'source'       => $filters['source'],
	'campaign'     => $filters['campaign'],
	'content'      => $filters['content'],
	'landing'      => $filters['landing'],
	'status'       => $filters['status'],
	'owner_filter' => $filters['owner_filter'],
);
$csv_args = array_filter(
	$csv_args,
	static function ( $value ) {
		return '' !== $value && null !== $value;
	}
);

$selected_range_label = isset( $date_ranges[ $filters['date_range'] ] ) ? $date_ranges[ $filters['date_range'] ] : $date_ranges['last_30_days'];
?>
<div class="wrap crpcrm-admin-wrap crpcrm-reports-admin" dir="rtl">
	<div class="crpcrm-page-header">
		<div class="crpcrm-page-header__content">
			<h1><?php echo esc_html( 'گزارشات' ); ?></h1>
			<p><?php echo esc_html( 'نمای کلی درخواست‌ها، منابع ورودی، عملکرد کارشناسان و کمپین‌ها' ); ?></p>
		</div>
		<div class="crpcrm-page-header__actions">
			<span class="crpcrm-badge crpcrm-badge-muted"><?php echo esc_html( 'بازه: ' . $selected_range_label ); ?></span>
			<?php if ( $request_total > 0 ) : ?>
				<span class="crpcrm-badge crpcrm-badge-success"><?php echo esc_html( number_format_i18n( $request_total ) . ' درخواست' ); ?></span>
			<?php endif; ?>
		</div>
	</div>

	<form method="get" class="crpcrm-filter-bar crpcrm-reports-filter-bar">
		<input type="hidden" name="page" value="crpcrm-reports">
		<div class="crpcrm-filter-toolbar">
		<div class="crpcrm-form-grid crpcrm-filter-grid">
			<label class="crpcrm-form-row">
				<span><?php echo esc_html( 'بازه زمانی' ); ?></span>
				<select name="date_range">
					<?php foreach ( $date_ranges as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $filters['date_range'], $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label class="crpcrm-form-row">
				<span><?php echo esc_html( 'از تاریخ' ); ?></span>
				<?php echo CRPCRM_Helpers::jalali_date_input( 'date_from', $filters['date_from'] ); ?>
			</label>
			<label class="crpcrm-form-row">
				<span><?php echo esc_html( 'تا تاریخ' ); ?></span>
				<?php echo CRPCRM_Helpers::jalali_date_input( 'date_to', $filters['date_to'] ); ?>
			</label>
			<label class="crpcrm-form-row">
				<span><?php echo esc_html( 'نوع درخواست' ); ?></span>
				<select name="request_type">
					<?php foreach ( $request_types as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $filters['request_type'], $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label class="crpcrm-form-row">
				<span><?php echo esc_html( 'منبع ورودی' ); ?></span>
				<select name="source">
					<?php foreach ( $sources as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $filters['source'], $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label class="crpcrm-form-row">
				<span><?php echo esc_html( 'کمپین' ); ?></span>
				<input type="text" name="campaign" value="<?php echo esc_attr( $filters['campaign'] ); ?>" placeholder="<?php echo esc_attr( 'نام یا کد کمپین' ); ?>">
			</label>
			<label class="crpcrm-form-row">
				<span><?php echo esc_html( 'محتوا' ); ?></span>
				<input type="text" name="content" value="<?php echo esc_attr( $filters['content'] ); ?>" placeholder="<?php echo esc_attr( 'کد محتوا یا جایگاه' ); ?>">
			</label>
			<label class="crpcrm-form-row">
				<span><?php echo esc_html( 'لندینگ' ); ?></span>
				<input type="text" name="landing" value="<?php echo esc_attr( $filters['landing'] ); ?>" placeholder="<?php echo esc_attr( 'slug لندینگ' ); ?>">
			</label>
			<label class="crpcrm-form-row">
				<span><?php echo esc_html( 'وضعیت' ); ?></span>
				<select name="status">
					<?php foreach ( $statuses as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $filters['status'], $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<?php if ( $staff_enabled ) : ?>
				<label class="crpcrm-form-row">
					<span><?php echo esc_html( 'کارشناس' ); ?></span>
					<select name="owner_filter">
						<option value="all" <?php selected( $filters['owner_filter'], 'all' ); ?>><?php echo esc_html( 'همه' ); ?></option>
						<option value="unassigned" <?php selected( $filters['owner_filter'], 'unassigned' ); ?>><?php echo esc_html( 'بدون مسئول' ); ?></option>
						<?php foreach ( $assignable_users as $user ) : ?>
							<option value="<?php echo esc_attr( $user->ID ); ?>" <?php selected( (string) $filters['owner_filter'], (string) $user->ID ); ?>><?php echo esc_html( $user->display_name ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			<?php endif; ?>
		</div>
		<div class="crpcrm-filter-actions">
			<button type="submit" class="crpcrm-btn crpcrm-btn-primary"><?php echo esc_html( 'اعمال فیلتر' ); ?></button>
			<a class="crpcrm-btn crpcrm-btn-ghost" href="<?php echo esc_url( add_query_arg( array( 'page' => 'crpcrm-reports' ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( 'پاک کردن فیلترها' ); ?></a>
		</div>
		</div>
	</form>

	<div class="crpcrm-kpi-grid crpcrm-kpi-grid--reports">
		<?php foreach ( $overview_cards as $card ) : ?>
			<div class="crpcrm-stat-card crpcrm-report-stat-card">
				<div class="crpcrm-stat-label"><?php echo esc_html( $card['label'] ); ?></div>
				<div class="crpcrm-stat-value"><?php echo esc_html( is_numeric( $card['value'] ) ? number_format_i18n( $card['value'] ) : $card['value'] ); ?></div>
				<div class="crpcrm-stat-meta"><?php echo esc_html( $card['meta'] ); ?></div>
			</div>
		<?php endforeach; ?>
	</div>

	<div class="crpcrm-dashboard-grid crpcrm-dashboard-grid--charts">
		<section class="crpcrm-section-card crpcrm-chart-card">
			<div class="crpcrm-section-header">
				<h2><?php echo esc_html( 'روند درخواست‌ها' ); ?></h2>
				<p><?php echo esc_html( 'تعداد درخواست‌های ثبت‌شده در بازه انتخاب‌شده' ); ?></p>
			</div>
			<?php if ( crpcrm_reports_chart_has_data( $charts['requestsTrend'] ?? array() ) ) : ?>
				<div class="crpcrm-chart-shell">
					<canvas class="crpcrm-chart" data-chart="requestsTrend"></canvas>
				</div>
			<?php else : ?>
				<div class="crpcrm-empty-state">
					<p><?php echo esc_html( 'در بازه انتخاب‌شده هنوز درخواستی ثبت نشده است.' ); ?></p>
				</div>
			<?php endif; ?>
		</section>

		<section class="crpcrm-section-card crpcrm-chart-card">
			<div class="crpcrm-section-header">
				<h2><?php echo esc_html( 'درخواست‌ها بر اساس منبع ورودی' ); ?></h2>
				<p><?php echo esc_html( 'منابع پرتکرار در همین بازه زمانی' ); ?></p>
			</div>
			<?php if ( crpcrm_reports_chart_has_data( $charts['sourceBreakdown'] ?? array() ) ) : ?>
				<div class="crpcrm-chart-shell">
					<canvas class="crpcrm-chart" data-chart="sourceBreakdown"></canvas>
				</div>
			<?php else : ?>
				<div class="crpcrm-empty-state">
					<p><?php echo esc_html( 'برای این فیلتر، داده‌ای برای نمایش نمودار وجود ندارد.' ); ?></p>
				</div>
			<?php endif; ?>
		</section>

		<section class="crpcrm-section-card crpcrm-chart-card">
			<div class="crpcrm-section-header">
				<h2><?php echo esc_html( 'وضعیت درخواست‌ها' ); ?></h2>
				<p><?php echo esc_html( 'توزیع وضعیت‌ها در بازه انتخاب‌شده' ); ?></p>
			</div>
			<?php if ( crpcrm_reports_chart_has_data( $charts['statusBreakdown'] ?? array() ) ) : ?>
				<div class="crpcrm-chart-shell">
					<canvas class="crpcrm-chart" data-chart="statusBreakdown"></canvas>
				</div>
			<?php else : ?>
				<div class="crpcrm-empty-state">
					<p><?php echo esc_html( 'هنوز داده‌ای برای وضعیت درخواست‌ها وجود ندارد.' ); ?></p>
				</div>
			<?php endif; ?>
		</section>

		<?php if ( $staff_enabled ) : ?>
			<section class="crpcrm-section-card crpcrm-chart-card">
				<div class="crpcrm-section-header">
					<h2><?php echo esc_html( 'عملکرد کارشناسان' ); ?></h2>
					<p><?php echo esc_html( 'درخواست‌های در جریان و موفق برای هر کارشناس' ); ?></p>
				</div>
				<?php if ( crpcrm_reports_chart_has_data( $charts['staffPerformance'] ?? array() ) ) : ?>
					<div class="crpcrm-chart-shell">
						<canvas class="crpcrm-chart" data-chart="staffPerformance"></canvas>
					</div>
				<?php else : ?>
					<div class="crpcrm-empty-state">
						<p><?php echo esc_html( 'هنوز داده‌ای برای عملکرد کارشناسان ثبت نشده است.' ); ?></p>
					</div>
				<?php endif; ?>
			</section>
		<?php endif; ?>
	</div>

	<div class="crpcrm-dashboard-grid crpcrm-dashboard-grid--tables">
		<section class="crpcrm-section-card">
			<div class="crpcrm-section-header crpcrm-section-header--inline">
				<div>
					<h2><?php echo esc_html( 'خلاصه کمپین‌ها و منابع' ); ?></h2>
					<p><?php echo esc_html( 'ترکیب منبع، کمپین و لندینگ برای درخواست‌های مهم' ); ?></p>
				</div>
			</div>
			<div class="crpcrm-table-wrap">
				<table class="crpcrm-table">
					<thead>
						<tr>
							<th><?php echo esc_html( 'منبع' ); ?></th>
							<th><?php echo esc_html( 'کمپین' ); ?></th>
							<?php if ( $landing_enabled ) : ?>
								<th><?php echo esc_html( 'لندینگ' ); ?></th>
								<th><?php echo esc_html( 'کلیک معتبر' ); ?></th>
							<?php endif; ?>
							<th><?php echo esc_html( 'درخواست' ); ?></th>
							<th><?php echo esc_html( 'موفق' ); ?></th>
							<th><?php echo esc_html( 'ناموفق' ); ?></th>
							<th><?php echo esc_html( 'نرخ تبدیل درخواست' ); ?></th>
							<th><?php echo esc_html( 'آخرین درخواست' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $campaign_summary ) ) : ?>
							<tr>
								<td colspan="<?php echo esc_attr( $landing_enabled ? 9 : 7 ); ?>">
									<div class="crpcrm-empty-state crpcrm-empty-state--inline"><?php echo esc_html( 'برای این فیلتر داده‌ای برای نمایش وجود ندارد.' ); ?></div>
								</td>
							</tr>
						<?php else : ?>
							<?php foreach ( $campaign_summary as $row ) : ?>
								<tr>
									<td><?php echo esc_html( CRPCRM_Helpers::get_source_label( $row['request_source'] ?? '' ) ); ?></td>
									<td><?php echo esc_html( crpcrm_reports_label( $row['request_campaign'] ?? '' ) ); ?></td>
									<?php if ( $landing_enabled ) : ?>
										<td>
											<div class="crpcrm-table-primary"><?php echo esc_html( crpcrm_reports_label( $row['landing_title'] ?? '' ) ); ?></div>
											<div class="crpcrm-table-secondary"><?php echo esc_html( crpcrm_reports_label( $row['landing_slug'] ?? '' ) ); ?></div>
										</td>
										<td><?php echo esc_html( number_format_i18n( absint( $row['clicks'] ?? 0 ) ) ); ?></td>
									<?php endif; ?>
									<td><?php echo esc_html( number_format_i18n( absint( $row['request_count'] ?? 0 ) ) ); ?></td>
									<td><?php echo esc_html( number_format_i18n( absint( $row['won_total'] ?? 0 ) ) ); ?></td>
									<td><?php echo esc_html( number_format_i18n( absint( $row['lost_total'] ?? 0 ) + absint( $row['invalid_total'] ?? 0 ) ) ); ?></td>
									<td><?php echo esc_html( crpcrm_reports_rate( $row['won_total'] ?? 0, absint( $row['request_count'] ?? 0 ) ) ); ?></td>
									<td><?php echo esc_html( ! empty( $row['last_request_at'] ) ? CRPCRM_Helpers::format_jalali_datetime( $row['last_request_at'] ) : '—' ); ?></td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</section>

		<?php if ( $landing_enabled ) : ?>
			<section class="crpcrm-section-card">
				<div class="crpcrm-section-header crpcrm-section-header--inline">
					<div>
						<h2><?php echo esc_html( 'عملکرد لندینگ‌ها' ); ?></h2>
						<p><?php echo esc_html( 'برترین لندینگ‌ها بر اساس درخواست ثبت‌شده و کلیک معتبر' ); ?></p>
					</div>
				</div>
				<div class="crpcrm-table-wrap">
					<table class="crpcrm-table">
						<thead>
							<tr>
								<th><?php echo esc_html( 'لندینگ' ); ?></th>
								<th><?php echo esc_html( 'Slug' ); ?></th>
								<th><?php echo esc_html( 'کلیک معتبر' ); ?></th>
								<th><?php echo esc_html( 'درخواست' ); ?></th>
								<th><?php echo esc_html( 'نرخ تبدیل' ); ?></th>
								<th><?php echo esc_html( 'آخرین درخواست' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $landing_performance ) ) : ?>
								<tr>
									<td colspan="6">
										<div class="crpcrm-empty-state crpcrm-empty-state--inline"><?php echo esc_html( 'هنوز لندینگی با درخواست ثبت‌شده وجود ندارد.' ); ?></div>
									</td>
								</tr>
							<?php else : ?>
								<?php foreach ( $landing_performance as $row ) : ?>
									<tr>
										<td><?php echo esc_html( crpcrm_reports_label( $row['title'] ?? '' ) ); ?></td>
										<td><?php echo esc_html( crpcrm_reports_label( $row['slug'] ?? '' ) ); ?></td>
										<td><?php echo esc_html( number_format_i18n( absint( $row['valid_clicks'] ?? 0 ) ) ); ?></td>
										<td><?php echo esc_html( number_format_i18n( absint( $row['request_count'] ?? 0 ) ) ); ?></td>
										<td><?php echo esc_html( isset( $row['conversion_rate'] ) && null !== $row['conversion_rate'] ? $row['conversion_rate'] . '%' : '—' ); ?></td>
										<td><?php echo esc_html( ! empty( $row['last_request_at'] ) ? CRPCRM_Helpers::format_jalali_datetime( $row['last_request_at'] ) : '—' ); ?></td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</section>
		<?php endif; ?>
	</div>

	<section class="crpcrm-section-card">
		<div class="crpcrm-section-header crpcrm-section-header--inline">
			<div>
				<h2><?php echo esc_html( 'آخرین درخواست‌ها' ); ?></h2>
				<p><?php echo esc_html( 'نمایش خلاصه درخواست‌های ثبت‌شده با امکان رفتن به جزئیات' ); ?></p>
			</div>
			<p>
				<a class="crpcrm-btn crpcrm-btn-secondary" href="<?php echo esc_url( wp_nonce_url( add_query_arg( $csv_args, admin_url( 'admin-post.php' ) ), 'crpcrm_reports_csv' ) ); ?>"><?php echo esc_html( 'خروجی CSV' ); ?></a>
			</p>
		</div>
		<div class="crpcrm-table-wrap">
			<table class="crpcrm-table crpcrm-requests-table">
				<thead>
					<tr>
						<th><?php echo esc_html( 'کد پیگیری' ); ?></th>
						<th><?php echo esc_html( 'تاریخ ثبت' ); ?></th>
						<th><?php echo esc_html( 'مشتری' ); ?></th>
						<th><?php echo esc_html( 'موبایل' ); ?></th>
						<th><?php echo esc_html( 'نوع درخواست' ); ?></th>
						<th><?php echo esc_html( 'خلاصه درخواست' ); ?></th>
						<th><?php echo esc_html( 'منبع' ); ?></th>
						<th><?php echo esc_html( 'کمپین' ); ?></th>
						<th><?php echo esc_html( 'محتوا' ); ?></th>
						<th><?php echo esc_html( 'وضعیت' ); ?></th>
						<th><?php echo esc_html( 'مسئول' ); ?></th>
						<th><?php echo esc_html( 'آخرین فعالیت' ); ?></th>
						<th><?php echo esc_html( 'عملیات' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $request_details ) ) : ?>
						<tr>
							<td colspan="13">
								<div class="crpcrm-empty-state crpcrm-empty-state--inline"><?php echo esc_html( 'درخواستی مطابق فیلترها یافت نشد.' ); ?></div>
							</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $request_details as $request ) : ?>
							<tr>
								<td><?php echo esc_html( $request['request_code'] ?? '—' ); ?></td>
								<td><?php echo esc_html( ! empty( $request['created_at'] ) ? CRPCRM_Helpers::format_jalali_datetime( $request['created_at'] ) : '—' ); ?></td>
								<td><?php echo esc_html( crpcrm_reports_label( $request['customer_name'] ?? '' ) ); ?></td>
								<td><?php echo esc_html( crpcrm_reports_label( $request['customer_phone'] ?? '' ) ); ?></td>
								<td><?php echo esc_html( CRPCRM_Request_Type_Registry::get_label( $request['request_type'] ?? '', $request ) ); ?></td>
								<td><?php echo esc_html( wp_trim_words( $request['request_summary'] ?? '', 18, '…' ) ); ?></td>
								<td><span class="crpcrm-badge crpcrm-badge-muted"><?php echo esc_html( CRPCRM_Helpers::get_source_label( $request['request_source'] ?? '' ) ); ?></span></td>
								<td><?php echo esc_html( crpcrm_reports_label( $request['request_campaign'] ?? '' ) ); ?></td>
								<td><?php echo esc_html( crpcrm_reports_label( $request['request_content'] ?? '' ) ); ?></td>
								<td><span class="crpcrm-badge crpcrm-badge-<?php echo esc_attr( crpcrm_reports_status_class( $request['status'] ?? '' ) ); ?>"><?php echo esc_html( CRPCRM_Helpers::get_persian_status_label( $request['status'] ?? '' ) ); ?></span></td>
								<td><?php echo esc_html( crpcrm_reports_owner_label( $request['owner_id'] ?? 0 ) ); ?></td>
								<td><?php echo esc_html( ! empty( $request['last_activity_at'] ) ? CRPCRM_Helpers::format_jalali_datetime( $request['last_activity_at'] ) : '—' ); ?></td>
								<td>
									<a class="crpcrm-link" href="<?php echo esc_url( add_query_arg( array( 'page' => 'crpcrm-requests', 'request_id' => absint( $request['id'] ) ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( 'مشاهده درخواست' ); ?></a>
									<span class="crpcrm-separator">|</span>
									<a class="crpcrm-link" href="<?php echo esc_url( add_query_arg( array( 'page' => 'crpcrm-customer-profile', 'customer_id' => absint( $request['customer_id'] ?? 0 ), 'return_request_id' => absint( $request['id'] ) ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( 'پروفایل مشتری' ); ?></a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<div class="tablenav">
			<div class="tablenav-pages">
				<span class="displaying-num"><?php echo esc_html( number_format_i18n( $request_total ) . ' مورد' ); ?></span>
				<?php if ( $total_pages > 1 ) : ?>
					<a class="button" href="<?php echo esc_url( crpcrm_reports_url( array( 'paged' => max( 1, $page - 1 ) ) ) ); ?>"><?php echo esc_html( 'قبلی' ); ?></a>
					<span class="crpcrm-page-count"><?php echo esc_html( $page . ' / ' . $total_pages ); ?></span>
					<a class="button" href="<?php echo esc_url( crpcrm_reports_url( array( 'paged' => min( $total_pages, $page + 1 ) ) ) ); ?>"><?php echo esc_html( 'بعدی' ); ?></a>
				<?php endif; ?>
			</div>
		</div>
	</section>
</div>

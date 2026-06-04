<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$notice_messages = array(
	'claimed'              => array( 'success', 'درخواست با موفقیت به شما اختصاص داده شد.' ),
	'already_owned'        => array( 'error', 'این درخواست قبلاً توسط کارشناس دیگری در حال پیگیری است.' ),
	'owner_changed'        => array( 'success', 'مسئول درخواست با موفقیت تغییر کرد.' ),
	'owner_released'       => array( 'success', 'درخواست با موفقیت آزاد شد.' ),
	'access_denied'        => array( 'error', 'شما اجازه مشاهده این درخواست را ندارید.' ),
	'owner_change_failed'  => array( 'error', 'تغییر مسئول درخواست انجام نشد.' ),
	'owner_release_failed' => array( 'error', 'آزادسازی درخواست انجام نشد.' ),
);
$notice = isset( $_GET['crpcrm_notice'] ) ? sanitize_key( wp_unslash( $_GET['crpcrm_notice'] ) ) : '';

if ( ! function_exists( 'crpcrm_admin_requests_url' ) ) {
function crpcrm_admin_requests_url( $args = array() ) {
	return add_query_arg( array_merge( array( 'page' => 'crpcrm-requests' ), $args ), admin_url( 'admin.php' ) );
}
}

if ( ! function_exists( 'crpcrm_admin_owner_form' ) ) {
function crpcrm_admin_owner_form( $request_id, $owner_id, $assignable_users ) {
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="crpcrm-inline-form">
		<input type="hidden" name="action" value="crpcrm_change_owner">
		<input type="hidden" name="request_id" value="<?php echo esc_attr( absint( $request_id ) ); ?>">
		<?php wp_nonce_field( 'crpcrm_change_owner_' . absint( $request_id ) ); ?>
		<select name="owner_id" class="crpcrm-owner-select" required>
			<option value=""><?php echo esc_html( 'انتخاب کارشناس' ); ?></option>
			<?php foreach ( $assignable_users as $user ) : ?>
				<option value="<?php echo esc_attr( $user->ID ); ?>" <?php selected( absint( $owner_id ), absint( $user->ID ) ); ?>><?php echo esc_html( $user->display_name ); ?></option>
			<?php endforeach; ?>
		</select>
		<button type="submit" class="button button-small"><?php echo esc_html( 'تغییر مسئول' ); ?></button>
	</form>
	<?php
}
}

if ( ! function_exists( 'crpcrm_admin_claim_form' ) ) {
function crpcrm_admin_claim_form( $request_id ) {
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="crpcrm-inline-form">
		<input type="hidden" name="action" value="crpcrm_claim_request">
		<input type="hidden" name="request_id" value="<?php echo esc_attr( absint( $request_id ) ); ?>">
		<?php wp_nonce_field( 'crpcrm_claim_request_' . absint( $request_id ) ); ?>
		<button type="submit" class="button button-primary button-small"><?php echo esc_html( 'شروع پیگیری' ); ?></button>
	</form>
	<?php
}
}

if ( ! function_exists( 'crpcrm_admin_release_form' ) ) {
function crpcrm_admin_release_form( $request_id ) {
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="crpcrm-inline-form">
		<input type="hidden" name="action" value="crpcrm_release_owner">
		<input type="hidden" name="request_id" value="<?php echo esc_attr( absint( $request_id ) ); ?>">
		<?php wp_nonce_field( 'crpcrm_release_owner_' . absint( $request_id ) ); ?>
		<button type="submit" class="button button-small crpcrm-danger-button"><?php echo esc_html( 'آزادسازی مسئول' ); ?></button>
	</form>
	<?php
}
}
?>
<div class="wrap crpcrm-admin-wrap crpcrm-requests-admin" dir="rtl">
	<h1><?php echo esc_html( 'درخواست‌های مشتریان' ); ?></h1>

	<?php if ( $notice && isset( $notice_messages[ $notice ] ) ) : ?>
		<div class="notice notice-<?php echo esc_attr( $notice_messages[ $notice ][0] ); ?> is-dismissible"><p><?php echo esc_html( $notice_messages[ $notice ][1] ); ?></p></div>
	<?php endif; ?>

	<?php if ( 'list' === $mode ) : ?>
		<form method="get" class="crpcrm-request-filters">
			<input type="hidden" name="page" value="crpcrm-requests">
			<div class="crpcrm-filter-grid">
				<label><?php echo esc_html( 'نوع درخواست' ); ?>
					<select name="request_type">
						<option value=""><?php echo esc_html( 'همه' ); ?></option>
						<option value="car_registration" <?php selected( $filters['request_type'], 'car_registration' ); ?>><?php echo esc_html( 'ثبت‌نام خودرو' ); ?></option>
						<option value="parts_request" <?php selected( $filters['request_type'], 'parts_request' ); ?>><?php echo esc_html( 'درخواست قطعات' ); ?></option>
						<option value="repair_booking" <?php selected( $filters['request_type'], 'repair_booking' ); ?>><?php echo esc_html( 'درخواست تعمیرات' ); ?></option>
					</select>
				</label>
				<label><?php echo esc_html( 'وضعیت' ); ?>
					<select name="status">
						<option value=""><?php echo esc_html( 'همه' ); ?></option>
						<?php foreach ( array( 'new', 'in_progress', 'no_answer', 'follow_up', 'won', 'lost', 'invalid' ) as $status_key ) : ?>
							<option value="<?php echo esc_attr( $status_key ); ?>" <?php selected( $filters['status'], $status_key ); ?>><?php echo esc_html( CRPCRM_Helpers::get_persian_status_label( $status_key ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label><?php echo esc_html( 'مسئول' ); ?>
					<select name="owner_filter">
						<option value="all" <?php selected( $filters['owner_filter'], 'all' ); ?>><?php echo esc_html( 'همه موارد مجاز' ); ?></option>
						<option value="unassigned" <?php selected( $filters['owner_filter'], 'unassigned' ); ?>><?php echo esc_html( 'بدون مسئول' ); ?></option>
						<option value="me" <?php selected( $filters['owner_filter'], 'me' ); ?>><?php echo esc_html( 'من' ); ?></option>
						<?php if ( $can_manage ) : ?>
							<?php foreach ( $assignable_users as $user ) : ?>
								<option value="<?php echo esc_attr( $user->ID ); ?>" <?php selected( absint( $filters['owner_filter'] ), absint( $user->ID ) ); ?>><?php echo esc_html( $user->display_name ); ?></option>
							<?php endforeach; ?>
						<?php endif; ?>
					</select>
				</label>
				<label><?php echo esc_html( 'منبع' ); ?>
					<select name="source">
						<option value=""><?php echo esc_html( 'همه' ); ?></option>
						<?php foreach ( array( 'direct', 'instagram', 'whatsapp', 'google', 'telegram', 'other' ) as $source_key ) : ?>
							<option value="<?php echo esc_attr( $source_key ); ?>" <?php selected( $filters['source'], $source_key ); ?>><?php echo esc_html( CRPCRM_Helpers::get_source_label( $source_key ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label><?php echo esc_html( 'کمپین' ); ?><input type="text" name="campaign" value="<?php echo esc_attr( $filters['campaign'] ); ?>"></label>
				<label><?php echo esc_html( 'از تاریخ' ); ?><input type="date" name="date_from" value="<?php echo esc_attr( $filters['date_from'] ); ?>"></label>
				<label><?php echo esc_html( 'تا تاریخ' ); ?><input type="date" name="date_to" value="<?php echo esc_attr( $filters['date_to'] ); ?>"></label>
				<label><?php echo esc_html( 'جستجو' ); ?><input type="search" name="search" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="<?php echo esc_attr( 'کد، نام، موبایل، خلاصه' ); ?>"></label>
			</div>
			<p class="submit"><button type="submit" class="button button-primary"><?php echo esc_html( 'اعمال فیلتر' ); ?></button> <a class="button" href="<?php echo esc_url( crpcrm_admin_requests_url() ); ?>"><?php echo esc_html( 'پاک کردن فیلترها' ); ?></a></p>
		</form>

		<table class="widefat fixed striped crpcrm-requests-table">
			<thead><tr>
				<th><?php echo esc_html( 'کد پیگیری' ); ?></th><th><?php echo esc_html( 'مشتری' ); ?></th><th><?php echo esc_html( 'موبایل' ); ?></th><th><?php echo esc_html( 'نوع درخواست' ); ?></th><th><?php echo esc_html( 'خلاصه درخواست' ); ?></th><th><?php echo esc_html( 'وضعیت' ); ?></th><th><?php echo esc_html( 'منبع' ); ?></th><th><?php echo esc_html( 'کمپین' ); ?></th><th><?php echo esc_html( 'مسئول' ); ?></th><th><?php echo esc_html( 'تاریخ ثبت' ); ?></th><th><?php echo esc_html( 'آخرین بروزرسانی' ); ?></th><th><?php echo esc_html( 'عملیات' ); ?></th>
			</tr></thead>
			<tbody>
			<?php if ( empty( $requests ) ) : ?>
				<tr><td colspan="12"><?php echo esc_html( 'درخواستی یافت نشد.' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $requests as $item ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $item['request_code'] ); ?></strong></td>
						<td><?php echo esc_html( $item['customer_name'] ? $item['customer_name'] : '—' ); ?></td>
						<td><?php echo esc_html( $item['customer_phone'] ? $item['customer_phone'] : $item['customer_phone_normalized'] ); ?></td>
						<td><?php echo esc_html( CRPCRM_Helpers::get_request_type_label( $item['request_type'] ) ); ?></td>
						<td><?php echo esc_html( wp_trim_words( $item['request_summary'], 14, '…' ) ); ?></td>
						<td><span class="crpcrm-badge crpcrm-status-badge crpcrm-status-<?php echo esc_attr( sanitize_html_class( $item['status'] ) ); ?>"><?php echo esc_html( CRPCRM_Helpers::get_persian_status_label( $item['status'] ) ); ?></span></td>
						<td><span class="crpcrm-badge crpcrm-source-badge"><?php echo esc_html( CRPCRM_Helpers::get_source_label( $item['request_source'] ) ); ?></span></td>
						<td><?php echo esc_html( $item['request_campaign'] ? $item['request_campaign'] : '—' ); ?></td>
						<td><?php echo esc_html( CRPCRM_Helpers::get_owner_label( $item['owner_id'] ) ); ?></td>
						<td><?php echo esc_html( $item['created_at'] ); ?></td>
						<td><?php echo esc_html( $item['updated_at'] ); ?></td>
						<td class="crpcrm-actions">
							<a class="button button-small" href="<?php echo esc_url( crpcrm_admin_requests_url( array( 'request_id' => absint( $item['id'] ) ) ) ); ?>"><?php echo esc_html( 'مشاهده' ); ?></a>
							<?php if ( empty( $item['owner_id'] ) && CRPCRM_Request_Access_Service::can_claim_request( $item ) ) : ?><?php crpcrm_admin_claim_form( $item['id'] ); ?><?php endif; ?>
							<?php if ( $can_manage ) : ?>
								<?php crpcrm_admin_owner_form( $item['id'], $item['owner_id'], $assignable_users ); ?>
								<?php if ( ! empty( $item['owner_id'] ) ) : ?><?php crpcrm_admin_release_form( $item['id'] ); ?><?php endif; ?>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
			</tbody>
		</table>

		<?php if ( $total_pages > 1 ) : ?>
			<div class="tablenav"><div class="tablenav-pages">
				<?php
				$base_args = array_filter( $filters );
				if ( $page > 1 ) {
					echo '<a class="button" href="' . esc_url( crpcrm_admin_requests_url( array_merge( $base_args, array( 'paged' => $page - 1 ) ) ) ) . '">' . esc_html( 'قبلی' ) . '</a> ';
				}
				echo '<span class="crpcrm-page-count">' . esc_html( sprintf( 'صفحه %1$d از %2$d', $page, $total_pages ) ) . '</span>';
				if ( $page < $total_pages ) {
					echo ' <a class="button" href="' . esc_url( crpcrm_admin_requests_url( array_merge( $base_args, array( 'paged' => $page + 1 ) ) ) ) . '">' . esc_html( 'بعدی' ) . '</a>';
				}
				?>
			</div></div>
		<?php endif; ?>
	<?php else : ?>
		<?php
		$request_data = CRPCRM_Helpers::maybe_json_decode( $request['request_data'], true );
		$request_data = is_array( $request_data ) ? $request_data : array();
		$form_labels  = array(
			'car_registration' => array( 'desired_vehicle' => 'خودروی موردنظر', 'request_kind' => 'نوع درخواست', 'budget_range' => 'بودجه حدودی', 'description' => 'توضیحات' ),
			'parts_request'    => array( 'part_name' => 'نام قطعه موردنیاز', 'vehicle_model' => 'مدل خودرو', 'vehicle_year' => 'سال خودرو', 'description' => 'توضیحات' ),
			'repair_booking'   => array( 'vehicle_model' => 'مدل خودرو', 'service_type' => 'نوع سرویس یا مشکل', 'problem_description' => 'شرح مشکل', 'preferred_date' => 'تاریخ پیشنهادی مراجعه', 'preferred_call_time' => 'زمان مناسب تماس' ),
		);
		$type_labels = isset( $form_labels[ $request['request_type'] ] ) ? $form_labels[ $request['request_type'] ] : array();
		?>
		<p><a class="button" href="<?php echo esc_url( crpcrm_admin_requests_url() ); ?>"><?php echo esc_html( 'بازگشت به لیست درخواست‌ها' ); ?></a></p>
		<div class="crpcrm-detail-grid">
			<div class="crpcrm-card crpcrm-info-card"><h2><?php echo esc_html( 'اطلاعات اصلی درخواست' ); ?></h2><dl>
				<dt><?php echo esc_html( 'کد پیگیری' ); ?></dt><dd><?php echo esc_html( $request['request_code'] ); ?></dd>
				<dt><?php echo esc_html( 'نوع درخواست' ); ?></dt><dd><?php echo esc_html( CRPCRM_Helpers::get_request_type_label( $request['request_type'] ) ); ?></dd>
				<dt><?php echo esc_html( 'وضعیت داخلی' ); ?></dt><dd><span class="crpcrm-badge crpcrm-status-badge"><?php echo esc_html( CRPCRM_Helpers::get_persian_status_label( $request['status'] ) ); ?></span></dd>
				<dt><?php echo esc_html( 'مسئول' ); ?></dt><dd><?php echo esc_html( CRPCRM_Helpers::get_owner_label( $request['owner_id'] ) ); ?></dd>
				<dt><?php echo esc_html( 'تاریخ ثبت' ); ?></dt><dd><?php echo esc_html( $request['created_at'] ); ?></dd>
				<dt><?php echo esc_html( 'آخرین بروزرسانی' ); ?></dt><dd><?php echo esc_html( $request['updated_at'] ); ?></dd>
				<dt><?php echo esc_html( 'منبع' ); ?></dt><dd><?php echo esc_html( CRPCRM_Helpers::get_source_label( $request['request_source'] ) ); ?></dd>
				<dt><?php echo esc_html( 'کمپین' ); ?></dt><dd><?php echo esc_html( $request['request_campaign'] ? $request['request_campaign'] : '—' ); ?></dd>
				<dt><?php echo esc_html( 'محتوا / utm_content' ); ?></dt><dd><?php echo esc_html( $request['request_content'] ? $request['request_content'] : '—' ); ?></dd>
				<dt><?php echo esc_html( 'landing page' ); ?></dt><dd><?php echo esc_html( $request['request_landing_page'] ? $request['request_landing_page'] : '—' ); ?></dd>
				<dt><?php echo esc_html( 'referrer' ); ?></dt><dd><?php echo esc_html( $request['request_referrer'] ? $request['request_referrer'] : '—' ); ?></dd>
			</dl></div>
			<div class="crpcrm-card crpcrm-customer-card"><h2><?php echo esc_html( 'اطلاعات مشتری' ); ?></h2><dl>
				<dt><?php echo esc_html( 'نام و نام خانوادگی' ); ?></dt><dd><?php echo esc_html( $request['customer_name'] ? $request['customer_name'] : '—' ); ?></dd>
				<dt><?php echo esc_html( 'شماره موبایل' ); ?></dt><dd><?php echo esc_html( $request['customer_phone'] ? $request['customer_phone'] : $request['customer_phone_normalized'] ); ?></dd>
				<dt><?php echo esc_html( 'استان' ); ?></dt><dd><?php echo esc_html( $request['customer_province'] ? $request['customer_province'] : '—' ); ?></dd>
				<dt><?php echo esc_html( 'شهر' ); ?></dt><dd><?php echo esc_html( $request['customer_city'] ? $request['customer_city'] : '—' ); ?></dd>
				<dt><?php echo esc_html( 'پروفایل مشتری' ); ?></dt><dd><a href="#crpcrm-customer-profile-placeholder"><?php echo esc_html( 'مشاهده پروفایل مشتری' ); ?></a></dd>
			</dl></div>
		</div>

		<div class="crpcrm-card"><h2><?php echo esc_html( 'اطلاعات فرم' ); ?></h2><dl class="crpcrm-form-data">
			<?php foreach ( $type_labels as $key => $label ) : ?>
				<dt><?php echo esc_html( $label ); ?></dt><dd><?php echo esc_html( isset( $request_data[ $key ] ) && '' !== $request_data[ $key ] ? $request_data[ $key ] : '—' ); ?></dd>
			<?php endforeach; ?>
		</dl></div>

		<div class="crpcrm-card"><h2><?php echo esc_html( 'عملیات' ); ?></h2>
			<?php if ( $can_claim ) : ?><?php crpcrm_admin_claim_form( $request['id'] ); ?><?php endif; ?>
			<?php if ( $can_manage ) : ?>
				<?php crpcrm_admin_owner_form( $request['id'], $request['owner_id'], $assignable_users ); ?>
				<?php if ( ! empty( $request['owner_id'] ) ) : ?><?php crpcrm_admin_release_form( $request['id'] ); ?><?php endif; ?>
			<?php endif; ?>
		</div>

		<div class="crpcrm-card"><h2><?php echo esc_html( 'تاریخچه فعالیت' ); ?></h2>
			<?php if ( empty( $activities ) ) : ?><p><?php echo esc_html( 'هنوز فعالیتی ثبت نشده است.' ); ?></p><?php else : ?>
				<ol class="crpcrm-activity-timeline">
					<?php foreach ( $activities as $activity ) : $actor = ! empty( $activity['actor_user_id'] ) ? get_userdata( absint( $activity['actor_user_id'] ) ) : null; ?>
						<li><time><?php echo esc_html( $activity['created_at'] ); ?></time><strong><?php echo esc_html( CRPCRM_Helpers::get_activity_type_label( $activity['activity_type'] ) ); ?></strong><span><?php echo esc_html( $actor ? $actor->display_name : $activity['actor_type'] ); ?></span><p><?php echo esc_html( $activity['note'] ? $activity['note'] : '—' ); ?></p></li>
					<?php endforeach; ?>
				</ol>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>

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
	'request_deleted'       => array( 'success', 'درخواست و تمام تاریخچه فعالیت آن برای همیشه حذف شد.' ),
	'request_delete_denied' => array( 'error', 'فقط مدیرکل سایت اجازه حذف کامل درخواست را دارد.' ),
	'request_delete_failed' => array( 'error', 'حذف کامل درخواست انجام نشد.' ),
	'invalid_action_type'           => array( 'error', 'نوع اقدام معتبر نیست.' ),
	'action_note_required'          => array( 'error', 'توضیحات الزامی است.' ),
	'follow_up_required'            => array( 'error', 'تاریخ پیگیری بعدی الزامی است.' ),
	'follow_up_in_past'             => array( 'error', 'تاریخ پیگیری نمی‌تواند در گذشته باشد.' ),
	'close_reason_required'         => array( 'error', 'دلیل ناموفق بودن الزامی است.' ),
	'invalid_reason_required'       => array( 'error', 'دلیل نامعتبر بودن الزامی است.' ),
	'sales_action_denied'           => array( 'error', 'شما اجازه ثبت اقدام برای این درخواست را ندارید.' ),
	'request_closed_action_denied'  => array( 'error', 'این درخواست بسته شده و امکان ثبت اقدام جدید وجود ندارد.' ),
	'sales_action_update_failed'    => array( 'error', 'ثبت اقدام انجام نشد.' ),
	'sales_action_added'            => array( 'success', 'اقدام با موفقیت ثبت شد.' ),
	'request_not_found'             => array( 'error', 'درخواست موردنظر یافت نشد.' ),
	'manual_request_created'       => array( 'success', 'درخواست دستی با موفقیت ثبت شد.' ),
	'manual_customer_required'     => array( 'error', 'ابتدا مشتری را پیدا یا ایجاد کنید.' ),
	'manual_customer_invalid'      => array( 'error', 'نام و شماره موبایل معتبر مشتری جدید الزامی است.' ),
	'manual_customer_exists'       => array( 'error', 'این شماره موبایل قبلاً ثبت شده است؛ مشتری موجود را جستجو کنید.' ),
	'manual_customer_failed'       => array( 'error', 'ساخت مشتری جدید انجام نشد.' ),
	'manual_request_type_invalid'  => array( 'error', 'نوع درخواست معتبر نیست.' ),
	'manual_request_failed'        => array( 'error', 'ثبت درخواست دستی انجام نشد.' ),
	'sales_action_call_answered'    => array( 'success', 'تماس پاسخ‌داده‌شده با موفقیت ثبت شد.' ),
	'sales_action_call_no_answer'   => array( 'success', 'تماس ناموفق با موفقیت ثبت شد.' ),
	'sales_action_whatsapp_sent'    => array( 'success', 'ارسال پیام واتساپ با موفقیت ثبت شد.' ),
	'sales_action_internal_note'    => array( 'success', 'یادداشت داخلی با موفقیت ثبت شد.' ),
	'sales_action_schedule_follow_up' => array( 'success', 'پیگیری بعدی با موفقیت ثبت شد.' ),
	'sales_action_mark_won'         => array( 'success', 'درخواست با موفقیت به وضعیت موفق تغییر کرد.' ),
	'sales_action_mark_lost'        => array( 'success', 'درخواست با موفقیت به وضعیت ناموفق تغییر کرد.' ),
	'sales_action_mark_invalid'     => array( 'success', 'درخواست با موفقیت به وضعیت نامعتبر تغییر کرد.' ),
	'request_reply_added'           => array( 'success', 'پاسخ شما با موفقیت ثبت شد.' ),
	'request_reply_empty'           => array( 'error', 'متن پاسخ نمی‌تواند خالی باشد.' ),
	'request_reply_denied'          => array( 'error', 'شما اجازه ارسال پاسخ برای این درخواست را ندارید.' ),
	'request_reply_failed'          => array( 'error', 'ارسال پاسخ انجام نشد. لطفاً دوباره تلاش کنید.' ),
);
$notice = isset( $_GET['crpcrm_notice'] ) ? sanitize_key( wp_unslash( $_GET['crpcrm_notice'] ) ) : '';
$custom_notice_message = isset( $_GET['crpcrm_notice_message'] ) ? sanitize_text_field( wp_unslash( $_GET['crpcrm_notice_message'] ) ) : '';
$custom_notice_type    = isset( $_GET['crpcrm_notice_type'] ) ? sanitize_key( wp_unslash( $_GET['crpcrm_notice_type'] ) ) : 'error';

if ( ! function_exists( 'crpcrm_admin_customer_profile_url' ) ) {
function crpcrm_admin_customer_profile_url( $customer_id, $args = array() ) {
	return add_query_arg( array_merge( array( 'page' => 'crpcrm-customer-profile', 'customer_id' => absint( $customer_id ) ), $args ), admin_url( 'admin.php' ) );
}
}

if ( ! function_exists( 'crpcrm_admin_request_display_summary' ) ) {
function crpcrm_admin_request_display_summary( $request ) {
	$request_data = CRPCRM_Request_Repository::get_merged_request_data( $request );
	return CRPCRM_Request_Forms::build_display_summary( $request['request_type'], $request_data, $request['request_summary'] );
}
}

if ( ! function_exists( 'crpcrm_request_landing_touch_summary' ) ) {
function crpcrm_request_landing_touch_summary( $touch ) {
	if ( empty( $touch ) || ! is_array( $touch ) ) {
		return '';
	}

	$parts = array_filter(
		array(
			! empty( $touch['source_label'] ) ? $touch['source_label'] : ( ! empty( $touch['source_code'] ) ? CRPCRM_Helpers::get_source_label( $touch['source_code'] ) : '' ),
			! empty( $touch['medium_label'] ) ? $touch['medium_label'] : ( ! empty( $touch['medium_code'] ) ? CRPCRM_Helpers::get_medium_label( $touch['medium_code'] ) : '' ),
			! empty( $touch['campaign_label'] ) ? $touch['campaign_label'] : ( ! empty( $touch['campaign_code'] ) ? $touch['campaign_code'] : '' ),
			! empty( $touch['content_label'] ) ? $touch['content_label'] : ( ! empty( $touch['content_code'] ) ? $touch['content_code'] : '' ),
			! empty( $touch['term_label'] ) ? $touch['term_label'] : ( ! empty( $touch['term_code'] ) ? $touch['term_code'] : '' ),
		)
	);

	return implode( ' / ', array_map( 'sanitize_text_field', $parts ) );
}
}

if ( ! function_exists( 'crpcrm_request_landing_touch_title' ) ) {
function crpcrm_request_landing_touch_title( $touch ) {
	if ( empty( $touch ) || ! is_array( $touch ) ) {
		return '';
	}

	if ( ! empty( $touch['landing_title'] ) ) {
		return sanitize_text_field( $touch['landing_title'] );
	}

	return ! empty( $touch['landing_slug'] ) ? sanitize_text_field( $touch['landing_slug'] ) : '';
}
}

if ( ! function_exists( 'crpcrm_request_landing_touch_datetime' ) ) {
function crpcrm_request_landing_touch_datetime( $touch ) {
	if ( empty( $touch ) || ! is_array( $touch ) || empty( $touch['clicked_at'] ) ) {
		return 'ثبت نشده';
	}

	return CRPCRM_Helpers::format_jalali_datetime( $touch['clicked_at'] );
}
}

if ( ! function_exists( 'crpcrm_request_focus_url' ) ) {
function crpcrm_request_focus_url( $focus_args, $filters ) {
	$base_args = array();
	foreach ( array( 'search', 'date_from', 'date_to', 'source', 'campaign', 'status_group' ) as $key ) {
		if ( ! empty( $filters[ $key ] ) ) {
			$base_args[ $key ] = $filters[ $key ];
		}
	}

	return crpcrm_admin_requests_url( array_merge( $base_args, $focus_args ) );
}
}

if ( ! function_exists( 'crpcrm_request_row_classes' ) ) {
function crpcrm_request_row_classes( $item, $current_user_id ) {
	$classes = array( 'crpcrm-request-row' );
	if ( empty( $item['owner_id'] ) ) {
		$classes[] = 'crpcrm-request-row-unassigned';
	}
	if ( ! empty( $item['owner_id'] ) && absint( $item['owner_id'] ) === absint( $current_user_id ) ) {
		$classes[] = 'crpcrm-request-row-mine';
	}
	if ( 'lead_follow_up' === sanitize_key( $item['request_type'] ?? '' ) ) {
		$classes[] = 'crpcrm-request-row-lead-followup';
	}
	if ( 'customer_reply' === sanitize_key( $item['last_action'] ?? '' ) ) {
		$classes[] = 'crpcrm-request-row-customer-reply';
	}
	if ( ! empty( $item['next_follow_up_at'] ) && in_array( sanitize_key( $item['status'] ?? '' ), array( 'follow_up', CRPCRM_Request_Workflow_Service::STATUS_FUTURE_FOLLOWUP ), true ) && false !== CRPCRM_Helpers::datetime_to_timestamp( $item['next_follow_up_at'] ) && CRPCRM_Helpers::datetime_to_timestamp( $item['next_follow_up_at'] ) < CRPCRM_Helpers::current_timestamp() ) {
		$classes[] = 'crpcrm-request-row-overdue';
	}

	return implode( ' ', array_map( 'sanitize_html_class', $classes ) );
}
}

if ( ! function_exists( 'crpcrm_request_focus_state' ) ) {
function crpcrm_request_focus_state( $filters ) {
	if ( 'new' === ( $filters['status'] ?? '' ) ) {
		return 'new';
	}
	if ( 'me' === ( $filters['owner_filter'] ?? '' ) ) {
		return 'mine';
	}
	if ( 'unassigned' === ( $filters['owner_filter'] ?? '' ) ) {
		return 'unassigned';
	}
	if ( 'customer_replies' === ( $filters['workflow_filter'] ?? '' ) ) {
		return 'customer_replies';
	}
	if ( 'followups_today' === ( $filters['workflow_filter'] ?? '' ) ) {
		return 'followups_today';
	}
	if ( 'overdue_followups' === ( $filters['workflow_filter'] ?? '' ) ) {
		return 'overdue_followups';
	}
	if ( CRPCRM_System_Request_Types::LEAD_FOLLOW_UP === ( $filters['request_type'] ?? '' ) ) {
		return 'lead_follow_up';
	}

	return 'all';
}
}

if ( ! function_exists( 'crpcrm_request_operational_badges' ) ) {
function crpcrm_request_operational_badges( $request ) {
	$badges = array();
	$last_action = sanitize_key( $request['last_action'] ?? '' );
	$request_type = sanitize_key( $request['request_type'] ?? '' );
	$status = sanitize_key( $request['status'] ?? '' );

	if ( 'customer_reply' === $last_action ) {
		$badges[] = '<span class="crpcrm-badge crpcrm-badge-new">پاسخ جدید مشتری</span>';
	}
	if ( 'lead_follow_up' === $request_type ) {
		$badges[] = '<span class="crpcrm-badge crpcrm-badge-pending">پیگیری خودکار</span>';
	}
	if ( empty( $request['owner_id'] ) ) {
		$badges[] = '<span class="crpcrm-badge crpcrm-badge-muted">بدون مسئول</span>';
	}
	if ( in_array( $status, array( 'follow_up', CRPCRM_Request_Workflow_Service::STATUS_FUTURE_FOLLOWUP ), true ) && ! empty( $request['next_follow_up_at'] ) && false !== CRPCRM_Helpers::datetime_to_timestamp( $request['next_follow_up_at'] ) && CRPCRM_Helpers::datetime_to_timestamp( $request['next_follow_up_at'] ) < CRPCRM_Helpers::current_timestamp() ) {
		$badges[] = '<span class="crpcrm-badge crpcrm-badge-failed">عقب‌افتاده</span>';
	}

	return implode( ' ', $badges );
}
}

if ( ! function_exists( 'crpcrm_admin_owner_form' ) ) {
function crpcrm_admin_owner_form( $request_id, $owner_id, $assignable_users ) {
	if ( ! CRPCRM_Feature_Manager::is_enabled( 'staff' ) ) {
		return;
	}
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

if ( ! function_exists( 'crpcrm_admin_transfer_form' ) ) {
function crpcrm_admin_transfer_form( $request_id, $owner_id, $assignable_users ) {
	if ( ! CRPCRM_Feature_Manager::is_enabled( 'staff' ) ) {
		return;
	}
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="crpcrm-sales-action-form">
		<input type="hidden" name="action" value="crpcrm_transfer_request">
		<input type="hidden" name="request_id" value="<?php echo esc_attr( absint( $request_id ) ); ?>">
		<?php wp_nonce_field( 'crpcrm_transfer_request_' . absint( $request_id ) ); ?>
		<label><?php echo esc_html( 'کارشناس مقصد' ); ?>
			<select name="new_owner_id" required>
				<option value=""><?php echo esc_html( 'انتخاب کنید' ); ?></option>
				<?php foreach ( $assignable_users as $user ) : ?>
					<option value="<?php echo esc_attr( $user->ID ); ?>" <?php disabled( absint( $owner_id ), absint( $user->ID ) ); ?>><?php echo esc_html( $user->display_name ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<label class="crpcrm-full-field"><?php echo esc_html( 'دلیل انتقال' ); ?>
			<textarea name="transfer_reason" rows="4" required placeholder="<?php echo esc_attr( 'دلیل انتقال را بنویسید.' ); ?>"></textarea>
		</label>
		<p class="submit"><button type="submit" class="button button-secondary"><?php echo esc_html( 'انتقال درخواست' ); ?></button></p>
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

if ( ! function_exists( 'crpcrm_admin_delete_form' ) ) {
function crpcrm_admin_delete_form( $request_id ) {
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="crpcrm-inline-form" onsubmit="return confirm('<?php echo esc_js( 'این درخواست و تمام تاریخچه فعالیت آن برای همیشه حذف می‌شود. آیا مطمئن هستید؟' ); ?>');">
		<input type="hidden" name="action" value="crpcrm_delete_request">
		<input type="hidden" name="request_id" value="<?php echo esc_attr( absint( $request_id ) ); ?>">
		<?php wp_nonce_field( 'crpcrm_delete_request_' . absint( $request_id ) ); ?>
		<button type="submit" class="button button-small crpcrm-danger-button"><?php echo esc_html( 'حذف کامل درخواست' ); ?></button>
	</form>
	<?php
}
}

if ( ! function_exists( 'crpcrm_admin_sales_action_form' ) ) {
function crpcrm_admin_sales_action_form( $request_id, $workflow, $closed_note_only = false ) {
	$actions = $workflow->get_action_labels();
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="crpcrm-sales-action-form">
		<input type="hidden" name="action" value="crpcrm_add_sales_action">
		<input type="hidden" name="request_id" value="<?php echo esc_attr( absint( $request_id ) ); ?>">
		<?php wp_nonce_field( 'crpcrm_add_sales_action_' . absint( $request_id ) ); ?>
		<label><?php echo esc_html( 'نوع اقدام' ); ?>
			<select name="action_type" class="crpcrm-action-type-select" required>
				<option value=""><?php echo esc_html( 'انتخاب کنید' ); ?></option>
				<?php foreach ( $actions as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<label class="crpcrm-full-field"><?php echo esc_html( 'توضیح کارشناس' ); ?>
			<textarea name="action_note" rows="4" required></textarea>
		</label>
		<label class="crpcrm-conditional-field crpcrm-follow-up-field"><?php echo esc_html( 'تاریخ و ساعت پیگیری بعدی' ); ?>
			<?php echo CRPCRM_Helpers::jalali_datetime_input( 'next_follow_up_at', '', array( 'time_hint' => 'ساعت پیگیری را هم انتخاب کنید.' ) ); ?>
		</label>
		<p class="submit"><button type="submit" class="button button-primary"><?php echo esc_html( 'ثبت اقدام' ); ?></button></p>
	</form>
	<?php
	return;
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="crpcrm-sales-action-form">
		<input type="hidden" name="action" value="crpcrm_add_sales_action">
		<input type="hidden" name="request_id" value="<?php echo esc_attr( absint( $request_id ) ); ?>">
		<?php wp_nonce_field( 'crpcrm_add_sales_action_' . absint( $request_id ) ); ?>
		<label><?php echo esc_html( 'نوع اقدام' ); ?>
			<select name="action_type" class="crpcrm-action-type-select" required>
				<option value=""><?php echo esc_html( 'انتخاب کنید' ); ?></option>
				<?php foreach ( $actions as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<label class="crpcrm-full-field"><?php echo esc_html( 'توضیح کارشناس' ); ?>
			<textarea name="action_note" rows="4" required></textarea>
		</label>
		<label class="crpcrm-conditional-field crpcrm-follow-up-field"><?php echo esc_html( 'تاریخ و ساعت پیگیری بعدی' ); ?>
			<?php echo CRPCRM_Helpers::jalali_datetime_input( 'next_follow_up_at', '', array( 'time_hint' => 'ساعت پیگیری را هم انتخاب کنید.' ) ); ?>
		</label>
		<label class="crpcrm-conditional-field crpcrm-lost-reason-field"><?php echo esc_html( 'دلیل ناموفق' ); ?>
			<?php if ( false ) : ?>
			<select name="close_reason">
				<option value=""><?php echo esc_html( 'انتخاب کنید' ); ?></option>
				<?php foreach ( $workflow->get_lost_reason_labels() as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php endif; ?>
		</label>
		<label class="crpcrm-conditional-field crpcrm-invalid-reason-field"><?php echo esc_html( 'دلیل نامعتبر' ); ?>
			<?php if ( false ) : ?>
			<select name="invalid_reason">
				<option value=""><?php echo esc_html( 'انتخاب کنید' ); ?></option>
				<?php foreach ( $workflow->get_invalid_reason_labels() as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php endif; ?>
		</label>
		<p class="submit"><button type="submit" class="button button-primary"><?php echo esc_html( 'ثبت اقدام' ); ?></button></p>
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
	<?php if ( $custom_notice_message ) : ?>
		<div class="notice notice-<?php echo esc_attr( 'success' === $custom_notice_type ? 'success' : 'error' ); ?> is-dismissible"><p><?php echo esc_html( $custom_notice_message ); ?></p></div>
	<?php endif; ?>

<?php if ( 'list' === $mode ) : ?>
		<?php $focus_state = crpcrm_request_focus_state( $filters ); ?>
		<div class="crpcrm-page-header crpcrm-request-header">
			<div>
				<h1><?php echo esc_html( 'درخواست‌ها' ); ?></h1>
				<p class="description"><?php echo esc_html( 'همه درخواست‌ها، پاسخ‌های جدید، پیگیری‌ها و موارد بدون مسئول را در یک نما دنبال کنید.' ); ?></p>
			</div>
			<div class="crpcrm-page-actions">
				<a class="button button-primary" href="<?php echo esc_url( crpcrm_admin_requests_url( array( 'action' => 'new' ) ) ); ?>"><?php echo esc_html( 'ایجاد درخواست جدید' ); ?></a>
			</div>
		</div>

		<?php
		$focus_tabs = array(
			'all' => array(
				'label' => 'همه',
				'count' => absint( $total ),
				'url'   => crpcrm_request_focus_url( array(), $filters ),
			),
			'new' => array(
				'label' => 'جدید',
				'count' => absint( $summary['new_requests'] ?? 0 ),
				'url'   => crpcrm_request_focus_url( array( 'status' => 'new' ), $filters ),
			),
			'mine' => array(
				'label' => 'درخواست‌های من',
				'count' => absint( $summary['mine'] ?? 0 ),
				'url'   => crpcrm_request_focus_url( array( 'owner_filter' => 'me' ), $filters ),
			),
			'unassigned' => array(
				'label' => 'بدون مسئول',
				'count' => absint( $summary['unassigned'] ?? 0 ),
				'url'   => crpcrm_request_focus_url( array( 'owner_filter' => 'unassigned' ), $filters ),
			),
			'customer_replies' => array(
				'label' => 'پاسخ جدید مشتری',
				'count' => absint( $summary['customer_replies'] ?? 0 ),
				'url'   => crpcrm_request_focus_url( array( 'workflow_filter' => 'customer_replies' ), $filters ),
			),
			'followups_today' => array(
				'label' => 'پیگیری امروز',
				'count' => absint( $summary['followups_today'] ?? 0 ),
				'url'   => crpcrm_request_focus_url( array( 'workflow_filter' => 'followups_today' ), $filters ),
			),
			'overdue_followups' => array(
				'label' => 'عقب‌افتاده',
				'count' => absint( $summary['overdue_followups'] ?? 0 ),
				'url'   => crpcrm_request_focus_url( array( 'workflow_filter' => 'overdue_followups' ), $filters ),
			),
			'lead_follow_up' => array(
				'label' => 'پیگیری خودکار',
				'count' => absint( $summary['lead_follow_ups'] ?? 0 ),
				'url'   => crpcrm_request_focus_url( array( 'request_type' => CRPCRM_System_Request_Types::LEAD_FOLLOW_UP ), $filters ),
			),
		);
		?>
		<div class="crpcrm-operational-tabs">
			<?php foreach ( $focus_tabs as $focus_key => $focus_tab ) : ?>
				<a class="crpcrm-operational-tab <?php echo $focus_state === $focus_key ? 'is-active' : ''; ?>" href="<?php echo esc_url( $focus_tab['url'] ); ?>">
					<strong><?php echo esc_html( $focus_tab['count'] ); ?></strong>
					<span><?php echo esc_html( $focus_tab['label'] ); ?></span>
				</a>
			<?php endforeach; ?>
		</div>

		<details class="crpcrm-card crpcrm-request-filters-accordion">
			<summary class="crpcrm-request-filters-toggle"><?php echo esc_html( 'فیلترها' ); ?></summary>
			<form method="get" class="crpcrm-request-filters">
				<input type="hidden" name="page" value="crpcrm-requests">
				<?php if ( 'lead_follow_up' === $focus_state ) : ?>
					<input type="hidden" name="request_type" value="<?php echo esc_attr( CRPCRM_System_Request_Types::LEAD_FOLLOW_UP ); ?>">
				<?php endif; ?>
				<div class="crpcrm-filter-grid">
					<label><?php echo esc_html( 'نوع درخواست' ); ?>
						<select name="request_type">
							<option value=""><?php echo esc_html( 'همه' ); ?></option>
							<?php foreach ( CRPCRM_Request_Type_Registry::get_request_types() as $request_type => $request_type_label ) : ?>
								<option value="<?php echo esc_attr( $request_type ); ?>" <?php selected( $filters['request_type'], $request_type ); ?>><?php echo esc_html( $request_type_label ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<label><?php echo esc_html( 'وضعیت' ); ?>
						<select name="status">
							<option value=""><?php echo esc_html( 'همه' ); ?></option>
							<?php foreach ( array( CRPCRM_Request_Workflow_Service::STATUS_IN_PROGRESS, CRPCRM_Request_Workflow_Service::STATUS_FOLLOWED_UP, CRPCRM_Request_Workflow_Service::STATUS_FUTURE_FOLLOWUP ) as $status_key ) : ?>
								<option value="<?php echo esc_attr( $status_key ); ?>" <?php selected( $filters['status'], $status_key ); ?>><?php echo esc_html( CRPCRM_Helpers::get_persian_status_label( $status_key ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<?php if ( CRPCRM_Feature_Manager::is_enabled( 'staff' ) ) : ?>
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
					<?php endif; ?>
					<label><?php echo esc_html( 'از تاریخ' ); ?><?php echo CRPCRM_Helpers::jalali_date_input( 'date_from', $filters['date_from'] ); ?></label>
					<label><?php echo esc_html( 'تا تاریخ' ); ?><?php echo CRPCRM_Helpers::jalali_date_input( 'date_to', $filters['date_to'] ); ?></label>
					<label><?php echo esc_html( 'فیلتر کاری' ); ?>
						<select name="workflow_filter">
							<option value=""><?php echo esc_html( 'همه' ); ?></option>
							<option value="followups_today" <?php selected( $filters['workflow_filter'], 'followups_today' ); ?>><?php echo esc_html( 'پیگیری‌های امروز' ); ?></option>
							<option value="overdue_followups" <?php selected( $filters['workflow_filter'], 'overdue_followups' ); ?>><?php echo esc_html( 'پیگیری‌های عقب‌افتاده' ); ?></option>
							<?php if ( $can_manage ) : ?><option value="stale" <?php selected( $filters['workflow_filter'], 'stale' ); ?>><?php echo esc_html( 'بدون فعالیت در ۴۸ ساعت اخیر' ); ?></option><?php endif; ?>
						</select>
					</label>
					<label><?php echo esc_html( 'گروه وضعیت' ); ?>
						<select name="status_group">
							<option value=""><?php echo esc_html( 'همه' ); ?></option>
							<option value="open" <?php selected( $filters['status_group'], 'open' ); ?>><?php echo esc_html( 'درخواست‌های باز' ); ?></option>
							<option value="closed" <?php selected( $filters['status_group'], 'closed' ); ?>><?php echo esc_html( 'درخواست‌های بسته‌شده' ); ?></option>
						</select>
					</label>
					<label><?php echo esc_html( 'جستجو' ); ?><input type="search" name="search" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="<?php echo esc_attr( 'کد، نام، موبایل' ); ?>"></label>
				</div>
				<p class="submit"><button type="submit" class="button button-primary"><?php echo esc_html( 'اعمال فیلتر' ); ?></button> <a class="button" href="<?php echo esc_url( crpcrm_admin_requests_url() ); ?>"><?php echo esc_html( 'پاک کردن فیلترها' ); ?></a></p>
			</form>
		</details>

		<div class="crpcrm-table-wrap">
			<table class="widefat fixed striped crpcrm-requests-table crpcrm-admin-requests-table">
				<thead><tr>
					<th><?php echo esc_html( 'شناسه' ); ?></th><th><?php echo esc_html( 'مشتری' ); ?></th><th><?php echo esc_html( 'موبایل' ); ?></th><th><?php echo esc_html( 'وضعیت' ); ?></th><th><?php echo esc_html( 'مسئول' ); ?></th><th><?php echo esc_html( 'پیگیری' ); ?></th><th><?php echo esc_html( 'تاریخ ثبت' ); ?></th><th><?php echo esc_html( 'بروزرسانی' ); ?></th><th><?php echo esc_html( 'عملیات' ); ?></th>
				</tr></thead>
				<tbody>
				<?php if ( empty( $requests ) ) : ?>
					<tr><td colspan="9"><?php echo esc_html( 'درخواستی یافت نشد.' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $requests as $item ) : ?>
						<?php $row_badges = crpcrm_request_operational_badges( $item ); ?>
						<tr class="<?php echo esc_attr( crpcrm_request_row_classes( $item, get_current_user_id() ) ); ?>">
							<td><strong><?php echo esc_html( $item['request_code'] ); ?></strong></td>
							<td><?php echo esc_html( $item['customer_name'] ? $item['customer_name'] : '—' ); ?></td>
							<td><?php echo esc_html( $item['customer_phone'] ? $item['customer_phone'] : $item['customer_phone_normalized'] ); ?></td>
							<td>
								<div class="crpcrm-request-status-stack">
									<span class="crpcrm-badge crpcrm-status-badge crpcrm-status-<?php echo esc_attr( sanitize_html_class( $item['status'] ) ); ?>"><?php echo esc_html( CRPCRM_Helpers::get_persian_status_label( $item['status'] ) ); ?></span>
									<?php if ( $row_badges ) : ?><div class="crpcrm-request-badge-line"><?php echo wp_kses_post( $row_badges ); ?></div><?php endif; ?>
								</div>
							</td>
							<td><?php echo esc_html( CRPCRM_Helpers::get_owner_label( $item['owner_id'] ) ); ?></td>
							<td class="<?php echo ( ! empty( $item['next_follow_up_at'] ) && CRPCRM_Helpers::datetime_to_timestamp( $item['next_follow_up_at'] ) < CRPCRM_Helpers::current_timestamp() && in_array( sanitize_key( $item['status'] ?? '' ), array( 'follow_up', CRPCRM_Request_Workflow_Service::STATUS_FUTURE_FOLLOWUP ), true ) ) ? 'crpcrm-overdue-followup' : ''; ?>"><?php echo esc_html( ! empty( $item['next_follow_up_at'] ) ? CRPCRM_Helpers::format_jalali_datetime( $item['next_follow_up_at'] ) : 'ثبت نشده' ); ?></td>
							<td><?php echo esc_html( CRPCRM_Helpers::format_jalali_datetime( $item['created_at'] ) ); ?></td>
							<td><?php echo esc_html( CRPCRM_Helpers::format_jalali_datetime( $item['updated_at'] ) ); ?></td>
							<td class="crpcrm-actions">
								<?php
								$can_claim_unassigned  = empty( $item['owner_id'] ) && CRPCRM_Request_Access_Service::can_claim_request( $item );
								$hide_view_until_claim = $can_claim_unassigned && ! $can_manage;
								?>
								<?php if ( ! $hide_view_until_claim ) : ?>
									<a class="button button-small" href="<?php echo esc_url( crpcrm_admin_requests_url( array( 'request_id' => absint( $item['id'] ) ) ) ); ?>"><?php echo esc_html( 'مشاهده' ); ?></a>
								<?php elseif ( $can_claim_unassigned ) : ?>
									<span class="description"><?php echo esc_html( 'ابتدا شروع پیگیری را بزنید.' ); ?></span>
								<?php endif; ?>
								<?php if ( $can_claim_unassigned ) : ?><?php crpcrm_admin_claim_form( $item['id'] ); ?><?php endif; ?>
								<?php if ( $can_manage && CRPCRM_Feature_Manager::is_enabled( 'staff' ) ) : ?>
									<?php crpcrm_admin_owner_form( $item['id'], $item['owner_id'], $assignable_users ); ?>
									<?php if ( ! empty( $item['owner_id'] ) ) : ?><?php crpcrm_admin_release_form( $item['id'] ); ?><?php endif; ?>
								<?php endif; ?>
								<?php if ( $can_delete ) : ?><?php crpcrm_admin_delete_form( $item['id'] ); ?><?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>

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
		$request_data = CRPCRM_Request_Repository::get_merged_request_data( $request );
		$detail_form  = CRPCRM_Request_Forms::get_form_for_request( $request['request_type'], $request_data );
		$detail_items = CRPCRM_Dynamic_Form_Renderer::get_display_items( $detail_form, $request_data );
		$request_badges = crpcrm_request_operational_badges( $request );
		ob_start();
		if ( $can_claim ) {
			crpcrm_admin_claim_form( $request['id'] );
		}
		if ( $can_manage && CRPCRM_Feature_Manager::is_enabled( 'staff' ) ) {
			crpcrm_admin_owner_form( $request['id'], $request['owner_id'], $assignable_users );
			if ( ! empty( $request['owner_id'] ) ) {
				crpcrm_admin_release_form( $request['id'] );
			}
		}
		if ( $can_delete ) {
			crpcrm_admin_delete_form( $request['id'] );
		}
		$request_actions_html = trim( (string) ob_get_clean() );
		?>
		<p><a class="button" href="<?php echo esc_url( crpcrm_admin_requests_url() ); ?>"><?php echo esc_html( 'بازگشت به لیست درخواست‌ها' ); ?></a></p>
		<div class="crpcrm-page-header crpcrm-request-detail-header">
			<div>
				<h1><?php echo esc_html( $request['request_code'] ); ?></h1>
				<p class="description">
					<?php echo esc_html( CRPCRM_Request_Type_Registry::get_label( $request['request_type'], $request ) ); ?>
					<span aria-hidden="true">·</span>
					<?php echo esc_html( CRPCRM_Helpers::format_jalali_datetime( $request['created_at'] ) ); ?>
				</p>
			</div>
			<div class="crpcrm-request-header-badges">
				<span class="crpcrm-badge crpcrm-status-badge crpcrm-status-<?php echo esc_attr( sanitize_html_class( $request['status'] ) ); ?>"><?php echo esc_html( CRPCRM_Helpers::get_persian_status_label( $request['status'] ) ); ?></span>
				<span class="crpcrm-badge crpcrm-source-badge"><?php echo esc_html( CRPCRM_Helpers::get_source_label( $request['request_source'] ) ); ?></span>
				<span class="crpcrm-badge crpcrm-badge-muted"><?php echo esc_html( CRPCRM_Helpers::get_owner_label( $request['owner_id'] ) ); ?></span>
				<?php if ( ! empty( $request['next_follow_up_at'] ) ) : ?><span class="crpcrm-badge crpcrm-badge-pending"><?php echo esc_html( CRPCRM_Helpers::format_jalali_datetime( $request['next_follow_up_at'] ) ); ?></span><?php endif; ?>
				<?php if ( $request_badges ) : ?><div class="crpcrm-request-badge-line"><?php echo wp_kses_post( $request_badges ); ?></div><?php endif; ?>
			</div>
		</div>
		<div class="crpcrm-request-detail-tabs" data-crpcrm-tabs="request-detail">
			<div class="crpcrm-request-tab-nav" role="tablist" aria-label="<?php echo esc_attr( 'بخش‌های جزئیات درخواست' ); ?>">
				<button type="button" class="crpcrm-request-tab-button is-active" data-tab-target="request-main" role="tab" aria-selected="true"><?php echo esc_html( 'اطلاعات اصلی درخواست' ); ?></button>
				<button type="button" class="crpcrm-request-tab-button" data-tab-target="request-activity" role="tab" aria-selected="false"><?php echo esc_html( 'فعالیت' ); ?></button>
				<button type="button" class="crpcrm-request-tab-button" data-tab-target="request-history" role="tab" aria-selected="false"><?php echo esc_html( 'تاریخچه' ); ?></button>
			</div>

			<div class="crpcrm-request-tab-panels">
				<div class="crpcrm-request-tab-panel is-active" data-tab-panel="request-main" role="tabpanel">
					<div class="crpcrm-detail-grid">
						<div class="crpcrm-card crpcrm-info-card crpcrm-request-meta-card crpcrm-request-section">
							<h2><?php echo esc_html( 'اطلاعات اصلی درخواست' ); ?></h2>
							<dl class="crpcrm-request-meta-list">
								<dt><?php echo esc_html( 'کد پیگیری' ); ?></dt><dd><?php echo esc_html( $request['request_code'] ); ?></dd>
								<dt><?php echo esc_html( 'نوع درخواست' ); ?></dt><dd><?php echo esc_html( CRPCRM_Request_Type_Registry::get_label( $request['request_type'], $request ) ); ?></dd>
								<dt><?php echo esc_html( 'وضعیت داخلی' ); ?></dt><dd><span class="crpcrm-badge crpcrm-status-badge"><?php echo esc_html( CRPCRM_Helpers::get_persian_status_label( $request['status'] ) ); ?></span></dd>
								<dt><?php echo esc_html( 'مسئول' ); ?></dt><dd><?php echo esc_html( CRPCRM_Helpers::get_owner_label( $request['owner_id'] ) ); ?></dd>
								<dt><?php echo esc_html( 'پیگیری بعدی' ); ?></dt><dd><?php echo esc_html( ! empty( $request['next_follow_up_at'] ) ? CRPCRM_Helpers::format_jalali_datetime( $request['next_follow_up_at'] ) : 'ثبت نشده' ); ?></dd>
								<dt><?php echo esc_html( 'تاریخ ثبت' ); ?></dt><dd><?php echo esc_html( CRPCRM_Helpers::format_jalali_datetime( $request['created_at'] ) ); ?></dd>
								<dt><?php echo esc_html( 'آخرین بروزرسانی' ); ?></dt><dd><?php echo esc_html( CRPCRM_Helpers::format_jalali_datetime( $request['updated_at'] ) ); ?></dd>
							</dl>
						</div>
					</div>

					<?php if ( '' !== $request_actions_html ) : ?>
						<div class="crpcrm-card crpcrm-request-section"><h2><?php echo esc_html( 'عملیات' ); ?></h2>
							<div class="crpcrm-request-actions crpcrm-request-actions-panel">
								<?php echo $request_actions_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>
						</div>
					<?php endif; ?>
					<?php if ( CRPCRM_Feature_Manager::is_enabled( 'staff' ) && ( $can_manage || absint( $request['owner_id'] ) === absint( get_current_user_id() ) ) ) : ?>
						<div class="crpcrm-card crpcrm-request-section">
							<h2><?php echo esc_html( 'انتقال درخواست' ); ?></h2>
							<?php crpcrm_admin_transfer_form( $request['id'], $request['owner_id'], $assignable_users ); ?>
						</div>
					<?php endif; ?>
				</div>

				<div class="crpcrm-request-tab-panel" data-tab-panel="request-activity" role="tabpanel">
					<div class="crpcrm-card crpcrm-request-section"><h2><?php echo esc_html( 'گفت‌وگو' ); ?></h2>
						<?php if ( ! empty( $request_conversation ) ) : ?>
							<ol class="crpcrm-request-conversation">
								<?php foreach ( $request_conversation as $message ) : ?>
									<?php
									$author_type  = sanitize_key( $message['activity_type'] ?? '' );
									$author_label = 'customer_reply' === $author_type ? 'مشتری' : ( 'manager_reply' === $author_type ? 'مدیر' : 'کارشناس فروش' );
									?>
									<li class="crpcrm-request-conversation-item crpcrm-request-conversation-<?php echo esc_attr( $author_type ); ?>">
										<div class="crpcrm-request-conversation-meta">
											<strong><?php echo esc_html( $author_label ); ?></strong>
											<time><?php echo esc_html( CRPCRM_Helpers::format_jalali_datetime( $message['created_at'] ) ); ?></time>
										</div>
										<div class="crpcrm-request-conversation-message"><?php echo nl2br( esc_html( $message['message'] ?? '' ) ); ?></div>
									</li>
								<?php endforeach; ?>
							</ol>
						<?php else : ?>
							<p><?php echo esc_html( 'هنوز پاسخی ثبت نشده است.' ); ?></p>
						<?php endif; ?>

						<?php if ( ! empty( $can_reply ) ) : ?>
							<h3><?php echo esc_html( 'ارسال پاسخ' ); ?></h3>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="crpcrm-request-reply-form">
								<input type="hidden" name="action" value="crpcrm_staff_request_reply">
								<input type="hidden" name="request_id" value="<?php echo esc_attr( absint( $request['id'] ) ); ?>">
								<?php wp_nonce_field( 'crpcrm_staff_request_reply_' . absint( $request['id'] ), 'crpcrm_request_reply_nonce' ); ?>
								<label for="crpcrm-staff-request-reply-message"><?php echo esc_html( 'متن پاسخ' ); ?></label>
								<textarea id="crpcrm-staff-request-reply-message" name="reply_message" rows="5" required></textarea>
								<p class="submit"><button type="submit" class="button button-primary"><?php echo esc_html( 'ارسال پاسخ' ); ?></button></p>
							</form>
						<?php endif; ?>
					</div>

					<div class="crpcrm-card crpcrm-sales-action-card"><h2><?php echo esc_html( 'ثبت اقدام' ); ?></h2>
						<?php if ( $can_add_action ) : ?>
							<?php crpcrm_admin_sales_action_form( $request['id'], $workflow, $workflow->is_closed_status( $request['status'] ) ); ?>
						<?php else : ?>
							<p class="crpcrm-closed-request-badge"><?php echo esc_html( $workflow->is_closed_status( $request['status'] ) ? 'این درخواست بسته شده و امکان ثبت اقدام جدید وجود ندارد.' : 'شما اجازه ثبت اقدام برای این درخواست را ندارید.' ); ?></p>
						<?php endif; ?>
					</div>
				</div>

				<div class="crpcrm-request-tab-panel" data-tab-panel="request-history" role="tabpanel">
					<div class="crpcrm-detail-grid">
						<div class="crpcrm-card crpcrm-customer-card crpcrm-request-meta-card crpcrm-request-section">
							<h2><?php echo esc_html( 'اطلاعات مشتری' ); ?></h2>
							<dl class="crpcrm-request-meta-list">
								<dt><?php echo esc_html( 'نام و نام خانوادگی' ); ?></dt><dd><?php echo esc_html( $request['customer_name'] ? $request['customer_name'] : '—' ); ?></dd>
								<dt><?php echo esc_html( 'شماره موبایل' ); ?></dt><dd><?php echo esc_html( $request['customer_phone'] ? $request['customer_phone'] : $request['customer_phone_normalized'] ); ?></dd>
								<dt><?php echo esc_html( 'استان' ); ?></dt><dd><?php echo esc_html( $request['customer_province'] ? $request['customer_province'] : '—' ); ?></dd>
								<dt><?php echo esc_html( 'شهر' ); ?></dt><dd><?php echo esc_html( $request['customer_city'] ? $request['customer_city'] : '—' ); ?></dd>
								<dt><?php echo esc_html( 'پروفایل مشتری' ); ?></dt><dd><a href="<?php echo esc_url( crpcrm_admin_customer_profile_url( $request['customer_id'], array( 'return_request_id' => absint( $request['id'] ) ) ) ); ?>"><?php echo esc_html( 'مشاهده پروفایل مشتری' ); ?></a></dd>
							</dl>
						</div>
					</div>

					<div class="crpcrm-card crpcrm-request-section"><h2><?php echo esc_html( 'اطلاعات فرم' ); ?></h2><dl class="crpcrm-form-data">
						<?php foreach ( $detail_items as $detail_key => $item ) : ?>
							<?php
							$item['source_type']  = 'request';
							$item['source_id']    = absint( $request['id'] ?? 0 );
							$item['source_field'] = sanitize_key( is_string( $detail_key ) ? $detail_key : ( $item['key'] ?? $item['field_key'] ?? '' ) );
							echo CRPCRM_Dynamic_Form_Renderer::render_display_item( $item, 'admin' );
							?>
						<?php endforeach; ?>
					</dl></div>

					<details class="crpcrm-card crpcrm-request-history-accordion" dir="rtl">
						<summary class="crpcrm-request-history-toggle"><h2><?php echo esc_html( 'تاریخچه فعالیت' ); ?></h2></summary>
						<div class="crpcrm-request-history-body">
							<?php if ( empty( $activities ) ) : ?><p><?php echo esc_html( 'هنوز فعالیتی ثبت نشده است.' ); ?></p><?php else : ?>
								<ol class="crpcrm-activity-timeline">
									<?php foreach ( $activities as $activity ) : $actor = ! empty( $activity['actor_user_id'] ) ? get_userdata( absint( $activity['actor_user_id'] ) ) : null; ?>
										<li><time><?php echo esc_html( CRPCRM_Helpers::format_jalali_datetime( $activity['created_at'] ) ); ?></time><strong><?php echo esc_html( CRPCRM_Helpers::get_activity_type_label( $activity['activity_type'] ) ); ?></strong><span><?php echo esc_html( $actor ? $actor->display_name : $activity['actor_type'] ); ?></span><?php if ( $activity['old_status'] || $activity['new_status'] ) : ?><em><?php echo esc_html( CRPCRM_Helpers::get_persian_status_label( $activity['old_status'] ) . ' ← ' . CRPCRM_Helpers::get_persian_status_label( $activity['new_status'] ) ); ?></em><?php endif; ?><p><?php echo esc_html( $activity['note'] ? $activity['note'] : '—' ); ?></p></li>
									<?php endforeach; ?>
								</ol>
							<?php endif; ?>
						</div>
					</details>
				</div>
			</div>
		</div>
	<?php endif; ?>
</div>


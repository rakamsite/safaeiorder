<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$messages = array(
	'manual_customer_required'    => 'یک مشتری ثبت‌شده را انتخاب کنید.',
	'manual_customer_invalid'     => 'نام و شماره موبایل معتبر مشتری جدید الزامی است.',
	'manual_customer_exists'      => 'این شماره موبایل قبلاً ثبت شده است؛ مشتری موجود را جستجو کنید.',
	'manual_customer_failed'      => 'ساخت مشتری جدید انجام نشد.',
	'manual_request_type_invalid' => 'نوع درخواست معتبر نیست.',
	'manual_request_failed'       => 'ثبت درخواست دستی انجام نشد.',
);
$notice = isset( $_GET['crpcrm_notice'] ) ? sanitize_key( wp_unslash( $_GET['crpcrm_notice'] ) ) : '';
$request_types = array_keys( CRPCRM_Request_Type_Registry::get_request_types() );
$sources = array( 'direct', 'instagram', 'whatsapp', 'telegram', 'google', 'bing', 'referral', 'other' );
?>
<div class="wrap crpcrm-admin-wrap crpcrm-requests-admin" dir="rtl">
	<h1><?php echo esc_html( 'ایجاد درخواست جدید' ); ?></h1>
	<p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=crpcrm-requests' ) ); ?>"><?php echo esc_html( 'بازگشت به درخواست‌ها' ); ?></a></p>
	<?php if ( isset( $messages[ $notice ] ) ) : ?><div class="notice notice-error"><p><?php echo esc_html( $messages[ $notice ] ); ?></p></div><?php endif; ?>

	<div class="crpcrm-card"><h2><?php echo esc_html( 'جستجوی مشتری ثبت‌شده' ); ?></h2>
		<form method="get" class="crpcrm-manual-search"><input type="hidden" name="page" value="crpcrm-requests"><input type="hidden" name="action" value="new"><input type="search" name="customer_search" value="<?php echo esc_attr( $customer_search ); ?>" placeholder="<?php echo esc_attr( 'نام یا شماره موبایل' ); ?>" required><button class="button" type="submit"><?php echo esc_html( 'جستجو' ); ?></button></form>
		<?php if ( '' !== $customer_search && empty( $customer_results ) ) : ?><p><?php echo esc_html( 'کاربری پیدا نشد؛ می‌توانید در فرم زیر کاربر جدید بسازید.' ); ?></p><?php endif; ?>
	</div>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="crpcrm-card crpcrm-manual-request-form">
		<input type="hidden" name="action" value="crpcrm_create_manual_request"><?php wp_nonce_field( 'crpcrm_create_manual_request' ); ?>
		<h2><?php echo esc_html( '۱. انتخاب یا ساخت مشتری' ); ?></h2>
		<div class="crpcrm-radio-row"><label><input type="radio" name="customer_mode" value="existing" checked> <?php echo esc_html( 'انتخاب کاربر ثبت‌شده' ); ?></label><label><input type="radio" name="customer_mode" value="new"> <?php echo esc_html( 'ساخت کاربر جدید بدون OTP' ); ?></label></div>
		<div class="crpcrm-existing-customer-fields"><label><?php echo esc_html( 'کاربر ثبت‌شده' ); ?><select name="customer_id"><option value=""><?php echo esc_html( 'از نتایج جستجو انتخاب کنید' ); ?></option><?php foreach ( $customer_results as $customer ) : ?><option value="<?php echo esc_attr( $customer['id'] ); ?>"><?php echo esc_html( ( $customer['full_name'] ? $customer['full_name'] : $customer['display_name'] ) . ' — ' . $customer['phone_normalized'] ); ?></option><?php endforeach; ?></select></label></div>
		<div class="crpcrm-new-customer-fields"><div class="crpcrm-form-grid"><label><?php echo esc_html( 'نام و نام خانوادگی' ); ?><input type="text" name="new_customer_name"></label><label><?php echo esc_html( 'شماره موبایل' ); ?><input type="tel" name="new_customer_phone" placeholder="09123456789"></label><label><?php echo esc_html( 'استان' ); ?><input type="text" name="new_customer_province"></label><label><?php echo esc_html( 'شهر' ); ?><input type="text" name="new_customer_city"></label><label><?php echo esc_html( 'منبع اولیه کاربر' ); ?><select name="new_customer_source"><?php foreach ( $sources as $source ) : ?><option value="<?php echo esc_attr( $source ); ?>"><?php echo esc_html( CRPCRM_Helpers::get_source_label( $source ) ); ?></option><?php endforeach; ?></select></label></div></div>

		<h2><?php echo esc_html( '۲. اطلاعات درخواست' ); ?></h2><div class="crpcrm-form-grid">
			<label><?php echo esc_html( 'نوع درخواست' ); ?><select name="request_type" required><?php foreach ( $request_types as $type ) : ?><option value="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( CRPCRM_Helpers::get_request_type_label( $type ) ); ?></option><?php endforeach; ?></select></label>
			<label><?php echo esc_html( 'وضعیت' ); ?><select name="request_status"><?php foreach ( array( 'new', 'in_progress', 'no_answer', 'follow_up', 'won', 'lost', 'invalid' ) as $status ) : ?><option value="<?php echo esc_attr( $status ); ?>"><?php echo esc_html( CRPCRM_Helpers::get_persian_status_label( $status ) ); ?></option><?php endforeach; ?></select></label>
			<label><?php echo esc_html( 'مسئول' ); ?><select name="owner_id"><option value="0"><?php echo esc_html( 'بدون مسئول' ); ?></option><?php foreach ( $assignable_users as $user ) : ?><option value="<?php echo esc_attr( $user->ID ); ?>" <?php selected( ! $can_manage && absint( $user->ID ) === get_current_user_id() ); ?>><?php echo esc_html( $user->display_name ); ?></option><?php endforeach; ?></select></label>
			<label><?php echo esc_html( 'منبع درخواست' ); ?><select name="request_source"><?php foreach ( $sources as $source ) : ?><option value="<?php echo esc_attr( $source ); ?>"><?php echo esc_html( CRPCRM_Helpers::get_source_label( $source ) ); ?></option><?php endforeach; ?></select></label><label><?php echo esc_html( 'مدیوم' ); ?><input type="text" name="request_medium"></label><label><?php echo esc_html( 'کمپین' ); ?><input type="text" name="request_campaign"></label><label><?php echo esc_html( 'محتوا / utm_content' ); ?><input type="text" name="request_content"></label><label><?php echo esc_html( 'عبارت / utm_term' ); ?><input type="text" name="request_term"></label><label><?php echo esc_html( 'Landing page' ); ?><input type="url" name="request_landing_page"></label><label><?php echo esc_html( 'Referrer' ); ?><input type="url" name="request_referrer"></label><label><?php echo esc_html( 'پیگیری بعدی' ); ?><?php echo CRPCRM_Helpers::jalali_datetime_input( 'next_follow_up_at' ); ?></label><label><?php echo esc_html( 'عنوان' ); ?><input type="text" name="request_title"></label>
			<label class="crpcrm-full-field"><?php echo esc_html( 'خلاصه درخواست' ); ?><textarea name="request_summary" rows="3"></textarea></label><label class="crpcrm-full-field"><?php echo esc_html( 'تمام جزئیات فرم / توضیحات دستی' ); ?><textarea name="manual_details" rows="5"></textarea></label>
			<label><?php echo esc_html( 'خودروی موردنظر' ); ?><input type="text" name="desired_vehicle"></label><label><?php echo esc_html( 'نام قطعه' ); ?><input type="text" name="part_name"></label><label><?php echo esc_html( 'مدل خودرو' ); ?><input type="text" name="vehicle_model"></label><label><?php echo esc_html( 'نوع سرویس' ); ?><input type="text" name="service_type"></label><label class="crpcrm-full-field"><?php echo esc_html( 'شرح مشکل / توضیحات' ); ?><textarea name="problem_description" rows="3"></textarea></label>
		</div><p class="submit"><button class="button button-primary" type="submit"><?php echo esc_html( 'ثبت درخواست' ); ?></button></p>
	</form>
</div>

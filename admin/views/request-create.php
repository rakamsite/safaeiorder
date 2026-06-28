<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$messages = array(
	'manual_customer_required'    => 'ابتدا مشتری را پیدا یا ایجاد کنید.',
	'manual_customer_invalid'     => 'اطلاعات مشتری معتبر نیست.',
	'manual_customer_exists'      => 'مشتری با این شماره موبایل قبلاً ثبت شده است و برای همین درخواست انتخاب شد.',
	'manual_customer_failed'      => 'ساخت یا انتخاب مشتری انجام نشد.',
	'manual_request_type_invalid' => 'نوع درخواست معتبر نیست.',
	'manual_form_invalid'         => 'نوع درخواست انتخاب‌شده یا اطلاعات واردشده معتبر نیست.',
	'manual_request_failed'       => 'ثبت درخواست دستی انجام نشد.',
	'manual_landing_required'     => 'برای ثبت درخواست، ابتدا باید حداقل یک لندینگ فعال در مدیریت لندینگ‌ها تعریف شود.',
	'manual_landing_invalid'      => 'منبع انتخاب‌شده معتبر نیست.',
);

$notice                 = isset( $_GET['crpcrm_notice'] ) ? sanitize_key( wp_unslash( $_GET['crpcrm_notice'] ) ) : '';
$customer_context       = is_array( $customer_context ?? null ) ? $customer_context : array();
$customer_state         = isset( $customer_context['state'] ) ? sanitize_key( $customer_context['state'] ) : 'idle';
$customer_summary       = isset( $customer_context['summary'] ) ? (string) $customer_context['summary'] : '';
$customer_message       = isset( $customer_context['message'] ) ? (string) $customer_context['message'] : '';
$customer_action        = isset( $customer_context['action_label'] ) ? (string) $customer_context['action_label'] : '';
$customer_form_html     = isset( $customer_context['form_html'] ) ? (string) $customer_context['form_html'] : '';
$current_customer_phone = isset( $current_customer_phone ) ? (string) $current_customer_phone : '';
$current_customer_phone = '' !== $current_customer_phone ? $current_customer_phone : (string) ( $customer_context['phone_normalized'] ?? '' );
$current_customer_id    = isset( $current_customer_id ) ? absint( $current_customer_id ) : 0;
$current_request_status = isset( $current_request_status ) ? sanitize_key( (string) $current_request_status ) : CRPCRM_Request_Workflow_Service::get_default_status();
$current_request_source = isset( $current_request_source ) ? sanitize_text_field( (string) $current_request_source ) : '';
$active_landings        = ! empty( $active_landings ) && is_array( $active_landings ) ? $active_landings : array();
$request_page_url       = crpcrm_admin_requests_url( array( 'action' => 'new' ) );
$has_active_landings    = ! empty( $active_landings );
?>
<div class="wrap crpcrm-admin-wrap crpcrm-requests-admin" dir="rtl">
	<h1><?php echo esc_html( 'ایجاد درخواست جدید' ); ?></h1>
	<p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=crpcrm-requests' ) ); ?>"><?php echo esc_html( 'بازگشت به درخواست‌ها' ); ?></a></p>

	<?php if ( isset( $messages[ $notice ] ) ) : ?>
		<div class="notice notice-error"><p><?php echo esc_html( $messages[ $notice ] ); ?></p></div>
	<?php endif; ?>

	<?php if ( ! $has_active_landings ) : ?>
		<div class="notice notice-warning"><p><?php echo esc_html( $messages['manual_landing_required'] ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="crpcrm-card crpcrm-manual-request-form" enctype="multipart/form-data" data-request-page-url="<?php echo esc_url( $request_page_url ); ?>">
		<input type="hidden" name="action" value="crpcrm_create_manual_request">
		<?php wp_nonce_field( 'crpcrm_create_manual_request' ); ?>
		<input type="hidden" name="customer_id" value="<?php echo esc_attr( $current_customer_id ? $current_customer_id : ( $customer_context['customer_id'] ?? 0 ) ); ?>">

		<div class="crpcrm-card-header">
			<div>
				<h2><?php echo esc_html( 'ثبت درخواست توسط کارشناس فروش' ); ?></h2>
				<p><?php echo esc_html( 'ابتدا نوع درخواست و مشتری را مشخص کنید، سپس اطلاعات درخواست را ثبت کنید.' ); ?></p>
			</div>
		</div>

		<?php if ( empty( $forms ) || ! $selected_form ) : ?>
			<p><?php echo esc_html( 'هیچ فرم فعالی برای ثبت درخواست وجود ندارد.' ); ?></p>
		<?php else : ?>
			<div class="crpcrm-form-grid">
				<label class="crpcrm-width-50">
					<?php echo esc_html( 'نوع درخواست' ); ?>
					<select name="manual_form_selector" class="crpcrm-manual-request-type-selector" data-base-url="<?php echo esc_url( $request_page_url ); ?>">
						<?php foreach ( $forms as $form_id => $form_definition ) : ?>
							<option value="<?php echo esc_attr( $form_id ); ?>" <?php selected( $selected_form['id'], $form_definition['id'] ); ?>>
								<?php echo esc_html( $form_definition['label'] ?? $form_definition['title'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</label>
				<input type="hidden" name="form_id" value="<?php echo esc_attr( $selected_form['id'] ); ?>">

				<label class="crpcrm-width-50">
					<?php echo esc_html( 'شماره موبایل مشتری' ); ?>
					<input type="tel" name="customer_phone" class="crpcrm-manual-customer-phone" value="<?php echo esc_attr( $current_customer_phone ); ?>" placeholder="09123456789" autocomplete="off" inputmode="tel" required>
				</label>
			</div>

			<div class="crpcrm-manual-customer-block">
				<div class="crpcrm-manual-customer-status" aria-live="polite"></div>

				<div class="crpcrm-manual-customer-result<?php echo 'idle' !== $customer_state ? ' is-visible' : ''; ?>" data-customer-state="<?php echo esc_attr( $customer_state ); ?>">
					<?php if ( '' !== $customer_summary ) : ?>
						<p class="crpcrm-manual-customer-summary"><?php echo esc_html( $customer_summary ); ?></p>
					<?php endif; ?>

					<?php if ( '' !== $customer_message ) : ?>
						<p class="crpcrm-manual-customer-message">
							<?php echo esc_html( $customer_message ); ?>
							<?php if ( '' !== $customer_action ) : ?>
								<?php echo esc_html( ' ' ); ?>
								<button type="button" class="button-link crpcrm-manual-customer-toggle"><?php echo esc_html( $customer_action ); ?></button>
							<?php endif; ?>
						</p>
					<?php endif; ?>
				</div>

				<div class="crpcrm-manual-customer-editor" hidden>
					<?php echo $customer_form_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			</div>

			<div class="crpcrm-manual-request-fields">
				<div class="crpcrm-form-grid">
					<?php CRPCRM_Dynamic_Form_Renderer::render_fields( $selected_form, 'admin' ); ?>

					<label class="crpcrm-width-50">
						<?php echo esc_html( 'وضعیت درخواست' ); ?>
						<select name="request_status" required>
							<?php foreach ( array( CRPCRM_Request_Workflow_Service::STATUS_IN_PROGRESS, CRPCRM_Request_Workflow_Service::STATUS_FOLLOWED_UP, CRPCRM_Request_Workflow_Service::STATUS_FUTURE_FOLLOWUP ) as $status ) : ?>
								<option value="<?php echo esc_attr( $status ); ?>" <?php selected( $current_request_status, $status ); ?>>
									<?php echo esc_html( CRPCRM_Helpers::get_persian_status_label( $status ) ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</label>

					<label class="crpcrm-width-50">
						<?php echo esc_html( 'منبع' ); ?>
						<select name="request_source" <?php disabled( ! $has_active_landings ); ?> required>
							<option value=""><?php echo esc_html( 'انتخاب منبع' ); ?></option>
							<?php foreach ( $active_landings as $landing ) : ?>
								<?php $landing_id = absint( $landing['id'] ?? 0 ); ?>
								<option value="<?php echo esc_attr( $landing_id ); ?>" <?php selected( (string) $current_request_source, (string) $landing_id ); ?>>
									<?php echo esc_html( $landing['title'] ?? '' ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</label>
				</div>
			</div>

			<p class="submit">
				<button class="button button-primary" type="submit" <?php disabled( ! $has_active_landings ); ?>><?php echo esc_html( 'ثبت درخواست' ); ?></button>
			</p>
		<?php endif; ?>
	</form>
</div>

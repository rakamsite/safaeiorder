<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$customer_name   = ! empty( $customer['full_name'] ) ? $customer['full_name'] : 'کاربر گرامی';
$portal_data     = isset( $portal_data ) && is_array( $portal_data ) ? $portal_data : array();
$latest_requests = ! empty( $portal_data['latest_requests'] ) && is_array( $portal_data['latest_requests'] ) ? $portal_data['latest_requests'] : array();
$my_requests     = ! empty( $portal_data['my_requests'] ) && is_array( $portal_data['my_requests'] ) ? $portal_data['my_requests'] : array();
$form            = ! empty( $portal_data['form'] ) && is_array( $portal_data['form'] ) ? $portal_data['form'] : null;
$request_detail  = ! empty( $portal_data['request_detail'] ) && is_array( $portal_data['request_detail'] ) ? $portal_data['request_detail'] : null;
$request_forms   = ! empty( $portal_data['request_forms'] ) && is_array( $portal_data['request_forms'] ) ? $portal_data['request_forms'] : CRPCRM_Request_Forms::get_forms();
$created_notice  = isset( $_GET['created'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['created'] ) );
?>
<div class="crpcrm-portal crpcrm-portal-shell" dir="rtl">
	<aside class="crpcrm-portal-sidebar" aria-label="<?php echo esc_attr( 'منوی پرتال' ); ?>">
		<div class="crpcrm-portal-sidebar-title"><?php echo esc_html( 'پرتال درخواست‌ها' ); ?></div>
		<nav class="crpcrm-portal-menu">
			<ul>
				<?php foreach ( $menu_items as $item ) : ?>
					<li>
						<a class="<?php echo ! empty( $item['active'] ) ? 'is-active' : ''; ?> <?php echo 'logout' === $item['key'] ? 'crpcrm-menu-logout' : ''; ?>" href="<?php echo esc_url( $item['url'] ); ?>">
							<?php echo esc_html( $item['label'] ); ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</nav>

		<?php if ( ! empty( $custom_links ) ) : ?>
			<div class="crpcrm-portal-menu-divider"></div>
			<nav class="crpcrm-portal-menu crpcrm-portal-custom-menu" aria-label="<?php echo esc_attr( 'لینک‌های سفارشی پرتال' ); ?>">
				<ul>
					<?php foreach ( $custom_links as $link ) : ?>
						<li>
							<a href="<?php echo esc_url( $link['url'] ); ?>" <?php echo ! empty( $link['target'] ) ? 'target="' . esc_attr( $link['target'] ) . '" rel="noopener noreferrer"' : ''; ?>>
								<?php echo esc_html( $link['label'] ); ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</nav>
		<?php endif; ?>
	</aside>

	<main class="crpcrm-portal-content">
		<?php if ( ! empty( $notice ) ) : ?>
			<div class="crpcrm-notice crpcrm-notice-<?php echo esc_attr( $notice['type'] ); ?>"><?php echo esc_html( $notice['message'] ); ?></div>
		<?php endif; ?>

		<?php if ( 'profile' === $current_page ) : ?>
			<?php
			$is_edit            = true;
			$is_embedded        = true;
			$portal_redirect_to = $portal_urls['profile'];
			include CRPCRM_PLUGIN_DIR . 'public/views/profile-form.php';
			?>
		<?php elseif ( 'my_requests' === $current_page ) : ?>
			<section class="crpcrm-portal-card crpcrm-my-requests-card">
				<div class="crpcrm-card-heading-row">
					<h2><?php echo esc_html( 'درخواست‌های من' ); ?></h2>
					<div class="crpcrm-button-row">
						<a class="crpcrm-button crpcrm-button-inline crpcrm-open-request-form" href="<?php echo esc_url( $portal_urls['new_car_registration'] ); ?>" data-crpcrm-open-form="new_car_registration"><?php echo esc_html( 'ثبت‌نام خودرو' ); ?></a>
						<a class="crpcrm-button crpcrm-button-inline crpcrm-open-request-form" href="<?php echo esc_url( $portal_urls['new_parts_request'] ); ?>" data-crpcrm-open-form="new_parts_request"><?php echo esc_html( 'درخواست قطعات' ); ?></a>
						<a class="crpcrm-button crpcrm-button-inline crpcrm-open-request-form" href="<?php echo esc_url( $portal_urls['new_repair_booking'] ); ?>" data-crpcrm-open-form="new_repair_booking"><?php echo esc_html( 'درخواست تعمیرات' ); ?></a>
					</div>
				</div>

				<?php if ( empty( $my_requests ) ) : ?>
					<p><?php echo esc_html( 'هنوز درخواستی ثبت نکرده‌اید.' ); ?></p>
				<?php else : ?>
					<div class="crpcrm-table-wrap">
						<table class="crpcrm-requests-table">
							<thead>
								<tr>
									<th><?php echo esc_html( 'کد پیگیری' ); ?></th>
									<th><?php echo esc_html( 'نوع درخواست' ); ?></th>
									<th><?php echo esc_html( 'خلاصه درخواست' ); ?></th>
									<th><?php echo esc_html( 'وضعیت' ); ?></th>
									<th><?php echo esc_html( 'تاریخ ثبت' ); ?></th>
									<th><?php echo esc_html( 'آخرین بروزرسانی' ); ?></th>
									<th><?php echo esc_html( 'عملیات' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $my_requests as $request ) : ?>
									<tr>
										<td><strong><?php echo esc_html( $request['request_code'] ); ?></strong></td>
										<td><?php echo esc_html( CRPCRM_Request_Forms::get_type_label( $request['request_type'] ) ); ?></td>
										<td><?php echo esc_html( wp_trim_words( $request['request_summary'], 18, '…' ) ); ?></td>
										<td><span class="crpcrm-status-badge"><?php echo esc_html( CRPCRM_Request_Forms::get_customer_status_label( $request['status'] ) ); ?></span></td>
										<td><?php echo esc_html( CRPCRM_Helpers::format_jalali_datetime( $request['created_at'] ) ); ?></td>
										<td><?php echo esc_html( CRPCRM_Helpers::format_jalali_datetime( ! empty( $request['last_activity_at'] ) ? $request['last_activity_at'] : $request['updated_at'] ) ); ?></td>
										<td><a class="crpcrm-table-action" href="<?php echo esc_url( add_query_arg( array( 'crpcrm_page' => 'request_detail', 'request_code' => $request['request_code'] ), $portal_urls['dashboard'] ) ); ?>"><?php echo esc_html( 'مشاهده جزئیات' ); ?></a></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
			</section>
		<?php foreach ( $request_forms as $inline_form ) : ?>
			<?php
			$form                   = $inline_form;
			$is_inline_request_form = true;
			include CRPCRM_PLUGIN_DIR . 'public/views/request-form-panel.php';
			?>
		<?php endforeach; ?>
	<?php elseif ( in_array( $current_page, CRPCRM_Request_Forms::get_form_pages(), true ) && $form ) : ?>
		<?php
		$is_inline_request_form = false;
		include CRPCRM_PLUGIN_DIR . 'public/views/request-form-panel.php';
		?>
	<?php elseif ( 'request_detail' === $current_page ) : ?>
			<section class="crpcrm-portal-card crpcrm-request-detail-card">
				<?php if ( ! empty( $portal_data['access_denied'] ) ) : ?>
					<div class="crpcrm-notice crpcrm-notice-error"><?php echo esc_html( 'شما اجازه مشاهده این درخواست را ندارید.' ); ?></div>
				<?php elseif ( $request_detail ) : ?>
					<?php $request_data = CRPCRM_Helpers::maybe_json_decode( $request_detail['request_data'], true ); ?>
					<?php if ( $created_notice ) : ?>
						<div class="crpcrm-notice crpcrm-notice-success">
							<?php echo esc_html( CRPCRM_Settings::get( 'request_success_message', 'درخواست شما با موفقیت ثبت شد. کارشناسان ما در اولین فرصت آن را بررسی می‌کنند.' ) ); ?><br />
							<?php echo esc_html( 'کد پیگیری شما: ' . $request_detail['request_code'] ); ?>
						</div>
					<?php endif; ?>
					<h2><?php echo esc_html( 'جزئیات درخواست' ); ?></h2>
					<div class="crpcrm-detail-grid">
						<div><span><?php echo esc_html( 'کد پیگیری' ); ?></span><strong><?php echo esc_html( $request_detail['request_code'] ); ?></strong></div>
						<div><span><?php echo esc_html( 'نوع درخواست' ); ?></span><strong><?php echo esc_html( CRPCRM_Request_Forms::get_type_label( $request_detail['request_type'] ) ); ?></strong></div>
						<div><span><?php echo esc_html( 'وضعیت' ); ?></span><strong><?php echo esc_html( CRPCRM_Request_Forms::get_customer_status_label( $request_detail['status'] ) ); ?></strong></div>
						<div><span><?php echo esc_html( 'تاریخ ثبت' ); ?></span><strong><?php echo esc_html( CRPCRM_Helpers::format_jalali_datetime( $request_detail['created_at'] ) ); ?></strong></div>
						<div><span><?php echo esc_html( 'آخرین بروزرسانی' ); ?></span><strong><?php echo esc_html( CRPCRM_Helpers::format_jalali_datetime( ! empty( $request_detail['last_activity_at'] ) ? $request_detail['last_activity_at'] : $request_detail['updated_at'] ) ); ?></strong></div>
					</div>
					<h3><?php echo esc_html( 'خلاصه درخواست' ); ?></h3>
					<p class="crpcrm-request-summary"><?php echo esc_html( $request_detail['request_summary'] ); ?></p>
					<h3><?php echo esc_html( 'اطلاعات ثبت‌شده در فرم' ); ?></h3>
					<dl class="crpcrm-data-list">
						<?php
						$detail_form = null;
						foreach ( CRPCRM_Request_Forms::get_forms() as $candidate_form ) {
							if ( $candidate_form['request_type'] === $request_detail['request_type'] ) {
								$detail_form = $candidate_form;
								break;
							}
						}
						?>
						<?php if ( $detail_form && is_array( $request_data ) ) : ?>
							<?php foreach ( $detail_form['fields'] as $field ) : ?>
								<dt><?php echo esc_html( $field['label'] ); ?></dt>
								<dd><?php echo esc_html( isset( $request_data[ $field['name'] ] ) ? $request_data[ $field['name'] ] : '' ); ?></dd>
							<?php endforeach; ?>
						<?php endif; ?>
					</dl>
					<div class="crpcrm-button-row">
						<a class="crpcrm-button crpcrm-button-inline crpcrm-open-request-form" href="<?php echo esc_url( $portal_urls['new_car_registration'] ); ?>" data-crpcrm-open-form="new_car_registration"><?php echo esc_html( 'ثبت درخواست جدید' ); ?></a>
						<a class="crpcrm-secondary-link" href="<?php echo esc_url( $portal_urls['my_requests'] ); ?>"><?php echo esc_html( 'مشاهده همه درخواست‌ها' ); ?></a>
						<a class="crpcrm-secondary-link" href="<?php echo esc_url( $portal_urls['dashboard'] ); ?>"><?php echo esc_html( 'بازگشت به داشبورد' ); ?></a>
					</div>
				<?php endif; ?>
			</section>

			<?php foreach ( $request_forms as $inline_form ) : ?>
				<?php
				$form                   = $inline_form;
				$is_inline_request_form = true;
				include CRPCRM_PLUGIN_DIR . 'public/views/request-form-panel.php';
				?>
			<?php endforeach; ?>
		<?php else : ?>
			<section class="crpcrm-portal-card crpcrm-dashboard-card">
				<h2><?php echo esc_html( 'سلام ' . $customer_name ); ?></h2>
				<p><?php echo esc_html( 'از این داشبورد می‌توانید درخواست‌های خود را ثبت و پیگیری کنید.' ); ?></p>
				<div class="crpcrm-portal-actions">
					<a class="crpcrm-action-card crpcrm-open-request-form" href="<?php echo esc_url( $portal_urls['new_car_registration'] ); ?>" data-crpcrm-open-form="new_car_registration">
						<span class="crpcrm-action-icon" aria-hidden="true">
							<svg viewBox="0 0 32 32" focusable="false">
								<path d="M3.5 12.5h14v12h-14z" />
								<path d="M17.5 15.5h5l4.5 5v4.5h-3" />
								<path d="M17.5 24.5h-6" />
								<path d="M3.5 24.5h2" />
								<path d="M21.5 18.5v3h5" />
								<circle cx="8.5" cy="24.5" r="3" />
								<circle cx="24.5" cy="24.5" r="3" />
							</svg>
						</span>
						<strong><?php echo esc_html( 'ثبت‌نام خودرو' ); ?></strong>
					</a>
					<a class="crpcrm-action-card crpcrm-open-request-form" href="<?php echo esc_url( $portal_urls['new_parts_request'] ); ?>" data-crpcrm-open-form="new_parts_request">
						<span class="crpcrm-action-icon" aria-hidden="true">
							<svg viewBox="0 0 32 32" focusable="false">
								<circle cx="16" cy="16" r="4" />
								<path d="M16 4.5v4" />
								<path d="M16 23.5v4" />
								<path d="M4.5 16h4" />
								<path d="M23.5 16h4" />
								<path d="M7.9 7.9l2.8 2.8" />
								<path d="M21.3 21.3l2.8 2.8" />
								<path d="M24.1 7.9l-2.8 2.8" />
								<path d="M10.7 21.3l-2.8 2.8" />
							</svg>
						</span>
						<strong><?php echo esc_html( 'درخواست قطعات' ); ?></strong>
					</a>
					<a class="crpcrm-action-card crpcrm-open-request-form" href="<?php echo esc_url( $portal_urls['new_repair_booking'] ); ?>" data-crpcrm-open-form="new_repair_booking">
						<span class="crpcrm-action-icon" aria-hidden="true">
							<svg viewBox="0 0 32 32" focusable="false">
								<path d="M21.5 5.5a6.2 6.2 0 0 0-6.6 8.2L6.2 22.4a2.8 2.8 0 0 0 4 4l8.7-8.7a6.2 6.2 0 0 0 7.6-7.7l-4.1 4.1-3.5-3.5 4.1-4.1a6.1 6.1 0 0 0-1.5-1Z" />
								<path d="M8.2 24.4h.1" />
							</svg>
						</span>
						<strong><?php echo esc_html( 'درخواست تعمیرات' ); ?></strong>
					</a>
				</div>
			</section>

			<?php foreach ( $request_forms as $inline_form ) : ?>
				<?php
				$form                   = $inline_form;
				$is_inline_request_form = true;
				include CRPCRM_PLUGIN_DIR . 'public/views/request-form-panel.php';
				?>
			<?php endforeach; ?>

			<section class="crpcrm-portal-card crpcrm-latest-requests-card">
				<h3><?php echo esc_html( 'آخرین درخواست‌ها' ); ?></h3>
				<?php if ( empty( $latest_requests ) ) : ?>
					<p><?php echo esc_html( 'هنوز درخواستی ثبت نکرده‌اید.' ); ?></p>
				<?php else : ?>
					<div class="crpcrm-latest-requests-list">
						<?php foreach ( $latest_requests as $request ) : ?>
							<a class="crpcrm-latest-request-item" href="<?php echo esc_url( add_query_arg( array( 'crpcrm_page' => 'request_detail', 'request_code' => $request['request_code'] ), $portal_urls['dashboard'] ) ); ?>">
								<strong><?php echo esc_html( $request['request_code'] ); ?></strong>
								<span><?php echo esc_html( CRPCRM_Request_Forms::get_type_label( $request['request_type'] ) ); ?></span>
								<span class="crpcrm-status-badge"><?php echo esc_html( CRPCRM_Request_Forms::get_customer_status_label( $request['status'] ) ); ?></span>
								<time><?php echo esc_html( CRPCRM_Helpers::format_jalali_datetime( $request['created_at'] ) ); ?></time>
							</a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</section>
		<?php endif; ?>
	</main>
</div>

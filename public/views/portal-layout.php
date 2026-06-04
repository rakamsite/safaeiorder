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
						<a class="crpcrm-button crpcrm-button-inline" href="<?php echo esc_url( $portal_urls['new_car_registration'] ); ?>"><?php echo esc_html( 'ثبت‌نام خودرو' ); ?></a>
						<a class="crpcrm-button crpcrm-button-inline" href="<?php echo esc_url( $portal_urls['new_parts_request'] ); ?>"><?php echo esc_html( 'درخواست قطعات' ); ?></a>
						<a class="crpcrm-button crpcrm-button-inline" href="<?php echo esc_url( $portal_urls['new_repair_booking'] ); ?>"><?php echo esc_html( 'درخواست تعمیرات' ); ?></a>
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
										<td><?php echo esc_html( mysql2date( 'Y/m/d H:i', $request['created_at'] ) ); ?></td>
										<td><?php echo esc_html( mysql2date( 'Y/m/d H:i', ! empty( $request['last_activity_at'] ) ? $request['last_activity_at'] : $request['updated_at'] ) ); ?></td>
										<td><a class="crpcrm-table-action" href="<?php echo esc_url( add_query_arg( array( 'crpcrm_page' => 'request_detail', 'request_code' => $request['request_code'] ), $portal_urls['dashboard'] ) ); ?>"><?php echo esc_html( 'مشاهده جزئیات' ); ?></a></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
			</section>
		<?php elseif ( in_array( $current_page, CRPCRM_Request_Forms::get_form_pages(), true ) && $form ) : ?>
			<section class="crpcrm-portal-card crpcrm-request-form-card">
				<h2><?php echo esc_html( $form['title'] ); ?></h2>
				<form class="crpcrm-request-form" method="post" action="<?php echo esc_url( $admin_post_url ); ?>">
					<input type="hidden" name="action" value="crpcrm_submit_request" />
					<input type="hidden" name="crpcrm_request_page" value="<?php echo esc_attr( $form['page'] ); ?>" />
					<input type="hidden" name="crpcrm_redirect_to" value="<?php echo esc_url( $portal_urls[ $form['page'] ] ); ?>" />
					<?php wp_nonce_field( 'crpcrm_submit_request_' . $form['page'], 'crpcrm_request_nonce' ); ?>

					<?php foreach ( $form['fields'] as $field ) : ?>
						<label class="crpcrm-field">
							<span><?php echo esc_html( $field['label'] ); ?></span>
							<?php if ( 'textarea' === $field['type'] ) : ?>
								<textarea name="<?php echo esc_attr( $field['name'] ); ?>" required placeholder="<?php echo esc_attr( isset( $field['placeholder'] ) ? $field['placeholder'] : '' ); ?>"></textarea>
							<?php elseif ( 'select' === $field['type'] ) : ?>
								<select name="<?php echo esc_attr( $field['name'] ); ?>" required>
									<option value=""><?php echo esc_html( 'انتخاب کنید' ); ?></option>
									<?php foreach ( $field['options'] as $option ) : ?>
										<option value="<?php echo esc_attr( $option ); ?>"><?php echo esc_html( $option ); ?></option>
									<?php endforeach; ?>
								</select>
							<?php else : ?>
								<input type="<?php echo esc_attr( $field['type'] ); ?>" name="<?php echo esc_attr( $field['name'] ); ?>" required placeholder="<?php echo esc_attr( isset( $field['placeholder'] ) ? $field['placeholder'] : '' ); ?>" />
							<?php endif; ?>
						</label>
					<?php endforeach; ?>

					<div class="crpcrm-button-row">
						<button class="crpcrm-button" type="submit"><?php echo esc_html( $form['submit_label'] ); ?></button>
						<a class="crpcrm-secondary-link" href="<?php echo esc_url( $portal_urls['dashboard'] ); ?>"><?php echo esc_html( 'بازگشت به داشبورد' ); ?></a>
					</div>
				</form>
			</section>
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
						<div><span><?php echo esc_html( 'تاریخ ثبت' ); ?></span><strong><?php echo esc_html( mysql2date( 'Y/m/d H:i', $request_detail['created_at'] ) ); ?></strong></div>
						<div><span><?php echo esc_html( 'آخرین بروزرسانی' ); ?></span><strong><?php echo esc_html( mysql2date( 'Y/m/d H:i', ! empty( $request_detail['last_activity_at'] ) ? $request_detail['last_activity_at'] : $request_detail['updated_at'] ) ); ?></strong></div>
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
						<a class="crpcrm-button crpcrm-button-inline" href="<?php echo esc_url( $portal_urls['new_car_registration'] ); ?>"><?php echo esc_html( 'ثبت درخواست جدید' ); ?></a>
						<a class="crpcrm-secondary-link" href="<?php echo esc_url( $portal_urls['my_requests'] ); ?>"><?php echo esc_html( 'مشاهده همه درخواست‌ها' ); ?></a>
						<a class="crpcrm-secondary-link" href="<?php echo esc_url( $portal_urls['dashboard'] ); ?>"><?php echo esc_html( 'بازگشت به داشبورد' ); ?></a>
					</div>
				<?php endif; ?>
			</section>
		<?php else : ?>
			<section class="crpcrm-portal-card crpcrm-dashboard-card">
				<h2><?php echo esc_html( 'سلام ' . $customer_name ); ?></h2>
				<p><?php echo esc_html( 'از این داشبورد می‌توانید درخواست‌های خود را ثبت و پیگیری کنید.' ); ?></p>
				<div class="crpcrm-portal-actions">
					<a class="crpcrm-action-card" href="<?php echo esc_url( $portal_urls['new_car_registration'] ); ?>">
						<strong><?php echo esc_html( 'ثبت‌نام خودرو' ); ?></strong>
						<span><?php echo esc_html( 'برای ثبت درخواست مشاوره یا خرید خودرو از این بخش استفاده کنید.' ); ?></span>
					</a>
					<a class="crpcrm-action-card" href="<?php echo esc_url( $portal_urls['new_parts_request'] ); ?>">
						<strong><?php echo esc_html( 'درخواست قطعات' ); ?></strong>
						<span><?php echo esc_html( 'برای ثبت درخواست خرید قطعه موردنیاز خودرو از این بخش استفاده کنید.' ); ?></span>
					</a>
					<a class="crpcrm-action-card" href="<?php echo esc_url( $portal_urls['new_repair_booking'] ); ?>">
						<strong><?php echo esc_html( 'درخواست تعمیرات' ); ?></strong>
						<span><?php echo esc_html( 'برای ثبت درخواست رزرو یا پیگیری خدمات تعمیرات از این بخش استفاده کنید.' ); ?></span>
					</a>
				</div>
			</section>

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
								<time><?php echo esc_html( mysql2date( 'Y/m/d H:i', $request['created_at'] ) ); ?></time>
							</a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</section>
		<?php endif; ?>
	</main>
</div>

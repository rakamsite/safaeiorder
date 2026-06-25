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
$request_forms         = ! empty( $portal_data['request_forms'] ) && is_array( $portal_data['request_forms'] ) ? $portal_data['request_forms'] : CRPCRM_Request_Forms::get_forms();
$default_request_form = $request_forms ? reset( $request_forms ) : null;
$created_value         = isset( $_GET['created'] ) ? sanitize_text_field( wp_unslash( $_GET['created'] ) ) : ( function_exists( 'get_query_var' ) ? sanitize_text_field( (string) get_query_var( 'created', '' ) ) : '' );
$created_notice        = '1' === $created_value;
$form_error            = ! empty( $portal_data['form_error'] ) ? $portal_data['form_error'] : '';
?>
<div class="crpcrm-portal crpcrm-portal-shell <?php echo esc_attr( $portal_theme_class ); ?>" style="<?php echo esc_attr( $portal_theme_style ); ?>" dir="rtl">
	<aside class="crpcrm-portal-sidebar crpcrm-portal-nav" aria-label="<?php echo esc_attr( 'منوی پرتال' ); ?>">
		<div class="crpcrm-portal-sidebar-title crpcrm-portal-header"><?php echo esc_html( 'پرتال درخواست‌ها' ); ?></div>
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
		<?php if ( ! empty( $notice ) && ! ( $created_notice && 'success' === $notice['type'] ) ) : ?>
			<div class="crpcrm-notice crpcrm-notice-<?php echo esc_attr( $notice['type'] ); ?> crpcrm-alert crpcrm-alert-<?php echo esc_attr( $notice['type'] ); ?>"><?php echo esc_html( $notice['message'] ); ?></div>
		<?php endif; ?>

		<?php if ( 'profile' === $current_page ) : ?>
			<?php
			$is_edit            = true;
			$is_embedded        = true;
			$portal_redirect_to = $portal_urls['profile'];
			include CRPCRM_PLUGIN_DIR . 'public/views/profile-form.php';
			?>
		<?php elseif ( 'my_requests' === $current_page ) : ?>
			<section class="crpcrm-portal-card crpcrm-card crpcrm-my-requests-card">
				<div class="crpcrm-card-heading-row">
					<h2><?php echo esc_html( 'درخواست‌های من' ); ?></h2>
					<div class="crpcrm-button-row crpcrm-form-actions">
						<?php foreach ( $request_forms as $request_form ) : ?>
							<a class="crpcrm-button crpcrm-button-primary crpcrm-button-inline crpcrm-open-request-form" href="<?php echo esc_url( add_query_arg( 'form_id', sanitize_key( $request_form['id'] ), $portal_urls['new_request'] ) ); ?>" data-crpcrm-open-form="<?php echo esc_attr( $request_form['page'] ); ?>"><?php echo esc_html( $request_form['title'] ); ?></a>
						<?php endforeach; ?>
					</div>
				</div>

				<?php if ( empty( $my_requests ) ) : ?>
					<p><?php echo esc_html( 'هنوز درخواستی ثبت نکرده‌اید.' ); ?></p>
				<?php else : ?>
					<div class="crpcrm-table-wrap">
						<table class="crpcrm-requests-table crpcrm-customer-requests-table">
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
										<td data-label="<?php echo esc_attr( 'کد پیگیری' ); ?>"><strong><?php echo esc_html( $request['request_code'] ); ?></strong></td>
										<td data-label="<?php echo esc_attr( 'نوع درخواست' ); ?>"><?php echo esc_html( CRPCRM_Request_Forms::get_type_label( $request['request_type'], $request ) ); ?></td>
										<td data-label="<?php echo esc_attr( 'خلاصه درخواست' ); ?>"><?php $request_data = CRPCRM_Request_Repository::get_merged_request_data( $request ); echo esc_html( wp_trim_words( CRPCRM_Request_Forms::build_display_summary( $request['request_type'], $request_data, $request['request_summary'] ), 18, '…' ) ); ?></td>
										<td data-label="<?php echo esc_attr( 'وضعیت' ); ?>"><span class="crpcrm-status-badge"><?php echo esc_html( CRPCRM_Request_Forms::get_customer_status_label( $request['status'] ) ); ?></span></td>
										<td data-label="<?php echo esc_attr( 'تاریخ ثبت' ); ?>"><?php echo esc_html( CRPCRM_Helpers::format_jalali_datetime( $request['created_at'] ) ); ?></td>
										<td data-label="<?php echo esc_attr( 'آخرین بروزرسانی' ); ?>"><?php echo esc_html( CRPCRM_Helpers::format_jalali_datetime( ! empty( $request['last_activity_at'] ) ? $request['last_activity_at'] : $request['updated_at'] ) ); ?></td>
										<td data-label="<?php echo esc_attr( 'عملیات' ); ?>"><a class="crpcrm-table-action" href="<?php echo esc_url( add_query_arg( array( 'crpcrm_page' => 'request_detail', 'request_id' => absint( $request['id'] ), 'request_code' => $request['request_code'] ), $portal_urls['dashboard'] ) ); ?>"><?php echo esc_html( 'مشاهده جزئیات' ); ?></a></td>
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
	<?php elseif ( 'new_request' === $current_page ) : ?>
		<?php if ( $form ) : ?>
			<?php
			$is_inline_request_form = false;
			include CRPCRM_PLUGIN_DIR . 'public/views/request-form-panel.php';
			?>
		<?php else : ?>
			<section class="crpcrm-portal-card crpcrm-card crpcrm-dashboard-card">
				<h2><?php echo esc_html( 'ثبت درخواست' ); ?></h2>
				<?php if ( $form_error ) : ?><div class="crpcrm-notice crpcrm-notice-error crpcrm-alert crpcrm-alert-error"><?php echo esc_html( $form_error ); ?></div><?php endif; ?>
				<?php if ( empty( $request_forms ) ) : ?>
					<p><?php echo esc_html( 'در حال حاضر فرم فعالی برای ثبت درخواست وجود ندارد.' ); ?></p>
				<?php else : ?>
					<p><?php echo esc_html( 'نوع درخواست موردنظر را انتخاب کنید.' ); ?></p>
					<div class="crpcrm-button-row crpcrm-form-actions">
						<?php foreach ( $request_forms as $request_form ) : ?>
							<a class="crpcrm-button crpcrm-button-primary crpcrm-button-inline" href="<?php echo esc_url( add_query_arg( 'form_id', sanitize_key( $request_form['id'] ), $portal_urls['new_request'] ) ); ?>"><?php echo esc_html( $request_form['title'] ); ?></a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</section>
		<?php endif; ?>
	<?php elseif ( in_array( $current_page, CRPCRM_Form_Registry::get_form_pages(), true ) ) : ?>
		<?php if ( $form ) : ?>
			<?php
			$is_inline_request_form = false;
			include CRPCRM_PLUGIN_DIR . 'public/views/request-form-panel.php';
			?>
		<?php else : ?>
			<section class="crpcrm-portal-card crpcrm-card crpcrm-dashboard-card">
				<div class="crpcrm-notice crpcrm-notice-error crpcrm-alert crpcrm-alert-error"><?php echo esc_html( $form_error ); ?></div>
				<a class="crpcrm-secondary-link crpcrm-button crpcrm-button-secondary" href="<?php echo esc_url( $portal_urls['new_request'] ); ?>"><?php echo esc_html( 'بازگشت به ثبت درخواست' ); ?></a>
			</section>
		<?php endif; ?>
	<?php elseif ( 'request_detail' === $current_page ) : ?>
			<section class="crpcrm-portal-card crpcrm-card crpcrm-request-detail-card<?php echo $created_notice ? ' crpcrm-request-detail-card-created' : ''; ?>">
				<?php if ( $created_notice ) : ?>
					<div class="crpcrm-request-success" role="status">
						<span class="crpcrm-request-success-icon" aria-hidden="true">
							<svg viewBox="0 0 24 24" focusable="false">
								<path d="m6.5 12.5 3.3 3.3 7.7-8" />
							</svg>
						</span>
						<span class="crpcrm-request-success-content">
							<strong><?php echo esc_html( CRPCRM_Settings::get( 'request_success_message', 'درخواست شما با موفقیت ثبت شد. کارشناسان ما در اولین فرصت آن را بررسی می‌کنند.' ) ); ?></strong>
							<?php if ( $request_detail ) : ?>
								<span><?php echo esc_html( 'کد پیگیری شما: ' . $request_detail['request_code'] ); ?></span>
							<?php endif; ?>
						</span>
					</div>
				<?php endif; ?>
				<?php if ( ! empty( $portal_data['access_denied'] ) ) : ?>
					<div class="crpcrm-notice crpcrm-notice-error crpcrm-alert crpcrm-alert-error"><?php echo esc_html( 'شما اجازه مشاهده این درخواست را ندارید.' ); ?></div>
				<?php elseif ( $request_detail ) : ?>
					<?php $request_data = CRPCRM_Request_Repository::get_merged_request_data( $request_detail ); ?>
					<?php
					$detail_form      = CRPCRM_Request_Forms::get_form_for_request( $request_detail['request_type'], $request_data );
					$summary_items    = CRPCRM_Dynamic_Form_Renderer::get_summary_items( $detail_form, $request_data );
					$attachment_items = CRPCRM_Dynamic_Form_Renderer::get_uploaded_file_items( $detail_form, $request_data );
					$conversation     = ! empty( $portal_data['request_conversation'] ) && is_array( $portal_data['request_conversation'] ) ? $portal_data['request_conversation'] : array();
					?>
					<h2><?php echo esc_html( 'جزئیات درخواست' ); ?></h2>
					<div class="crpcrm-request-meta-row">
						<div class="crpcrm-request-meta-card"><span class="crpcrm-request-meta-label"><?php echo esc_html( 'کد پیگیری' ); ?></span><strong class="crpcrm-request-meta-value"><?php echo esc_html( $request_detail['request_code'] ); ?></strong></div>
						<div class="crpcrm-request-meta-card"><span class="crpcrm-request-meta-label"><?php echo esc_html( 'نوع درخواست' ); ?></span><strong class="crpcrm-request-meta-value"><?php echo esc_html( CRPCRM_Request_Forms::get_type_label( $request_detail['request_type'], $request_detail ) ); ?></strong></div>
						<div class="crpcrm-request-meta-card"><span class="crpcrm-request-meta-label"><?php echo esc_html( 'وضعیت' ); ?></span><strong class="crpcrm-request-meta-value"><?php echo esc_html( CRPCRM_Request_Forms::get_customer_status_label( $request_detail['status'] ) ); ?></strong></div>
						<div class="crpcrm-request-meta-card"><span class="crpcrm-request-meta-label"><?php echo esc_html( 'تاریخ ثبت' ); ?></span><strong class="crpcrm-request-meta-value"><?php echo esc_html( CRPCRM_Helpers::format_jalali_datetime( $request_detail['created_at'] ) ); ?></strong></div>
						<div class="crpcrm-request-meta-card"><span class="crpcrm-request-meta-label"><?php echo esc_html( 'آخرین بروزرسانی' ); ?></span><strong class="crpcrm-request-meta-value"><?php echo esc_html( CRPCRM_Helpers::format_jalali_datetime( ! empty( $request_detail['last_activity_at'] ) ? $request_detail['last_activity_at'] : $request_detail['updated_at'] ) ); ?></strong></div>
					</div>
					<h3><?php echo esc_html( 'خلاصه درخواست' ); ?></h3>
					<?php if ( ! empty( $summary_items ) ) : ?>
						<dl class="crpcrm-request-summary-list">
							<?php foreach ( $summary_items as $item ) : ?>
								<dt><?php echo esc_html( $item['label'] ); ?></dt>
								<dd><?php echo nl2br( esc_html( $item['value'] ) ); ?></dd>
							<?php endforeach; ?>
						</dl>
					<?php else : ?>
						<p class="crpcrm-request-summary-empty"><?php echo esc_html( 'اطلاعاتی برای نمایش وجود ندارد.' ); ?></p>
					<?php endif; ?>

					<?php if ( ! empty( $attachment_items ) ) : ?>
						<h3><?php echo esc_html( 'فایل‌های پیوست' ); ?></h3>
						<div class="crpcrm-request-attachments">
							<?php foreach ( $attachment_items as $item ) : ?>
								<div class="crpcrm-request-attachment">
									<strong><?php echo esc_html( $item['label'] ); ?></strong>
									<?php
									$item['source_type']  = 'request';
									$item['source_id']    = absint( $request_detail['id'] ?? 0 );
									$item['source_field'] = sanitize_key( $item['key'] ?? $item['field_key'] ?? '' );
									echo CRPCRM_Dynamic_Form_Renderer::render_uploaded_file_value( $item['raw'], $item, 'public' );
									?>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>

					<h3><?php echo esc_html( 'گفت‌وگو' ); ?></h3>
					<?php if ( ! empty( $conversation ) ) : ?>
						<ol class="crpcrm-request-conversation">
							<?php foreach ( $conversation as $message ) : ?>
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
						<p class="crpcrm-request-conversation-empty"><?php echo esc_html( 'هنوز پاسخی ثبت نشده است.' ); ?></p>
					<?php endif; ?>

					<?php if ( is_user_logged_in() && absint( $request_detail['user_id'] ) === get_current_user_id() ) : ?>
						<h3><?php echo esc_html( 'ارسال پاسخ' ); ?></h3>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="crpcrm-request-reply-form">
							<input type="hidden" name="action" value="crpcrm_portal_request_reply">
							<input type="hidden" name="request_id" value="<?php echo esc_attr( absint( $request_detail['id'] ) ); ?>">
							<?php wp_nonce_field( 'crpcrm_portal_request_reply_' . absint( $request_detail['id'] ), 'crpcrm_request_reply_nonce' ); ?>
							<label for="crpcrm-request-reply-message"><?php echo esc_html( 'متن پاسخ' ); ?></label>
							<textarea id="crpcrm-request-reply-message" name="reply_message" rows="5" required></textarea>
							<button type="submit" class="crpcrm-button crpcrm-button-primary"><?php echo esc_html( 'ارسال پاسخ' ); ?></button>
						</form>
					<?php endif; ?>
					<div class="crpcrm-button-row crpcrm-form-actions">
						<?php if ( $default_request_form ) : ?><a class="crpcrm-button crpcrm-button-primary crpcrm-button-inline crpcrm-open-request-form" href="<?php echo esc_url( add_query_arg( 'form_id', sanitize_key( $default_request_form['id'] ), $portal_urls['new_request'] ) ); ?>" data-crpcrm-open-form="<?php echo esc_attr( $default_request_form['page'] ); ?>"><?php echo esc_html( 'ثبت درخواست جدید' ); ?></a><?php endif; ?>
						<a class="crpcrm-secondary-link crpcrm-button crpcrm-button-secondary" href="<?php echo esc_url( $portal_urls['my_requests'] ); ?>"><?php echo esc_html( 'مشاهده همه درخواست‌ها' ); ?></a>
						<a class="crpcrm-secondary-link crpcrm-button crpcrm-button-secondary" href="<?php echo esc_url( $portal_urls['dashboard'] ); ?>"><?php echo esc_html( 'بازگشت به داشبورد' ); ?></a>
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
			<section class="crpcrm-portal-card crpcrm-card crpcrm-dashboard-card">
				<h2><?php echo esc_html( 'سلام ' . $customer_name ); ?></h2>
				<p><?php echo esc_html( 'از این داشبورد می‌توانید درخواست‌های خود را ثبت و پیگیری کنید.' ); ?></p>
				<?php if ( empty( $request_forms ) ) : ?><p><?php echo esc_html( 'در حال حاضر فرم فعالی برای ثبت درخواست وجود ندارد.' ); ?></p><?php endif; ?>
					<div class="crpcrm-portal-actions crpcrm-grid crpcrm-grid-3">
						<?php foreach ( $request_forms as $request_form ) : ?>
							<a class="crpcrm-action-card crpcrm-form-card crpcrm-open-request-form" href="<?php echo esc_url( add_query_arg( 'form_id', sanitize_key( $request_form['id'] ), $portal_urls['new_request'] ) ); ?>" data-crpcrm-open-form="<?php echo esc_attr( $request_form['page'] ); ?>">
							<span class="crpcrm-action-icon" aria-hidden="true"><?php echo CRPCRM_Form_Icon_Pack::get_icon_markup( $request_form['icon'] ?? '' ); ?></span>
							<strong class="crpcrm-form-card-title"><?php echo esc_html( $request_form['title'] ); ?></strong>
							<?php if ( ! empty( $request_form['description'] ) ) : ?><span class="crpcrm-form-card-description"><?php echo esc_html( $request_form['description'] ); ?></span><?php endif; ?>
							<span class="crpcrm-form-card-action"><?php echo esc_html( 'ثبت درخواست' ); ?></span>
						</a>
					<?php endforeach; ?>
				</div>
			</section>

			<?php foreach ( $request_forms as $inline_form ) : ?>
				<?php
				$form                   = $inline_form;
				$is_inline_request_form = true;
				include CRPCRM_PLUGIN_DIR . 'public/views/request-form-panel.php';
				?>
			<?php endforeach; ?>

			<section class="crpcrm-portal-card crpcrm-card crpcrm-latest-requests-card">
				<h3><?php echo esc_html( 'آخرین درخواست‌ها' ); ?></h3>
				<?php if ( empty( $latest_requests ) ) : ?>
					<p><?php echo esc_html( 'هنوز درخواستی ثبت نکرده‌اید.' ); ?></p>
				<?php else : ?>
					<div class="crpcrm-latest-requests-list">
						<?php foreach ( $latest_requests as $request ) : ?>
							<a class="crpcrm-latest-request-item" href="<?php echo esc_url( add_query_arg( array( 'crpcrm_page' => 'request_detail', 'request_id' => absint( $request['id'] ), 'request_code' => $request['request_code'] ), $portal_urls['dashboard'] ) ); ?>">
								<strong><?php echo esc_html( $request['request_code'] ); ?></strong>
								<span><?php echo esc_html( CRPCRM_Request_Forms::get_type_label( $request['request_type'], $request ) ); ?></span>
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

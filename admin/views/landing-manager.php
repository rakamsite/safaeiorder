<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$items       = isset( $list['items'] ) && is_array( $list['items'] ) ? $list['items'] : array();
$total       = isset( $list['total'] ) ? absint( $list['total'] ) : 0;
$total_pages = max( 1, (int) ceil( $total / $per_page ) );
$page        = min( max( 1, absint( $page ) ), $total_pages );
$status      = isset( $status ) ? sanitize_key( $status ) : '';
$form_values  = $landing && is_array( $landing ) ? $landing : array(
	'id'                  => 0,
	'title'               => '',
	'slug'                => '',
	'destination_post_id' => 0,
	'destination_url'     => '',
	'source_label'        => '',
	'source_code'         => '',
	'medium_label'        => '',
	'medium_code'         => '',
	'campaign_label'      => '',
	'campaign_code'       => '',
	'content_label'       => '',
	'content_code'        => '',
	'term_label'          => '',
	'term_code'           => '',
	'append_standard_utm' => 0,
	'status'              => 'active',
	'final_url'           => '',
	'destination_label'   => '',
);
$status_counts = array(
	'active'   => 0,
	'inactive' => 0,
	'archived' => 0,
);
if ( ! isset( $stats_map ) || ! is_array( $stats_map ) ) {
	$stats_map = array();
}
if ( ! isset( $landing_stats ) || ! is_array( $landing_stats ) ) {
	$landing_stats = array();
}
$overview_clicks      = 0;
$overview_conversions = 0;
foreach ( $stats_map as $stats ) {
	$overview_clicks      += absint( $stats['valid_clicks'] ?? 0 );
	$overview_conversions += absint( $stats['conversions'] ?? 0 );
}
foreach ( $items as $item ) {
	$item_status = isset( $item['status'] ) ? sanitize_key( $item['status'] ) : '';
	if ( isset( $status_counts[ $item_status ] ) ) {
		$status_counts[ $item_status ]++;
	}
}
?>
<div class="wrap crpcrm-admin-wrap" dir="rtl">
	<h1><?php echo esc_html( 'مدیریت لندینگ‌ها' ); ?></h1>
	<p class="description"><?php echo esc_html( 'در این بخش می‌توانید برای صفحات مقصد، لینک داخلی کمپین بسازید؛ مثل example.com/product?u=insta' ); ?></p>

	<?php if ( isset( $_GET['landing-saved'] ) ) : ?>
		<div class="notice notice-success"><p><?php echo esc_html( 'لندینگ با موفقیت ذخیره شد.' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['landing-updated'] ) ) : ?>
		<div class="notice notice-success"><p><?php echo esc_html( 'وضعیت لندینگ به‌روزرسانی شد.' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['landing-error'] ) ) : ?>
		<div class="notice notice-error"><p><?php echo esc_html( isset( $_GET['landing-message'] ) ? sanitize_text_field( wp_unslash( $_GET['landing-message'] ) ) : 'عملیات انجام نشد.' ); ?></p></div>
	<?php endif; ?>

	<div class="crpcrm-card crpcrm-landing-summary">
		<h2><?php echo esc_html( 'خلاصه وضعیت' ); ?></h2>
		<p><?php echo esc_html( 'نمایش سریع وضعیت فعلی لندینگ‌ها. آمار تفصیلی در فاز بعدی کامل می‌شود.' ); ?></p>
		<ul class="crpcrm-landing-status-cards">
			<li><strong><?php echo esc_html( number_format_i18n( $total ) ); ?></strong><span><?php echo esc_html( 'کل لندینگ‌ها' ); ?></span></li>
			<li><strong><?php echo esc_html( number_format_i18n( $status_counts['active'] ) ); ?></strong><span><?php echo esc_html( 'فعال' ); ?></span></li>
			<li><strong><?php echo esc_html( number_format_i18n( $status_counts['inactive'] ) ); ?></strong><span><?php echo esc_html( 'غیرفعال' ); ?></span></li>
			<li><strong><?php echo esc_html( number_format_i18n( $status_counts['archived'] ) ); ?></strong><span><?php echo esc_html( 'آرشیو' ); ?></span></li>
			<li><strong><?php echo esc_html( number_format_i18n( $overview_clicks ) ); ?></strong><span><?php echo esc_html( 'کلیک معتبر' ); ?></span></li>
			<li><strong><?php echo esc_html( number_format_i18n( $overview_conversions ) ); ?></strong><span><?php echo esc_html( 'تبدیل' ); ?></span></li>
		</ul>
	</div>

	<div class="crpcrm-card">
		<div class="crpcrm-card-header crpcrm-landing-header">
			<div>
				<h2><?php echo esc_html( 'لندینگ‌های ثبت‌شده' ); ?></h2>
				<p class="description"><?php echo esc_html( 'هر لندینگ یک مقصد داخلی و یک slug برای پارامتر u دارد.' ); ?></p>
			</div>
			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="crpcrm-inline-form">
				<input type="hidden" name="page" value="crpcrm-landings">
				<label>
					<span class="screen-reader-text"><?php echo esc_html( 'فیلتر وضعیت' ); ?></span>
					<select name="status">
						<option value=""><?php echo esc_html( 'همه وضعیت‌ها' ); ?></option>
						<option value="active" <?php selected( $status, 'active' ); ?>><?php echo esc_html( 'فعال' ); ?></option>
						<option value="inactive" <?php selected( $status, 'inactive' ); ?>><?php echo esc_html( 'غیرفعال' ); ?></option>
						<option value="archived" <?php selected( $status, 'archived' ); ?>><?php echo esc_html( 'آرشیو' ); ?></option>
					</select>
				</label>
				<button class="button"><?php echo esc_html( 'فیلتر' ); ?></button>
			</form>
		</div>

		<?php if ( empty( $items ) ) : ?>
			<p><?php echo esc_html( 'هنوز لندینگی ثبت نشده است.' ); ?></p>
		<?php else : ?>
			<div class="crpcrm-table-scroll">
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php echo esc_html( 'عنوان' ); ?></th>
							<th><?php echo esc_html( 'اسلاگ' ); ?></th>
							<th><?php echo esc_html( 'مقصد' ); ?></th>
							<th><?php echo esc_html( 'منبع / کانال / کمپین' ); ?></th>
							<th><?php echo esc_html( 'وضعیت' ); ?></th>
							<th><?php echo esc_html( 'کلیک معتبر' ); ?></th>
							<th><?php echo esc_html( 'تبدیل' ); ?></th>
							<th><?php echo esc_html( 'نرخ تبدیل' ); ?></th>
							<th><?php echo esc_html( 'آخرین کلیک' ); ?></th>
							<th><?php echo esc_html( 'آخرین تبدیل' ); ?></th>
							<th><?php echo esc_html( 'لینک نهایی' ); ?></th>
							<th><?php echo esc_html( 'عملیات' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $items as $item ) : ?>
							<?php
							$final_url = isset( $item['final_url'] ) ? $item['final_url'] : '';
							$status_label = 'active' === ( $item['status'] ?? '' ) ? 'فعال' : ( 'inactive' === ( $item['status'] ?? '' ) ? 'غیرفعال' : 'آرشیو' );
							$destination_label = isset( $item['destination_label'] ) && '' !== $item['destination_label'] ? $item['destination_label'] : ( isset( $item['destination_url'] ) ? $item['destination_url'] : '' );
							$meta_bits = array_filter(
								array(
									( $item['source_label'] ?? '' ) ? $item['source_label'] : '',
									( $item['medium_label'] ?? '' ) ? $item['medium_label'] : '',
									( $item['campaign_label'] ?? '' ) ? $item['campaign_label'] : '',
								)
							);
							$stats = isset( $stats_map[ absint( $item['id'] ) ] ) ? $stats_map[ absint( $item['id'] ) ] : array( 'valid_clicks' => 0, 'conversions' => 0, 'conversion_rate' => null, 'last_click_at' => '', 'last_conversion_at' => '' );
							?>
							<tr>
								<td><strong><?php echo esc_html( $item['title'] ?? '' ); ?></strong></td>
								<td><code><?php echo esc_html( $item['slug'] ?? '' ); ?></code></td>
								<td><?php echo esc_html( $destination_label ); ?></td>
								<td><?php echo esc_html( implode( ' / ', $meta_bits ) ); ?></td>
								<td><?php echo esc_html( $status_label ); ?></td>
								<td><?php echo esc_html( number_format_i18n( absint( $stats['valid_clicks'] ?? 0 ) ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( absint( $stats['conversions'] ?? 0 ) ) ); ?></td>
								<td><?php echo esc_html( null === ( $stats['conversion_rate'] ?? null ) ? '—' : number_format_i18n( (float) $stats['conversion_rate'], 1 ) . '%' ); ?></td>
								<td><?php echo esc_html( ! empty( $stats['last_click_at'] ) ? CRPCRM_Helpers::format_jalali_datetime( $stats['last_click_at'] ) : '—' ); ?></td>
								<td><?php echo esc_html( ! empty( $stats['last_conversion_at'] ) ? CRPCRM_Helpers::format_jalali_datetime( $stats['last_conversion_at'] ) : '—' ); ?></td>
								<td>
									<div class="crpcrm-landing-link-wrap">
										<a href="<?php echo esc_url( $final_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $final_url ); ?></a>
										<button type="button" class="button button-small crpcrm-copy-landing-link" data-copy-url="<?php echo esc_attr( $final_url ); ?>"><?php echo esc_html( 'کپی لینک' ); ?></button>
									</div>
								</td>
								<td>
									<div class="crpcrm-actions">
										<a class="button button-small" href="<?php echo esc_url( add_query_arg( array( 'page' => 'crpcrm-landings', 'landing_id' => absint( $item['id'] ) ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( 'ویرایش' ); ?></a>

										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="crpcrm-inline-form">
											<input type="hidden" name="action" value="crpcrm_landing_action">
											<input type="hidden" name="landing_id" value="<?php echo esc_attr( absint( $item['id'] ) ); ?>">
											<input type="hidden" name="landing_action_type" value="toggle">
											<?php wp_nonce_field( 'crpcrm_landing_action', 'crpcrm_landing_action_nonce' ); ?>
											<button type="submit" class="button button-small"><?php echo esc_html( 'تغییر وضعیت' ); ?></button>
										</form>

										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="crpcrm-inline-form" onsubmit="return confirm('<?php echo esc_attr( 'این لندینگ آرشیو شود؟' ); ?>');">
											<input type="hidden" name="action" value="crpcrm_landing_action">
											<input type="hidden" name="landing_id" value="<?php echo esc_attr( absint( $item['id'] ) ); ?>">
											<input type="hidden" name="landing_action_type" value="archive">
											<?php wp_nonce_field( 'crpcrm_landing_action', 'crpcrm_landing_action_nonce' ); ?>
											<button type="submit" class="button button-secondary button-small"><?php echo esc_html( 'آرشیو' ); ?></button>
										</form>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<?php if ( $total_pages > 1 ) : ?>
				<div class="tablenav bottom">
					<div class="tablenav-pages">
						<span class="displaying-num"><?php echo esc_html( sprintf( '%s مورد', number_format_i18n( $total ) ) ); ?></span>
						<span class="pagination-links">
							<?php if ( $page > 1 ) : ?>
								<a class="button" href="<?php echo esc_url( add_query_arg( array( 'page' => 'crpcrm-landings', 'paged' => 1, 'status' => $status ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( 'اول' ); ?></a>
								<a class="button" href="<?php echo esc_url( add_query_arg( array( 'page' => 'crpcrm-landings', 'paged' => $page - 1, 'status' => $status ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( 'قبلی' ); ?></a>
							<?php endif; ?>
							<span class="paging-input"><?php echo esc_html( number_format_i18n( $page ) . ' از ' . number_format_i18n( $total_pages ) ); ?></span>
							<?php if ( $page < $total_pages ) : ?>
								<a class="button" href="<?php echo esc_url( add_query_arg( array( 'page' => 'crpcrm-landings', 'paged' => $page + 1, 'status' => $status ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( 'بعدی' ); ?></a>
								<a class="button" href="<?php echo esc_url( add_query_arg( array( 'page' => 'crpcrm-landings', 'paged' => $total_pages, 'status' => $status ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( 'آخر' ); ?></a>
							<?php endif; ?>
						</span>
					</div>
				</div>
			<?php endif; ?>
	<?php endif; ?>
</div>

<?php if ( $landing ) : ?>
	<div class="crpcrm-card crpcrm-landing-stats-card">
		<h2><?php echo esc_html( 'آمار همین لندینگ' ); ?></h2>
		<div class="crpcrm-landing-stats">
			<div><strong><?php echo esc_html( number_format_i18n( absint( $landing_stats['valid_clicks'] ?? 0 ) ) ); ?></strong><span><?php echo esc_html( 'کلیک معتبر' ); ?></span></div>
			<div><strong><?php echo esc_html( number_format_i18n( absint( $landing_stats['conversions'] ?? 0 ) ) ); ?></strong><span><?php echo esc_html( 'تبدیل' ); ?></span></div>
			<div><strong><?php echo esc_html( null === ( $landing_stats['conversion_rate'] ?? null ) ? '—' : number_format_i18n( (float) $landing_stats['conversion_rate'], 1 ) . '%' ); ?></strong><span><?php echo esc_html( 'نرخ تبدیل' ); ?></span></div>
			<div><strong><?php echo esc_html( ! empty( $landing_stats['last_click_at'] ) ? CRPCRM_Helpers::format_jalali_datetime( $landing_stats['last_click_at'] ) : '—' ); ?></strong><span><?php echo esc_html( 'آخرین کلیک' ); ?></span></div>
			<div><strong><?php echo esc_html( ! empty( $landing_stats['last_conversion_at'] ) ? CRPCRM_Helpers::format_jalali_datetime( $landing_stats['last_conversion_at'] ) : '—' ); ?></strong><span><?php echo esc_html( 'آخرین تبدیل' ); ?></span></div>
			<div><strong><?php echo esc_html( ! empty( $landing['final_url'] ) ? $landing['final_url'] : '—' ); ?></strong><span><?php echo esc_html( 'لینک نهایی قابل کپی' ); ?></span></div>
		</div>
	</div>
<?php endif; ?>

	<div class="crpcrm-card">
		<h2><?php echo esc_html( $landing_id ? 'ویرایش لندینگ' : 'ساخت لندینگ جدید' ); ?></h2>
		<p class="description"><?php echo esc_html( 'انتخاب مقصد فقط از میان پست، برگه یا محصول داخلی انجام می‌شود. URL دستی در این فاز فعال نیست.' ); ?></p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="crpcrm-landing-form">
			<input type="hidden" name="action" value="crpcrm_save_landing_link">
			<input type="hidden" name="landing_id" value="<?php echo esc_attr( absint( $landing_id ) ); ?>">
			<input type="hidden" name="crpcrm_landing[destination_post_id]" value="<?php echo esc_attr( absint( $form_values['destination_post_id'] ) ); ?>" data-destination-id-input>
			<?php wp_nonce_field( 'crpcrm_save_landing_link', 'crpcrm_landing_nonce' ); ?>

			<table class="form-table">
				<tbody>
					<tr>
						<th><label for="crpcrm-landing-title"><?php echo esc_html( 'عنوان لندینگ' ); ?></label></th>
						<td><input id="crpcrm-landing-title" class="regular-text" name="crpcrm_landing[title]" required value="<?php echo esc_attr( $form_values['title'] ); ?>"></td>
					</tr>
					<tr>
						<th><label for="crpcrm-landing-slug"><?php echo esc_html( 'اسلاگ لینک' ); ?></label></th>
						<td>
							<input id="crpcrm-landing-slug" class="regular-text" name="crpcrm_landing[slug]" required pattern="[a-z0-9_-]+" value="<?php echo esc_attr( $form_values['slug'] ); ?>">
							<p class="description"><?php echo esc_html( 'فقط حروف لاتین کوچک، عدد، خط تیره و underscore مجاز است.' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php echo esc_html( 'صفحه مقصد' ); ?></th>
						<td>
							<input type="search" class="regular-text" data-destination-search placeholder="<?php echo esc_attr( 'جستجوی صفحه، برگه یا محصول' ); ?>">
							<div class="crpcrm-landing-search-results" data-destination-results></div>
							<div class="crpcrm-landing-selected-destination" data-selected-destination>
								<?php if ( ! empty( $form_values['destination_label'] ) ) : ?>
									<strong><?php echo esc_html( $form_values['destination_label'] ); ?></strong>
									<span><?php echo esc_html( $form_values['destination_url'] ); ?></span>
								<?php else : ?>
									<span><?php echo esc_html( 'هنوز مقصدی انتخاب نشده است.' ); ?></span>
								<?php endif; ?>
							</div>
						</td>
					</tr>
					<tr>
						<th><?php echo esc_html( 'وضعیت' ); ?></th>
						<td>
							<select name="crpcrm_landing[status]">
								<option value="active" <?php selected( $form_values['status'], 'active' ); ?>><?php echo esc_html( 'فعال' ); ?></option>
								<option value="inactive" <?php selected( $form_values['status'], 'inactive' ); ?>><?php echo esc_html( 'غیرفعال' ); ?></option>
								<option value="archived" <?php selected( $form_values['status'], 'archived' ); ?>><?php echo esc_html( 'آرشیو' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th><?php echo esc_html( 'منبع ورود' ); ?></th>
						<td><input class="regular-text" name="crpcrm_landing[source_label]" value="<?php echo esc_attr( $form_values['source_label'] ); ?>" placeholder="<?php echo esc_attr( 'اینستاگرام' ); ?>"></td>
					</tr>
					<tr>
						<th><?php echo esc_html( 'کد منبع' ); ?></th>
						<td><input class="regular-text" name="crpcrm_landing[source_code]" required pattern="[a-z0-9_-]+" value="<?php echo esc_attr( $form_values['source_code'] ); ?>" placeholder="instagram"></td>
					</tr>
					<tr>
						<th><?php echo esc_html( 'کانال / رسانه' ); ?></th>
						<td><input class="regular-text" name="crpcrm_landing[medium_label]" value="<?php echo esc_attr( $form_values['medium_label'] ); ?>" placeholder="<?php echo esc_attr( 'شبکه اجتماعی' ); ?>"></td>
					</tr>
					<tr>
						<th><?php echo esc_html( 'کد کانال' ); ?></th>
						<td><input class="regular-text" name="crpcrm_landing[medium_code]" required pattern="[a-z0-9_-]+" value="<?php echo esc_attr( $form_values['medium_code'] ); ?>" placeholder="social"></td>
					</tr>
					<tr>
						<th><?php echo esc_html( 'نام کمپین' ); ?></th>
						<td><input class="regular-text" name="crpcrm_landing[campaign_label]" value="<?php echo esc_attr( $form_values['campaign_label'] ); ?>" placeholder="<?php echo esc_attr( 'جشنواره تابستان' ); ?>"></td>
					</tr>
					<tr>
						<th><?php echo esc_html( 'کد کمپین' ); ?></th>
						<td><input class="regular-text" name="crpcrm_landing[campaign_code]" required pattern="[a-z0-9_-]+" value="<?php echo esc_attr( $form_values['campaign_code'] ); ?>" placeholder="summer_sale"></td>
					</tr>
					<tr>
						<th><?php echo esc_html( 'محتوا / جایگاه تبلیغ' ); ?></th>
						<td><input class="regular-text" name="crpcrm_landing[content_label]" value="<?php echo esc_attr( $form_values['content_label'] ); ?>" placeholder="<?php echo esc_attr( 'استوری اینستاگرام' ); ?>"></td>
					</tr>
					<tr>
						<th><?php echo esc_html( 'کد محتوا' ); ?></th>
						<td><input class="regular-text" name="crpcrm_landing[content_code]" pattern="[a-z0-9_-]+" value="<?php echo esc_attr( $form_values['content_code'] ); ?>" placeholder="instagram_story"></td>
					</tr>
					<tr>
						<th><?php echo esc_html( 'کلمه کلیدی' ); ?></th>
						<td><input class="regular-text" name="crpcrm_landing[term_label]" value="<?php echo esc_attr( $form_values['term_label'] ); ?>"></td>
					</tr>
					<tr>
						<th><?php echo esc_html( 'کد کلمه کلیدی' ); ?></th>
						<td><input class="regular-text" name="crpcrm_landing[term_code]" pattern="[a-z0-9_-]+" value="<?php echo esc_attr( $form_values['term_code'] ); ?>"></td>
					</tr>
					<tr>
						<th><?php echo esc_html( 'افزودن UTM استاندارد' ); ?></th>
						<td><label><input type="hidden" name="crpcrm_landing[append_standard_utm]" value="0"><input type="checkbox" name="crpcrm_landing[append_standard_utm]" value="1" <?php checked( ! empty( $form_values['append_standard_utm'] ) ); ?>> <?php echo esc_html( 'به لینک مقصد UTM استاندارد اضافه شود' ); ?></label></td>
					</tr>
				</tbody>
			</table>

			<?php if ( ! empty( $form_values['final_url'] ) ) : ?>
				<div class="crpcrm-landing-preview">
					<strong><?php echo esc_html( 'لینک نهایی' ); ?></strong>
					<div class="crpcrm-landing-preview-url">
						<code data-landing-final-url><?php echo esc_html( $form_values['final_url'] ); ?></code>
						<button type="button" class="button button-small crpcrm-copy-landing-link" data-copy-url="<?php echo esc_attr( $form_values['final_url'] ); ?>"><?php echo esc_html( 'کپی لینک' ); ?></button>
					</div>
				</div>
			<?php endif; ?>

			<?php submit_button( $landing_id ? 'به‌روزرسانی لندینگ' : 'ساخت لندینگ جدید' ); ?>
		</form>
	</div>
</div>

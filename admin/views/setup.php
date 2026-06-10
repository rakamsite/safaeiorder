<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap crpcrm-admin-wrap" dir="rtl">
	<h1><?php echo esc_html( 'راه‌اندازی اولیه پرتال و CRM' ); ?></h1>
	<p><?php echo esc_html( 'پروفایل کسب‌وکار را انتخاب کنید. این انتخاب پس از ذخیره برای همیشه قفل می‌شود.' ); ?></p>

	<?php if ( isset( $_GET['setup-error'] ) ) : ?>
		<div class="notice notice-error"><p><?php echo esc_html( 'پروفایل انتخاب‌شده معتبر نیست.' ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="crpcrm_complete_setup" />
		<?php wp_nonce_field( 'crpcrm_complete_setup', 'crpcrm_setup_nonce' ); ?>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th><label for="business_profile_id"><?php echo esc_html( 'پروفایل کسب‌وکار' ); ?></label></th>
					<td>
						<select name="business_profile_id" id="business_profile_id" required>
							<option value=""><?php echo esc_html( '— انتخاب کنید —' ); ?></option>
							<?php foreach ( $business_profiles as $profile_id => $profile ) : ?>
								<option value="<?php echo esc_attr( $profile_id ); ?>"><?php echo esc_html( $profile->get_label() ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			</tbody>
		</table>
		<?php submit_button( 'تکمیل راه‌اندازی و قفل پروفایل' ); ?>
	</form>
</div>

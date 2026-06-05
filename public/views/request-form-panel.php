<?php
/**
 * Request form panel used by the customer portal.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$form                   = isset( $form ) && is_array( $form ) ? $form : null;
$is_inline_request_form = ! empty( $is_inline_request_form );
$request_form_classes   = 'crpcrm-portal-card crpcrm-request-form-card';

if ( $is_inline_request_form ) {
	$request_form_classes .= ' crpcrm-inline-request-form-card';
}
?>
<?php if ( $form ) : ?>
	<section
		class="<?php echo esc_attr( $request_form_classes ); ?>"
		id="crpcrm-request-form-<?php echo esc_attr( $form['page'] ); ?>"
		data-crpcrm-request-form="<?php echo esc_attr( $form['page'] ); ?>"
		<?php echo $is_inline_request_form ? 'hidden' : ''; ?>
	>
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
<?php endif; ?>

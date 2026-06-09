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
$request_form_classes   = 'crpcrm-portal-card crpcrm-card crpcrm-request-form-card';
$form_id                = $form && ! empty( $form['id'] ) ? sanitize_key( $form['id'] ) : '';
$form_url               = $form_id ? add_query_arg( 'form_id', $form_id, $portal_urls['new_request'] ) : $portal_urls['new_request'];

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
		<header class="crpcrm-card-header">
			<h2><?php echo esc_html( $form['title'] ); ?></h2>
			<?php if ( ! empty( $form['description'] ) ) : ?><p><?php echo esc_html( $form['description'] ); ?></p><?php endif; ?>
		</header>
		<div class="crpcrm-card-body">
			<form class="crpcrm-request-form crpcrm-form" method="post" action="<?php echo esc_url( $admin_post_url ); ?>">
				<input type="hidden" name="action" value="crpcrm_submit_request" />
				<input type="hidden" name="crpcrm_request_page" value="<?php echo esc_attr( $form['page'] ); ?>" />
				<input type="hidden" name="crpcrm_form_id" value="<?php echo esc_attr( $form_id ); ?>" />
				<input type="hidden" name="crpcrm_redirect_to" value="<?php echo esc_url( $form_url ); ?>" />
				<?php wp_nonce_field( 'crpcrm_submit_request_' . $form_id, 'crpcrm_request_nonce' ); ?>

				<?php foreach ( $form['fields'] as $field ) : ?>
					<?php
					$field_type    = isset( $field['type'] ) ? sanitize_key( $field['type'] ) : 'text';
					$field_name    = isset( $field['name'] ) ? sanitize_key( $field['name'] ) : '';
					$field_options = CRPCRM_Request_Forms::get_field_options( $field );
					$is_required   = ! empty( $field['required'] );
					$default_value = isset( $field['default'] ) ? $field['default'] : '';
					$input_type    = in_array( $field_type, array( 'email', 'number', 'date' ), true ) ? $field_type : ( 'mobile' === $field_type ? 'tel' : 'text' );
					$field_id      = 'crpcrm-field-' . $form_id . '-' . $field_name;
					?>
					<div class="crpcrm-field crpcrm-field-<?php echo esc_attr( sanitize_html_class( $field_type, 'text' ) ); ?>">
						<label class="crpcrm-field-label" for="<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
						<div class="crpcrm-field-control">
							<?php if ( 'textarea' === $field_type ) : ?>
								<textarea class="crpcrm-textarea" id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $field_name ); ?>" <?php echo $is_required ? 'required' : ''; ?> placeholder="<?php echo esc_attr( isset( $field['placeholder'] ) ? $field['placeholder'] : '' ); ?>"><?php echo esc_textarea( $default_value ); ?></textarea>
							<?php elseif ( 'select' === $field_type ) : ?>
								<select class="crpcrm-select" id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $field_name ); ?>" <?php echo $is_required ? 'required' : ''; ?>>
									<option value=""><?php echo esc_html( 'انتخاب کنید' ); ?></option>
									<?php foreach ( $field_options as $option_value => $option_label ) : ?>
										<option value="<?php echo esc_attr( $option_value ); ?>" <?php selected( (string) $default_value, (string) $option_value ); ?>><?php echo esc_html( $option_label ); ?></option>
									<?php endforeach; ?>
								</select>
							<?php elseif ( 'radio' === $field_type ) : ?>
								<?php foreach ( $field_options as $option_value => $option_label ) : ?><label class="crpcrm-radio"><input type="radio" name="<?php echo esc_attr( $field_name ); ?>" value="<?php echo esc_attr( $option_value ); ?>" <?php checked( (string) $default_value, (string) $option_value ); ?> <?php echo $is_required ? 'required' : ''; ?> /> <span><?php echo esc_html( $option_label ); ?></span></label><?php endforeach; ?>
							<?php elseif ( 'checkbox' === $field_type ) : ?>
								<?php if ( $field_options ) : ?><?php foreach ( $field_options as $option_value => $option_label ) : ?><label class="crpcrm-checkbox"><input type="checkbox" name="<?php echo esc_attr( $field_name ); ?>[]" value="<?php echo esc_attr( $option_value ); ?>" /> <span><?php echo esc_html( $option_label ); ?></span></label><?php endforeach; ?><?php else : ?><label class="crpcrm-checkbox"><input id="<?php echo esc_attr( $field_id ); ?>" type="checkbox" name="<?php echo esc_attr( $field_name ); ?>" value="1" <?php checked( ! empty( $default_value ) ); ?> <?php echo $is_required ? 'required' : ''; ?> /> <span><?php echo esc_html( $field['label'] ); ?></span></label><?php endif; ?>
							<?php else : ?>
								<input class="crpcrm-input" id="<?php echo esc_attr( $field_id ); ?>" type="<?php echo esc_attr( $input_type ); ?>" name="<?php echo esc_attr( $field_name ); ?>" value="<?php echo esc_attr( $default_value ); ?>" <?php echo $is_required ? 'required' : ''; ?> placeholder="<?php echo esc_attr( isset( $field['placeholder'] ) ? $field['placeholder'] : '' ); ?>" />
							<?php endif; ?>
						</div>
						<?php if ( ! empty( $field['help'] ) ) : ?><div class="crpcrm-help-text"><?php echo esc_html( $field['help'] ); ?></div><?php endif; ?>
					</div>
				<?php endforeach; ?>

				<div class="crpcrm-button-row crpcrm-form-actions">
					<button class="crpcrm-button crpcrm-button-primary" type="submit"><?php echo esc_html( $form['submit_label'] ); ?></button>
				</div>
			</form>
		</div>
	</section>
<?php endif; ?>

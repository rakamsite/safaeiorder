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
			<form class="crpcrm-request-form crpcrm-form" method="post" action="<?php echo esc_url( $admin_post_url ); ?>" enctype="multipart/form-data">
				<input type="hidden" name="action" value="crpcrm_submit_request" />
				<input type="hidden" name="crpcrm_request_page" value="<?php echo esc_attr( $form['page'] ); ?>" />
				<input type="hidden" name="crpcrm_form_id" value="<?php echo esc_attr( $form_id ); ?>" />
				<input type="hidden" name="crpcrm_redirect_to" value="<?php echo esc_url( $form_url ); ?>" />
				<?php wp_nonce_field( 'crpcrm_submit_request_' . $form_id, 'crpcrm_request_nonce' ); ?>

				<div class="crpcrm-request-form-grid">
					<?php CRPCRM_Dynamic_Form_Renderer::render_fields( $form, 'public' ); ?>
				</div>

				<div class="crpcrm-button-row crpcrm-form-actions">
					<button class="crpcrm-button crpcrm-button-primary" type="submit"><?php echo esc_html( $form['submit_label'] ); ?></button>
				</div>
			</form>
		</div>
	</section>
<?php endif; ?>

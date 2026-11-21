<?php
/**
 * [atp_quote_form] Quote request form and submission handler.
 *
 * Three entry routes feed one enquiry: the quote list, a written requirement
 * and uploaded purchase lists. The enquiry is saved before success is shown.
 *
 * @package atp-quote
 */

defined( 'ABSPATH' ) || exit;

/**
 * Fields kept when a submission fails, so the buyer does not retype them.
 */
function atp_form_text_fields() {
	return array( 'name', 'org', 'phone', 'email', 'contact_pref', 'district', 'delivery_date', 'deadline', 'budget', 'free_text', 'specs' );
}

/**
 * Signature tying a success link to its enquiry.
 *
 * @param string $ref Reference.
 */
function atp_ref_sig( $ref ) {
	return substr( hash_hmac( 'sha256', $ref, wp_salt( 'nonce' ) ), 0, 20 );
}

add_shortcode( 'atp_quote_form', 'atp_render_quote_form' );

/**
 * Render the form, or the success state.
 */
function atp_render_quote_form() {
	nocache_headers();
	// phpcs:disable WordPress.Security.NonceVerification
	if ( isset( $_GET['atp_ref'], $_GET['k'] ) ) {
		$ref = sanitize_text_field( wp_unslash( $_GET['atp_ref'] ) );
		if ( hash_equals( atp_ref_sig( $ref ), sanitize_text_field( wp_unslash( $_GET['k'] ) ) ) ) {
			$id = atp_find_enquiry( '_atp_reference', $ref );
			if ( $id ) {
				return atp_render_success( $id );
			}
		}
	}
	$state  = array( 'values' => array(), 'errors' => array() );
	$token  = isset( $_GET['atp_form'] ) ? sanitize_key( $_GET['atp_form'] ) : '';
	if ( $token ) {
		$saved = get_transient( 'atp_form_' . $token );
		if ( is_array( $saved ) ) {
			$state = $saved;
		} elseif ( 'expired' === $token ) {
			$state['errors'][] = __( 'Your session expired before the request was sent. Please check your details and submit again.', 'atp-quote' );
		}
	}
	$mode = isset( $_GET['mode'] ) ? sanitize_key( $_GET['mode'] ) : '';
	// phpcs:enable
	$v     = fn( $k ) => isset( $state['values'][ $k ] ) ? $state['values'][ $k ] : '';
	$items = atp_list_get();
	$units = atp_units();
	$privacy = atp_setting( 'privacy_url' ) ? atp_setting( 'privacy_url' ) : home_url( '/privacy/' );

	ob_start();
	?>
	<div class="atp-quote" id="atp-quote-form" data-mode="<?php echo esc_attr( $mode ); ?>">
		<p class="atp-quote__intro"><?php esc_html_e( 'Add your items or upload a purchase list. Share your contact details and delivery district so we can review the requirement and contact you about the next steps.', 'atp-quote' ); ?></p>

		<?php if ( $state['errors'] ) : ?>
			<div class="atp-alert atp-alert--error" role="alert" tabindex="-1" id="atp-errors">
				<p><strong><?php esc_html_e( 'Your request has not been sent yet. Please check the following:', 'atp-quote' ); ?></strong></p>
				<ul>
					<?php foreach ( $state['errors'] as $err ) : ?>
						<li><?php echo esc_html( $err ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<nav class="atp-routes" aria-label="<?php esc_attr_e( 'Ways to send your requirement', 'atp-quote' ); ?>">
			<a href="#atp-route-list" class="atp-route<?php echo ( '' === $mode || 'list' === $mode ) ? ' is-active' : ''; ?>"><?php esc_html_e( 'My Quote List', 'atp-quote' ); ?> <span class="atp-count" data-atp-count><?php echo count( $items ); ?></span></a>
			<a href="#atp-route-type" class="atp-route<?php echo 'type' === $mode ? ' is-active' : ''; ?>"><?php esc_html_e( 'Type My Requirements', 'atp-quote' ); ?></a>
			<a href="#atp-route-upload" class="atp-route<?php echo 'upload' === $mode ? ' is-active' : ''; ?>"><?php esc_html_e( 'Upload Purchase List', 'atp-quote' ); ?></a>
		</nav>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" novalidate class="atp-form">
			<input type="hidden" name="action" value="atp_submit_quote">
			<input type="hidden" name="atp_token" value="<?php echo esc_attr( strtolower( wp_generate_password( 24, false, false ) ) ); ?>">
			<input type="hidden" name="atp_return" value="<?php echo esc_url( get_permalink() ); ?>">
			<input type="hidden" name="atp_landing" value="" data-atp-landing>
			<?php wp_nonce_field( 'atp_submit_quote', 'atp_nonce' ); ?>
			<div class="atp-hp" aria-hidden="true"><label for="atp_website">Website</label><input type="text" id="atp_website" name="atp_website" tabindex="-1" autocomplete="off"></div>

			<fieldset class="atp-section" id="atp-route-list">
				<legend><?php esc_html_e( 'My Quote List', 'atp-quote' ); ?></legend>
				<div data-atp-list>
				<?php if ( $items ) : ?>
					<table class="atp-list">
						<thead><tr><th><?php esc_html_e( 'Product', 'atp-quote' ); ?></th><th><?php esc_html_e( 'Quantity', 'atp-quote' ); ?></th><th><?php esc_html_e( 'Notes', 'atp-quote' ); ?></th><th><span class="screen-reader-text"><?php esc_html_e( 'Remove', 'atp-quote' ); ?></span></th></tr></thead>
						<tbody>
						<?php foreach ( $items as $key => $it ) : ?>
							<tr data-key="<?php echo esc_attr( $key ); ?>">
								<td>
									<a href="<?php echo esc_url( get_permalink( $it['product_id'] ) ); ?>"><?php echo esc_html( $it['name'] ); ?></a>
									<?php foreach ( (array) $it['options'] as $ok => $ov ) : ?>
										<br><small><?php echo esc_html( $ok . ': ' . $ov ); ?></small>
									<?php endforeach; ?>
									<?php if ( $it['pack'] ) : ?><br><small><?php echo esc_html( $it['pack'] ); ?></small><?php endif; ?>
								</td>
								<td class="atp-list__qty">
									<label class="screen-reader-text" for="atp-q-<?php echo esc_attr( $key ); ?>"><?php esc_html_e( 'Quantity', 'atp-quote' ); ?></label>
									<input id="atp-q-<?php echo esc_attr( $key ); ?>" type="number" min="<?php echo in_array( $it['unit'], atp_decimal_units(), true ) ? '0.01' : '1'; ?>" step="<?php echo in_array( $it['unit'], atp_decimal_units(), true ) ? '0.01' : '1'; ?>" value="<?php echo esc_attr( $it['qty'] ); ?>" data-atp-qty inputmode="decimal">
									<span><?php echo esc_html( $units[ $it['unit'] ] ?? $it['unit'] ); ?></span>
								</td>
								<td>
									<label class="screen-reader-text" for="atp-n-<?php echo esc_attr( $key ); ?>"><?php esc_html_e( 'Notes', 'atp-quote' ); ?></label>
									<textarea id="atp-n-<?php echo esc_attr( $key ); ?>" rows="2" data-atp-notes><?php echo esc_textarea( $it['notes'] ); ?></textarea>
								</td>
								<td><button type="button" class="atp-link-btn" data-atp-remove><?php esc_html_e( 'Remove', 'atp-quote' ); ?></button></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<p class="atp-empty"><?php esc_html_e( 'Your quote list is empty. Browse products or upload a purchase list to get started.', 'atp-quote' ); ?> <a href="<?php echo esc_url( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/products/' ) ); ?>"><?php esc_html_e( 'Browse Products', 'atp-quote' ); ?></a></p>
				<?php endif; ?>
				</div>
			</fieldset>

			<fieldset class="atp-section" id="atp-route-type">
				<legend><?php esc_html_e( 'Type My Requirements', 'atp-quote' ); ?></legend>
				<label for="atp_free_text"><?php esc_html_e( 'Items not in the catalogue, or your full requirement', 'atp-quote' ); ?></label>
				<p class="atp-help" id="atp_free_text_help"><?php esc_html_e( 'One item per line with product, specification, quantity and unit. For example: Exercise book, A5, 120 pages, 70 GSM, 500 pieces.', 'atp-quote' ); ?></p>
				<textarea id="atp_free_text" name="atp_free_text" rows="6" aria-describedby="atp_free_text_help"><?php echo esc_textarea( $v( 'free_text' ) ); ?></textarea>
			</fieldset>

			<fieldset class="atp-section" id="atp-route-upload">
				<legend><?php esc_html_e( 'Upload Purchase List', 'atp-quote' ); ?></legend>
				<label for="atp_files"><?php esc_html_e( 'Attach your list, specification sheet or reference images', 'atp-quote' ); ?></label>
				<p class="atp-help" id="atp_files_help"><?php esc_html_e( 'PDF, XLSX, DOCX, JPG or PNG. Up to 5 files, 10 MB each.', 'atp-quote' ); ?>
				<?php if ( $state['errors'] ) : ?> <strong><?php esc_html_e( 'Please attach your files again.', 'atp-quote' ); ?></strong><?php endif; ?></p>
				<input type="file" id="atp_files" name="atp_files[]" multiple accept=".pdf,.xlsx,.docx,.jpg,.jpeg,.png" aria-describedby="atp_files_help" data-atp-files>
				<ul class="atp-file-list" data-atp-file-list aria-live="polite"></ul>
			</fieldset>

			<fieldset class="atp-section">
				<legend><?php esc_html_e( 'Your details', 'atp-quote' ); ?></legend>
				<div class="atp-grid">
					<p><label for="atp_name"><?php esc_html_e( 'Full name', 'atp-quote' ); ?> <span class="atp-req"><?php esc_html_e( '(required)', 'atp-quote' ); ?></span></label>
					<input type="text" id="atp_name" name="atp_name" required autocomplete="name" value="<?php echo esc_attr( $v( 'name' ) ); ?>"></p>
					<p><label for="atp_org"><?php esc_html_e( 'Organisation', 'atp-quote' ); ?></label>
					<input type="text" id="atp_org" name="atp_org" autocomplete="organization" placeholder="<?php esc_attr_e( 'Company, institution or project name', 'atp-quote' ); ?>" value="<?php echo esc_attr( $v( 'org' ) ); ?>"></p>
					<p><label for="atp_phone"><?php esc_html_e( 'Phone', 'atp-quote' ); ?></label>
					<input type="tel" id="atp_phone" name="atp_phone" autocomplete="tel" aria-describedby="atp_contact_help" value="<?php echo esc_attr( $v( 'phone' ) ); ?>"></p>
					<p><label for="atp_email"><?php esc_html_e( 'Email', 'atp-quote' ); ?></label>
					<input type="email" id="atp_email" name="atp_email" autocomplete="email" aria-describedby="atp_contact_help" value="<?php echo esc_attr( $v( 'email' ) ); ?>"></p>
				</div>
				<p class="atp-help" id="atp_contact_help"><?php esc_html_e( 'Enter a phone number or email so we can contact you.', 'atp-quote' ); ?></p>
				<p><label for="atp_contact_pref"><?php esc_html_e( 'Preferred contact method', 'atp-quote' ); ?></label>
				<select id="atp_contact_pref" name="atp_contact_pref">
					<option value=""><?php esc_html_e( 'No preference', 'atp-quote' ); ?></option>
					<?php foreach ( array( 'Phone call', 'WhatsApp', 'Email' ) as $opt ) : ?>
						<option <?php selected( $v( 'contact_pref' ), $opt ); ?>><?php echo esc_html( $opt ); ?></option>
					<?php endforeach; ?>
				</select></p>
			</fieldset>

			<fieldset class="atp-section">
				<legend><?php esc_html_e( 'Delivery and timing', 'atp-quote' ); ?></legend>
				<div class="atp-grid">
					<p><label for="atp_district"><?php esc_html_e( 'Delivery district / city', 'atp-quote' ); ?> <span class="atp-req"><?php esc_html_e( '(required)', 'atp-quote' ); ?></span></label>
					<input type="text" id="atp_district" name="atp_district" required list="atp-districts" value="<?php echo esc_attr( $v( 'district' ) ); ?>"></p>
					<p><label for="atp_delivery_date"><?php esc_html_e( 'Required delivery date', 'atp-quote' ); ?></label>
					<input type="date" id="atp_delivery_date" name="atp_delivery_date" value="<?php echo esc_attr( $v( 'delivery_date' ) ); ?>"></p>
					<p><label for="atp_deadline"><?php esc_html_e( 'Quotation deadline', 'atp-quote' ); ?></label>
					<input type="date" id="atp_deadline" name="atp_deadline" value="<?php echo esc_attr( $v( 'deadline' ) ); ?>"></p>
					<p><label for="atp_budget"><?php esc_html_e( 'Budget range', 'atp-quote' ); ?></label>
					<input type="text" id="atp_budget" name="atp_budget" placeholder="<?php esc_attr_e( 'Prefer to discuss', 'atp-quote' ); ?>" value="<?php echo esc_attr( $v( 'budget' ) ); ?>"></p>
				</div>
				<p class="atp-help"><?php esc_html_e( 'A delivery date is a request, not a confirmed promise. Your full address can follow when the order is discussed.', 'atp-quote' ); ?></p>
				<p><label for="atp_specs"><?php esc_html_e( 'Specifications / notes', 'atp-quote' ); ?></label>
				<textarea id="atp_specs" name="atp_specs" rows="3" placeholder="<?php esc_attr_e( 'Sizes, materials, brand preferences, printing or other requirements.', 'atp-quote' ); ?>"><?php echo esc_textarea( $v( 'specs' ) ); ?></textarea></p>
				<datalist id="atp-districts">
					<?php foreach ( atp_districts() as $d ) : ?>
						<option value="<?php echo esc_attr( $d ); ?>"></option>
					<?php endforeach; ?>
				</datalist>
			</fieldset>

			<p class="atp-privacy"><label><input type="checkbox" name="atp_privacy" value="1" required>
				<?php
				printf(
					/* translators: %s: privacy notice link */
					esc_html__( 'I have read the %s.', 'atp-quote' ),
					'<a href="' . esc_url( $privacy ) . '" target="_blank">' . esc_html__( 'Privacy Notice', 'atp-quote' ) . '</a>'
				);
				?>
			</label></p>
			<p class="atp-help"><?php esc_html_e( 'We use the details you provide to review your enquiry and contact you about the requested products.', 'atp-quote' ); ?></p>

			<p><button type="submit" class="wp-element-button atp-submit" data-atp-submit><?php esc_html_e( 'Submit Quote Request', 'atp-quote' ); ?></button></p>
			<p class="atp-help"><?php esc_html_e( 'No account required. Sending a request does not place an order.', 'atp-quote' ); ?></p>
		</form>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Success state, shown only after the enquiry is saved.
 *
 * @param int $id Enquiry ID.
 */
function atp_render_success( $id ) {
	$ref        = get_post_meta( $id, '_atp_reference', true );
	$email      = get_post_meta( $id, '_atp_email', true );
	$first_view = ! get_post_meta( $id, '_atp_conversion_shown', true );
	if ( $first_view ) {
		update_post_meta( $id, '_atp_conversion_shown', 1 );
	}
	$masked = '';
	if ( $email ) {
		list( $user, $domain ) = array_pad( explode( '@', $email, 2 ), 2, '' );
		$masked = substr( $user, 0, 1 ) . str_repeat( '•', max( 2, strlen( $user ) - 1 ) ) . '@' . $domain;
	}
	ob_start();
	?>
	<div class="atp-success" role="status" <?php echo $first_view ? 'data-atp-conversion="' . esc_attr( $ref ) . '"' : ''; ?>>
		<h2><?php esc_html_e( 'Your Quote Request Has Been Received', 'atp-quote' ); ?></h2>
		<p>
			<?php
			printf(
				/* translators: %s: enquiry reference */
				esc_html__( 'Thank you for contacting Abedin Trade Point. Your reference is %s. Our team will review your requirements and contact you if more information is needed.', 'atp-quote' ),
				'<strong>' . esc_html( $ref ) . '</strong>'
			);
			?>
		</p>
		<?php if ( $masked ) : ?>
			<p>
			<?php
			/* translators: %s: masked email */
			printf( esc_html__( 'A confirmation email has been requested for %s.', 'atp-quote' ), esc_html( $masked ) );
			?>
			</p>
		<?php endif; ?>
		<div class="wp-block-buttons">
			<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' ) ); ?>"><?php esc_html_e( 'Continue Browsing', 'atp-quote' ); ?></a></div>
			<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php esc_html_e( 'Contact Our Team', 'atp-quote' ); ?></a></div>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Districts of Bangladesh for the delivery field suggestions.
 */
function atp_districts() {
	return array( 'Bagerhat', 'Bandarban', 'Barguna', 'Barishal', 'Bhola', 'Bogura', 'Brahmanbaria', 'Chandpur', 'Chapai Nawabganj', 'Chattogram', 'Chuadanga', "Cox's Bazar", 'Cumilla', 'Dhaka', 'Dinajpur', 'Faridpur', 'Feni', 'Gaibandha', 'Gazipur', 'Gopalganj', 'Habiganj', 'Jamalpur', 'Jashore', 'Jhalokati', 'Jhenaidah', 'Joypurhat', 'Khagrachhari', 'Khulna', 'Kishoreganj', 'Kurigram', 'Kushtia', 'Lakshmipur', 'Lalmonirhat', 'Madaripur', 'Magura', 'Manikganj', 'Meherpur', 'Moulvibazar', 'Munshiganj', 'Mymensingh', 'Naogaon', 'Narail', 'Narayanganj', 'Narsingdi', 'Natore', 'Netrokona', 'Nilphamari', 'Noakhali', 'Pabna', 'Panchagarh', 'Patuakhali', 'Pirojpur', 'Rajbari', 'Rajshahi', 'Rangamati', 'Rangpur', 'Satkhira', 'Shariatpur', 'Sherpur', 'Sirajganj', 'Sunamganj', 'Sylhet', 'Tangail', 'Thakurgaon' );
}

add_action( 'admin_post_nopriv_atp_submit_quote', 'atp_handle_quote_submit' );
add_action( 'admin_post_atp_submit_quote', 'atp_handle_quote_submit' );

/**
 * Handle a submission: validate, save once, notify, redirect.
 */
function atp_handle_quote_submit() {
	$return = isset( $_POST['atp_return'] ) ? esc_url_raw( wp_unslash( $_POST['atp_return'] ) ) : '';
	$return = wp_validate_redirect( $return, atp_quote_page_url() );
	$token  = isset( $_POST['atp_token'] ) ? sanitize_key( $_POST['atp_token'] ) : '';

	if ( ! $token || ! isset( $_POST['atp_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['atp_nonce'] ), 'atp_submit_quote' ) ) {
		wp_safe_redirect( add_query_arg( 'atp_form', 'expired', $return ) . '#atp-quote-form' );
		exit;
	}

	// Already saved (double click, retry after a dropped connection): show the same result.
	$existing = atp_find_enquiry( '_atp_token', $token );
	if ( $existing ) {
		atp_redirect_success( $existing, $return );
	}

	// Spam trap: pretend success without saving.
	if ( ! empty( $_POST['atp_website'] ) ) {
		wp_safe_redirect( $return );
		exit;
	}

	$values = array();
	foreach ( atp_form_text_fields() as $f ) {
		$raw          = isset( $_POST[ 'atp_' . $f ] ) ? wp_unslash( $_POST[ 'atp_' . $f ] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$values[ $f ] = in_array( $f, array( 'free_text', 'specs' ), true ) ? sanitize_textarea_field( $raw ) : sanitize_text_field( $raw );
	}
	$values['email'] = sanitize_email( $values['email'] );
	foreach ( array( 'delivery_date', 'deadline' ) as $d ) {
		if ( $values[ $d ] && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $values[ $d ] ) ) {
			$values[ $d ] = '';
		}
	}

	$errors = array();
	$items  = atp_list_get();
	$upload = atp_validate_uploads();
	$errors = array_merge( $errors, $upload['errors'] );

	if ( '' === $values['name'] ) {
		$errors[] = __( 'Enter your name.', 'atp-quote' );
	}
	if ( '' === $values['phone'] && '' === $values['email'] ) {
		$errors[] = __( 'Enter a phone number or email address so we can contact you.', 'atp-quote' );
	}
	if ( isset( $_POST['atp_email'] ) && '' !== trim( (string) wp_unslash( $_POST['atp_email'] ) ) && ! is_email( $values['email'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$errors[] = __( 'Check the email address.', 'atp-quote' );
	}
	if ( '' !== $values['phone'] && strlen( preg_replace( '/\D/', '', $values['phone'] ) ) < 6 ) {
		$errors[] = __( 'Check the phone number.', 'atp-quote' );
	}
	if ( ! $items && '' === $values['free_text'] && ! $upload['ok'] && ! $upload['errors'] ) {
		$errors[] = __( 'Add a product, describe your requirements or upload a purchase list.', 'atp-quote' );
	}
	if ( '' === $values['district'] ) {
		$errors[] = __( 'Enter your delivery district or city.', 'atp-quote' );
	}
	if ( empty( $_POST['atp_privacy'] ) ) {
		$errors[] = __( 'Please confirm you have read the Privacy Notice.', 'atp-quote' );
	}

	if ( $errors ) {
		set_transient( 'atp_form_' . $token, array( 'values' => $values, 'errors' => $errors ), 30 * MINUTE_IN_SECONDS );
		wp_safe_redirect( add_query_arg( 'atp_form', $token, $return ) . '#atp-errors' );
		exit;
	}

	// One saver per token, even with parallel requests.
	if ( ! add_option( 'atp_lock_' . $token, time(), '', false ) ) {
		sleep( 2 );
		$existing = atp_find_enquiry( '_atp_token', $token );
		if ( $existing ) {
			atp_redirect_success( $existing, $return );
		}
	}

	$ref = atp_new_reference();
	$id  = wp_insert_post(
		array(
			'post_type'   => 'atp_enquiry',
			'post_status' => 'private',
			'post_title'  => $ref,
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		delete_option( 'atp_lock_' . $token );
		set_transient(
			'atp_form_' . $token,
			array( 'values' => $values, 'errors' => array( __( 'Your request has not been confirmed. Your entered details are still shown below. Please try again or contact our team.', 'atp-quote' ) ) ),
			30 * MINUTE_IN_SECONDS
		);
		wp_safe_redirect( add_query_arg( 'atp_form', $token, $return ) . '#atp-errors' );
		exit;
	}

	update_post_meta( $id, '_atp_token', $token );
	update_post_meta( $id, '_atp_reference', $ref );
	update_post_meta( $id, '_atp_status', 'new' );
	foreach ( $values as $k => $val ) {
		update_post_meta( $id, '_atp_' . $k, $val );
	}
	update_post_meta( $id, '_atp_items', $items ); // Snapshot: later catalogue edits do not change it.
	update_post_meta( $id, '_atp_files', atp_store_uploads( $upload['ok'], $ref ) );
	$source = array();
	if ( $items ) {
		$source[] = 'quote list';
	}
	if ( $values['free_text'] ) {
		$source[] = 'typed';
	}
	if ( $upload['ok'] ) {
		$source[] = 'upload';
	}
	update_post_meta( $id, '_atp_source', implode( ' + ', $source ) );
	update_post_meta( $id, '_atp_landing', isset( $_POST['atp_landing'] ) ? esc_url_raw( wp_unslash( $_POST['atp_landing'] ) ) : '' );
	atp_log( $id, __( 'Enquiry received.', 'atp-quote' ) );
	delete_option( 'atp_lock_' . $token );

	atp_list_save( array() );
	delete_transient( 'atp_form_' . $token );
	atp_send_notifications( $id );

	atp_redirect_success( $id, $return );
}

/**
 * Redirect to the success state for an enquiry.
 *
 * @param int    $id     Enquiry ID.
 * @param string $return Page URL.
 */
function atp_redirect_success( $id, $return ) {
	$ref = get_post_meta( $id, '_atp_reference', true );
	wp_safe_redirect( add_query_arg( array( 'atp_ref' => rawurlencode( $ref ), 'k' => atp_ref_sig( $ref ) ), $return ) );
	exit;
}

<?php
/**
 * WooCommerce as a quotation catalogue: no prices, no cart, no checkout.
 *
 * @package atp-quote
 */

defined( 'ABSPATH' ) || exit;

add_action( 'plugins_loaded', function () {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	// Price text and no purchasing.
	add_filter( 'woocommerce_get_price_html', fn() => '<span class="atp-price">' . esc_html__( 'Price on quotation', 'atp-quote' ) . '</span>', 99 );
	add_filter( 'woocommerce_is_purchasable', '__return_false', 99 );
	add_filter( 'woocommerce_variation_is_purchasable', '__return_false', 99 );

	// Keep the cart and checkout out of reach.
	add_action( 'template_redirect', function () {
		if ( ( function_exists( 'is_cart' ) && is_cart() ) || ( function_exists( 'is_checkout' ) && is_checkout() ) ) {
			wp_safe_redirect( atp_quote_page_url() );
			exit;
		}
	} );

	// Stock wording: availability is confirmed with the quotation.
	add_filter( 'woocommerce_get_availability_text', fn() => __( 'Availability confirmed with quotation', 'atp-quote' ), 99 );

	// No public offer with a dummy or hidden price in structured data.
	add_filter( 'woocommerce_structured_data_product', function ( $markup ) {
		unset( $markup['offers'] );
		return $markup;
	}, 99 );

	// Loop button: Add to Quote for simple products, View Specifications for variable.
	add_filter( 'woocommerce_loop_add_to_cart_link', function ( $html, $product ) {
		if ( $product->is_type( 'simple' ) ) {
			return sprintf(
				'<button type="button" class="wp-element-button atp-add" data-atp-add="%1$d" data-atp-default-qty="%2$s">%3$s</button> <a class="atp-specs-link" href="%4$s">%5$s</a>',
				$product->get_id(),
				esc_attr( max( 1, (float) $product->get_meta( '_atp_min_qty' ) ) ),
				esc_html__( 'Add to Quote', 'atp-quote' ),
				esc_url( $product->get_permalink() ),
				esc_html__( 'View Specifications', 'atp-quote' )
			);
		}
		return sprintf(
			'<a class="wp-element-button atp-add" href="%1$s">%2$s</a>',
			esc_url( $product->get_permalink() ),
			esc_html__( 'Choose Options', 'atp-quote' )
		);
	}, 99, 2 );

	// Single product: replace the add-to-cart form with the quote form.
	add_action( 'woocommerce_single_product_summary', function () {
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
	}, 1 );
	add_action( 'woocommerce_single_product_summary', 'atp_single_quote_controls', 30 );

	// Product facts in the admin General tab.
	add_action( 'woocommerce_product_options_general_product_data', 'atp_product_fields' );
	add_action( 'woocommerce_admin_process_product_object', 'atp_save_product_fields' );

	// Category requirement prompt and quote button below the product grid.
	add_action( 'product_cat_edit_form_fields', 'atp_cat_fields', 10, 1 );
	add_action( 'edited_product_cat', 'atp_save_cat_fields' );
	add_action( 'woocommerce_after_shop_loop', 'atp_category_footer', 20 );
	add_action( 'woocommerce_no_products_found', 'atp_category_footer', 20 );
} );

/**
 * Quote controls on the product page.
 */
function atp_single_quote_controls() {
	global $product;
	if ( ! $product ) {
		return;
	}
	$unit    = atp_product_unit( $product );
	$units   = atp_units();
	$min     = (float) $product->get_meta( '_atp_min_qty' );
	$pack    = $product->get_meta( '_atp_pack_contents' );
	$decimal = in_array( $unit, atp_decimal_units(), true );
	$attrs   = $product->is_type( 'variable' ) ? $product->get_variation_attributes() : array();
	?>
	<form class="atp-single" data-atp-single="<?php echo (int) $product->get_id(); ?>" novalidate>
		<?php foreach ( $attrs as $attr => $values ) : ?>
			<?php $label = wc_attribute_label( $attr, $product ); ?>
			<p>
				<label for="atp-opt-<?php echo esc_attr( sanitize_title( $attr ) ); ?>"><?php echo esc_html( $label ); ?></label>
				<select id="atp-opt-<?php echo esc_attr( sanitize_title( $attr ) ); ?>" data-atp-option="<?php echo esc_attr( $label ); ?>" required>
					<option value=""><?php esc_html_e( 'Choose', 'atp-quote' ); ?></option>
					<?php
					$terms = taxonomy_exists( $attr ) ? wc_get_product_terms( $product->get_id(), $attr, array( 'fields' => 'all' ) ) : array();
					if ( $terms ) {
						foreach ( $terms as $t ) {
							if ( in_array( $t->slug, $values, true ) ) {
								echo '<option>' . esc_html( $t->name ) . '</option>';
							}
						}
					} else {
						foreach ( $values as $val ) {
							echo '<option>' . esc_html( $val ) . '</option>';
						}
					}
					?>
				</select>
			</p>
		<?php endforeach; ?>
		<p class="atp-single__qty">
			<label for="atp-qty"><?php esc_html_e( 'Quantity', 'atp-quote' ); ?></label>
			<input id="atp-qty" type="number" inputmode="decimal" min="<?php echo esc_attr( $min > 0 ? $min : ( $decimal ? 0.01 : 1 ) ); ?>" step="<?php echo $decimal ? '0.01' : '1'; ?>" value="<?php echo esc_attr( $min > 0 ? $min : 1 ); ?>" required data-atp-qty>
			<span><?php echo esc_html( $units[ $unit ] ?? $unit ); ?><?php echo $pack ? ' · ' . esc_html( $pack ) : ''; ?></span>
		</p>
		<p class="atp-help">
			<?php echo $min > 0 ? esc_html( sprintf( /* translators: 1: qty 2: unit */ __( 'Minimum %1$s %2$s.', 'atp-quote' ), $min, $units[ $unit ] ?? $unit ) ) : esc_html__( 'Minimum quantity confirmed with quotation.', 'atp-quote' ); ?>
			<?php esc_html_e( 'Lead time confirmed after specification and availability review.', 'atp-quote' ); ?>
		</p>
		<p>
			<label for="atp-notes"><?php esc_html_e( 'Notes for this item', 'atp-quote' ); ?></label>
			<textarea id="atp-notes" rows="3" data-atp-notes placeholder="<?php echo esc_attr( $product->get_meta( '_atp_spec_prompt' ) ? $product->get_meta( '_atp_spec_prompt' ) : __( 'Sizes, colours, branding or other requirements.', 'atp-quote' ) ); ?>"></textarea>
		</p>
		<p><button type="submit" class="wp-element-button atp-add"><?php echo esc_html( sprintf( /* translators: %s: product name */ __( 'Add %s to Quote', 'atp-quote' ), $product->get_name() ) ); ?></button></p>
		<p class="atp-msg" role="status" aria-live="polite"></p>
		<p class="atp-help"><?php esc_html_e( 'Product images and descriptions identify the listed option. Any proposed alternative must be described separately in the quotation and agreed before supply.', 'atp-quote' ); ?></p>
	</form>
	<?php
}

/**
 * Product edit fields.
 */
function atp_product_fields() {
	echo '<div class="options_group">';
	woocommerce_wp_select(
		array(
			'id'      => '_atp_unit',
			'label'   => __( 'Unit of supply', 'atp-quote' ),
			'options' => atp_units(),
		)
	);
	woocommerce_wp_text_input(
		array(
			'id'          => '_atp_pack_contents',
			'label'       => __( 'Pack contents', 'atp-quote' ),
			'placeholder' => __( 'e.g. 12 pens per pack', 'atp-quote' ),
		)
	);
	woocommerce_wp_text_input(
		array(
			'id'                => '_atp_min_qty',
			'label'             => __( 'Confirmed minimum quantity', 'atp-quote' ),
			'type'              => 'number',
			'custom_attributes' => array( 'step' => 'any', 'min' => '0' ),
			'desc_tip'          => true,
			'description'       => __( 'Leave empty to show "Minimum quantity confirmed with quotation".', 'atp-quote' ),
		)
	);
	woocommerce_wp_textarea_input(
		array(
			'id'          => '_atp_spec_prompt',
			'label'       => __( 'Specification prompt', 'atp-quote' ),
			'placeholder' => __( 'e.g. Fabric composition; GSM; fit; size chart; colour; quantity per size; print or embroidery request.', 'atp-quote' ),
		)
	);
	echo '</div>';
}

/**
 * Save product edit fields.
 *
 * @param WC_Product $product Product.
 */
function atp_save_product_fields( $product ) {
	// phpcs:disable WordPress.Security.NonceVerification -- WooCommerce verifies the product form nonce.
	$unit = isset( $_POST['_atp_unit'] ) ? sanitize_key( $_POST['_atp_unit'] ) : 'piece';
	$product->update_meta_data( '_atp_unit', array_key_exists( $unit, atp_units() ) ? $unit : 'piece' );
	$product->update_meta_data( '_atp_pack_contents', isset( $_POST['_atp_pack_contents'] ) ? sanitize_text_field( wp_unslash( $_POST['_atp_pack_contents'] ) ) : '' );
	$min = isset( $_POST['_atp_min_qty'] ) ? wp_unslash( $_POST['_atp_min_qty'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$product->update_meta_data( '_atp_min_qty', is_numeric( $min ) && $min > 0 ? (float) $min : '' );
	$product->update_meta_data( '_atp_spec_prompt', isset( $_POST['_atp_spec_prompt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['_atp_spec_prompt'] ) ) : '' );
	// phpcs:enable
}

/**
 * Category edit fields: requirement prompt and button label.
 *
 * @param WP_Term $term Category.
 */
function atp_cat_fields( $term ) {
	wp_nonce_field( 'atp_cat_' . $term->term_id, 'atp_cat_nonce' );
	?>
	<tr class="form-field">
		<th scope="row"><label for="atp_requirement_prompt"><?php esc_html_e( 'Requirement prompt', 'atp-quote' ); ?></label></th>
		<td><textarea id="atp_requirement_prompt" name="atp_requirement_prompt" rows="3"><?php echo esc_textarea( get_term_meta( $term->term_id, 'atp_requirement_prompt', true ) ); ?></textarea>
		<p class="description"><?php esc_html_e( 'Shown under the product grid, e.g. "Tell us the fabric or GSM requirement, colour, quantity by size…"', 'atp-quote' ); ?></p></td>
	</tr>
	<tr class="form-field">
		<th scope="row"><label for="atp_quote_label"><?php esc_html_e( 'Quote button label', 'atp-quote' ); ?></label></th>
		<td><input id="atp_quote_label" type="text" name="atp_quote_label" value="<?php echo esc_attr( get_term_meta( $term->term_id, 'atp_quote_label', true ) ); ?>" placeholder="<?php esc_attr_e( 'Request a Quote', 'atp-quote' ); ?>"></td>
	</tr>
	<?php
}

/**
 * Save category fields.
 *
 * @param int $term_id Term ID.
 */
function atp_save_cat_fields( $term_id ) {
	if ( ! isset( $_POST['atp_cat_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['atp_cat_nonce'] ), 'atp_cat_' . $term_id ) || ! current_user_can( 'manage_product_terms' ) ) {
		return;
	}
	update_term_meta( $term_id, 'atp_requirement_prompt', isset( $_POST['atp_requirement_prompt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['atp_requirement_prompt'] ) ) : '' );
	update_term_meta( $term_id, 'atp_quote_label', isset( $_POST['atp_quote_label'] ) ? sanitize_text_field( wp_unslash( $_POST['atp_quote_label'] ) ) : '' );
}

/**
 * Below a category grid: requirement prompt, quote button, unlisted item.
 */
function atp_category_footer() {
	static $done = false;
	if ( $done || ! is_product_category() ) {
		return;
	}
	$done   = true;
	$term   = get_queried_object();
	$prompt = get_term_meta( $term->term_id, 'atp_requirement_prompt', true );
	$label  = get_term_meta( $term->term_id, 'atp_quote_label', true );
	?>
	<section class="atp-cat-help">
		<h2><?php esc_html_e( 'Help Us Prepare Your Quotation', 'atp-quote' ); ?></h2>
		<p><?php esc_html_e( 'The more detail you can share about the product, quantity and delivery requirement, the more useful your quotation can be. Upload a specification sheet or reference image if you have one.', 'atp-quote' ); ?></p>
		<?php if ( $prompt ) : ?><p class="atp-cat-help__prompt"><?php echo esc_html( $prompt ); ?></p><?php endif; ?>
		<p><a class="wp-element-button" href="<?php echo esc_url( atp_quote_page_url() ); ?>"><?php echo esc_html( $label ? $label : __( 'Request a Quote', 'atp-quote' ) ); ?></a></p>
		<h3><?php esc_html_e( 'Cannot Find the Item You Need?', 'atp-quote' ); ?></h3>
		<p><?php esc_html_e( 'Send the product name, specification and quantity. We will review whether it can be sourced for your requirement.', 'atp-quote' ); ?></p>
		<p><a href="<?php echo esc_url( add_query_arg( 'mode', 'type', atp_quote_page_url() ) ); ?>"><?php esc_html_e( 'Enquire About an Unlisted Item', 'atp-quote' ); ?></a></p>
	</section>
	<?php
}

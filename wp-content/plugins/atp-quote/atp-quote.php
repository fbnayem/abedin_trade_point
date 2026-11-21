<?php
/**
 * Plugin Name: ATP Quote
 * Description: Quote list, guest quotation requests, purchase-list uploads and enquiry records for Abedin Trade Point. WooCommerce prices and checkout are switched off; every product leads to a quotation request.
 * Version: 0.1.0
 * Author: Abedin Tech
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * License: GPL-2.0-or-later
 * Text Domain: atp-quote
 *
 * @package atp-quote
 */

defined( 'ABSPATH' ) || exit;

define( 'ATP_QUOTE_VERSION', '0.1.0' );
define( 'ATP_QUOTE_FILE', __FILE__ );
define( 'ATP_QUOTE_DIR', plugin_dir_path( __FILE__ ) );
define( 'ATP_QUOTE_URL', plugin_dir_url( __FILE__ ) );

require_once ATP_QUOTE_DIR . 'includes/settings.php';
require_once ATP_QUOTE_DIR . 'includes/quote-list.php';
require_once ATP_QUOTE_DIR . 'includes/enquiry.php';
require_once ATP_QUOTE_DIR . 'includes/uploads.php';
require_once ATP_QUOTE_DIR . 'includes/notifications.php';
require_once ATP_QUOTE_DIR . 'includes/form.php';
require_once ATP_QUOTE_DIR . 'includes/woocommerce.php';
require_once ATP_QUOTE_DIR . 'includes/starter-content.php';

register_activation_hook( __FILE__, 'atp_quote_activate' );

/**
 * Activation: capability for staff, private upload folder, rewrite flush.
 */
function atp_quote_activate() {
	foreach ( array( 'administrator', 'shop_manager', 'editor' ) as $role_name ) {
		$role = get_role( $role_name );
		if ( $role ) {
			$role->add_cap( 'atp_manage_enquiries' );
		}
	}
	atp_private_dir();
	atp_register_enquiry_type();
	flush_rewrite_rules();
}

add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style( 'atp-quote', ATP_QUOTE_URL . 'assets/css/quote.css', array(), ATP_QUOTE_VERSION );
	wp_enqueue_script( 'atp-quote', ATP_QUOTE_URL . 'assets/js/quote.js', array(), ATP_QUOTE_VERSION, true );
	wp_localize_script(
		'atp-quote',
		'atpQuote',
		array(
			'rest'     => esc_url_raw( rest_url( 'atp/v1/quote-list' ) ),
			'quoteUrl' => esc_url_raw( atp_quote_page_url() ),
			'i18n'     => array(
				'added'   => __( 'Added to your quote list. Continue browsing or review your items.', 'atp-quote' ),
				'removed' => __( 'Removed from your quote list.', 'atp-quote' ),
				'error'   => __( 'That did not work. Please try again.', 'atp-quote' ),
				'qty'     => __( 'Enter a quantity of at least 1.', 'atp-quote' ),
			),
		)
	);
} );

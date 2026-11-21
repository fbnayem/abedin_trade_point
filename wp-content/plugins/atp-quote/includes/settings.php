<?php
/**
 * Site settings: one place for every business contact field.
 *
 * @package atp-quote
 */

defined( 'ABSPATH' ) || exit;

/**
 * Field definitions. Keys are stored in the atp_settings option.
 */
function atp_setting_fields() {
	return array(
		'phone'          => array( __( 'Business phone (with country code, e.g. +8801XXXXXXXXX)', 'atp-quote' ), 'text' ),
		'whatsapp'       => array( __( 'WhatsApp number (with country code)', 'atp-quote' ), 'text' ),
		'sales_email'    => array( __( 'Sales email (shown publicly)', 'atp-quote' ), 'email' ),
		'address'        => array( __( 'Business address', 'atp-quote' ), 'textarea' ),
		'hours'          => array( __( 'Business hours', 'atp-quote' ), 'text' ),
		'notify_emails'  => array( __( 'Staff emails for new enquiries (comma separated)', 'atp-quote' ), 'text' ),
		'privacy_url'    => array( __( 'Privacy Notice URL', 'atp-quote' ), 'url' ),
	);
}

/**
 * Read one setting.
 *
 * @param string $key Field key.
 * @return string
 */
function atp_setting( $key ) {
	$all = get_option( 'atp_settings', array() );
	return isset( $all[ $key ] ) ? (string) $all[ $key ] : '';
}

/**
 * URL of the quote request page.
 */
function atp_quote_page_url() {
	$page = get_page_by_path( 'request-a-quote' );
	return $page ? get_permalink( $page ) : home_url( '/request-a-quote/' );
}

/**
 * wa.me link with the buyer-initiated message from the copy document.
 *
 * @param string $item Optional item or category to prefill.
 */
function atp_whatsapp_url( $item = '' ) {
	$number = preg_replace( '/\D/', '', atp_setting( 'whatsapp' ) );
	if ( ! $number ) {
		return '';
	}
	$text = sprintf(
		/* translators: %s: item or category */
		__( 'Hello Abedin Trade Point. I would like a quotation for %s. Quantity: . Delivery district: . Please let me know the details you need.', 'atp-quote' ),
		$item ? $item : __( '[item / category]', 'atp-quote' )
	);
	return 'https://wa.me/' . $number . '?text=' . rawurlencode( $text );
}

add_action( 'admin_menu', function () {
	add_menu_page(
		__( 'Enquiries', 'atp-quote' ),
		__( 'Enquiries', 'atp-quote' ),
		'atp_manage_enquiries',
		'edit.php?post_type=atp_enquiry',
		'',
		'dashicons-clipboard',
		26
	);
	add_submenu_page(
		'edit.php?post_type=atp_enquiry',
		__( 'Site Settings', 'atp-quote' ),
		__( 'Site Settings', 'atp-quote' ),
		'manage_options',
		'atp-settings',
		'atp_render_settings_page'
	);
} );

add_action( 'admin_init', function () {
	register_setting(
		'atp_settings',
		'atp_settings',
		array(
			'type'              => 'array',
			'sanitize_callback' => function ( $input ) {
				$clean = array();
				foreach ( atp_setting_fields() as $key => $def ) {
					$val = isset( $input[ $key ] ) ? wp_unslash( $input[ $key ] ) : '';
					switch ( $def[1] ) {
						case 'email':
							$clean[ $key ] = sanitize_email( $val );
							break;
						case 'url':
							$clean[ $key ] = esc_url_raw( $val );
							break;
						case 'textarea':
							$clean[ $key ] = sanitize_textarea_field( $val );
							break;
						default:
							$clean[ $key ] = sanitize_text_field( $val );
					}
				}
				return $clean;
			},
		)
	);
} );

/**
 * Settings screen.
 */
function atp_render_settings_page() {
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Site Settings', 'atp-quote' ); ?></h1>
		<p><?php esc_html_e( 'These details appear in the header, footer, contact page, mobile action bar and emails. Leave a field blank to hide it.', 'atp-quote' ); ?></p>
		<form method="post" action="options.php">
			<?php settings_fields( 'atp_settings' ); ?>
			<table class="form-table" role="presentation">
				<?php foreach ( atp_setting_fields() as $key => $def ) : ?>
					<tr>
						<th scope="row"><label for="atp-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $def[0] ); ?></label></th>
						<td>
							<?php if ( 'textarea' === $def[1] ) : ?>
								<textarea id="atp-<?php echo esc_attr( $key ); ?>" name="atp_settings[<?php echo esc_attr( $key ); ?>]" rows="3" class="large-text"><?php echo esc_textarea( atp_setting( $key ) ); ?></textarea>
							<?php else : ?>
								<input id="atp-<?php echo esc_attr( $key ); ?>" type="<?php echo esc_attr( 'url' === $def[1] ? 'url' : ( 'email' === $def[1] ? 'email' : 'text' ) ); ?>" name="atp_settings[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( atp_setting( $key ) ); ?>" class="regular-text">
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

/* Shortcodes used by the theme. */

add_shortcode( 'atp_contact', function ( $atts ) {
	$atts = shortcode_atts( array( 'field' => 'phone' ), $atts );
	return esc_html( atp_setting( $atts['field'] ) );
} );

add_shortcode( 'atp_contact_block', function () {
	$rows  = array();
	$phone = atp_setting( 'phone' );
	$email = atp_setting( 'sales_email' );
	$addr  = atp_setting( 'address' );
	$hours = atp_setting( 'hours' );
	$wa    = atp_whatsapp_url();
	if ( $phone ) {
		$rows[] = '<li><a href="' . esc_url( 'tel:' . preg_replace( '/[^0-9+]/', '', $phone ) ) . '">' . esc_html( $phone ) . '</a></li>';
	}
	if ( $wa ) {
		$rows[] = '<li><a href="' . esc_url( $wa ) . '" target="_blank" rel="noopener">' . esc_html__( 'Message on WhatsApp', 'atp-quote' ) . '</a></li>';
	}
	if ( $email ) {
		$rows[] = '<li><a href="' . esc_url( 'mailto:' . $email ) . '">' . esc_html( $email ) . '</a></li>';
	}
	if ( $addr ) {
		$rows[] = '<li>' . nl2br( esc_html( $addr ) ) . '</li>';
	}
	if ( $hours ) {
		$rows[] = '<li>' . esc_html( $hours ) . '</li>';
	}
	if ( ! $rows ) {
		return current_user_can( 'manage_options' ) ? '<p><em>' . esc_html__( 'Add contact details under Enquiries → Site Settings.', 'atp-quote' ) . '</em></p>' : '';
	}
	return '<ul class="atp-contact-list">' . implode( '', $rows ) . '</ul>';
} );

add_shortcode( 'atp_copyright', function () {
	return '<p class="atp-copyright">' . esc_html( sprintf( '© %s Abedin Trade Point. All rights reserved.', wp_date( 'Y' ) ) ) . '</p>';
} );

add_shortcode( 'atp_whatsapp_button', function ( $atts ) {
	$atts = shortcode_atts( array( 'label' => __( 'Chat on WhatsApp', 'atp-quote' ), 'item' => '' ), $atts );
	$url  = atp_whatsapp_url( $atts['item'] );
	if ( ! $url ) {
		return '';
	}
	return '<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $atts['label'] ) . '</a></div>';
} );

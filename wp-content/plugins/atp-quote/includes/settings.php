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
		'profile_url'    => array( __( 'Company profile PDF URL (upload under Media, then paste the file URL). The download button appears only when this is set.', 'atp-quote' ), 'url' ),
		'ga4_id'         => array( __( 'Google Analytics 4 measurement ID (e.g. G-XXXXXXXXXX). Leave blank for no analytics.', 'atp-quote' ), 'text' ),
		'placeholders'   => array( __( 'Show highlighted placeholders for missing contact details (turn off before launch)', 'atp-quote' ), 'checkbox' ),
	);
}

/**
 * Public label for a missing contact detail.
 *
 * @param string $key Field key.
 */
function atp_placeholder_label( $key ) {
	$labels = array(
		'phone'       => __( 'BUSINESS PHONE', 'atp-quote' ),
		'whatsapp'    => __( 'WHATSAPP NUMBER', 'atp-quote' ),
		'sales_email' => __( 'SALES EMAIL', 'atp-quote' ),
		'address'     => __( 'BUSINESS ADDRESS', 'atp-quote' ),
		'hours'       => __( 'BUSINESS HOURS', 'atp-quote' ),
	);
	return isset( $labels[ $key ] ) ? '[' . $labels[ $key ] . ']' : '';
}

/**
 * Whether placeholders should show for missing details. On until someone
 * saves the settings with the box unticked.
 */
function atp_show_placeholders() {
	$all = get_option( 'atp_settings', null );
	return ! is_array( $all ) || ! array_key_exists( 'placeholders', $all ) || '1' === (string) $all['placeholders'];
}

/**
 * Highlighted placeholder markup, or '' when not shown.
 *
 * @param string $key Field key.
 */
function atp_placeholder_html( $key ) {
	if ( ! atp_show_placeholders() ) {
		return '';
	}
	return '<mark class="atp-placeholder">' . esc_html( atp_placeholder_label( $key ) ) . '</mark>';
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
						case 'checkbox':
							$clean[ $key ] = empty( $val ) ? '0' : '1';
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
							<?php if ( 'checkbox' === $def[1] ) : ?>
								<input type="hidden" name="atp_settings[<?php echo esc_attr( $key ); ?>]" value="0">
								<input id="atp-<?php echo esc_attr( $key ); ?>" type="checkbox" name="atp_settings[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( 'placeholders' === $key ? atp_show_placeholders() : '1' === atp_setting( $key ) ); ?>>
							<?php elseif ( 'textarea' === $def[1] ) : ?>
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
	$ph    = 'atp_placeholder_html';
	if ( $phone ) {
		$rows[] = '<li><span class="atp-contact-label">' . esc_html__( 'Call Our Team', 'atp-quote' ) . '</span> <a href="' . esc_url( 'tel:' . preg_replace( '/[^0-9+]/', '', $phone ) ) . '">' . esc_html( $phone ) . '</a></li>';
	} elseif ( $ph( 'phone' ) ) {
		$rows[] = '<li><span class="atp-contact-label">' . esc_html__( 'Call Our Team', 'atp-quote' ) . '</span> ' . $ph( 'phone' ) . '</li>';
	}
	if ( $wa ) {
		$rows[] = '<li><span class="atp-contact-label">' . esc_html__( 'WhatsApp', 'atp-quote' ) . '</span> <a href="' . esc_url( $wa ) . '" target="_blank" rel="noopener">' . esc_html__( 'Message on WhatsApp', 'atp-quote' ) . '</a></li>';
	} elseif ( $ph( 'whatsapp' ) ) {
		$rows[] = '<li><span class="atp-contact-label">' . esc_html__( 'WhatsApp', 'atp-quote' ) . '</span> ' . $ph( 'whatsapp' ) . '</li>';
	}
	if ( $email ) {
		$rows[] = '<li><span class="atp-contact-label">' . esc_html__( 'Email', 'atp-quote' ) . '</span> <a href="' . esc_url( 'mailto:' . $email ) . '">' . esc_html( $email ) . '</a></li>';
	} elseif ( $ph( 'sales_email' ) ) {
		$rows[] = '<li><span class="atp-contact-label">' . esc_html__( 'Email', 'atp-quote' ) . '</span> ' . $ph( 'sales_email' ) . '</li>';
	}
	if ( $addr || $ph( 'address' ) ) {
		$rows[] = '<li><span class="atp-contact-label">' . esc_html__( 'Address', 'atp-quote' ) . '</span> ' . ( $addr ? nl2br( esc_html( $addr ) ) : $ph( 'address' ) ) . '</li>';
	}
	if ( $hours || $ph( 'hours' ) ) {
		$rows[] = '<li><span class="atp-contact-label">' . esc_html__( 'Hours', 'atp-quote' ) . '</span> ' . ( $hours ? esc_html( $hours ) : $ph( 'hours' ) ) . '</li>';
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

add_shortcode( 'atp_profile_download', function () {
	$url = atp_setting( 'profile_url' );
	if ( ! $url ) {
		return '';
	}
	return '<div class="atp-profile"><p>' . esc_html__( 'Read a short overview of Abedin Trade Point, our published supply categories and the process for sending a quotation request.', 'atp-quote' ) . '</p>'
		. '<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="' . esc_url( $url ) . '" download>' . esc_html__( 'Download Company Profile', 'atp-quote' ) . '</a></div></div>';
} );

/* ---------- Launch checklist ---------- */

/**
 * Items still to finish before launch.
 *
 * @return array<int, array{0: string, 1: string}> Label and admin link.
 */
function atp_launch_todo() {
	$todo     = array();
	$settings = admin_url( 'edit.php?post_type=atp_enquiry&page=atp-settings' );
	$labels   = array(
		'phone'         => __( 'Business phone', 'atp-quote' ),
		'whatsapp'      => __( 'WhatsApp number', 'atp-quote' ),
		'sales_email'   => __( 'Sales email', 'atp-quote' ),
		'address'       => __( 'Business address', 'atp-quote' ),
		'hours'         => __( 'Business hours', 'atp-quote' ),
		'notify_emails' => __( 'Staff emails for new enquiries', 'atp-quote' ),
	);
	foreach ( $labels as $key => $label ) {
		if ( '' === atp_setting( $key ) ) {
			/* translators: %s: field name */
			$todo[] = array( sprintf( __( 'Add the %s in Site Settings.', 'atp-quote' ), $label ), $settings );
		}
	}
	if ( atp_show_placeholders() ) {
		$todo[] = array( __( 'Turn off "Show highlighted placeholders" in Site Settings.', 'atp-quote' ), $settings );
	}
	if ( ! get_theme_mod( 'custom_logo' ) ) {
		$todo[] = array( __( 'Upload the real logo (Appearance → Editor → Header → Site Logo). A text placeholder shows until then.', 'atp-quote' ), admin_url( 'site-editor.php' ) );
	}
	foreach ( array( 'privacy', 'ordering-delivery' ) as $slug ) {
		$page = get_page_by_path( $slug );
		if ( $page && false !== strpos( $page->post_content, 'atp-placeholder' ) ) {
			/* translators: %s: page title */
			$todo[] = array( sprintf( __( 'Replace the highlighted [BRACKETED] details on the %s page.', 'atp-quote' ), get_the_title( $page ) ), get_edit_post_link( $page->ID, 'raw' ) );
		}
	}
	if ( function_exists( 'atp_sample_product_ids' ) && atp_sample_product_ids() ) {
		$todo[] = array( __( 'Replace or remove the sample products.', 'atp-quote' ), admin_url( 'edit.php?post_type=atp_enquiry&page=atp-setup' ) );
	}
	if ( false !== strpos( home_url(), 'localhost' ) || false !== strpos( home_url(), '.local' ) || false !== strpos( home_url(), 'staging' ) ) {
		$todo[] = array( __( 'Move the site to the live domain and update Settings → General.', 'atp-quote' ), admin_url( 'options-general.php' ) );
	}
	if ( '0' === get_option( 'blog_public', '1' ) ) {
		$todo[] = array( __( 'Allow search engines (Settings → Reading) when the site goes live.', 'atp-quote' ), admin_url( 'options-reading.php' ) );
	}
	return $todo;
}

/**
 * Print the checklist.
 */
function atp_render_launch_checklist() {
	$todo = atp_launch_todo();
	if ( ! $todo ) {
		echo '<p>' . esc_html__( 'Everything on the launch checklist is done.', 'atp-quote' ) . '</p>';
		return;
	}
	echo '<ol class="atp-launch-list">';
	foreach ( $todo as $item ) {
		echo '<li><a href="' . esc_url( $item[1] ) . '">' . esc_html( $item[0] ) . '</a></li>';
	}
	echo '</ol>';
}

add_action( 'admin_notices', function () {
	$screen = get_current_screen();
	if ( ! current_user_can( 'manage_options' ) || ! $screen || ! in_array( $screen->id, array( 'dashboard', 'edit-atp_enquiry', 'atp_enquiry_page_atp-settings' ), true ) ) {
		return;
	}
	$todo = atp_launch_todo();
	if ( ! $todo ) {
		return;
	}
	echo '<div class="notice notice-warning"><p><strong>' . esc_html(
		/* translators: %d: number of items */
		sprintf( _n( 'Website launch checklist: %d item to finish.', 'Website launch checklist: %d items to finish.', count( $todo ), 'atp-quote' ), count( $todo ) )
	) . '</strong> <a href="' . esc_url( admin_url( 'edit.php?post_type=atp_enquiry&page=atp-setup' ) ) . '">' . esc_html__( 'See the list', 'atp-quote' ) . '</a></p></div>';
} );

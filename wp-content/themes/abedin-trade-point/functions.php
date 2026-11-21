<?php
/**
 * Abedin Trade Point theme setup.
 *
 * @package abedin-trade-point
 */

defined( 'ABSPATH' ) || exit;

define( 'ATP_THEME_VERSION', '1.0.0' );

add_action( 'after_setup_theme', function () {
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'woocommerce' );
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );
	add_editor_style( 'assets/css/theme.css' );
} );

add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style(
		'atp-theme',
		get_theme_file_uri( 'assets/css/theme.css' ),
		array(),
		ATP_THEME_VERSION
	);
} );

/**
 * Shortcode blocks inside patterns are not expanded by the template renderer,
 * so run them when the block itself renders.
 */
add_filter( 'render_block_core/shortcode', function ( $content, $block ) {
	// Use the raw shortcode text: the default render adds wpautop paragraphs
	// that break form and grid markup.
	return do_shortcode( trim( $block['innerHTML'] ) );
}, 10, 2 );

/**
 * WooCommerce auto-inserts account and cart icons next to the navigation.
 * There is no cart on this site, so keep them out.
 */
add_filter( 'hooked_block_types', function ( $hooked ) {
	return array_values( array_diff( $hooked, array( 'woocommerce/customer-account', 'woocommerce/mini-cart' ) ) );
}, 20 );

/**
 * Pattern category for the homepage and shared page sections.
 */
add_action( 'init', function () {
	register_block_pattern_category(
		'abedin-trade-point',
		array( 'label' => __( 'Abedin Trade Point', 'abedin-trade-point' ) )
	);
} );

/**
 * Mobile action bar (Call, WhatsApp, Get Quote). Values come from the
 * ATP Quote plugin's Site Settings so they are edited in one place.
 */
add_action( 'wp_footer', function () {
	if ( ! function_exists( 'atp_setting' ) ) {
		return;
	}
	$phone    = atp_setting( 'phone' );
	$whatsapp = atp_setting( 'whatsapp' );
	$quote    = atp_quote_page_url();
	$fallback = function_exists( 'atp_show_placeholders' ) && atp_show_placeholders() ? home_url( '/contact/' ) : '';
	?>
	<nav class="atp-mbar" aria-label="<?php esc_attr_e( 'Quick actions', 'abedin-trade-point' ); ?>">
		<?php if ( $phone ) : ?>
			<a class="atp-mbar__btn" href="<?php echo esc_url( 'tel:' . preg_replace( '/[^0-9+]/', '', $phone ) ); ?>"><?php esc_html_e( 'Call', 'abedin-trade-point' ); ?></a>
		<?php elseif ( $fallback ) : ?>
			<a class="atp-mbar__btn" href="<?php echo esc_url( $fallback ); ?>"><?php esc_html_e( 'Call', 'abedin-trade-point' ); ?></a>
		<?php endif; ?>
		<?php if ( $whatsapp ) : ?>
			<a class="atp-mbar__btn" href="<?php echo esc_url( atp_whatsapp_url() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'WhatsApp', 'abedin-trade-point' ); ?></a>
		<?php elseif ( $fallback ) : ?>
			<a class="atp-mbar__btn" href="<?php echo esc_url( $fallback ); ?>"><?php esc_html_e( 'WhatsApp', 'abedin-trade-point' ); ?></a>
		<?php endif; ?>
		<a class="atp-mbar__btn atp-mbar__btn--primary" href="<?php echo esc_url( $quote ); ?>"><?php esc_html_e( 'Get Quote', 'abedin-trade-point' ); ?></a>
	</nav>
	<?php
} );

/**
 * Until the real logo is uploaded, show a clearly marked placeholder mark so
 * the header layout is final. Replace it under Appearance → Editor → Header.
 */
add_filter( 'render_block_core/site-logo', function ( $content ) {
	if ( '' !== trim( $content ) || get_theme_mod( 'custom_logo' ) ) {
		return $content;
	}
	return '<div class="wp-block-site-logo atp-logo-placeholder"><a href="' . esc_url( home_url( '/' ) ) . '" rel="home"><img src="' . esc_url( get_theme_file_uri( 'assets/images/logo-placeholder.svg' ) ) . '" width="44" height="44" alt="' . esc_attr__( 'Abedin Trade Point (logo placeholder)', 'abedin-trade-point' ) . '"></a></div>';
} );

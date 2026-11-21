<?php
/**
 * Search basics: page and category meta titles and descriptions, robots
 * rules for private pages, organisation structured data and GA4.
 * Title and description output steps aside when Yoast SEO or Rank Math is
 * active, so a dedicated SEO plugin can take over later.
 *
 * @package atp-quote
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether a dedicated SEO plugin handles titles and descriptions.
 */
function atp_seo_plugin_active() {
	return defined( 'WPSEO_VERSION' ) || class_exists( 'RankMath' ) || defined( 'AIOSEO_VERSION' );
}

/**
 * Meta title and description for the current request.
 *
 * @return array{0: string, 1: string}
 */
function atp_current_meta() {
	$title = '';
	$desc  = '';
	if ( is_front_page() ) {
		$title = 'Bulk Supply in Bangladesh - Abedin Trade Point';
		$desc  = 'Source garments, bags, stationery and business essentials. Send quantities and specifications to Abedin Trade Point for a bulk quotation.';
	} elseif ( function_exists( 'is_shop' ) && is_shop() && ! is_search() ) {
		$id    = wc_get_page_id( 'shop' );
		$title = (string) get_post_meta( $id, '_atp_meta_title', true );
		$desc  = (string) get_post_meta( $id, '_atp_meta_description', true );
	} elseif ( function_exists( 'is_product_category' ) && is_product_category() ) {
		$term  = get_queried_object();
		$title = (string) get_term_meta( $term->term_id, 'atp_meta_title', true );
		$desc  = (string) get_term_meta( $term->term_id, 'atp_meta_description', true );
		if ( ! $title ) {
			$title = $term->name . ' | Abedin Trade Point';
		}
		if ( ! $desc ) {
			$desc = wp_trim_words( wp_strip_all_tags( $term->description ), 26, '…' );
		}
	} elseif ( is_singular( 'product' ) ) {
		$product = wc_get_product( get_queried_object_id() );
		if ( $product ) {
			$title = $product->get_name() . ' | Abedin Trade Point';
			$desc  = wp_trim_words( wp_strip_all_tags( $product->get_short_description() ? $product->get_short_description() : $product->get_description() ), 26, '…' );
		}
	} elseif ( is_singular() ) {
		$id    = get_queried_object_id();
		$title = (string) get_post_meta( $id, '_atp_meta_title', true );
		$desc  = (string) get_post_meta( $id, '_atp_meta_description', true );
	}
	return array( $title, $desc );
}

add_filter( 'pre_get_document_title', function ( $title ) {
	if ( atp_seo_plugin_active() ) {
		return $title;
	}
	$meta = atp_current_meta();
	return $meta[0] ? $meta[0] : $title;
}, 20 );

add_action( 'wp_head', function () {
	if ( atp_seo_plugin_active() ) {
		return;
	}
	$meta = atp_current_meta();
	if ( $meta[1] ) {
		echo '<meta name="description" content="' . esc_attr( $meta[1] ) . '">' . "\n";
	}
}, 1 );

// Keep private and thin pages out of search results.
add_filter( 'wp_robots', function ( $robots ) {
	$noindex = is_search() || is_404();
	if ( is_page( 'request-a-quote' ) ) {
		$noindex = true;
	}
	if ( function_exists( 'is_product_category' ) && is_product_category() && in_array( (int) get_queried_object_id(), atp_hidden_cat_ids(), true ) ) {
		$noindex = true;
	}
	if ( isset( $_GET['atp_ref'] ) || isset( $_GET['atp_form'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$noindex = true;
	}
	if ( $noindex ) {
		$robots['noindex'] = true;
		$robots['follow']  = true;
		unset( $robots['max-image-preview'] );
	}
	return $robots;
} );

// Organisation structured data on the homepage, from Site Settings only.
add_action( 'wp_head', function () {
	if ( ! is_front_page() ) {
		return;
	}
	$org = array(
		'@context' => 'https://schema.org',
		'@type'    => 'Organization',
		'name'     => 'Abedin Trade Point',
		'url'      => home_url( '/' ),
	);
	$logo = get_theme_mod( 'custom_logo' );
	if ( $logo ) {
		$org['logo'] = wp_get_attachment_image_url( $logo, 'full' );
	}
	if ( atp_setting( 'phone' ) ) {
		$org['telephone'] = atp_setting( 'phone' );
	}
	if ( atp_setting( 'sales_email' ) ) {
		$org['email'] = atp_setting( 'sales_email' );
	}
	if ( atp_setting( 'address' ) ) {
		$org['address'] = array(
			'@type'          => 'PostalAddress',
			'streetAddress'  => preg_replace( '/\s*\n\s*/', ', ', atp_setting( 'address' ) ),
			'addressCountry' => 'BD',
		);
	}
	echo '<script type="application/ld+json">' . wp_json_encode( $org, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
} );

// Google Analytics 4, only when a measurement ID is set. Enquiry events are
// pushed to dataLayer by the quote script without personal details.
add_action( 'wp_head', function () {
	$id = atp_setting( 'ga4_id' );
	if ( ! $id || ! preg_match( '/^G-[A-Z0-9]+$/', $id ) || current_user_can( 'edit_posts' ) ) {
		return;
	}
	// phpcs:disable WordPress.WP.EnqueuedResources.NonEnqueuedScript
	?>
	<script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr( $id ); ?>"></script>
	<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','<?php echo esc_js( $id ); ?>');</script>
	<?php
	// phpcs:enable
}, 2 );

// Page SEO fields on the page editor (classic meta box, works in the block editor sidebar).
add_action( 'add_meta_boxes_page', function () {
	if ( atp_seo_plugin_active() ) {
		return;
	}
	add_meta_box( 'atp_seo', __( 'Search appearance', 'atp-quote' ), function ( $post ) {
		wp_nonce_field( 'atp_seo_' . $post->ID, 'atp_seo_nonce' );
		?>
		<p><label for="atp_meta_title"><?php esc_html_e( 'Meta title', 'atp-quote' ); ?></label><br>
		<input id="atp_meta_title" type="text" name="atp_meta_title" value="<?php echo esc_attr( get_post_meta( $post->ID, '_atp_meta_title', true ) ); ?>" style="width:100%"></p>
		<p><label for="atp_meta_description"><?php esc_html_e( 'Meta description', 'atp-quote' ); ?></label><br>
		<textarea id="atp_meta_description" name="atp_meta_description" rows="3" style="width:100%"><?php echo esc_textarea( get_post_meta( $post->ID, '_atp_meta_description', true ) ); ?></textarea></p>
		<?php
	}, 'page', 'side', 'low' );
} );

add_action( 'save_post_page', function ( $post_id ) {
	if ( ! isset( $_POST['atp_seo_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['atp_seo_nonce'] ), 'atp_seo_' . $post_id ) || ! current_user_can( 'edit_page', $post_id ) ) {
		return;
	}
	update_post_meta( $post_id, '_atp_meta_title', isset( $_POST['atp_meta_title'] ) ? sanitize_text_field( wp_unslash( $_POST['atp_meta_title'] ) ) : '' );
	update_post_meta( $post_id, '_atp_meta_description', isset( $_POST['atp_meta_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['atp_meta_description'] ) ) : '' );
} );

<?php
/**
 * One-click starter content: pages with the approved copy and the six
 * priority product categories. Safe to run again: existing items are skipped.
 *
 * @package atp-quote
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', function () {
	add_submenu_page(
		'edit.php?post_type=atp_enquiry',
		__( 'Setup', 'atp-quote' ),
		__( 'Setup', 'atp-quote' ),
		'manage_options',
		'atp-setup',
		'atp_render_setup_page'
	);
} );

/**
 * Setup screen.
 */
function atp_render_setup_page() {
	$report = get_transient( 'atp_setup_report' );
	delete_transient( 'atp_setup_report' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Website setup', 'atp-quote' ); ?></h1>
		<p><?php esc_html_e( 'Creates the site pages with the approved copy, the six priority product categories, and sets the shop page to /products/. Anything that already exists is left alone.', 'atp-quote' ); ?></p>
		<?php if ( $report ) : ?>
			<div class="notice notice-success"><ul>
				<?php foreach ( $report as $line ) : ?>
					<li><?php echo esc_html( $line ); ?></li>
				<?php endforeach; ?>
			</ul></div>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="atp_setup">
			<?php wp_nonce_field( 'atp_setup' ); ?>
			<?php submit_button( __( 'Create starter pages and categories', 'atp-quote' ) ); ?>
		</form>
		<h2><?php esc_html_e( 'Still to do by hand', 'atp-quote' ); ?></h2>
		<ol>
			<li><?php esc_html_e( 'Enquiries → Site Settings: enter phone, WhatsApp, email, address, hours and staff notification emails.', 'atp-quote' ); ?></li>
			<li><?php esc_html_e( 'Settings → Permalinks: Post name; product category base "products"; product base "product".', 'atp-quote' ); ?></li>
			<li><?php esc_html_e( 'Appearance → Editor: upload the logo in the header.', 'atp-quote' ); ?></li>
			<li><?php esc_html_e( 'Complete and publish the Privacy and Ordering & Delivery drafts.', 'atp-quote' ); ?></li>
			<li><?php esc_html_e( 'Exclude /request-a-quote/ and /wp-json/atp/ from any page cache.', 'atp-quote' ); ?></li>
		</ol>
	</div>
	<?php
}

add_action( 'admin_post_atp_setup', function () {
	check_admin_referer( 'atp_setup' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to do this.', 'atp-quote' ), 403 );
	}
	$report = atp_run_setup();
	set_transient( 'atp_setup_report', $report, MINUTE_IN_SECONDS * 5 );
	wp_safe_redirect( admin_url( 'edit.php?post_type=atp_enquiry&page=atp-setup' ) );
	exit;
} );

/**
 * Create pages and categories. Returns a report.
 */
function atp_run_setup() {
	$report = array();
	$dir    = ATP_QUOTE_DIR . 'content/';
	$pages  = json_decode( (string) file_get_contents( $dir . 'pages/pages.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions

	foreach ( (array) $pages as $slug => $def ) {
		if ( get_page_by_path( $slug ) ) {
			/* translators: %s: page slug */
			$report[] = sprintf( __( 'Skipped /%s/ (already exists).', 'atp-quote' ), $slug );
			continue;
		}
		$id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => $def['status'],
				'post_name'    => $slug,
				'post_title'   => $def['h1'],
				'post_content' => (string) file_get_contents( $dir . 'pages/' . $slug . '.html' ), // phpcs:ignore WordPress.WP.AlternativeFunctions
			)
		);
		if ( ! is_wp_error( $id ) && 'request-a-quote' === $slug ) {
			update_post_meta( $id, '_wp_page_template', 'page-quote' );
		}
		if ( ! is_wp_error( $id ) && 'privacy' === $slug && ! atp_setting( 'privacy_url' ) ) {
			$opts                = get_option( 'atp_settings', array() );
			$opts['privacy_url'] = home_url( '/privacy/' );
			update_option( 'atp_settings', $opts );
		}
		/* translators: 1: page slug, 2: status */
		$report[] = is_wp_error( $id ) ? $id->get_error_message() : sprintf( __( 'Created /%1$s/ (%2$s).', 'atp-quote' ), $slug, $def['status'] );
	}

	if ( taxonomy_exists( 'product_cat' ) ) {
		$cats = json_decode( (string) file_get_contents( $dir . 'categories.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		foreach ( (array) $cats as $c ) {
			if ( term_exists( $c['slug'], 'product_cat' ) ) {
				/* translators: %s: category name */
				$report[] = sprintf( __( 'Skipped category %s (already exists).', 'atp-quote' ), $c['name'] );
				continue;
			}
			$parent = term_exists( $c['group'], 'product_cat' );
			if ( ! $parent ) {
				$parent = wp_insert_term( $c['group'], 'product_cat' );
			}
			$term = wp_insert_term(
				$c['name'],
				'product_cat',
				array(
					'slug'        => $c['slug'],
					'description' => $c['intro'],
					'parent'      => is_array( $parent ) ? (int) $parent['term_id'] : 0,
				)
			);
			if ( is_wp_error( $term ) ) {
				$report[] = $term->get_error_message();
				continue;
			}
			update_term_meta( $term['term_id'], 'atp_requirement_prompt', $c['prompt'] );
			update_term_meta( $term['term_id'], 'atp_quote_label', $c['button'] );
			/* translators: %s: category name */
			$report[] = sprintf( __( 'Created category %s.', 'atp-quote' ), $c['name'] );
		}

		$shop_id = function_exists( 'wc_get_page_id' ) ? wc_get_page_id( 'shop' ) : 0;
		if ( $shop_id > 0 && 'products' !== get_post_field( 'post_name', $shop_id ) ) {
			wp_update_post( array( 'ID' => $shop_id, 'post_name' => 'products', 'post_title' => 'Products' ) );
			$report[] = __( 'Shop page moved to /products/.', 'atp-quote' );
		}
	} else {
		$report[] = __( 'WooCommerce is not active, so categories were not created.', 'atp-quote' );
	}
	flush_rewrite_rules();
	return $report;
}

<?php
/**
 * One-click setup: pages with the approved copy, the ten browsing groups and
 * 31 categories, permalinks, and optional sample products.
 * Safe to run again: existing pages are left alone and categories only get
 * missing copy filled in.
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
	$report  = get_transient( 'atp_setup_report' );
	$samples = atp_sample_product_ids();
	delete_transient( 'atp_setup_report' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Website setup', 'atp-quote' ); ?></h1>
		<?php if ( $report ) : ?>
			<div class="notice notice-success"><ul>
				<?php foreach ( $report as $line ) : ?>
					<li><?php echo esc_html( $line ); ?></li>
				<?php endforeach; ?>
			</ul></div>
		<?php endif; ?>

		<h2><?php esc_html_e( '1. Pages and catalogue', 'atp-quote' ); ?></h2>
		<p><?php esc_html_e( 'Creates every site page with the approved copy, the ten product groups and all 31 categories, and sets the product links to /products/ and /product/. The nine launch categories are published; the other 22 are created as drafts. Anything that already exists is left alone.', 'atp-quote' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="atp_setup">
			<?php wp_nonce_field( 'atp_setup' ); ?>
			<?php submit_button( __( 'Create pages and categories', 'atp-quote' ), 'primary', 'submit', false ); ?>
		</form>

		<h2><?php esc_html_e( '2. Sample products (optional)', 'atp-quote' ); ?></h2>
		<p><?php esc_html_e( 'Adds 31 example products across the launch categories so the site can be reviewed before real products are ready. They use the plan\'s wording and no prices, photos or stock claims. Replace them with real products, or remove them all in one click.', 'atp-quote' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:8px">
			<input type="hidden" name="action" value="atp_samples">
			<input type="hidden" name="do" value="add">
			<?php wp_nonce_field( 'atp_samples' ); ?>
			<?php submit_button( __( 'Add sample products', 'atp-quote' ), 'secondary', 'submit', false ); ?>
		</form>
		<?php if ( $samples ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block">
				<input type="hidden" name="action" value="atp_samples">
				<input type="hidden" name="do" value="remove">
				<?php wp_nonce_field( 'atp_samples' ); ?>
				<?php /* translators: %d: number of sample products */ submit_button( sprintf( __( 'Remove %d sample products', 'atp-quote' ), count( $samples ) ), 'delete', 'submit', false ); ?>
			</form>
		<?php endif; ?>

		<h2><?php esc_html_e( '3. Before launch', 'atp-quote' ); ?></h2>
		<?php atp_render_launch_checklist(); ?>
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

add_action( 'admin_post_atp_samples', function () {
	check_admin_referer( 'atp_samples' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to do this.', 'atp-quote' ), 403 );
	}
	$report = ( isset( $_POST['do'] ) && 'remove' === $_POST['do'] ) ? atp_remove_sample_products() : atp_add_sample_products();
	set_transient( 'atp_setup_report', $report, MINUTE_IN_SECONDS * 5 );
	wp_safe_redirect( admin_url( 'edit.php?post_type=atp_enquiry&page=atp-setup' ) );
	exit;
} );

/**
 * Read a JSON file from the plugin's content folder.
 *
 * @param string $file Relative path.
 */
function atp_content_json( $file ) {
	return json_decode( (string) file_get_contents( ATP_QUOTE_DIR . 'content/' . $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
}

/**
 * Create pages, groups and categories. Returns a report.
 */
function atp_run_setup() {
	$report = array();
	$dir    = ATP_QUOTE_DIR . 'content/';
	$pages  = atp_content_json( 'pages/pages.json' );

	foreach ( (array) $pages as $slug => $def ) {
		$existing = get_page_by_path( $slug );
		if ( $existing ) {
			// Fill in SEO fields on older installs without touching the content.
			if ( ! empty( $def['meta_title'] ) && ! get_post_meta( $existing->ID, '_atp_meta_title', true ) ) {
				update_post_meta( $existing->ID, '_atp_meta_title', $def['meta_title'] );
				update_post_meta( $existing->ID, '_atp_meta_description', $def['meta_description'] ?? '' );
			}
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
		if ( is_wp_error( $id ) ) {
			$report[] = $id->get_error_message();
			continue;
		}
		if ( ! empty( $def['template'] ) ) {
			update_post_meta( $id, '_wp_page_template', $def['template'] );
		}
		if ( ! empty( $def['meta_title'] ) ) {
			update_post_meta( $id, '_atp_meta_title', $def['meta_title'] );
			update_post_meta( $id, '_atp_meta_description', $def['meta_description'] ?? '' );
		}
		if ( 'privacy' === $slug ) {
			$opts                = get_option( 'atp_settings', array() );
			$opts['privacy_url'] = empty( $opts['privacy_url'] ) ? home_url( '/privacy/' ) : $opts['privacy_url'];
			update_option( 'atp_settings', $opts );
			update_option( 'wp_page_for_privacy_policy', $id );
		}
		/* translators: 1: page slug, 2: status */
		$report[] = sprintf( __( 'Created /%1$s/ (%2$s).', 'atp-quote' ), $slug, $def['status'] );
	}

	if ( taxonomy_exists( 'product_cat' ) ) {
		$report = array_merge( $report, atp_setup_categories() );

		$shop_id = function_exists( 'wc_get_page_id' ) ? wc_get_page_id( 'shop' ) : 0;
		if ( $shop_id > 0 && 'products' !== get_post_field( 'post_name', $shop_id ) ) {
			wp_update_post( array( 'ID' => $shop_id, 'post_name' => 'products', 'post_title' => 'Products' ) );
			$report[] = __( 'Shop page moved to /products/.', 'atp-quote' );
		}
		if ( $shop_id > 0 ) {
			update_post_meta( $shop_id, '_atp_meta_title', 'Bulk Supply Products - Abedin Trade Point' );
			update_post_meta( $shop_id, '_atp_meta_description', "Browse published supply categories and product specifications. Build a quote list or upload your organisation's purchase requirements." );
		}

		// Product links: /products/<category>/ and /product/<product>/.
		$permalinks                  = (array) get_option( 'woocommerce_permalinks', array() );
		$permalinks['category_base'] = 'products';
		$permalinks['product_base']  = 'product';
		update_option( 'woocommerce_permalinks', $permalinks );
		update_option( 'woocommerce_category_archive_display', '' );
		update_option( 'woocommerce_coming_soon', 'no' );
	} else {
		$report[] = __( 'WooCommerce is not active, so categories were not created.', 'atp-quote' );
	}

	if ( ! get_option( 'permalink_structure' ) ) {
		update_option( 'permalink_structure', '/%postname%/' );
		$report[] = __( 'Permalinks set to "Post name".', 'atp-quote' );
	}
	update_option( 'blogname', 'Abedin Trade Point' );
	update_option( 'blogdescription', 'Bulk supplies for businesses, institutions and projects in Bangladesh' );
	flush_rewrite_rules();
	return $report;
}

/**
 * Ten groups and 31 categories with their copy.
 */
function atp_setup_categories() {
	$report = array();
	$data   = atp_content_json( 'categories.json' );
	$groups = array();

	foreach ( (array) $data['groups'] as $i => $g ) {
		$term = get_term_by( 'slug', $g['slug'], 'product_cat' );
		if ( ! $term ) {
			// Older installs created groups without a slug; adopt them by name.
			$by_name = get_term_by( 'name', $g['name'], 'product_cat' );
			if ( $by_name && 0 === (int) $by_name->parent ) {
				wp_update_term( $by_name->term_id, 'product_cat', array( 'slug' => $g['slug'] ) );
				$term = get_term( $by_name->term_id, 'product_cat' );
			}
		}
		if ( ! $term ) {
			$new = wp_insert_term( $g['name'], 'product_cat', array( 'slug' => $g['slug'] ) );
			if ( is_wp_error( $new ) ) {
				$report[] = $new->get_error_message();
				continue;
			}
			$term = get_term( $new['term_id'], 'product_cat' );
			/* translators: %s: group name */
			$report[] = sprintf( __( 'Created group %s.', 'atp-quote' ), $g['name'] );
		}
		update_term_meta( $term->term_id, 'order', $i + 1 );
		$groups[ $g['slug'] ] = (int) $term->term_id;
	}

	$created = 0;
	$updated = 0;
	foreach ( (array) $data['categories'] as $i => $c ) {
		$parent = $groups[ $c['group'] ] ?? 0;
		$term   = get_term_by( 'slug', $c['slug'], 'product_cat' );
		if ( $term ) {
			if ( $parent && (int) $term->parent !== $parent ) {
				wp_update_term( $term->term_id, 'product_cat', array( 'parent' => $parent ) );
			}
			if ( '' === trim( $term->description ) ) {
				wp_update_term( $term->term_id, 'product_cat', array( 'description' => $c['intro'] ) );
			}
			$term_id = (int) $term->term_id;
			++$updated;
		} else {
			$new = wp_insert_term(
				$c['name'],
				'product_cat',
				array(
					'slug'        => $c['slug'],
					'description' => $c['intro'],
					'parent'      => $parent,
				)
			);
			if ( is_wp_error( $new ) ) {
				$report[] = $new->get_error_message();
				continue;
			}
			$term_id = (int) $new['term_id'];
			if ( empty( $c['published'] ) ) {
				update_term_meta( $term_id, 'atp_hidden', '1' );
			}
			++$created;
		}
		$fill = array(
			'atp_h1'                 => $c['h1'] ?? '',
			'atp_requirement_prompt' => $c['prompt'] ?? '',
			'atp_quote_label'        => $c['button'] ?? '',
			'atp_meta_title'         => $c['meta_title'] ?? '',
			'atp_meta_description'   => $c['meta_description'] ?? '',
			'atp_card_copy'          => $c['card'] ?? '',
			'atp_release'            => $c['release'] ?? '',
		);
		foreach ( $fill as $key => $val ) {
			if ( '' !== $val && '' === (string) get_term_meta( $term_id, $key, true ) ) {
				update_term_meta( $term_id, $key, $val );
			}
		}
		update_term_meta( $term_id, 'order', $i + 1 );
	}
	// Rebuild the cached category tree so group pages list their categories.
	delete_option( 'product_cat_children' );
	clean_taxonomy_cache( 'product_cat' );
	_get_term_hierarchy( 'product_cat' );
	/* translators: 1: created count, 2: updated count */
	$report[] = sprintf( __( 'Categories: %1$d created, %2$d already existed (missing copy filled in).', 'atp-quote' ), $created, $updated );
	return $report;
}

/**
 * IDs of sample products.
 *
 * @return int[]
 */
function atp_sample_product_ids() {
	return get_posts(
		array(
			'post_type'      => 'product',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_key'       => '_atp_sample', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
}

/**
 * Create the sample products.
 */
function atp_add_sample_products() {
	if ( ! class_exists( 'WC_Product_Simple' ) ) {
		return array( __( 'WooCommerce is not active.', 'atp-quote' ) );
	}
	$report = array();
	$count  = 0;
	foreach ( (array) atp_content_json( 'products.json' ) as $i => $p ) {
		if ( wc_get_product_id_by_sku( $p['sku'] ) ) {
			continue;
		}
		$cat = get_term_by( 'slug', $p['category'], 'product_cat' );
		if ( ! $cat ) {
			/* translators: %s: product name */
			$report[] = sprintf( __( 'Skipped %s: run "Create pages and categories" first.', 'atp-quote' ), $p['name'] );
			continue;
		}
		$product = $p['sizes'] ? new WC_Product_Variable() : new WC_Product_Simple();
		$product->set_name( $p['name'] );
		$product->set_status( 'publish' );
		$product->set_sku( $p['sku'] );
		$product->set_description( '<p>' . esc_html( $p['description'] ) . '</p><p>' . esc_html__( 'Available materials, branding options and delivery timing will be confirmed in your quotation.', 'atp-quote' ) . '</p>' );
		$product->set_short_description( $p['description'] );
		$product->set_category_ids( array( (int) $cat->term_id ) );
		$product->set_featured( ! empty( $p['featured'] ) );
		$product->set_menu_order( $i );
		$product->update_meta_data( '_atp_unit', $p['unit'] );
		$product->update_meta_data( '_atp_spec_prompt', $p['spec_prompt'] );
		$product->update_meta_data( '_atp_sample', '1' );
		if ( $p['pack'] ) {
			$product->update_meta_data( '_atp_pack_contents', $p['pack'] );
		}
		if ( $p['sizes'] ) {
			$attr = new WC_Product_Attribute();
			$attr->set_name( 'Size' );
			$attr->set_options( $p['sizes'] );
			$attr->set_visible( true );
			$attr->set_variation( true );
			$product->set_attributes( array( $attr ) );
		}
		$id = $product->save();
		if ( $p['sizes'] ) {
			foreach ( $p['sizes'] as $size ) {
				$v = new WC_Product_Variation();
				$v->set_parent_id( $id );
				$v->set_attributes( array( 'size' => $size ) );
				$v->set_status( 'publish' );
				$v->save();
			}
			WC_Product_Variable::sync( $id );
		}
		++$count;
	}
	/* translators: %d: number of products */
	$report[] = sprintf( __( 'Added %d sample products.', 'atp-quote' ), $count );
	return $report;
}

/**
 * Delete the sample products permanently.
 */
function atp_remove_sample_products() {
	$ids = atp_sample_product_ids();
	foreach ( $ids as $id ) {
		$product = wc_get_product( $id );
		if ( $product ) {
			foreach ( $product->get_children() as $child ) {
				wp_delete_post( $child, true );
			}
		}
		wp_delete_post( $id, true );
	}
	/* translators: %d: number of products */
	return array( sprintf( __( 'Removed %d sample products.', 'atp-quote' ), count( $ids ) ) );
}

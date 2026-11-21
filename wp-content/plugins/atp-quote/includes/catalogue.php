<?php
/**
 * Catalogue structure: the ten browsing groups, 31 categories with a
 * "Published on website" switch, the Products hub directory, the Products
 * menu, category page copy and search synonyms.
 *
 * Unpublished categories (and products that only sit in them) stay out of
 * public menus, listings, search and sitemaps. Staff can still preview them.
 *
 * @package atp-quote
 */

defined( 'ABSPATH' ) || exit;

/**
 * IDs of product categories that are not published on the website.
 * A child of an unpublished group is also unpublished.
 *
 * @return int[]
 */
function atp_hidden_cat_ids() {
	static $ids = null;
	if ( null !== $ids ) {
		return $ids;
	}
	$ids = array(); // Set first: the get_terms call below runs our own filter.
	if ( ! taxonomy_exists( 'product_cat' ) ) {
		return $ids;
	}
	$hidden = get_terms(
		array(
			'taxonomy'      => 'product_cat',
			'hide_empty'    => false,
			'fields'        => 'ids',
			'meta_query'     => array( array( 'key' => 'atp_hidden', 'value' => '1' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
			'atp_unfiltered' => true,
		)
	);
	$all = is_wp_error( $hidden ) ? array() : array_map( 'intval', $hidden );
	foreach ( $all as $id ) {
		$children = get_term_children( $id, 'product_cat' );
		if ( ! is_wp_error( $children ) ) {
			$all = array_merge( $all, array_map( 'intval', $children ) );
		}
	}
	// A group whose categories are all drafts has nothing to show either.
	$groups = get_terms( array( 'taxonomy' => 'product_cat', 'parent' => 0, 'hide_empty' => false, 'fields' => 'ids', 'atp_unfiltered' => true ) );
	foreach ( is_wp_error( $groups ) ? array() : $groups as $gid ) {
		$children = get_terms( array( 'taxonomy' => 'product_cat', 'parent' => (int) $gid, 'hide_empty' => false, 'fields' => 'ids', 'atp_unfiltered' => true ) );
		if ( ! is_wp_error( $children ) && $children && ! array_diff( array_map( 'intval', $children ), $all ) ) {
			$all[] = (int) $gid;
		}
	}
	$ids = array_values( array_unique( $all ) );
	return $ids;
}

/**
 * Forget the cached list after a category changes.
 */
function atp_reset_hidden_cache() {
	// The static cache lives for one request only; nothing persistent to clear.
	wp_cache_delete( 'last_changed', 'terms' );
}

/**
 * Whether the visitor should see unpublished categories (staff preview).
 */
function atp_can_preview_catalogue() {
	return current_user_can( 'edit_products' );
}

/**
 * True for front-end requests where unpublished categories must be hidden.
 */
function atp_filter_catalogue() {
	if ( ( is_admin() && ! wp_doing_ajax() ) || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
		return false;
	}
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST && current_user_can( 'edit_products' ) ) {
		return false; // Block editor and staff tools.
	}
	return true;
}

/* ---------- Hide unpublished categories from public lists ---------- */

add_filter( 'get_terms_args', function ( $args, $taxonomies ) {
	if ( ! empty( $args['atp_unfiltered'] ) || ! in_array( 'product_cat', (array) $taxonomies, true ) || ! atp_filter_catalogue() ) {
		return $args;
	}
	// Lookups of a specific term (get_term_by, term_exists, object terms)
	// must still find drafts; only public listings are filtered.
	if ( ! empty( $args['slug'] ) || ! empty( $args['name'] ) || ! empty( $args['include'] ) || ! empty( $args['object_ids'] ) || ! empty( $args['term_taxonomy_id'] ) || 'all' === ( $args['get'] ?? '' ) ) {
		return $args;
	}
	$hidden = atp_hidden_cat_ids();
	if ( $hidden ) {
		$args['exclude'] = array_merge( wp_parse_id_list( $args['exclude'] ?? array() ), $hidden );
	}
	return $args;
}, 10, 2 );

/**
 * Tax query that removes products in unpublished categories, unless the
 * product is also in a published one.
 */
function atp_hidden_products_tax_query() {
	$hidden = atp_hidden_cat_ids();
	if ( ! $hidden ) {
		return array();
	}
	$visible = get_terms(
		array(
			'taxonomy'       => 'product_cat',
			'hide_empty'     => false,
			'fields'         => 'ids',
			'exclude'        => $hidden,
			'atp_unfiltered' => true,
		)
	);
	if ( is_wp_error( $visible ) || ! $visible ) {
		return array( array( 'taxonomy' => 'product_cat', 'operator' => 'NOT EXISTS' ) );
	}
	return array(
		array(
			'taxonomy'         => 'product_cat',
			'field'            => 'term_id',
			'terms'            => array_map( 'intval', $visible ),
			'include_children' => false,
			'operator'         => 'IN',
		),
	);
}

add_action( 'pre_get_posts', function ( $q ) {
	if ( is_admin() || ! atp_filter_catalogue() || atp_can_preview_catalogue() ) {
		return;
	}
	$pt         = (array) $q->get( 'post_type' );
	$is_product = in_array( 'product', $pt, true ) || $q->is_tax( get_object_taxonomies( 'product' ) ) || ( $q->is_main_query() && $q->is_post_type_archive( 'product' ) );
	if ( ! $is_product && ! ( $q->is_search() && empty( $pt[0] ) ) ) {
		return;
	}
	$tq = atp_hidden_products_tax_query();
	if ( ! $tq ) {
		return;
	}
	if ( $q->is_search() && ! $is_product ) {
		return; // General site search: products filtered in posts_where below.
	}
	$existing = (array) $q->get( 'tax_query' );
	$q->set( 'tax_query', array_merge( $existing, $tq ) );
} );

// WooCommerce shortcodes and blocks build their own queries.
add_filter( 'woocommerce_shortcode_products_query', function ( $args ) {
	if ( atp_can_preview_catalogue() ) {
		return $args;
	}
	$tq = atp_hidden_products_tax_query();
	if ( $tq ) {
		$args['tax_query'] = array_merge( (array) ( $args['tax_query'] ?? array() ), $tq ); // phpcs:ignore WordPress.DB.SlowDBQuery
	}
	return $args;
} );

add_filter( 'woocommerce_related_products', function ( $ids ) {
	if ( atp_can_preview_catalogue() ) {
		return $ids;
	}
	return array_values( array_filter( $ids, 'atp_product_is_public' ) );
} );

/**
 * Whether a product sits in at least one published category.
 *
 * @param int $product_id Product ID.
 */
function atp_product_is_public( $product_id ) {
	$hidden = atp_hidden_cat_ids();
	if ( ! $hidden ) {
		return true;
	}
	$cats = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids', 'atp_unfiltered' => true ) );
	if ( is_wp_error( $cats ) || ! $cats ) {
		return true;
	}
	return (bool) array_diff( array_map( 'intval', $cats ), $hidden );
}

// Direct visits to an unpublished category or product: not found for the public.
add_action( 'template_redirect', function () {
	if ( atp_can_preview_catalogue() || ! function_exists( 'is_product_category' ) ) {
		return;
	}
	$hide = false;
	if ( is_product_category() ) {
		$hide = in_array( (int) get_queried_object_id(), atp_hidden_cat_ids(), true );
	} elseif ( is_singular( 'product' ) ) {
		$hide = ! atp_product_is_public( get_queried_object_id() );
	}
	if ( $hide ) {
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
	}
}, 5 );

// Products added to the quote list must be public too.
add_filter( 'atp_can_add_product', function ( $ok, $product_id ) {
	return $ok && ( atp_can_preview_catalogue() || atp_product_is_public( $product_id ) );
}, 10, 2 );

// Sitemaps.
add_filter( 'wp_sitemaps_taxonomies_query_args', function ( $args, $taxonomy ) {
	if ( 'product_cat' === $taxonomy ) {
		$args['exclude'] = array_merge( wp_parse_id_list( $args['exclude'] ?? array() ), atp_hidden_cat_ids() );
	}
	return $args;
}, 10, 2 );
add_filter( 'wp_sitemaps_posts_query_args', function ( $args, $post_type ) {
	if ( 'product' === $post_type ) {
		$tq = atp_hidden_products_tax_query();
		if ( $tq ) {
			$args['tax_query'] = $tq; // phpcs:ignore WordPress.DB.SlowDBQuery
		}
	}
	if ( 'page' === $post_type ) {
		$quote = get_page_by_path( 'request-a-quote' );
		if ( $quote ) {
			$args['post__not_in'] = array_merge( (array) ( $args['post__not_in'] ?? array() ), array( $quote->ID ) );
		}
	}
	return $args;
}, 10, 2 );

/* ---------- Admin: "Published on website" switch ---------- */

add_action( 'product_cat_add_form_fields', function () {
	?>
	<div class="form-field">
		<label><input type="checkbox" name="atp_published" value="1"> <?php esc_html_e( 'Published on website', 'atp-quote' ); ?></label>
		<p><?php esc_html_e( 'Leave unticked until the products, specifications and supply arrangements are ready. Unpublished categories are hidden from menus, listings and search.', 'atp-quote' ); ?></p>
	</div>
	<?php
	wp_nonce_field( 'atp_cat_new', 'atp_cat_new_nonce' );
} );

add_action( 'product_cat_edit_form_fields', function ( $term ) {
	$published = '1' !== get_term_meta( $term->term_id, 'atp_hidden', true );
	?>
	<tr class="form-field">
		<th scope="row"><?php esc_html_e( 'Published on website', 'atp-quote' ); ?></th>
		<td><label><input type="checkbox" name="atp_published" value="1" <?php checked( $published ); ?>> <?php esc_html_e( 'Show this category to visitors', 'atp-quote' ); ?></label>
		<p class="description"><?php esc_html_e( 'Untick to keep the category as a draft. Staff who are logged in can still preview it.', 'atp-quote' ); ?></p></td>
	</tr>
	<tr class="form-field">
		<th scope="row"><label for="atp_h1"><?php esc_html_e( 'Page heading (H1)', 'atp-quote' ); ?></label></th>
		<td><input id="atp_h1" type="text" name="atp_h1" value="<?php echo esc_attr( get_term_meta( $term->term_id, 'atp_h1', true ) ); ?>"></td>
	</tr>
	<tr class="form-field">
		<th scope="row"><label for="atp_meta_title"><?php esc_html_e( 'Meta title', 'atp-quote' ); ?></label></th>
		<td><input id="atp_meta_title" type="text" name="atp_meta_title" value="<?php echo esc_attr( get_term_meta( $term->term_id, 'atp_meta_title', true ) ); ?>"></td>
	</tr>
	<tr class="form-field">
		<th scope="row"><label for="atp_meta_description"><?php esc_html_e( 'Meta description', 'atp-quote' ); ?></label></th>
		<td><textarea id="atp_meta_description" name="atp_meta_description" rows="2"><?php echo esc_textarea( get_term_meta( $term->term_id, 'atp_meta_description', true ) ); ?></textarea></td>
	</tr>
	<?php
}, 5 );

add_action( 'created_product_cat', function ( $term_id ) {
	if ( ! isset( $_POST['atp_cat_new_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['atp_cat_new_nonce'] ), 'atp_cat_new' ) ) {
		return;
	}
	if ( empty( $_POST['atp_published'] ) ) {
		update_term_meta( $term_id, 'atp_hidden', '1' );
	}
} );

add_action( 'edited_product_cat', function ( $term_id ) {
	if ( ! isset( $_POST['atp_cat_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['atp_cat_nonce'] ), 'atp_cat_' . $term_id ) || ! current_user_can( 'manage_product_terms' ) ) {
		return;
	}
	if ( empty( $_POST['atp_published'] ) ) {
		update_term_meta( $term_id, 'atp_hidden', '1' );
	} else {
		delete_term_meta( $term_id, 'atp_hidden' );
	}
	foreach ( array( 'atp_h1', 'atp_meta_title' ) as $key ) {
		update_term_meta( $term_id, $key, isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '' );
	}
	update_term_meta( $term_id, 'atp_meta_description', isset( $_POST['atp_meta_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['atp_meta_description'] ) ) : '' );
} );

add_filter( 'manage_edit-product_cat_columns', function ( $cols ) {
	$cols['atp_published'] = __( 'Website', 'atp-quote' );
	return $cols;
} );
add_filter( 'manage_product_cat_custom_column', function ( $out, $col, $term_id ) {
	if ( 'atp_published' === $col ) {
		$out = in_array( (int) $term_id, atp_hidden_cat_ids(), true )
			? '<span style="color:#996800">' . esc_html__( 'Draft', 'atp-quote' ) . '</span>'
			: '<span style="color:#1a7f37">' . esc_html__( 'Published', 'atp-quote' ) . '</span>';
	}
	return $out;
}, 10, 3 );

/* ---------- Products hub, group pages and category copy ---------- */

/**
 * Published groups (top-level categories) with their published children.
 *
 * @return array<int, array{term: WP_Term, children: WP_Term[]}>
 */
function atp_catalogue_tree() {
	$out    = array();
	$groups = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'parent'     => 0,
			'hide_empty' => false,
			'orderby'    => 'name',
			'exclude'    => array_filter( array( (int) get_option( 'default_product_cat' ) ) ),
		)
	);
	if ( is_wp_error( $groups ) ) {
		return $out;
	}
	usort( $groups, fn( $a, $b ) => (int) get_term_meta( $a->term_id, 'order', true ) <=> (int) get_term_meta( $b->term_id, 'order', true ) );
	foreach ( $groups as $g ) {
		$children = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'parent'     => $g->term_id,
				'hide_empty' => false,
			)
		);
		if ( is_wp_error( $children ) || ! $children ) {
			continue;
		}
		usort( $children, fn( $a, $b ) => (int) get_term_meta( $a->term_id, 'order', true ) <=> (int) get_term_meta( $b->term_id, 'order', true ) );
		$out[] = array(
			'term'     => $g,
			'children' => $children,
		);
	}
	return $out;
}

/**
 * Category cards for a list of terms.
 *
 * @param WP_Term[] $terms Categories.
 */
function atp_category_cards( $terms ) {
	$html = '<ul class="atp-cat-grid">';
	foreach ( $terms as $t ) {
		$summary = get_term_meta( $t->term_id, 'atp_card_copy', true );
		if ( ! $summary ) {
			$summary = wp_trim_words( wp_strip_all_tags( $t->description ), 18 );
		}
		$html .= '<li class="atp-cat-card"><a href="' . esc_url( get_term_link( $t ) ) . '"><span class="atp-cat-card__name">' . esc_html( $t->name ) . '</span>';
		if ( $summary ) {
			$html .= '<span class="atp-cat-card__text">' . esc_html( $summary ) . '</span>';
		}
		$html .= '<span class="atp-cat-card__cta">' . esc_html__( 'Explore Category', 'atp-quote' ) . ' →</span></a></li>';
	}
	return $html . '</ul>';
}

/**
 * [atp_category_directory]: every published group with its categories.
 */
function atp_category_directory() {
	$tree = atp_catalogue_tree();
	if ( ! $tree ) {
		return '';
	}
	$html = '<div class="atp-directory" id="all-categories">';
	$html .= '<nav class="atp-directory__jump" aria-label="' . esc_attr__( 'Product groups', 'atp-quote' ) . '"><ul>';
	foreach ( $tree as $g ) {
		$html .= '<li><a href="#group-' . esc_attr( $g['term']->slug ) . '">' . esc_html( $g['term']->name ) . '</a></li>';
	}
	$html .= '</ul></nav>';
	foreach ( $tree as $g ) {
		$html .= '<section class="atp-directory__group" id="group-' . esc_attr( $g['term']->slug ) . '">';
		$html .= '<h2><a href="' . esc_url( get_term_link( $g['term'] ) ) . '">' . esc_html( $g['term']->name ) . '</a></h2>';
		$html .= atp_category_cards( $g['children'] );
		$html .= '</section>';
	}
	return $html . '</div>';
}
add_shortcode( 'atp_category_directory', 'atp_category_directory' );

/**
 * Purchase-list prompt used on the product hub and category pages.
 */
function atp_purchase_list_prompt() {
	return '<section class="atp-sourcing">'
		. '<h2>' . esc_html__( 'Send Your Full Requirement', 'atp-quote' ) . '</h2>'
		. '<p>' . esc_html__( 'Your enquiry can include several categories. Upload a purchase list or add individual items, then share the delivery location and any essential specifications.', 'atp-quote' ) . '</p>'
		. '<p><a class="wp-element-button" href="' . esc_url( add_query_arg( 'mode', 'upload', atp_quote_page_url() ) ) . '">' . esc_html__( 'Upload Purchase List', 'atp-quote' ) . '</a></p>'
		. '</section>';
}

add_action( 'plugins_loaded', function () {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	// The products hub keeps a calm layout: no sorting dropdown or result count.
	remove_action( 'woocommerce_before_shop_loop', 'woocommerce_result_count', 20 );
	remove_action( 'woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30 );

	add_filter( 'woocommerce_page_title', function ( $title ) {
		if ( is_search() ) {
			return $title;
		}
		if ( is_shop() ) {
			return __( 'Bulk Supply Products', 'atp-quote' );
		}
		if ( is_product_category() ) {
			$h1 = get_term_meta( get_queried_object_id(), 'atp_h1', true );
			return $h1 ? $h1 : $title;
		}
		return $title;
	} );

	// Products hub: intro, directory of groups, purchase-list prompt, then products.
	add_action( 'woocommerce_archive_description', function () {
		if ( ! is_shop() || is_search() || is_paged() ) {
			return;
		}
		echo '<div class="atp-hub">';
		echo '<p class="atp-hub__intro">' . esc_html__( 'Browse published supply categories and product specifications. Build a quote list or upload your organisation\'s purchase requirements.', 'atp-quote' ) . '</p>';
		echo atp_category_directory(); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside.
		echo atp_purchase_list_prompt(); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<h2 class="atp-hub__products">' . esc_html__( 'Start Your Quote List', 'atp-quote' ) . '</h2>';
		echo '<p>' . esc_html__( 'Select the products you are interested in, add the quantities you need and send them together in one request.', 'atp-quote' ) . '</p>';
		echo '</div>';
	}, 20 );

	// Group page (a top-level category): show its categories above any products.
	add_action( 'woocommerce_archive_description', function () {
		if ( ! is_product_category() ) {
			return;
		}
		$term     = get_queried_object();
		$children = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'parent'     => $term->term_id,
				'hide_empty' => false,
			)
		);
		if ( ! is_wp_error( $children ) && $children ) {
			usort( $children, fn( $a, $b ) => (int) get_term_meta( $a->term_id, 'order', true ) <=> (int) get_term_meta( $b->term_id, 'order', true ) );
			echo atp_category_cards( $children ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		if ( atp_can_preview_catalogue() && in_array( (int) $term->term_id, atp_hidden_cat_ids(), true ) ) {
			echo '<p class="atp-preview-note">' . esc_html__( 'Draft category: only logged-in staff can see this page. Tick "Published on website" on the category to make it public.', 'atp-quote' ) . '</p>';
		}
		$group = $term->parent ? get_term( $term->parent, 'product_cat' ) : $term;
		if ( $group && ! is_wp_error( $group ) && 'healthcare-laboratory' === $group->slug ) {
			echo '<p class="atp-notice">' . esc_html__( 'Only the specific items listed on this website are offered for enquiry. Availability, product details, documentation and supply terms are confirmed during the quotation process.', 'atp-quote' ) . '</p>';
		}
		if ( $term->parent ) {
			echo '<h2 class="atp-cat-products">' . esc_html__( 'Explore Products', 'atp-quote' ) . '</h2>';
		}
	}, 20 );

	// No products yet in a published category: a helpful message, not an empty shop notice.
	add_action( 'woocommerce_no_products_found', function () {
		if ( is_product_category() ) {
			remove_action( 'woocommerce_no_products_found', 'wc_no_products_found' );
			echo '<p class="atp-empty">' . esc_html__( 'Products for this category are being added. Send your requirement now and we will review it for you.', 'atp-quote' ) . '</p>';
		}
	}, 5 );

	// Sibling categories and the purchase-list prompt after the category footer.
	add_action( 'woocommerce_after_main_content', function () {
		if ( ! is_product_category() ) {
			return;
		}
		$term = get_queried_object();
		if ( $term->parent ) {
			$siblings = get_terms(
				array(
					'taxonomy'   => 'product_cat',
					'parent'     => $term->parent,
					'hide_empty' => false,
					'exclude'    => array( $term->term_id ),
				)
			);
			if ( ! is_wp_error( $siblings ) && $siblings ) {
				$group = get_term( $term->parent, 'product_cat' );
				/* translators: %s: group name */
				echo '<section class="atp-related-cats"><h2>' . esc_html( sprintf( __( 'More in %s', 'atp-quote' ), $group->name ) ) . '</h2>' . atp_category_cards( $siblings ) . '</section>'; // phpcs:ignore WordPress.Security.EscapeOutput
			}
		}
		echo atp_purchase_list_prompt(); // phpcs:ignore WordPress.Security.EscapeOutput
	}, 5 );
} );

/* ---------- Header: Products menu with the ten groups ---------- */

add_filter( 'block_core_navigation_render_inner_blocks', function ( $inner_blocks ) {
	if ( is_admin() || ! taxonomy_exists( 'product_cat' ) ) {
		return $inner_blocks;
	}
	$parsed  = array();
	$changed = false;
	foreach ( $inner_blocks as $block ) {
		$pb = $block->parsed_block;
		if ( 'core/navigation-link' === $block->name && isset( $pb['attrs']['url'] ) && '/products/' === wp_parse_url( $pb['attrs']['url'], PHP_URL_PATH ) && empty( $pb['attrs']['atpPlain'] ) ) {
			$children = array(
				array(
					'blockName'    => 'core/navigation-link',
					'attrs'        => array( 'label' => __( 'View All Categories', 'atp-quote' ), 'url' => $pb['attrs']['url'] ),
					'innerBlocks'  => array(),
					'innerHTML'    => '',
					'innerContent' => array(),
				),
			);
			foreach ( atp_catalogue_tree() as $g ) {
				$children[] = array(
					'blockName'    => 'core/navigation-link',
					'attrs'        => array( 'label' => $g['term']->name, 'url' => get_term_link( $g['term'] ) ),
					'innerBlocks'  => array(),
					'innerHTML'    => '',
					'innerContent' => array(),
				);
			}
			$pb = array(
				'blockName'    => 'core/navigation-submenu',
				'attrs'        => array( 'label' => $pb['attrs']['label'] ?? __( 'Products', 'atp-quote' ), 'url' => $pb['attrs']['url'], 'className' => 'atp-products-menu' ),
				'innerBlocks'  => $children,
				'innerHTML'    => '',
				'innerContent' => array_fill( 0, count( $children ), null ),
			);
			$changed = true;
		}
		$parsed[] = $pb;
	}
	if ( ! $changed ) {
		return $inner_blocks;
	}
	$context = array();
	foreach ( $inner_blocks as $block ) {
		$context = $block->context;
		break;
	}
	return new WP_Block_List( $parsed, $context );
} );

/* ---------- Search synonyms ---------- */

/**
 * Synonym groups from the plan. Filterable for more.
 */
function atp_search_synonyms() {
	return apply_filters(
		'atp_search_synonyms',
		array(
			array( 't-shirt', 't shirt', 'tshirt', 'tee' ),
			array( 'exercise book', 'khata', 'notebook' ),
			array( 'backpack', 'school bag', 'rucksack' ),
			array( 'corporate gift', 'promotional item', 'promotional', 'gift' ),
			array( 'jute bag', 'tote bag', 'shopping bag' ),
			array( 'hoodie', 'hoody', 'sweatshirt' ),
		)
	);
}

/**
 * The search terms to OR together for a phrase.
 *
 * @param string $s Search phrase.
 * @return string[]
 */
function atp_search_variants( $s ) {
	$s        = strtolower( trim( $s ) );
	$variants = array( $s );
	foreach ( atp_search_synonyms() as $group ) {
		foreach ( $group as $word ) {
			if ( preg_match( '/(^|\W)' . preg_quote( $word, '/' ) . 's?($|\W)/', $s ) ) {
				foreach ( $group as $alt ) {
					$variants[] = trim( preg_replace( '/(^|\W)' . preg_quote( $word, '/' ) . '(s?)($|\W)/', '$1' . $alt . '$2$3', $s ) );
				}
			}
		}
	}
	return array_values( array_unique( array_filter( $variants ) ) );
}

add_filter( 'posts_search', function ( $search, $q ) {
	if ( is_admin() || ! $q->is_search() || '' === (string) $q->get( 's' ) ) {
		return $search;
	}
	$pt = (array) $q->get( 'post_type' );
	if ( ! in_array( 'product', $pt, true ) ) {
		return $search;
	}
	$variants = atp_search_variants( (string) $q->get( 's' ) );
	if ( count( $variants ) < 2 ) {
		return $search;
	}
	global $wpdb;
	$or = array();
	foreach ( $variants as $v ) {
		$like = '%' . $wpdb->esc_like( $v ) . '%';
		$or[] = $wpdb->prepare( "({$wpdb->posts}.post_title LIKE %s OR {$wpdb->posts}.post_excerpt LIKE %s OR {$wpdb->posts}.post_content LIKE %s)", $like, $like, $like );
	}
	return ' AND (' . implode( ' OR ', $or ) . ') ';
}, 20, 2 );

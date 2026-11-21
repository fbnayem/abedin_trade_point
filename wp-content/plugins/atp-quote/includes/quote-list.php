<?php
/**
 * Guest quote list (the "quote basket").
 *
 * The list is kept server-side in a transient keyed by a random cookie, so it
 * survives page changes without an account and works with page caching.
 *
 * @package atp-quote
 */

defined( 'ABSPATH' ) || exit;

const ATP_LIST_COOKIE = 'atp_quote_list';
const ATP_LIST_TTL    = 14 * DAY_IN_SECONDS;
const ATP_LIST_MAX    = 100;

/**
 * Units a product can be supplied in.
 */
function atp_units() {
	return array(
		'piece' => __( 'Piece', 'atp-quote' ),
		'pack'  => __( 'Pack', 'atp-quote' ),
		'set'   => __( 'Set', 'atp-quote' ),
		'ream'  => __( 'Ream', 'atp-quote' ),
		'roll'  => __( 'Roll', 'atp-quote' ),
		'pair'  => __( 'Pair', 'atp-quote' ),
		'box'   => __( 'Box', 'atp-quote' ),
		'metre' => __( 'Metre', 'atp-quote' ),
		'kg'    => __( 'Kilogram', 'atp-quote' ),
	);
}

/**
 * Units that may take decimal quantities.
 */
function atp_decimal_units() {
	return array( 'metre', 'kg' );
}

/**
 * Current list token, creating one when $create is true.
 *
 * @param bool $create Create a token and cookie if missing.
 * @return string Empty string if none.
 */
function atp_list_token( $create = false ) {
	static $token = null;
	if ( null !== $token ) {
		return $token;
	}
	$raw = isset( $_COOKIE[ ATP_LIST_COOKIE ] ) ? sanitize_key( wp_unslash( $_COOKIE[ ATP_LIST_COOKIE ] ) ) : '';
	if ( preg_match( '/^[a-z0-9]{32}$/', $raw ) ) {
		$token = $raw;
		return $token;
	}
	if ( ! $create ) {
		return '';
	}
	$token = strtolower( wp_generate_password( 32, false, false ) );
	if ( ! headers_sent() ) {
		setcookie(
			ATP_LIST_COOKIE,
			$token,
			array(
				'expires'  => time() + ATP_LIST_TTL,
				'path'     => COOKIEPATH ? COOKIEPATH : '/',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}
	return $token;
}

/**
 * Get the list items.
 *
 * @return array<string,array> Keyed by line key.
 */
function atp_list_get() {
	$token = atp_list_token();
	if ( ! $token ) {
		return array();
	}
	$items = get_transient( 'atp_ql_' . $token );
	return is_array( $items ) ? $items : array();
}

/**
 * Save the list items.
 *
 * @param array $items Items.
 */
function atp_list_save( array $items ) {
	$token = atp_list_token( true );
	if ( empty( $items ) ) {
		delete_transient( 'atp_ql_' . $token );
		return;
	}
	set_transient( 'atp_ql_' . $token, $items, ATP_LIST_TTL );
}

/**
 * Validate and normalise a quantity for a unit.
 *
 * @param mixed  $qty  Raw quantity.
 * @param string $unit Unit key.
 * @return float|WP_Error
 */
function atp_normalise_qty( $qty, $unit ) {
	if ( ! is_numeric( $qty ) || (float) $qty <= 0 ) {
		return new WP_Error( 'atp_qty', __( 'Enter a quantity greater than zero.', 'atp-quote' ) );
	}
	$qty = (float) $qty;
	if ( ! in_array( $unit, atp_decimal_units(), true ) && floor( $qty ) !== $qty ) {
		return new WP_Error( 'atp_qty', __( 'Piece counts must be whole numbers.', 'atp-quote' ) );
	}
	if ( $qty > 10000000 ) {
		return new WP_Error( 'atp_qty', __( 'That quantity is too large. Please contact us directly.', 'atp-quote' ) );
	}
	return $qty;
}

/**
 * Add a product line. Lines with the same product, variation and options merge.
 *
 * @param int    $product_id Product or variation parent ID.
 * @param float  $qty        Quantity.
 * @param array  $options    Selected attribute values (label => value).
 * @param string $notes      Buyer notes for this line.
 * @return array|WP_Error The saved line.
 */
function atp_list_add( $product_id, $qty, array $options = array(), $notes = '' ) {
	$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
	if ( ! $product || 'publish' !== $product->get_status() ) {
		return new WP_Error( 'atp_product', __( 'This product is not available for enquiry.', 'atp-quote' ) );
	}
	$unit = atp_product_unit( $product );
	$qty  = atp_normalise_qty( $qty, $unit );
	if ( is_wp_error( $qty ) ) {
		return $qty;
	}
	$min = (float) $product->get_meta( '_atp_min_qty' );
	if ( $min > 0 && $qty < $min ) {
		/* translators: 1: minimum quantity, 2: unit */
		return new WP_Error( 'atp_min', sprintf( __( 'The minimum for this product is %1$s %2$s.', 'atp-quote' ), $min, $unit ) );
	}
	$clean_opts = array();
	foreach ( $options as $label => $value ) {
		$label = sanitize_text_field( (string) $label );
		$value = sanitize_text_field( (string) $value );
		if ( '' !== $label && '' !== $value ) {
			$clean_opts[ $label ] = $value;
		}
	}
	ksort( $clean_opts );
	$key   = md5( $product->get_id() . '|' . wp_json_encode( $clean_opts ) );
	$items = atp_list_get();
	if ( count( $items ) >= ATP_LIST_MAX && ! isset( $items[ $key ] ) ) {
		return new WP_Error( 'atp_full', __( 'Your quote list is full. Upload a purchase list for larger requirements.', 'atp-quote' ) );
	}
	if ( isset( $items[ $key ] ) ) {
		$items[ $key ]['qty'] += $qty;
		if ( $notes ) {
			$items[ $key ]['notes'] = trim( $items[ $key ]['notes'] . "\n" . sanitize_textarea_field( $notes ) );
		}
	} else {
		$items[ $key ] = array(
			'key'        => $key,
			'product_id' => $product->get_id(),
			'name'       => $product->get_name(),
			'sku'        => $product->get_sku(),
			'unit'       => $unit,
			'pack'       => (string) $product->get_meta( '_atp_pack_contents' ),
			'options'    => $clean_opts,
			'qty'        => $qty,
			'notes'      => sanitize_textarea_field( $notes ),
		);
	}
	atp_list_save( $items );
	return $items[ $key ];
}

/**
 * Unit for a product (meta, defaulting to piece).
 *
 * @param WC_Product $product Product.
 */
function atp_product_unit( $product ) {
	$unit = (string) $product->get_meta( '_atp_unit' );
	return array_key_exists( $unit, atp_units() ) ? $unit : 'piece';
}

/* REST endpoints: GET list, POST add, PATCH update, DELETE remove. */
add_action( 'rest_api_init', function () {
	register_rest_route(
		'atp/v1',
		'/quote-list',
		array(
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => function () {
					return atp_list_response();
				},
			),
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true',
				'args'                => array(
					'product_id' => array( 'type' => 'integer', 'required' => true ),
					'qty'        => array( 'type' => 'number', 'required' => true ),
					'options'    => array( 'type' => 'object', 'default' => array() ),
					'notes'      => array( 'type' => 'string', 'default' => '' ),
				),
				'callback'            => function ( WP_REST_Request $req ) {
					$line = atp_list_add( (int) $req['product_id'], $req['qty'], (array) $req['options'], (string) $req['notes'] );
					if ( is_wp_error( $line ) ) {
						$line->add_data( array( 'status' => 400 ) );
						return $line;
					}
					return atp_list_response();
				},
			),
			array(
				'methods'             => 'PATCH',
				'permission_callback' => '__return_true',
				'args'                => array(
					'key'   => array( 'type' => 'string', 'required' => true ),
					'qty'   => array( 'type' => 'number' ),
					'notes' => array( 'type' => 'string' ),
				),
				'callback'            => function ( WP_REST_Request $req ) {
					$items = atp_list_get();
					$key   = sanitize_key( $req['key'] );
					if ( ! isset( $items[ $key ] ) ) {
						return new WP_Error( 'atp_missing', __( 'That item is no longer in your quote list.', 'atp-quote' ), array( 'status' => 404 ) );
					}
					if ( null !== $req['qty'] ) {
						$qty = atp_normalise_qty( $req['qty'], $items[ $key ]['unit'] );
						if ( is_wp_error( $qty ) ) {
							$qty->add_data( array( 'status' => 400 ) );
							return $qty;
						}
						$items[ $key ]['qty'] = $qty;
					}
					if ( null !== $req['notes'] ) {
						$items[ $key ]['notes'] = sanitize_textarea_field( $req['notes'] );
					}
					atp_list_save( $items );
					return atp_list_response();
				},
			),
			array(
				'methods'             => 'DELETE',
				'permission_callback' => '__return_true',
				'args'                => array( 'key' => array( 'type' => 'string', 'required' => true ) ),
				'callback'            => function ( WP_REST_Request $req ) {
					$items = atp_list_get();
					unset( $items[ sanitize_key( $req['key'] ) ] );
					atp_list_save( $items );
					return atp_list_response();
				},
			),
		)
	);
} );

/**
 * REST response body for the list.
 */
function atp_list_response() {
	$items = array_values( atp_list_get() );
	$units = atp_units();
	foreach ( $items as &$item ) {
		$item['unit_label'] = $units[ $item['unit'] ] ?? $item['unit'];
		$item['url']        = get_permalink( $item['product_id'] );
	}
	$res = rest_ensure_response( array( 'count' => count( $items ), 'items' => $items ) );
	$res->header( 'Cache-Control', 'no-store, private' );
	return $res;
}

/**
 * [atp_quote_count] Header link with live item count.
 */
add_shortcode( 'atp_quote_count', function () {
	$count = count( atp_list_get() );
	return sprintf(
		'<a class="atp-quote-pill" href="%1$s">%2$s <span class="atp-count" data-atp-count aria-live="polite">%3$d</span></a>',
		esc_url( atp_quote_page_url() ),
		esc_html__( 'Quote List', 'atp-quote' ),
		$count
	);
} );

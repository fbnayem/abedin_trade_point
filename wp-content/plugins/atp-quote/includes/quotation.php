<?php
/**
 * Quotation builder on each enquiry: priced lines, delivery, VAT and terms,
 * with a branded print view that staff save as PDF from the browser.
 *
 * @package atp-quote
 */

defined( 'ABSPATH' ) || exit;

/**
 * Stored quotation, or a draft built from the enquiry.
 *
 * @param int $id Enquiry ID.
 * @return array
 */
function atp_get_quotation( $id ) {
	$q = get_post_meta( $id, '_atp_quotation', true );
	if ( is_array( $q ) && ! empty( $q['lines'] ) ) {
		return $q;
	}
	$units = atp_units();
	$lines = array();
	foreach ( array_filter( (array) get_post_meta( $id, '_atp_items', true ) ) as $it ) {
		$spec = array();
		foreach ( (array) ( $it['options'] ?? array() ) as $k => $v ) {
			$spec[] = $k . ': ' . $v;
		}
		if ( ! empty( $it['notes'] ) ) {
			$spec[] = $it['notes'];
		}
		$lines[] = array(
			'item'  => $it['name'],
			'spec'  => implode( '; ', $spec ),
			'qty'   => (float) $it['qty'],
			'unit'  => $units[ $it['unit'] ] ?? $it['unit'],
			'price' => '',
		);
	}
	if ( ! $lines ) {
		$lines[] = array( 'item' => '', 'spec' => '', 'qty' => '', 'unit' => '', 'price' => '' );
	}
	return array(
		'lines'    => $lines,
		'delivery' => '',
		'vat'      => '',
		'validity' => '15 days',
		'payment'  => '',
		'lead'     => '',
		'notes'    => '',
		'rev'      => 0,
		'date'     => '',
	);
}

/**
 * Totals for a quotation.
 *
 * @param array $q Quotation.
 * @return array{subtotal: float, delivery: float, vat: float, total: float}
 */
function atp_quotation_totals( $q ) {
	$sub = 0.0;
	foreach ( $q['lines'] as $l ) {
		$sub += (float) $l['qty'] * (float) $l['price'];
	}
	$delivery = (float) $q['delivery'];
	$vat      = round( ( $sub + $delivery ) * (float) $q['vat'] / 100, 2 );
	return array(
		'subtotal' => $sub,
		'delivery' => $delivery,
		'vat'      => $vat,
		'total'    => $sub + $delivery + $vat,
	);
}

/**
 * Taka amount.
 *
 * @param float $n Amount.
 */
function atp_money( $n ) {
	return 'Tk ' . number_format( (float) $n, 2 );
}

/**
 * Quotation number shown on the document.
 *
 * @param int   $id Enquiry ID.
 * @param array $q  Quotation.
 */
function atp_quotation_number( $id, $q ) {
	return str_replace( 'ATP-', 'ATPQ-', (string) get_post_meta( $id, '_atp_reference', true ) ) . ( $q['rev'] > 1 ? '-R' . ( $q['rev'] - 1 ) : '' );
}

add_action( 'add_meta_boxes_atp_enquiry', function () {
	add_meta_box( 'atp_quotation', __( 'Quotation', 'atp-quote' ), 'atp_box_quotation', 'atp_enquiry', 'normal', 'default' );
} );

/**
 * Quotation meta box.
 *
 * @param WP_Post $post Enquiry.
 */
function atp_box_quotation( $post ) {
	$q   = atp_get_quotation( $post->ID );
	$t   = atp_quotation_totals( $q );
	$row = function ( $i, $l ) {
		?>
		<tr>
			<td><input type="text" name="atp_q[lines][<?php echo esc_attr( $i ); ?>][item]" value="<?php echo esc_attr( $l['item'] ); ?>" style="width:100%" aria-label="<?php esc_attr_e( 'Item', 'atp-quote' ); ?>"></td>
			<td><textarea name="atp_q[lines][<?php echo esc_attr( $i ); ?>][spec]" rows="2" style="width:100%" aria-label="<?php esc_attr_e( 'Specification', 'atp-quote' ); ?>"><?php echo esc_textarea( $l['spec'] ); ?></textarea></td>
			<td><input type="number" step="any" min="0" name="atp_q[lines][<?php echo esc_attr( $i ); ?>][qty]" value="<?php echo esc_attr( $l['qty'] ); ?>" style="width:80px" aria-label="<?php esc_attr_e( 'Quantity', 'atp-quote' ); ?>"></td>
			<td><input type="text" name="atp_q[lines][<?php echo esc_attr( $i ); ?>][unit]" value="<?php echo esc_attr( $l['unit'] ); ?>" style="width:80px" aria-label="<?php esc_attr_e( 'Unit', 'atp-quote' ); ?>"></td>
			<td><input type="number" step="0.01" min="0" name="atp_q[lines][<?php echo esc_attr( $i ); ?>][price]" value="<?php echo esc_attr( $l['price'] ); ?>" style="width:110px" aria-label="<?php esc_attr_e( 'Unit price (Tk)', 'atp-quote' ); ?>"></td>
			<td><?php echo '' !== (string) $l['price'] ? esc_html( atp_money( (float) $l['qty'] * (float) $l['price'] ) ) : '—'; ?></td>
		</tr>
		<?php
	};
	?>
	<p class="description"><?php esc_html_e( 'Prefilled from the buyer\'s quote list. Add prices and terms, save, then open the print view to save the quotation as PDF. Leave a line\'s item empty to remove it.', 'atp-quote' ); ?></p>
	<table class="widefat atp-q-table">
		<thead><tr><th><?php esc_html_e( 'Item', 'atp-quote' ); ?></th><th><?php esc_html_e( 'Specification', 'atp-quote' ); ?></th><th><?php esc_html_e( 'Qty', 'atp-quote' ); ?></th><th><?php esc_html_e( 'Unit', 'atp-quote' ); ?></th><th><?php esc_html_e( 'Unit price (Tk)', 'atp-quote' ); ?></th><th><?php esc_html_e( 'Amount', 'atp-quote' ); ?></th></tr></thead>
		<tbody id="atp-q-rows">
			<?php
			foreach ( array_values( $q['lines'] ) as $i => $l ) {
				$row( $i, $l );
			}
			?>
		</tbody>
	</table>
	<template id="atp-q-template"><?php $row( '__i__', array( 'item' => '', 'spec' => '', 'qty' => '', 'unit' => '', 'price' => '' ) ); ?></template>
	<p><button type="button" class="button" id="atp-q-add"><?php esc_html_e( 'Add line', 'atp-quote' ); ?></button></p>
	<table class="form-table" role="presentation">
		<tr><th><label for="atp-q-delivery"><?php esc_html_e( 'Delivery charge (Tk)', 'atp-quote' ); ?></label></th><td><input id="atp-q-delivery" type="number" step="0.01" min="0" name="atp_q[delivery]" value="<?php echo esc_attr( $q['delivery'] ); ?>"></td></tr>
		<tr><th><label for="atp-q-vat"><?php esc_html_e( 'VAT %', 'atp-quote' ); ?></label></th><td><input id="atp-q-vat" type="number" step="0.01" min="0" max="100" name="atp_q[vat]" value="<?php echo esc_attr( $q['vat'] ); ?>"> <span class="description"><?php esc_html_e( 'Leave empty if prices include VAT or VAT does not apply.', 'atp-quote' ); ?></span></td></tr>
		<tr><th><label for="atp-q-validity"><?php esc_html_e( 'Valid for', 'atp-quote' ); ?></label></th><td><input id="atp-q-validity" type="text" name="atp_q[validity]" value="<?php echo esc_attr( $q['validity'] ); ?>"></td></tr>
		<tr><th><label for="atp-q-lead"><?php esc_html_e( 'Delivery / lead time', 'atp-quote' ); ?></label></th><td><input id="atp-q-lead" type="text" name="atp_q[lead]" value="<?php echo esc_attr( $q['lead'] ); ?>" class="regular-text"></td></tr>
		<tr><th><label for="atp-q-payment"><?php esc_html_e( 'Payment terms', 'atp-quote' ); ?></label></th><td><input id="atp-q-payment" type="text" name="atp_q[payment]" value="<?php echo esc_attr( $q['payment'] ); ?>" class="regular-text"></td></tr>
		<tr><th><label for="atp-q-notes"><?php esc_html_e( 'Notes and terms', 'atp-quote' ); ?></label></th><td><textarea id="atp-q-notes" name="atp_q[notes]" rows="3" class="large-text"><?php echo esc_textarea( $q['notes'] ); ?></textarea></td></tr>
	</table>
	<p>
		<strong><?php esc_html_e( 'Total', 'atp-quote' ); ?>: <?php echo esc_html( atp_money( $t['total'] ) ); ?></strong>
		<?php if ( $q['rev'] ) : ?>
			&nbsp;·&nbsp;<?php echo esc_html( atp_quotation_number( $post->ID, $q ) ); ?>
			&nbsp;·&nbsp;<a class="button button-secondary" target="_blank" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=atp_quotation&id=' . $post->ID ), 'atp_quotation_' . $post->ID ) ); ?>"><?php esc_html_e( 'Open print view / save as PDF', 'atp-quote' ); ?></a>
		<?php else : ?>
			&nbsp;·&nbsp;<span class="description"><?php esc_html_e( 'Save to create the quotation.', 'atp-quote' ); ?></span>
		<?php endif; ?>
	</p>
	<p><label><input type="checkbox" name="atp_q_mark_quoted" value="1"> <?php esc_html_e( 'Set status to Quoted when saving', 'atp-quote' ); ?></label></p>
	<script>
	(function(){var b=document.getElementById('atp-q-add'),t=document.getElementById('atp-q-template'),r=document.getElementById('atp-q-rows');if(!b||!t||!r)return;b.addEventListener('click',function(){var n=r.children.length+Date.now()%1000;r.insertAdjacentHTML('beforeend',t.innerHTML.replace(/__i__/g,String(n)));});})();
	</script>
	<?php
}

// Save the quotation with the rest of the enquiry (same form and nonce).
add_action( 'save_post_atp_enquiry', function ( $id ) {
	if ( ! isset( $_POST['atp_manage_nonce'], $_POST['atp_q'] ) || ! wp_verify_nonce( sanitize_key( $_POST['atp_manage_nonce'] ), 'atp_manage_' . $id ) || ! current_user_can( 'atp_manage_enquiries' ) ) {
		return;
	}
	$in    = wp_unslash( $_POST['atp_q'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitised field by field below.
	$num   = fn( $v ) => is_numeric( $v ) && $v >= 0 ? (float) $v : '';
	$lines = array();
	foreach ( (array) ( $in['lines'] ?? array() ) as $l ) {
		$item = sanitize_text_field( $l['item'] ?? '' );
		if ( '' === $item ) {
			continue;
		}
		$lines[] = array(
			'item'  => $item,
			'spec'  => sanitize_textarea_field( $l['spec'] ?? '' ),
			'qty'   => $num( $l['qty'] ?? '' ),
			'unit'  => sanitize_text_field( $l['unit'] ?? '' ),
			'price' => $num( $l['price'] ?? '' ),
		);
	}
	$old = get_post_meta( $id, '_atp_quotation', true );
	$new = array(
		'lines'    => $lines,
		'delivery' => $num( $in['delivery'] ?? '' ),
		'vat'      => $num( $in['vat'] ?? '' ),
		'validity' => sanitize_text_field( $in['validity'] ?? '' ),
		'lead'     => sanitize_text_field( $in['lead'] ?? '' ),
		'payment'  => sanitize_text_field( $in['payment'] ?? '' ),
		'notes'    => sanitize_textarea_field( $in['notes'] ?? '' ),
	);
	$compare = is_array( $old ) ? array_intersect_key( $old, $new ) : array();
	if ( ! $lines || $compare === $new ) {
		$changed = false;
	} else {
		$changed = true;
	}
	if ( $changed ) {
		$new['rev']  = ( is_array( $old ) ? (int) $old['rev'] : 0 ) + 1;
		$new['date'] = wp_date( 'Y-m-d' );
		update_post_meta( $id, '_atp_quotation', $new );
		$t = atp_quotation_totals( $new );
		/* translators: 1: quotation number, 2: total */
		atp_log( $id, sprintf( __( 'Quotation %1$s saved, total %2$s', 'atp-quote' ), atp_quotation_number( $id, $new ), atp_money( $t['total'] ) ) );
	}
	if ( ! empty( $_POST['atp_q_mark_quoted'] ) && 'quoted' !== get_post_meta( $id, '_atp_status', true ) ) {
		$statuses = atp_statuses();
		$prev     = get_post_meta( $id, '_atp_status', true );
		update_post_meta( $id, '_atp_status', 'quoted' );
		/* translators: 1: old status, 2: new status */
		atp_log( $id, sprintf( __( 'Status: %1$s → %2$s', 'atp-quote' ), $statuses[ $prev ] ?? $prev, $statuses['quoted'] ?? 'Quoted' ) );
	}
}, 20 );

// Print view.
add_action( 'admin_post_atp_quotation', function () {
	$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
	check_admin_referer( 'atp_quotation_' . $id );
	if ( ! $id || 'atp_enquiry' !== get_post_type( $id ) || ! current_user_can( 'atp_manage_enquiries' ) ) {
		wp_die( esc_html__( 'You do not have permission to view this quotation.', 'atp-quote' ), 403 );
	}
	$q = get_post_meta( $id, '_atp_quotation', true );
	if ( ! is_array( $q ) || empty( $q['lines'] ) ) {
		wp_die( esc_html__( 'Save the quotation first.', 'atp-quote' ) );
	}
	nocache_headers();
	header( 'X-Robots-Tag: noindex' );
	atp_render_quotation_document( $id, $q );
	exit;
} );

/**
 * The printable quotation.
 *
 * @param int   $id Enquiry ID.
 * @param array $q  Quotation.
 */
function atp_render_quotation_document( $id, $q ) {
	$m     = fn( $k ) => (string) get_post_meta( $id, '_atp_' . $k, true );
	$t     = atp_quotation_totals( $q );
	$logo  = get_theme_mod( 'custom_logo' ) ? wp_get_attachment_image_url( get_theme_mod( 'custom_logo' ), 'medium' ) : '';
	$owner = get_userdata( (int) $m( 'owner' ) );
	$no    = atp_quotation_number( $id, $q );
	?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html( $no ); ?> - Abedin Trade Point</title>
<style>
	*{box-sizing:border-box}body{font:14px/1.5 -apple-system,"Segoe UI",Inter,Arial,sans-serif;color:#1d2327;margin:0;background:#f0f0f1}
	.sheet{max-width:820px;margin:24px auto;background:#fff;padding:40px;box-shadow:0 1px 4px rgba(0,0,0,.08)}
	header{display:flex;justify-content:space-between;gap:24px;border-bottom:3px solid #1F5F99;padding-bottom:16px;margin-bottom:24px}
	.brand{font-size:22px;font-weight:800;color:#1F5F99}.brand img{max-height:56px;display:block;margin-bottom:6px}
	.from{font-size:12px;color:#50575e;white-space:pre-line}.doc{text-align:right}.doc h1{margin:0 0 6px;font-size:24px;letter-spacing:.04em}
	.cols{display:flex;gap:24px;margin-bottom:24px}.cols>div{flex:1}.label{font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:#646970;margin:0 0 4px}
	table{width:100%;border-collapse:collapse;margin-bottom:16px}th,td{border-bottom:1px solid #dcdcde;padding:8px;text-align:left;vertical-align:top}
	th{background:#f6f7f7;font-size:12px}td.n,th.n{text-align:right;white-space:nowrap}.spec{font-size:12px;color:#50575e;white-space:pre-line}
	.totals{margin-left:auto;width:320px}.totals td{border:0;padding:4px 8px}.totals .grand td{border-top:2px solid #1d2327;font-weight:700;font-size:16px}
	.terms{font-size:13px}.terms p{margin:4px 0}.foot{margin-top:32px;font-size:12px;color:#646970;border-top:1px solid #dcdcde;padding-top:12px}
	.bar{max-width:820px;margin:16px auto 0;display:flex;gap:8px}.bar button{font:inherit;padding:8px 16px;border:0;border-radius:4px;background:#1F5F99;color:#fff;cursor:pointer}
	mark{background:#fff3c4}
	@media print{body{background:#fff}.sheet{box-shadow:none;margin:0;max-width:none;padding:0}.bar{display:none}@page{margin:16mm}}
</style>
</head>
<body>
<div class="bar"><button type="button" onclick="window.print()"><?php esc_html_e( 'Print or save as PDF', 'atp-quote' ); ?></button></div>
<div class="sheet">
	<header>
		<div>
			<div class="brand">
				<?php if ( $logo ) : ?><img src="<?php echo esc_url( $logo ); ?>" alt=""><?php endif; ?>
				Abedin Trade Point
			</div>
			<div class="from"><?php
			$from = array_filter( array( atp_setting( 'address' ), atp_setting( 'phone' ), atp_setting( 'sales_email' ), wp_parse_url( home_url(), PHP_URL_HOST ) ) );
			echo $from ? esc_html( implode( "\n", $from ) ) : '<mark>' . esc_html__( '[Add contact details in Site Settings]', 'atp-quote' ) . '</mark>';
			?></div>
		</div>
		<div class="doc">
			<h1><?php esc_html_e( 'QUOTATION', 'atp-quote' ); ?></h1>
			<div><strong><?php echo esc_html( $no ); ?></strong></div>
			<div><?php echo esc_html( wp_date( 'j F Y', strtotime( $q['date'] ) ) ); ?></div>
			<?php /* translators: %s: enquiry reference */ ?>
			<div><?php echo esc_html( sprintf( __( 'Your enquiry: %s', 'atp-quote' ), $m( 'reference' ) ) ); ?></div>
		</div>
	</header>
	<div class="cols">
		<div>
			<p class="label"><?php esc_html_e( 'Prepared for', 'atp-quote' ); ?></p>
			<strong><?php echo esc_html( $m( 'name' ) ); ?></strong><br>
			<?php echo $m( 'org' ) ? esc_html( $m( 'org' ) ) . '<br>' : ''; ?>
			<?php echo esc_html( implode( ' · ', array_filter( array( $m( 'phone' ), $m( 'email' ) ) ) ) ); ?>
		</div>
		<div>
			<p class="label"><?php esc_html_e( 'Delivery', 'atp-quote' ); ?></p>
			<?php echo esc_html( $m( 'district' ) ); ?><?php echo $m( 'delivery_date' ) ? '<br>' . esc_html__( 'Required by', 'atp-quote' ) . ' ' . esc_html( $m( 'delivery_date' ) ) : ''; ?>
			<?php if ( $owner ) : ?>
				<p class="label" style="margin-top:10px"><?php esc_html_e( 'Your contact', 'atp-quote' ); ?></p><?php echo esc_html( $owner->display_name ); ?>
			<?php endif; ?>
		</div>
	</div>
	<table>
		<thead><tr><th>#</th><th><?php esc_html_e( 'Item and specification', 'atp-quote' ); ?></th><th class="n"><?php esc_html_e( 'Qty', 'atp-quote' ); ?></th><th class="n"><?php esc_html_e( 'Unit price', 'atp-quote' ); ?></th><th class="n"><?php esc_html_e( 'Amount', 'atp-quote' ); ?></th></tr></thead>
		<tbody>
		<?php foreach ( array_values( $q['lines'] ) as $i => $l ) : ?>
			<tr>
				<td><?php echo (int) $i + 1; ?></td>
				<td><strong><?php echo esc_html( $l['item'] ); ?></strong><?php echo $l['spec'] ? '<div class="spec">' . esc_html( $l['spec'] ) . '</div>' : ''; ?></td>
				<td class="n"><?php echo esc_html( rtrim( rtrim( number_format( (float) $l['qty'], 2, '.', '' ), '0' ), '.' ) . ' ' . $l['unit'] ); ?></td>
				<td class="n"><?php echo '' === $l['price'] ? '—' : esc_html( atp_money( $l['price'] ) ); ?></td>
				<td class="n"><?php echo '' === $l['price'] ? '—' : esc_html( atp_money( (float) $l['qty'] * (float) $l['price'] ) ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<table class="totals">
		<tr><td><?php esc_html_e( 'Subtotal', 'atp-quote' ); ?></td><td class="n"><?php echo esc_html( atp_money( $t['subtotal'] ) ); ?></td></tr>
		<?php if ( $t['delivery'] ) : ?><tr><td><?php esc_html_e( 'Delivery', 'atp-quote' ); ?></td><td class="n"><?php echo esc_html( atp_money( $t['delivery'] ) ); ?></td></tr><?php endif; ?>
		<?php /* translators: %s: VAT rate */ ?>
		<?php if ( $t['vat'] ) : ?><tr><td><?php echo esc_html( sprintf( __( 'VAT (%s%%)', 'atp-quote' ), $q['vat'] ) ); ?></td><td class="n"><?php echo esc_html( atp_money( $t['vat'] ) ); ?></td></tr><?php endif; ?>
		<tr class="grand"><td><?php esc_html_e( 'Total', 'atp-quote' ); ?></td><td class="n"><?php echo esc_html( atp_money( $t['total'] ) ); ?></td></tr>
	</table>
	<div class="terms">
		<?php if ( $q['validity'] ) : ?><p><strong><?php esc_html_e( 'Valid for:', 'atp-quote' ); ?></strong> <?php echo esc_html( $q['validity'] ); ?></p><?php endif; ?>
		<?php if ( $q['lead'] ) : ?><p><strong><?php esc_html_e( 'Delivery / lead time:', 'atp-quote' ); ?></strong> <?php echo esc_html( $q['lead'] ); ?></p><?php endif; ?>
		<?php if ( $q['payment'] ) : ?><p><strong><?php esc_html_e( 'Payment terms:', 'atp-quote' ); ?></strong> <?php echo esc_html( $q['payment'] ); ?></p><?php endif; ?>
		<?php if ( $q['notes'] ) : ?><p style="white-space:pre-line"><?php echo esc_html( $q['notes'] ); ?></p><?php endif; ?>
		<p><?php esc_html_e( 'Product images and descriptions identify the listed option. Any proposed alternative is described in this quotation and must be agreed before supply.', 'atp-quote' ); ?></p>
	</div>
	<div class="foot"><?php esc_html_e( 'To confirm this order, reply with the quotation number. Customised goods go into production only after the final artwork and specifications are approved.', 'atp-quote' ); ?></div>
</div>
</body>
</html>
	<?php
}

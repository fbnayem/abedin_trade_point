<?php
/**
 * Staff alert and buyer receipt. Failures are recorded on the enquiry so
 * staff can see them and retry from the edit screen.
 *
 * @package atp-quote
 */

defined( 'ABSPATH' ) || exit;

/**
 * Send (or resend) the notifications for an enquiry.
 *
 * @param int $id Enquiry ID.
 * @return bool True if every attempted email was accepted by the mailer.
 */
function atp_send_notifications( $id ) {
	$ref   = get_post_meta( $id, '_atp_reference', true );
	$name  = get_post_meta( $id, '_atp_name', true );
	$email = get_post_meta( $id, '_atp_email', true );
	$ok    = true;
	$errs  = array();

	$capture = function ( $err ) use ( &$errs ) {
		$errs[] = $err->get_error_message();
	};
	add_action( 'wp_mail_failed', $capture );

	// Staff alert.
	$staff = array_filter( array_map( 'sanitize_email', array_map( 'trim', explode( ',', atp_setting( 'notify_emails' ) ) ) ) );
	if ( ! $staff ) {
		$staff = array( get_option( 'admin_email' ) );
	}
	$items   = array_filter( (array) get_post_meta( $id, '_atp_items', true ) );
	$summary = array();
	foreach ( $items as $it ) {
		$summary[] = '- ' . $it['name'] . ' × ' . $it['qty'] . ' ' . $it['unit'];
	}
	$body  = sprintf( "New quote request %s\n\n", $ref );
	$body .= sprintf( "Name: %s\nOrganisation: %s\nPhone: %s\nEmail: %s\nDistrict: %s\n\n", $name, get_post_meta( $id, '_atp_org', true ), get_post_meta( $id, '_atp_phone', true ), $email, get_post_meta( $id, '_atp_district', true ) );
	if ( $summary ) {
		$body .= "Items:\n" . implode( "\n", $summary ) . "\n\n";
	}
	if ( get_post_meta( $id, '_atp_free_text', true ) ) {
		$body .= "Written requirement:\n" . get_post_meta( $id, '_atp_free_text', true ) . "\n\n";
	}
	$files = array_filter( (array) get_post_meta( $id, '_atp_files', true ) );
	if ( $files ) {
		$body .= sprintf( "%d attachment(s). Download them from the enquiry screen (not attached here for privacy).\n\n", count( $files ) );
	}
	$body .= 'Open: ' . admin_url( 'post.php?post=' . $id . '&action=edit' ) . "\n";
	$staff_ok = wp_mail( $staff, sprintf( 'New quote request %s from %s', $ref, $name ), $body );
	$ok       = $ok && $staff_ok;

	// Buyer receipt, only when an email was supplied.
	if ( $email ) {
		$first   = strtok( $name, ' ' );
		$subject = sprintf( 'We received your supply enquiry | %s', $ref );
		$receipt = sprintf(
			"Hi %1\$s,\n\nThank you for contacting Abedin Trade Point. We have received your supply request under reference %2\$s.\n\nWe will review the items, quantities and delivery requirements you provided. If we need more information to prepare a quotation, our team will contact you.\n\nYou can reply to this email with additional details and include your reference.\n\nRegards,\nAbedin Trade Point",
			$first,
			$ref
		);
		$headers = array();
		if ( atp_setting( 'sales_email' ) ) {
			$headers[] = 'Reply-To: Abedin Trade Point <' . atp_setting( 'sales_email' ) . '>';
		}
		$buyer_ok = wp_mail( $email, $subject, $receipt, $headers );
		$ok       = $ok && $buyer_ok;
	}

	remove_action( 'wp_mail_failed', $capture );

	update_post_meta( $id, '_atp_notify_status', $ok ? 'sent' : 'failed' );
	atp_log( $id, $ok ? __( 'Notifications sent.', 'atp-quote' ) : __( 'Notification failed: ', 'atp-quote' ) . implode( '; ', $errs ) );
	return $ok;
}

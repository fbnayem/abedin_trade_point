<?php
/**
 * Private storage for purchase lists and reference files.
 *
 * Files are checked by real content type, renamed, and kept outside the
 * media library. Only staff with atp_manage_enquiries can download them.
 *
 * @package atp-quote
 */

defined( 'ABSPATH' ) || exit;

const ATP_MAX_FILES     = 5;
const ATP_MAX_FILE_SIZE = 10 * MB_IN_BYTES;

/**
 * Allowed extensions and the MIME types their content may report.
 */
function atp_allowed_files() {
	return array(
		'pdf'  => array( 'application/pdf' ),
		'xlsx' => array( 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream' ),
		'docx' => array( 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream' ),
		'jpg'  => array( 'image/jpeg' ),
		'jpeg' => array( 'image/jpeg' ),
		'png'  => array( 'image/png' ),
	);
}

/**
 * Private folder path, created with deny rules on first use.
 */
function atp_private_dir() {
	// Best practice: define ATP_PRIVATE_DIR in wp-config.php as a folder outside the web root.
	if ( defined( 'ATP_PRIVATE_DIR' ) ) {
		$dir = untrailingslashit( ATP_PRIVATE_DIR );
	} else {
		$up  = wp_upload_dir( null, false );
		$dir = trailingslashit( $up['basedir'] ) . 'atp-private';
	}
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	if ( ! file_exists( $dir . '/.htaccess' ) ) {
		file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
	if ( ! file_exists( $dir . '/index.php' ) ) {
		file_put_contents( $dir . '/index.php', "<?php // Silence.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
	return $dir;
}

/**
 * Check that an Office file is a real OOXML package and not macro-enabled.
 *
 * @param string $path File path.
 * @param string $ext  Extension.
 */
function atp_ooxml_ok( $path, $ext ) {
	if ( ! class_exists( 'ZipArchive' ) ) {
		return true; // Extension and finfo checks still apply.
	}
	$zip = new ZipArchive();
	if ( true !== $zip->open( $path ) ) {
		return false;
	}
	$types = $zip->getFromName( '[Content_Types].xml' );
	$has   = 'xlsx' === $ext ? $zip->locateName( 'xl/workbook.xml' ) : $zip->locateName( 'word/document.xml' );
	$macro = false !== $zip->locateName( 'xl/vbaProject.bin' ) || false !== $zip->locateName( 'word/vbaProject.bin' );
	$zip->close();
	return $types && false !== $has && ! $macro && false === stripos( $types, 'macroEnabled' );
}

/**
 * Validate the uploaded files in $_FILES['atp_files'].
 *
 * @return array{ok:array,errors:array} ok = list of validated temp files.
 */
function atp_validate_uploads() {
	$out = array( 'ok' => array(), 'errors' => array() );
	if ( empty( $_FILES['atp_files'] ) || ! is_array( $_FILES['atp_files']['name'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return $out;
	}
	$f       = $_FILES['atp_files']; // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
	$allowed = atp_allowed_files();
	$finfo   = function_exists( 'finfo_open' ) ? finfo_open( FILEINFO_MIME_TYPE ) : null;
	$count   = 0;
	foreach ( $f['name'] as $i => $name ) {
		if ( UPLOAD_ERR_NO_FILE === (int) $f['error'][ $i ] || '' === $name ) {
			continue;
		}
		$name = sanitize_file_name( wp_unslash( $name ) );
		++$count;
		if ( $count > ATP_MAX_FILES ) {
			/* translators: %d: max files */
			$out['errors'][] = sprintf( __( 'You can attach up to %d files.', 'atp-quote' ), ATP_MAX_FILES );
			break;
		}
		if ( UPLOAD_ERR_OK !== (int) $f['error'][ $i ] || (int) $f['size'][ $i ] > ATP_MAX_FILE_SIZE ) {
			/* translators: %s: file name */
			$out['errors'][] = sprintf( __( '%s could not be uploaded. Check the file type and 10 MB limit, then try again.', 'atp-quote' ), $name );
			continue;
		}
		$tmp = $f['tmp_name'][ $i ];
		$ext = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		$mime = $finfo ? finfo_file( $finfo, $tmp ) : '';
		$valid = is_uploaded_file( $tmp ) && isset( $allowed[ $ext ] ) && ( ! $finfo || in_array( $mime, $allowed[ $ext ], true ) );
		if ( $valid && in_array( $ext, array( 'xlsx', 'docx' ), true ) ) {
			$valid = atp_ooxml_ok( $tmp, $ext );
		}
		if ( ! $valid ) {
			/* translators: %s: file name */
			$out['errors'][] = sprintf( __( '%s could not be uploaded. Check the file type and 10 MB limit, then try again.', 'atp-quote' ), $name );
			continue;
		}
		$out['ok'][] = array( 'tmp' => $tmp, 'original' => $name, 'ext' => $ext, 'size' => (int) $f['size'][ $i ], 'mime' => $mime );
	}
	if ( $finfo ) {
		finfo_close( $finfo );
	}
	return $out;
}

/**
 * Move validated files into private storage.
 *
 * @param array  $files     From atp_validate_uploads()['ok'].
 * @param string $reference Enquiry reference, used in the folder name.
 * @return array Stored file records.
 */
function atp_store_uploads( array $files, $reference ) {
	$base   = atp_private_dir() . '/' . wp_date( 'Y/m' ) . '/' . sanitize_file_name( $reference );
	wp_mkdir_p( $base );
	$stored = array();
	foreach ( $files as $file ) {
		$target = $base . '/' . strtolower( wp_generate_password( 16, false, false ) ) . '.' . $file['ext'];
		if ( move_uploaded_file( $file['tmp'], $target ) ) {
			chmod( $target, 0640 ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			$stored[] = array(
				'path'     => str_replace( atp_private_dir() . '/', '', $target ),
				'original' => $file['original'],
				'size'     => $file['size'],
				'mime'     => $file['mime'],
			);
		}
	}
	return $stored;
}

/**
 * Staff download link for an attachment.
 *
 * @param int $id    Enquiry ID.
 * @param int $index File index.
 */
function atp_file_download_url( $id, $index ) {
	return wp_nonce_url(
		admin_url( 'admin-post.php?action=atp_file&enquiry=' . (int) $id . '&file=' . (int) $index ),
		'atp_file_' . $id . '_' . $index
	);
}

add_action( 'admin_post_atp_file', function () {
	$id    = isset( $_GET['enquiry'] ) ? absint( $_GET['enquiry'] ) : 0;
	$index = isset( $_GET['file'] ) ? absint( $_GET['file'] ) : 0;
	check_admin_referer( 'atp_file_' . $id . '_' . $index );
	if ( ! current_user_can( 'atp_manage_enquiries' ) || 'atp_enquiry' !== get_post_type( $id ) ) {
		wp_die( esc_html__( 'You do not have access to this file.', 'atp-quote' ), 403 );
	}
	$files = (array) get_post_meta( $id, '_atp_files', true );
	if ( empty( $files[ $index ]['path'] ) ) {
		wp_die( esc_html__( 'File not found.', 'atp-quote' ), 404 );
	}
	$root = realpath( atp_private_dir() );
	$path = realpath( atp_private_dir() . '/' . $files[ $index ]['path'] );
	if ( ! $path || ! $root || 0 !== strpos( $path, $root . DIRECTORY_SEPARATOR ) || ! is_readable( $path ) ) {
		wp_die( esc_html__( 'File not found.', 'atp-quote' ), 404 );
	}
	nocache_headers();
	header( 'Content-Type: application/octet-stream' );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'Content-Disposition: attachment; filename="' . rawurlencode( $files[ $index ]['original'] ) . '"' );
	header( 'Content-Length: ' . filesize( $path ) );
	readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	exit;
} );

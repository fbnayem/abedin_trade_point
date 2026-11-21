<?php
/**
 * Enquiry records and the staff workflow.
 *
 * @package atp-quote
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sales statuses from the plan. Kept separate from any fulfilment stage.
 */
function atp_statuses() {
	return array(
		'new'              => __( 'New', 'atp-quote' ),
		'reviewing'        => __( 'Reviewing', 'atp-quote' ),
		'awaiting_details' => __( 'Awaiting Details', 'atp-quote' ),
		'quoted'           => __( 'Quoted', 'atp-quote' ),
		'won'              => __( 'Won', 'atp-quote' ),
		'lost'             => __( 'Lost / Closed', 'atp-quote' ),
	);
}

/**
 * Register the private enquiry post type.
 */
function atp_register_enquiry_type() {
	register_post_type(
		'atp_enquiry',
		array(
			'labels'              => array(
				'name'          => __( 'Enquiries', 'atp-quote' ),
				'singular_name' => __( 'Enquiry', 'atp-quote' ),
				'edit_item'     => __( 'Enquiry', 'atp-quote' ),
				'search_items'  => __( 'Search enquiries', 'atp-quote' ),
				'not_found'     => __( 'No enquiries yet.', 'atp-quote' ),
				'all_items'     => __( 'All Enquiries', 'atp-quote' ),
			),
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_ui'             => true,
			'show_in_menu'        => false,
			'show_in_rest'        => false,
			'supports'            => array( 'title' ),
			'capability_type'     => 'atp_enquiry',
			'capabilities'        => array(
				'edit_post'          => 'atp_manage_enquiries',
				'read_post'          => 'atp_manage_enquiries',
				'delete_post'        => 'manage_options',
				'edit_posts'         => 'atp_manage_enquiries',
				'edit_others_posts'  => 'atp_manage_enquiries',
				'publish_posts'      => 'atp_manage_enquiries',
				'read_private_posts' => 'atp_manage_enquiries',
				'create_posts'       => 'do_not_allow',
				'delete_posts'       => 'manage_options',
			),
			'map_meta_cap'        => false,
		)
	);
}
add_action( 'init', 'atp_register_enquiry_type' );

/**
 * Create a unique enquiry reference such as ATP-261120-K7Q4.
 */
function atp_new_reference() {
	do {
		$ref = 'ATP-' . wp_date( 'ymd' ) . '-' . strtoupper( wp_generate_password( 4, false, false ) );
	} while ( atp_find_enquiry( '_atp_reference', $ref ) );
	return $ref;
}

/**
 * Find an enquiry ID by a meta value.
 *
 * @param string $key   Meta key.
 * @param string $value Value.
 * @return int 0 if none.
 */
function atp_find_enquiry( $key, $value ) {
	$ids = get_posts(
		array(
			'post_type'      => 'atp_enquiry',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_key'       => $key, // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'     => $value, // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
	return $ids ? (int) $ids[0] : 0;
}

/**
 * Append to the status history.
 *
 * @param int    $id   Enquiry ID.
 * @param string $note What happened.
 */
function atp_log( $id, $note ) {
	$log   = get_post_meta( $id, '_atp_history', true );
	$log   = is_array( $log ) ? $log : array();
	$user  = wp_get_current_user();
	$log[] = array(
		'time' => time(),
		'user' => $user && $user->exists() ? $user->display_name : __( 'System', 'atp-quote' ),
		'note' => $note,
	);
	update_post_meta( $id, '_atp_history', $log );
}

/* ---------- Admin list ---------- */

add_filter( 'manage_atp_enquiry_posts_columns', function () {
	return array(
		'cb'        => '<input type="checkbox">',
		'title'     => __( 'Reference', 'atp-quote' ),
		'atp_buyer' => __( 'Buyer', 'atp-quote' ),
		'atp_items' => __( 'Requirement', 'atp-quote' ),
		'atp_dist'  => __( 'District', 'atp-quote' ),
		'atp_stat'  => __( 'Status', 'atp-quote' ),
		'atp_owner' => __( 'Owner', 'atp-quote' ),
		'atp_next'  => __( 'Follow up', 'atp-quote' ),
		'date'      => __( 'Received', 'atp-quote' ),
	);
} );

add_action( 'manage_atp_enquiry_posts_custom_column', function ( $col, $id ) {
	switch ( $col ) {
		case 'atp_buyer':
			echo esc_html( get_post_meta( $id, '_atp_name', true ) );
			$org = get_post_meta( $id, '_atp_org', true );
			if ( $org ) {
				echo '<br><small>' . esc_html( $org ) . '</small>';
			}
			break;
		case 'atp_items':
			$items = (array) get_post_meta( $id, '_atp_items', true );
			$files = (array) get_post_meta( $id, '_atp_files', true );
			$parts = array();
			if ( array_filter( $items ) ) {
				/* translators: %d: number of items */
				$parts[] = sprintf( _n( '%d item', '%d items', count( $items ), 'atp-quote' ), count( $items ) );
			}
			if ( get_post_meta( $id, '_atp_free_text', true ) ) {
				$parts[] = __( 'written requirement', 'atp-quote' );
			}
			if ( array_filter( $files ) ) {
				/* translators: %d: number of files */
				$parts[] = sprintf( _n( '%d file', '%d files', count( $files ), 'atp-quote' ), count( $files ) );
			}
			echo esc_html( implode( ' · ', $parts ) );
			if ( 'failed' === get_post_meta( $id, '_atp_notify_status', true ) ) {
				echo '<br><strong style="color:#b32d2e">' . esc_html__( 'Email failed', 'atp-quote' ) . '</strong>';
			}
			break;
		case 'atp_dist':
			echo esc_html( get_post_meta( $id, '_atp_district', true ) );
			break;
		case 'atp_stat':
			$s = get_post_meta( $id, '_atp_status', true );
			echo esc_html( atp_statuses()[ $s ] ?? $s );
			break;
		case 'atp_owner':
			$u = get_userdata( (int) get_post_meta( $id, '_atp_owner', true ) );
			echo $u ? esc_html( $u->display_name ) : '<em>' . esc_html__( 'Unassigned', 'atp-quote' ) . '</em>';
			break;
		case 'atp_next':
			$d = get_post_meta( $id, '_atp_follow_up', true );
			echo $d ? esc_html( $d ) : '—';
			break;
	}
}, 10, 2 );

/* Filter list by status. */
add_action( 'restrict_manage_posts', function ( $post_type ) {
	if ( 'atp_enquiry' !== $post_type ) {
		return;
	}
	$current = isset( $_GET['atp_status'] ) ? sanitize_key( $_GET['atp_status'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	echo '<select name="atp_status"><option value="">' . esc_html__( 'All statuses', 'atp-quote' ) . '</option>';
	foreach ( atp_statuses() as $k => $label ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $current, $k, false ), esc_html( $label ) );
	}
	echo '</select>';
} );

add_action( 'pre_get_posts', function ( $q ) {
	if ( ! is_admin() || ! $q->is_main_query() || 'atp_enquiry' !== $q->get( 'post_type' ) ) {
		return;
	}
	$s = isset( $_GET['atp_status'] ) ? sanitize_key( $_GET['atp_status'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	if ( $s ) {
		$q->set( 'meta_key', '_atp_status' );
		$q->set( 'meta_value', $s );
	}
} );

/* ---------- Edit screen ---------- */

add_action( 'add_meta_boxes_atp_enquiry', function () {
	remove_meta_box( 'submitdiv', 'atp_enquiry', 'side' );
	add_meta_box( 'atp_details', __( 'Requirement', 'atp-quote' ), 'atp_box_details', 'atp_enquiry', 'normal', 'high' );
	add_meta_box( 'atp_manage', __( 'Sales handling', 'atp-quote' ), 'atp_box_manage', 'atp_enquiry', 'side', 'high' );
	add_meta_box( 'atp_history', __( 'History', 'atp-quote' ), 'atp_box_history', 'atp_enquiry', 'normal', 'low' );
} );

/**
 * Requirement box: buyer, items snapshot, free text, files.
 *
 * @param WP_Post $post Enquiry.
 */
function atp_box_details( $post ) {
	$m     = fn( $k ) => get_post_meta( $post->ID, '_atp_' . $k, true );
	$units = atp_units();
	?>
	<table class="widefat striped" style="margin-bottom:16px">
		<tbody>
		<?php
		$rows = array(
			__( 'Reference', 'atp-quote' )         => $m( 'reference' ),
			__( 'Name', 'atp-quote' )              => $m( 'name' ),
			__( 'Organisation', 'atp-quote' )      => $m( 'org' ),
			__( 'Phone', 'atp-quote' )             => $m( 'phone' ),
			__( 'Email', 'atp-quote' )             => $m( 'email' ),
			__( 'Preferred contact', 'atp-quote' ) => $m( 'contact_pref' ),
			__( 'Delivery district', 'atp-quote' ) => $m( 'district' ),
			__( 'Required delivery', 'atp-quote' ) => $m( 'delivery_date' ),
			__( 'Quotation deadline', 'atp-quote' ) => $m( 'deadline' ),
			__( 'Budget range', 'atp-quote' )      => $m( 'budget' ),
			__( 'Source', 'atp-quote' )            => $m( 'source' ),
			__( 'Landing page', 'atp-quote' )      => $m( 'landing' ),
		);
		foreach ( $rows as $label => $value ) {
			if ( '' === (string) $value ) {
				continue;
			}
			printf( '<tr><th style="width:180px">%s</th><td>%s</td></tr>', esc_html( $label ), esc_html( $value ) );
		}
		?>
		</tbody>
	</table>

	<?php $items = array_filter( (array) $m( 'items' ) ); ?>
	<?php if ( $items ) : ?>
		<h4><?php esc_html_e( 'Quote list (snapshot at submission)', 'atp-quote' ); ?></h4>
		<table class="widefat striped" style="margin-bottom:16px">
			<thead><tr><th>#</th><th><?php esc_html_e( 'Product', 'atp-quote' ); ?></th><th><?php esc_html_e( 'Options', 'atp-quote' ); ?></th><th><?php esc_html_e( 'Quantity', 'atp-quote' ); ?></th><th><?php esc_html_e( 'Notes', 'atp-quote' ); ?></th></tr></thead>
			<tbody>
			<?php foreach ( array_values( $items ) as $i => $it ) : ?>
				<tr>
					<td><?php echo (int) $i + 1; ?></td>
					<td><?php echo esc_html( $it['name'] ); ?><?php echo $it['sku'] ? '<br><small>' . esc_html( $it['sku'] ) . '</small>' : ''; ?></td>
					<td>
					<?php
					foreach ( (array) $it['options'] as $k => $v ) {
						echo esc_html( $k . ': ' . $v ) . '<br>';
					}
					?>
					</td>
					<td><?php echo esc_html( rtrim( rtrim( number_format( (float) $it['qty'], 2, '.', '' ), '0' ), '.' ) . ' ' . ( $units[ $it['unit'] ] ?? $it['unit'] ) ); ?><?php echo $it['pack'] ? '<br><small>' . esc_html( $it['pack'] ) . '</small>' : ''; ?></td>
					<td><?php echo nl2br( esc_html( $it['notes'] ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<?php if ( $m( 'free_text' ) ) : ?>
		<h4><?php esc_html_e( 'Written requirement', 'atp-quote' ); ?></h4>
		<div style="white-space:pre-wrap;background:#f6f7f7;padding:12px;border-radius:4px;margin-bottom:16px"><?php echo esc_html( $m( 'free_text' ) ); ?></div>
	<?php endif; ?>

	<?php if ( $m( 'specs' ) ) : ?>
		<h4><?php esc_html_e( 'Specifications / notes', 'atp-quote' ); ?></h4>
		<div style="white-space:pre-wrap;background:#f6f7f7;padding:12px;border-radius:4px;margin-bottom:16px"><?php echo esc_html( $m( 'specs' ) ); ?></div>
	<?php endif; ?>

	<?php $files = array_filter( (array) $m( 'files' ) ); ?>
	<?php if ( $files ) : ?>
		<h4><?php esc_html_e( 'Attachments', 'atp-quote' ); ?></h4>
		<ul>
		<?php foreach ( $files as $i => $f ) : ?>
			<li><a href="<?php echo esc_url( atp_file_download_url( $post->ID, $i ) ); ?>"><?php echo esc_html( $f['original'] ); ?></a> (<?php echo esc_html( size_format( $f['size'] ) ); ?>)</li>
		<?php endforeach; ?>
		</ul>
	<?php endif; ?>
	<?php
}

/**
 * Sales handling box: status, owner, follow-up, notes, notification retry.
 *
 * @param WP_Post $post Enquiry.
 */
function atp_box_manage( $post ) {
	wp_nonce_field( 'atp_manage_' . $post->ID, 'atp_manage_nonce' );
	$status = get_post_meta( $post->ID, '_atp_status', true );
	$owner  = (int) get_post_meta( $post->ID, '_atp_owner', true );
	$staff  = get_users( array( 'capability' => 'atp_manage_enquiries', 'fields' => array( 'ID', 'display_name' ) ) );
	?>
	<p><label for="atp_status"><strong><?php esc_html_e( 'Status', 'atp-quote' ); ?></strong></label><br>
	<select id="atp_status" name="atp_status" style="width:100%">
		<?php foreach ( atp_statuses() as $k => $label ) : ?>
			<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $status, $k ); ?>><?php echo esc_html( $label ); ?></option>
		<?php endforeach; ?>
	</select></p>
	<p><label for="atp_lost_reason"><?php esc_html_e( 'Reason (if Lost / Closed)', 'atp-quote' ); ?></label><br>
	<input id="atp_lost_reason" type="text" name="atp_lost_reason" value="<?php echo esc_attr( get_post_meta( $post->ID, '_atp_lost_reason', true ) ); ?>" style="width:100%"></p>
	<p><label for="atp_owner"><strong><?php esc_html_e( 'Owner', 'atp-quote' ); ?></strong></label><br>
	<select id="atp_owner" name="atp_owner" style="width:100%">
		<option value="0"><?php esc_html_e( 'Unassigned', 'atp-quote' ); ?></option>
		<?php foreach ( $staff as $u ) : ?>
			<option value="<?php echo (int) $u->ID; ?>" <?php selected( $owner, (int) $u->ID ); ?>><?php echo esc_html( $u->display_name ); ?></option>
		<?php endforeach; ?>
	</select></p>
	<p><label for="atp_follow_up"><strong><?php esc_html_e( 'Next follow-up', 'atp-quote' ); ?></strong></label><br>
	<input id="atp_follow_up" type="date" name="atp_follow_up" value="<?php echo esc_attr( get_post_meta( $post->ID, '_atp_follow_up', true ) ); ?>" style="width:100%"></p>
	<p><label for="atp_note"><strong><?php esc_html_e( 'Add a note', 'atp-quote' ); ?></strong></label><br>
	<textarea id="atp_note" name="atp_note" rows="3" style="width:100%"></textarea></p>
	<?php
	$notify = get_post_meta( $post->ID, '_atp_notify_status', true );
	if ( 'failed' === $notify ) {
		echo '<p style="color:#b32d2e"><strong>' . esc_html__( 'A notification email failed to send.', 'atp-quote' ) . '</strong></p>';
		echo '<p><label><input type="checkbox" name="atp_retry_notify" value="1"> ' . esc_html__( 'Retry notifications on save', 'atp-quote' ) . '</label></p>';
	}
	submit_button( __( 'Save', 'atp-quote' ), 'primary', 'save', false );
}

/**
 * History box.
 *
 * @param WP_Post $post Enquiry.
 */
function atp_box_history( $post ) {
	$log = array_reverse( (array) get_post_meta( $post->ID, '_atp_history', true ) );
	if ( ! array_filter( $log ) ) {
		esc_html_e( 'No history yet.', 'atp-quote' );
		return;
	}
	echo '<ul>';
	foreach ( $log as $row ) {
		printf(
			'<li><strong>%s</strong> · %s<br>%s</li>',
			esc_html( wp_date( 'j M Y, g:i a', $row['time'] ) ),
			esc_html( $row['user'] ),
			nl2br( esc_html( $row['note'] ) )
		);
	}
	echo '</ul>';
}

add_action( 'save_post_atp_enquiry', function ( $id ) {
	if ( ! isset( $_POST['atp_manage_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['atp_manage_nonce'] ), 'atp_manage_' . $id ) ) {
		return;
	}
	if ( ! current_user_can( 'atp_manage_enquiries' ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
		return;
	}
	$statuses = atp_statuses();
	$new      = isset( $_POST['atp_status'] ) ? sanitize_key( $_POST['atp_status'] ) : '';
	$old      = get_post_meta( $id, '_atp_status', true );
	if ( isset( $statuses[ $new ] ) && $new !== $old ) {
		update_post_meta( $id, '_atp_status', $new );
		/* translators: 1: old status, 2: new status */
		atp_log( $id, sprintf( __( 'Status: %1$s → %2$s', 'atp-quote' ), $statuses[ $old ] ?? $old, $statuses[ $new ] ) );
	}
	$reason = isset( $_POST['atp_lost_reason'] ) ? sanitize_text_field( wp_unslash( $_POST['atp_lost_reason'] ) ) : '';
	if ( $reason !== get_post_meta( $id, '_atp_lost_reason', true ) ) {
		update_post_meta( $id, '_atp_lost_reason', $reason );
		if ( $reason ) {
			/* translators: %s: reason */
			atp_log( $id, sprintf( __( 'Closed reason: %s', 'atp-quote' ), $reason ) );
		}
	}
	$owner = isset( $_POST['atp_owner'] ) ? absint( $_POST['atp_owner'] ) : 0;
	if ( $owner !== (int) get_post_meta( $id, '_atp_owner', true ) ) {
		update_post_meta( $id, '_atp_owner', $owner );
		$u = get_userdata( $owner );
		/* translators: %s: staff name */
		atp_log( $id, sprintf( __( 'Owner set to %s', 'atp-quote' ), $u ? $u->display_name : __( 'Unassigned', 'atp-quote' ) ) );
	}
	$follow = isset( $_POST['atp_follow_up'] ) ? sanitize_text_field( wp_unslash( $_POST['atp_follow_up'] ) ) : '';
	if ( '' === $follow || preg_match( '/^\d{4}-\d{2}-\d{2}$/', $follow ) ) {
		if ( $follow !== get_post_meta( $id, '_atp_follow_up', true ) ) {
			update_post_meta( $id, '_atp_follow_up', $follow );
			/* translators: %s: date */
			atp_log( $id, $follow ? sprintf( __( 'Follow-up set for %s', 'atp-quote' ), $follow ) : __( 'Follow-up cleared', 'atp-quote' ) );
		}
	}
	$note = isset( $_POST['atp_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['atp_note'] ) ) : '';
	if ( $note ) {
		atp_log( $id, $note );
	}
	if ( ! empty( $_POST['atp_retry_notify'] ) ) {
		atp_send_notifications( $id );
	}
} );

/* Enquiries are edited on their own screen only. */
add_filter( 'post_row_actions', function ( $actions, $post ) {
	if ( 'atp_enquiry' === $post->post_type ) {
		unset( $actions['inline hide-if-no-js'], $actions['view'] );
	}
	return $actions;
}, 10, 2 );

<?php
/**
 * Lightweight log file endpoint
 * Serves log content without loading WordPress
 */

// Define log file path
$log_file = __DIR__ . '/logs/conversion.log';

// Set headers
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Check if log file exists
if ( ! file_exists( $log_file ) ) {
	wp_send_json_success( array(
		'content' => '',
		'mtime' => 0,
		'changed' => false,
		'size' => 0,
	) );
	exit;
}

$current_mtime = filemtime( $log_file );
$current_size = filesize( $log_file );
$last_mtime = isset( $_GET['mtime'] ) ? intval( $_GET['mtime'] ) : 0;

$changed = $current_mtime > $last_mtime;
$content = $changed ? file_get_contents( $log_file ) : '';

// Send JSON response
echo json_encode( array(
	'content' => $content,
	'mtime' => $current_mtime,
	'size' => $current_size,
	'changed' => $changed,
) );

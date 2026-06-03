<?php
/**
 * Uninstall Hook
 * Handles cleanup when plugin is uninstalled
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Define plugin directory
$plugin_dir = plugin_dir_path( __FILE__ );

// Remove .htaccess rules
$htaccess_path = ABSPATH . '.htaccess';
if ( file_exists( $htaccess_path ) ) {
	$htaccess_content = file_get_contents( $htaccess_path );
	$pattern = '/# WebP Image Converter Rules.*?# End WebP Rules\n/s';
	$cleaned_content = preg_replace( $pattern, '', $htaccess_content );
	file_put_contents( $htaccess_path, $cleaned_content );
}

// Delete plugin options
delete_option( 'wic_auto_convert_enabled' );
delete_option( 'wic_webp_quality' );
delete_option( 'wic_auto_backup_enabled' );
delete_option( 'wic_htaccess_configured' );
delete_option( 'wic_update_db_refs' );

// Optional: Delete plugin directories (logs, backups)
// Uncomment if you want to remove logs and backups on uninstall
/*
function wic_delete_dir_recursive( $dir ) {
	if ( is_dir( $dir ) ) {
		$files = array_diff( scandir( $dir ), array( '.', '..' ) );
		foreach ( $files as $file ) {
			$path = $dir . DIRECTORY_SEPARATOR . $file;
			if ( is_dir( $path ) ) {
				wic_delete_dir_recursive( $path );
			} else {
				unlink( $path );
			}
		}
		rmdir( $dir );
	}
}

// Uncomment to delete backups directory
// wic_delete_dir_recursive( $plugin_dir . 'backups' );

// Uncomment to delete logs directory
// wic_delete_dir_recursive( $plugin_dir . 'logs' );
*/

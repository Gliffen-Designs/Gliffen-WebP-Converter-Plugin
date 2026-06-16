<?php
/**
 * Plugin Name: Gliffen WebP Converter
 * Plugin URI: https://gliffen.com
 * Description: Convert images to WebP format with intelligent URL redirects and space savings
 * Version: 1.0.1
 * Author: Gliffen
 * Author URI: https://thecoda.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: webp-image-converter
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WIC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WIC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Require all necessary classes
require_once WIC_PLUGIN_DIR . 'inc/class-file-logger.php';
require_once WIC_PLUGIN_DIR . 'inc/class-converter.php';
require_once WIC_PLUGIN_DIR . 'inc/class-settings.php';
require_once WIC_PLUGIN_DIR . 'inc/class-redirect-handler.php';
require_once WIC_PLUGIN_DIR . 'inc/class-update-checker.php';
require_once WIC_PLUGIN_DIR . 'admin/class-admin-page.php';

/**
 * Initialize the plugin
 */
function wic_init() {
	// Create necessary directories on initialization
	if ( ! is_dir( WIC_PLUGIN_DIR . 'logs' ) ) {
		wp_mkdir_p( WIC_PLUGIN_DIR . 'logs' );
	}

	// Initialize logger
	WIC_File_Logger::init();

	// Initialize settings
	WIC_Settings::init();

	// Initialize redirect handler
	WIC_Redirect_Handler::init();

	// Initialize admin page (only in admin)
	if ( is_admin() ) {
		WIC_Admin_Page::init();
	}

	// Initialize GitHub update checker
	WIC_Update_Checker::init();
}
add_action( 'plugins_loaded', 'wic_init' );

/**
 * Hook into image upload process for auto-conversion
 */
function wic_handle_upload( $upload ) {
	// Only process if auto-conversion is enabled
	if ( ! WIC_Settings::get_option( 'auto_convert_enabled', true ) ) {
		return $upload;
	}

	$file_path = $upload['file'];
	$file_type = wp_check_filetype( $file_path );

	// Check if file is a supported image format
	$supported = array( 'jpg', 'jpeg', 'png', 'gif' );
	if ( ! in_array( $file_type['ext'], $supported, true ) ) {
		return $upload;
	}

	// Convert to WebP
	$quality = WIC_Settings::get_option( 'webp_quality', 80 );
	$backup = WIC_Settings::get_option( 'auto_backup_enabled', false );

	$converter = new WIC_Converter();
	$result = $converter->convert_image( $file_path, $quality, $backup );

	if ( is_array( $result ) && isset( $result['webp_path'] ) ) {
		// Delete original file
		if ( file_exists( $file_path ) ) {
			unlink( $file_path );
		}

		// Update the upload array to point to WebP
		$upload['file'] = $result['webp_path'];
		$upload['url'] = str_replace( WIC_PLUGIN_DIR, WIC_PLUGIN_URL, $result['webp_path'] );
		$upload['type'] = 'image/webp';
	}

	return $upload;
}
add_filter( 'wp_handle_upload', 'wic_handle_upload' );

/**
 * Handle intermediate image sizes conversion after attachment metadata is generated
 */
function wic_handle_attachment_metadata( $metadata, $attachment_id ) {
	// Only process if auto-conversion is enabled
	if ( ! WIC_Settings::get_option( 'auto_convert_enabled', true ) ) {
		return $metadata;
	}

	// Check if this attachment is an image
	$post = get_post( $attachment_id );
	if ( ! $post || strpos( $post->post_mime_type, 'image/' ) === false ) {
		return $metadata;
	}

	// Skip if already processed
	if ( isset( $metadata['webp_converted'] ) && $metadata['webp_converted'] ) {
		return $metadata;
	}

	$quality = WIC_Settings::get_option( 'webp_quality', 80 );
	$backup = WIC_Settings::get_option( 'auto_backup_enabled', false );

	$converter = new WIC_Converter();
	$result = $converter->convert_attachment_with_sizes( $attachment_id, $quality, $backup );

	// Return updated metadata
	return wp_get_attachment_metadata( $attachment_id );
}
add_filter( 'wp_generate_attachment_metadata', 'wic_handle_attachment_metadata', 10, 2 );

/**
 * Handle 404 requests for converted images
 */
function wic_handle_404() {
	global $wp_query;

	if ( $wp_query->is_404() ) {
		$requested_url = $_SERVER['REQUEST_URI'];
		$converter = new WIC_Converter();
		$webp_url = $converter->find_webp_equivalent( $requested_url );

		if ( $webp_url ) {
			wp_redirect( $webp_url, 301 );
			exit;
		}
	}
}
add_action( 'template_redirect', 'wic_handle_404' );

/**
 * Add plugin action links
 */
function wic_add_action_links( $links ) {
	$settings_link = '<a href="' . admin_url( 'tools.php?page=webp-converter' ) . '">Settings</a>';
	array_unshift( $links, $settings_link );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wic_add_action_links' );

/**
 * Plugin activation hook
 */
function wic_activate() {
	// Create logs directory
	wp_mkdir_p( WIC_PLUGIN_DIR . 'logs' );

	// Set default options
	if ( ! get_option( 'wic_auto_convert_enabled' ) ) {
		add_option( 'wic_auto_convert_enabled', true );
	}
	if ( ! get_option( 'wic_webp_quality' ) ) {
		add_option( 'wic_webp_quality', 80 );
	}
	if ( ! get_option( 'wic_auto_backup_enabled' ) ) {
		add_option( 'wic_auto_backup_enabled', false );
	}
	if ( ! get_option( 'wic_htaccess_configured' ) ) {
		add_option( 'wic_htaccess_configured', false );
	}
	if ( ! get_option( 'wic_batch_size' ) ) {
		add_option( 'wic_batch_size', 200 );
	}

	// Flush rewrite rules
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'wic_activate' );

/**
 * Plugin deactivation hook
 */
function wic_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'wic_deactivate' );

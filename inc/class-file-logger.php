<?php
/**
 * File Logger Class
 * Handles minimal file-based logging for image conversions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WIC_File_Logger {

	private static $log_file;

	/**
	 * Initialize logger
	 */
	public static function init() {
		self::$log_file = WIC_PLUGIN_DIR . 'logs/conversion.log';
	}

	/**
	 * Log a generic message.
	 *
	 * @param string $message Message to write to the log.
	 * @param string $level Log level label.
	 */
	public static function log( $message, $level = 'info' ) {
		if ( ! self::$log_file ) {
			self::init();
		}

		$timestamp = current_time( 'Y-m-d H:i:s' );
		$log_entry = sprintf(
			"[%s] [%s] %s\n",
			$timestamp,
			strtoupper( $level ),
			$message
		);

		if ( is_writable( dirname( self::$log_file ) ) ) {
			file_put_contents( self::$log_file, $log_entry, FILE_APPEND );
		}
	}

	/**
	 * Log a conversion entry
	 *
	 * @param string $filename Original filename
	 * @param int    $original_size Original file size in bytes
	 * @param int    $webp_size WebP file size in bytes
	 * @param string $status Status (OK, ERROR, etc.)
	 * @param string $error_message Optional error message
	 */
	public static function log_conversion( $filename, $original_size, $webp_size = 0, $status = 'OK', $error_message = '' ) {
		if ( ! self::$log_file ) {
			self::init();
		}

		$timestamp = current_time( 'Y-m-d H:i:s' );
		$saved = $original_size - $webp_size;
		
		if ( 'OK' === $status ) {
			$log_entry = sprintf(
				"[%s] %s → %.1f MB → %.1f MB webp (saved %.1f MB) | Status: OK\n",
				$timestamp,
				$filename,
				$original_size / 1048576,
				$webp_size / 1048576,
				$saved / 1048576
			);
		} else {
			$log_entry = sprintf(
				"[%s] %s → ERROR: %s\n",
				$timestamp,
				$filename,
				$error_message
			);
		}

		// Append to log file
		if ( is_writable( dirname( self::$log_file ) ) ) {
			file_put_contents( self::$log_file, $log_entry, FILE_APPEND );
		}
	}

	/**
	 * Get log file contents
	 *
	 * @return string Log file contents
	 */
	public static function get_log_contents() {
		if ( ! self::$log_file ) {
			self::init();
		}

		if ( file_exists( self::$log_file ) ) {
			return file_get_contents( self::$log_file );
		}

		return '';
	}

	/**
	 * Clear log file
	 */
	public static function clear_log() {
		if ( ! self::$log_file ) {
			self::init();
		}

		if ( file_exists( self::$log_file ) ) {
			file_put_contents( self::$log_file, '' );
		}
	}

	/**
	 * Log database reference updates
	 *
	 * @param string $original_filename Original filename (e.g., "2025/09/image.jpg")
	 * @param int    $posts_updated Number of posts updated
	 * @param int    $postmeta_updated Number of postmeta records updated
	 * @param int    $options_updated Number of options updated
	 * @param int    $links_updated Number of links updated
	 * @param string $type Type of update: 'primary', 'legacy', or 'complete'
	 */
	public static function log_database_update( $original_filename, $posts_updated = 0, $postmeta_updated = 0, $options_updated = 0, $links_updated = 0, $type = 'primary' ) {
		if ( ! self::$log_file ) {
			self::init();
		}

		$timestamp = current_time( 'Y-m-d H:i:s' );
		$total_replacements = $posts_updated + $postmeta_updated + $options_updated + $links_updated;

		if ( 'complete' === $type ) {
			// Summary line
			$log_entry = sprintf(
				"[%s] ═══ DATABASE UPDATE COMPLETE ═══ Posts: %d | Postmeta: %d | Options: %d | Links: %d | Total: %d\n",
				$timestamp,
				$posts_updated,
				$postmeta_updated,
				$options_updated,
				$links_updated,
				$total_replacements
			);
		} else {
			// Per-file logging
			$prefix = ( 'legacy' === $type ) ? '[LEGACY] ' : '';
			$log_entry = sprintf(
				"[%s] Processing %s%s → Posts: %d | Postmeta: %d | Options: %d | Links: %d | Total: %d\n",
				$timestamp,
				$prefix,
				$original_filename,
				$posts_updated,
				$postmeta_updated,
				$options_updated,
				$links_updated,
				$total_replacements
			);
		}

		// Append to log file
		if ( is_writable( dirname( self::$log_file ) ) ) {
			file_put_contents( self::$log_file, $log_entry, FILE_APPEND );
		}
	}

	/**
	 * Get log file path
	 *
	 * @return string Path to log file
	 */
	public static function get_log_file_path() {
		if ( ! self::$log_file ) {
			self::init();
		}

		return self::$log_file;
	}
}

// Initialize logger
WIC_File_Logger::init();

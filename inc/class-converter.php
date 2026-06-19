<?php
/**
 * Image Converter Class
 * Handles image conversion to WebP format
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WIC_Converter {

	/**
	 * Supported image formats
	 */
	const SUPPORTED_FORMATS = array( 'jpg', 'jpeg', 'png', 'gif' );

	/**
	 * Attachment meta keys for failed conversions
	 */
	const FAILED_CONVERSION_META_KEY = '_wic_webp_conversion_failed';
	const FAILED_CONVERSION_REASON_META_KEY = '_wic_webp_conversion_failed_reason';
	const FAILED_CONVERSION_TIME_META_KEY = '_wic_webp_conversion_failed_at';

	/**
	 * Convert image to WebP
	 *
	 * @param string $image_path Path to original image file
	 * @param int    $quality WebP quality (1-100)
	 * @param bool   $backup Whether to backup original file
	 *
	 * @return array|WP_Error Array with success details or WP_Error on failure
	 */
	public function convert_image( $image_path, $quality = 80, $backup = false ) {
		// Check if file exists
		if ( ! file_exists( $image_path ) ) {
			return new WP_Error( 'file_not_found', 'Image file not found: ' . $image_path );
		}

		// Get image information
		$image_info = pathinfo( $image_path );
		$file_ext = strtolower( $image_info['extension'] );

		// Check if format is supported
		if ( ! in_array( $file_ext, self::SUPPORTED_FORMATS, true ) ) {
			return new WP_Error( 'unsupported_format', 'Image format not supported: ' . $file_ext );
		}

		// Create WebP file path
		$webp_path = $image_info['dirname'] . '/' . $image_info['filename'] . '.webp';

		// Get original file size
		$original_size = filesize( $image_path );

		try {
			// Load image using GD library
			$image = $this->load_image( $image_path, $file_ext );

			if ( ! $image ) {
				throw new Exception( 'Failed to load image with GD library' );
			}

			// Backup original if requested
			if ( $backup ) {
				$this->backup_original( $image_path );
			}

			// Convert to WebP
			$success = imagewebp( $image, $webp_path, (int) $quality );
			imagedestroy( $image );

			if ( ! $success ) {
				throw new Exception( 'Failed to save WebP image' );
			}

			// Get WebP file size
			$webp_size = filesize( $webp_path );

			// Log success
			WIC_File_Logger::log_conversion(
				basename( $image_path ),
				$original_size,
				$webp_size,
				'OK'
			);

			return array(
				'webp_path' => $webp_path,
				'webp_url' => str_replace( WIC_PLUGIN_DIR, WIC_PLUGIN_URL, $webp_path ),
				'original_size' => $original_size,
				'webp_size' => $webp_size,
				'saved_size' => $original_size - $webp_size,
			);
		} catch ( Exception $e ) {
			// Log error
			WIC_File_Logger::log_conversion(
				basename( $image_path ),
				$original_size,
				0,
				'ERROR',
				$e->getMessage()
			);

			return new WP_Error( 'conversion_failed', $e->getMessage() );
		}
	}

	/**
	 * Load image using GD library
	 *
	 * @param string $image_path Path to image
	 * @param string $format Image format (jpg, png, gif)
	 *
	 * @return resource|false Image resource or false on failure
	 */
	private function load_image( $image_path, $format ) {
		switch ( strtolower( $format ) ) {
			case 'jpg':
			case 'jpeg':
				return imagecreatefromjpeg( $image_path );
			case 'png':
				$image = imagecreatefrompng( $image_path );
				// Preserve transparency for PNG
				if ( $image ) {
					imagepalettetotruecolor( $image );
					imagealphablending( $image, false );
					imagesavealpha( $image, true );
				}
				return $image;
			case 'gif':
				return imagecreatefromgif( $image_path );
			default:
				return false;
		}
	}

	/**
	 * Backup original image file
	 * Preserves directory structure under original-backups folder
	 *
	 * @param string $image_path Path to image
	 *
	 * @return bool Success status
	 */
	private function backup_original( $image_path ) {
		$upload_dir = wp_upload_dir();
		$upload_base = $upload_dir['basedir'];
		$backup_base = $upload_base . '/original-backups';

		// Create base backup directory if it doesn't exist
		if ( ! is_dir( $backup_base ) ) {
			wp_mkdir_p( $backup_base );
		}

		// Get relative path from upload directory
		$relative_path = str_replace( $upload_base . '/', '', $image_path );
		$relative_dir = dirname( $relative_path );

		// Create subdirectories in backup folder to mirror original structure
		$backup_dir = $backup_base;
		if ( $relative_dir && '.' !== $relative_dir ) {
			$backup_dir = $backup_base . '/' . $relative_dir;
		}

		if ( ! is_dir( $backup_dir ) ) {
			wp_mkdir_p( $backup_dir );
		}

		// Build backup file path with original filename
		$backup_filename = basename( $image_path );
		$backup_path = $backup_dir . '/' . $backup_filename;

		return copy( $image_path, $backup_path );
	}

	/**
	 * Find WebP equivalent of an image URL
	 *
	 * @param string $requested_url URL that was requested
	 *
	 * @return string|false WebP URL if found, false otherwise
	 */
	public function find_webp_equivalent( $requested_url ) {
		$upload_dir = wp_upload_dir();
		$base_path  = wp_normalize_path( $upload_dir['basedir'] );
		$base_url   = $upload_dir['baseurl'];

		// Normalize request input: support absolute URLs and site-relative paths.
		$parsed_path = wp_parse_url( $requested_url, PHP_URL_PATH );
		if ( ! is_string( $parsed_path ) || '' === $parsed_path ) {
			return false;
		}

		$clean_path = rawurldecode( $parsed_path );
		$file_path  = '';

		if ( strpos( $requested_url, $base_url ) === 0 ) {
			// Absolute uploads URL case.
			$file_path = str_replace( $base_url, $base_path, $requested_url );
			$file_path = wp_parse_url( $file_path, PHP_URL_PATH );
		} else {
			// Typical REQUEST_URI case: /wp-content/uploads/...
			$file_path = ABSPATH . ltrim( $clean_path, '/' );
		}

		$file_path = wp_normalize_path( (string) $file_path );

		// Only handle paths inside uploads.
		if ( strpos( $file_path, $base_path . '/' ) !== 0 ) {
			return false;
		}

		// Only redirect when original is missing.
		if ( file_exists( $file_path ) ) {
			return false;
		}

		$path_info = pathinfo( $file_path );
		if ( empty( $path_info['dirname'] ) || empty( $path_info['filename'] ) ) {
			return false;
		}

		$webp_path = $path_info['dirname'] . '/' . $path_info['filename'] . '.webp';
		if ( ! file_exists( $webp_path ) ) {
			return false;
		}

		// Convert local uploads path back to public uploads URL.
		$relative_webp = ltrim( str_replace( $base_path, '', $webp_path ), '/' );
		return trailingslashit( $base_url ) . $relative_webp;
	}

	/**
	 * Get the next unconverted image from media library
	 *
	 * @return object|null Next unconverted image attachment or null if none found
	 */
	public function get_media_library_images() {
		$args = array(
			'post_type' => 'attachment',
			'post_mime_type' => 'image/jpeg,image/png,image/gif',
			'posts_per_page' => -1,
			'post_status' => 'inherit',
		);

		$all_images = get_posts( $args );

		foreach ( $all_images as $image ) {
			// Skip files that have already failed conversion in batch mode.
			if ( $this->is_attachment_marked_failed( $image->ID ) ) {
				continue;
			}

			$image_path = get_attached_file( $image->ID );

			// Skip if file path is invalid or doesn't exist
			if ( ! $image_path || ! file_exists( $image_path ) ) {
				continue;
			}

			$path_info = pathinfo( $image_path );
			$file_ext = strtolower( $path_info['extension'] );

			// Skip if this is already a WebP file
			if ( $file_ext === 'webp' ) {
				continue;
			}

			// Skip if WebP already exists for this image
			$webp_path = $path_info['dirname'] . '/' . $path_info['filename'] . '.webp';
			if ( file_exists( $webp_path ) ) {
				continue;
			}

			// Return the first unconverted image found
			return $image;
		}

		// No unconverted images found
		return null;
	}

	/**
	 * Get conversion stats for media library
	 *
	 * @return array Conversion statistics
	 */
	public function get_conversion_stats() {
		// Get all images (not just unconverted) for stats purposes
		$args = array(
			'post_type' => 'attachment',
			'post_mime_type' => 'image/jpeg,image/png,image/gif',
			'posts_per_page' => -1,
			'post_status' => 'inherit',
		);

		$all_images = get_posts( $args );

		$stats = array(
			'total_images' => count( $all_images ),
			'converted_count' => 0,
			'unconverted_count' => 0,
			'failed_count' => 0,
			'total_original_size' => 0,
			'total_webp_size' => 0,
			'potential_savings' => 0,
		);

		foreach ( $all_images as $image ) {
			$attached_file = get_attached_file( $image->ID );
			if ( ! $attached_file || ! file_exists( $attached_file ) ) {
				continue;
			}

			$path_info = pathinfo( $attached_file );
			$file_ext = strtolower( $path_info['extension'] );

			// Check if this is already a WebP file
			if ( $file_ext === 'webp' ) {
				// This is a converted image
				$stats['converted_count']++;
				$webp_size = filesize( $attached_file );
				$stats['total_webp_size'] += $webp_size;
			} else {
				// This is an original format image
				$original_size = filesize( $attached_file );
				$stats['total_original_size'] += $original_size;

				// Check if WebP version exists
				$webp_path = $path_info['dirname'] . '/' . $path_info['filename'] . '.webp';
				if ( file_exists( $webp_path ) ) {
					// WebP exists, so it's been converted
					$stats['converted_count']++;
					$webp_size = filesize( $webp_path );
					$stats['total_webp_size'] += $webp_size;
				} elseif ( $this->is_attachment_marked_failed( $image->ID ) ) {
					// Failed images are tracked separately and excluded from remaining queue.
					$stats['failed_count']++;
				} else {
					// No WebP, so it's unconverted
					$stats['unconverted_count']++;
					// Estimate savings (typically 25-35% for WebP)
					$stats['potential_savings'] += (int) ( $original_size * 0.30 );
				}
			}
		}

		return $stats;
	}

	/**
	 * Convert attachment and all intermediate image sizes
	 *
	 * @param int    $attachment_id Attachment ID
	 * @param int    $quality WebP quality (1-100)
	 * @param bool   $backup Whether to backup original files
	 *
	 * @return array|WP_Error Array with conversion stats or WP_Error on failure
	 */
	public function convert_attachment_with_sizes( $attachment_id, $quality = 80, $backup = false ) {
		$image_path = get_attached_file( $attachment_id );

		if ( ! $image_path || ! file_exists( $image_path ) ) {
			$this->mark_attachment_failed( $attachment_id, 'Attachment file not found' );
			return new WP_Error( 'file_not_found', 'Attachment file not found' );
		}

		// Get attachment metadata to find intermediate sizes
		$metadata = wp_get_attachment_metadata( $attachment_id );
		if ( ! is_array( $metadata ) ) {
			$metadata = array();
		}

		$converted_count = 0;
		$failed_count = 0;
		$converted_sizes = array();
		$failure_messages = array();

		// Convert main image
		$main_result = $this->convert_image( $image_path, $quality, $backup );
		if ( is_array( $main_result ) ) {
			$this->update_attachment_to_webp( $attachment_id, $main_result['webp_path'] );
			$this->delete_image( $image_path );
			$converted_count++;
			$converted_sizes['main'] = $main_result['webp_path'];
		} else {
			$failed_count++;
			if ( is_wp_error( $main_result ) ) {
				$failure_messages[] = $main_result->get_error_message();
			}
		}

		// Convert intermediate sizes
		if ( isset( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
			$upload_dir = wp_upload_dir();
			$base_dir = $upload_dir['basedir'];
			$image_dir = dirname( $image_path );

			foreach ( $metadata['sizes'] as $size_name => $size_data ) {
				if ( ! isset( $size_data['file'] ) ) {
					continue;
				}

				$intermediate_path = $image_dir . '/' . $size_data['file'];

				// Skip if file doesn't exist
				if ( ! file_exists( $intermediate_path ) ) {
					continue;
				}

				// Skip if already converted to WebP
				$path_info = pathinfo( $intermediate_path );
				$webp_path = $path_info['dirname'] . '/' . $path_info['filename'] . '.webp';
				if ( file_exists( $webp_path ) ) {
					$converted_sizes[ $size_name ] = $webp_path;
					continue;
				}

				// Convert intermediate size
				$result = $this->convert_image( $intermediate_path, $quality, $backup );

				if ( is_array( $result ) ) {
					// Update metadata for this size
					$metadata['sizes'][ $size_name ]['file'] = basename( $result['webp_path'] );
					$metadata['sizes'][ $size_name ]['webp_converted'] = true;

					// Delete original
					$this->delete_image( $intermediate_path );
					$converted_count++;
					$converted_sizes[ $size_name ] = $result['webp_path'];
				} else {
					$failed_count++;
					if ( is_wp_error( $result ) ) {
						$failure_messages[] = sprintf( '%s: %s', $size_name, $result->get_error_message() );
					}
				}
			}
		}

		// Update metadata with conversion info
		$metadata['webp_converted'] = $converted_count > 0;
		$metadata['webp_converted_count'] = $converted_count;
		wp_update_attachment_metadata( $attachment_id, $metadata );

		if ( $converted_count > 0 ) {
			$this->clear_attachment_failed( $attachment_id );
		} else {
			$error_message = ! empty( $failure_messages )
				? implode( ' | ', array_unique( $failure_messages ) )
				: 'Conversion failed for unknown reason';

			$this->mark_attachment_failed( $attachment_id, $error_message );
		}

		return array(
			'converted_count' => $converted_count,
			'failed_count' => $failed_count,
			'converted_sizes' => $converted_sizes,
		);
	}

	/**
	 * Update attachment to point to WebP
	 *
	 * @param int    $attachment_id Attachment ID
	 * @param string $webp_path Path to WebP file
	 *
	 * @return bool Success status
	 */
	public function update_attachment_to_webp( $attachment_id, $webp_path ) {
		// Store original filename before updating (for database reference updates)
		// Store the relative path, not the full filesystem path
		$old_attached_file = get_attached_file( $attachment_id );
		if ( $old_attached_file ) {
			// Get upload directory to extract relative path
			$upload_dir = wp_upload_dir();
			$upload_base = $upload_dir['basedir'];
			
			// Get relative path by removing the base directory
			$relative_path = str_replace( $upload_base . '/', '', $old_attached_file );
			update_post_meta( $attachment_id, '_webp_original_file', $relative_path );
		}
		
		// Update the attached file path
		$update_result = update_attached_file( $attachment_id, $webp_path );
		
		if ( ! $update_result ) {
			return false;
		}

		// Update the attachment post's post_title and post_name if needed
		$webp_filename = basename( $webp_path );
		wp_update_post( array(
			'ID' => $attachment_id,
			'post_title' => pathinfo( $webp_filename, PATHINFO_FILENAME ),
		) );

		// Regenerate attachment metadata
		$attachment = get_post( $attachment_id );
		if ( $attachment && $attachment->post_type === 'attachment' ) {
			// Update metadata
			$metadata = wp_get_attachment_metadata( $attachment_id );
			if ( ! is_array( $metadata ) ) {
				$metadata = array();
			}

			// Convert main file from original format to WebP
			if ( isset( $metadata['file'] ) ) {
				$metadata['file'] = preg_replace( '/\.(jpg|jpeg|png|gif)$/i', '.webp', $metadata['file'] );
			}

			// Try to get WebP dimensions
			$webp_info = getimagesize( $webp_path );
			if ( $webp_info ) {
				$metadata['width'] = $webp_info[0];
				$metadata['height'] = $webp_info[1];
			}

			// Add conversion marker
			$metadata['webp_converted'] = true;

			wp_update_attachment_metadata( $attachment_id, $metadata );
			
			// Also save as separate postmeta for easier querying
			update_post_meta( $attachment_id, '_webp_converted', 1 );
		}

		$this->clear_attachment_failed( $attachment_id );

		return true;
	}

	/**
	 * Mark an attachment as failed so batch conversion can skip it.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $reason Failure reason.
	 *
	 * @return void
	 */
	private function mark_attachment_failed( $attachment_id, $reason = '' ) {
		$attachment_id = (int) $attachment_id;
		if ( $attachment_id <= 0 ) {
			return;
		}

		update_post_meta( $attachment_id, self::FAILED_CONVERSION_META_KEY, 1 );
		update_post_meta( $attachment_id, self::FAILED_CONVERSION_REASON_META_KEY, sanitize_text_field( (string) $reason ) );
		update_post_meta( $attachment_id, self::FAILED_CONVERSION_TIME_META_KEY, current_time( 'mysql' ) );
	}

	/**
	 * Clear failed conversion markers when an attachment converts successfully.
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @return void
	 */
	private function clear_attachment_failed( $attachment_id ) {
		$attachment_id = (int) $attachment_id;
		if ( $attachment_id <= 0 ) {
			return;
		}

		delete_post_meta( $attachment_id, self::FAILED_CONVERSION_META_KEY );
		delete_post_meta( $attachment_id, self::FAILED_CONVERSION_REASON_META_KEY );
		delete_post_meta( $attachment_id, self::FAILED_CONVERSION_TIME_META_KEY );
	}

	/**
	 * Check if an attachment has been marked as failed.
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @return bool
	 */
	private function is_attachment_marked_failed( $attachment_id ) {
		return (bool) get_post_meta( (int) $attachment_id, self::FAILED_CONVERSION_META_KEY, true );
	}

	/**
	 * Delete image file
	 *
	 * @param string $image_path Path to image
	 *
	 * @return bool Success status
	 */
	public function delete_image( $image_path ) {
		if ( file_exists( $image_path ) ) {
			return unlink( $image_path );
		}
		return false;
	}
}
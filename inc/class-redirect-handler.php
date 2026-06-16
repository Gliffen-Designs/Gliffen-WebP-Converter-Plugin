<?php
/**
 * Redirect Handler Class
 * Manages URL redirects from old image URLs to WebP equivalents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WIC_Redirect_Handler {

	/**
	 * Initialize redirect handler
	 */
	public static function init() {
		// No need for persistent initialization, methods are called on demand
	}

	/**
	 * Update .htaccess file with WebP redirect rules
	 *
	 * @return bool Success status
	 */
	public static function update_htaccess() {
		$htaccess_path = ABSPATH . '.htaccess';

		// Get current .htaccess content
		$htaccess_content = '';
		if ( file_exists( $htaccess_path ) ) {
			$htaccess_content = file_get_contents( $htaccess_path );
		}

		// Remove old WebP rules if they exist
		$htaccess_content = self::remove_webp_rules( $htaccess_content );

		// Add new WebP rules
		$webp_rules = self::get_webp_rules();
		$htaccess_content = $webp_rules . "\n" . $htaccess_content;

		// Write to .htaccess
		if ( ! is_writable( ABSPATH ) ) {
			return false;
		}

		$result = file_put_contents( $htaccess_path, $htaccess_content );
		
		if ( $result ) {
			WIC_Settings::update_option( 'htaccess_configured', true );
			return true;
		}

		return false;
	}

	/**
	 * Remove WebP rules from .htaccess content
	 *
	 * @param string $content .htaccess file content
	 *
	 * @return string Cleaned content
	 */
	private static function remove_webp_rules( $content ) {
		$pattern = '/# WebP Image Converter Rules.*?# End WebP Rules\n/s';
		return preg_replace( $pattern, '', $content );
	}

	/**
	 * Get WebP redirect rules for .htaccess
	 *
	 * @return string .htaccess rules
	 */
	private static function get_webp_rules() {
		$upload_dir = wp_upload_dir();
		$upload_path = str_replace( ABSPATH, '', $upload_dir['basedir'] );

		return <<<HTACCESS
# WebP Image Converter Rules
<IfModule mod_rewrite.c>
	RewriteEngine On
	
	# Redirect .jpg requests to .webp if original doesn't exist
	RewriteCond %{REQUEST_FILENAME} !-f
	RewriteCond %{REQUEST_URI} \\.jpg$ [NC]
	RewriteRule ^(.+)\\.jpg$ \$1.webp [L,R=301,NC]
	
	# Redirect .jpeg requests to .webp if original doesn't exist
	RewriteCond %{REQUEST_FILENAME} !-f
	RewriteCond %{REQUEST_URI} \\.jpeg$ [NC]
	RewriteRule ^(.+)\\.jpeg$ \$1.webp [L,R=301,NC]
	
	# Redirect .png requests to .webp if original doesn't exist
	RewriteCond %{REQUEST_FILENAME} !-f
	RewriteCond %{REQUEST_URI} \\.png$ [NC]
	RewriteRule ^(.+)\\.png$ \$1.webp [L,R=301,NC]
	
	# Redirect .gif requests to .webp if original doesn't exist
	RewriteCond %{REQUEST_FILENAME} !-f
	RewriteCond %{REQUEST_URI} \\.gif$ [NC]
	RewriteRule ^(.+)\\.gif$ \$1.webp [L,R=301,NC]
</IfModule>
# End WebP Rules
HTACCESS;
	}

	/**
	 * Remove WebP rules from .htaccess
	 *
	 * @return bool Success status
	 */
	public static function remove_htaccess_rules() {
		$htaccess_path = ABSPATH . '.htaccess';

		if ( ! file_exists( $htaccess_path ) ) {
			return true;
		}

		$htaccess_content = file_get_contents( $htaccess_path );
		$cleaned_content = self::remove_webp_rules( $htaccess_content );

		if ( ! is_writable( $htaccess_path ) ) {
			return false;
		}

		file_put_contents( $htaccess_path, $cleaned_content );
		WIC_Settings::update_option( 'htaccess_configured', false );

		return true;
	}

	/**
	 * Helper: Safely replace strings in any value type, handling serialization
	 *
	 * @param mixed  $value Value to search/replace in (string, array, object, or serialized data)
	 * @param string $old_url Old URL to search for
	 * @param string $new_url New URL to replace with
	 *
	 * @return array|mixed The modified value, or original if no changes
	 */
	private static function safely_replace_in_value( $value, $old_url, $new_url ) {
		if ( is_string( $value ) ) {
			// Check if it's serialized data
			$unserialized = @unserialize( $value );
			if ( false !== $unserialized ) {
				// Recursively process unserialized data
				$processed = self::safely_replace_in_value( $unserialized, $old_url, $new_url );
				if ( $processed !== $unserialized ) {
					// Re-serialize only if something changed
					return serialize( $processed );
				}
			} else {
				// Regular string - do simple replace
				$replaced = str_replace( $old_url, $new_url, $value );
				if ( $replaced !== $value ) {
					return $replaced;
				}
			}
		} elseif ( is_array( $value ) ) {
			// Recursively process array values
			$modified = false;
			foreach ( $value as $key => $item ) {
				$new_item = self::safely_replace_in_value( $item, $old_url, $new_url );
				if ( $new_item !== $item ) {
					$value[ $key ] = $new_item;
					$modified = true;
				}
			}
			if ( $modified ) {
				return $value;
			}
		} elseif ( is_object( $value ) ) {
			// Recursively process object properties
			$modified = false;
			foreach ( $value as $key => $item ) {
				$new_item = self::safely_replace_in_value( $item, $old_url, $new_url );
				if ( $new_item !== $item ) {
					$value->$key = $new_item;
					$modified = true;
				}
			}
			if ( $modified ) {
				return $value;
			}
		}

		return $value;
	}

	/**
	 * Update database references from old image URLs to WebP
	 *
	 * @return array Update statistics
	 */
	public static function update_database_references() {
		global $wpdb;

		$stats = array(
			'posts_updated' => 0,
			'postmeta_updated' => 0,
			'options_updated' => 0,
			'links_updated' => 0,
		);

		// Get upload directory
		$upload_dir = wp_upload_dir();
		$upload_url = $upload_dir['baseurl'];
		$upload_path = $upload_dir['basedir'];

		// Get all converted attachments
		$converted_attachments = get_posts( array(
			'post_type'      => 'attachment',
			'posts_per_page' => -1,
			'post_status'    => 'inherit',
			'meta_query'     => array(
				array(
					'key'     => '_webp_converted',
					'value'   => 1,
					'compare' => '=',
				),
			),
		) );

		// First, fix any old full-path metadata from previous conversions
		$old_path_attachments = get_posts( array(
			'post_type'      => 'attachment',
			'posts_per_page' => -1,
			'post_status'    => 'inherit',
			'meta_query'     => array(
				array(
					'key'     => '_webp_original_file',
					'compare' => 'EXISTS',
				),
			),
		) );

		foreach ( $old_path_attachments as $attach ) {
			$original_file = get_post_meta( $attach->ID, '_webp_original_file', true );
			if ( strpos( $original_file, $upload_path ) === 0 ) {
				// Convert full path to relative path
				$relative_path = str_replace( $upload_path . '/', '', $original_file );
				update_post_meta( $attach->ID, '_webp_original_file', $relative_path );
			}
		}

		// For each converted attachment, update references in the database
		foreach ( $converted_attachments as $attachment ) {
			// Get the original file that was stored before conversion
			$original_file = get_post_meta( $attachment->ID, '_webp_original_file', true );
			
			if ( ! $original_file ) {
				continue;
			}

			// Handle both full paths and relative paths
			if ( strpos( $original_file, $upload_path ) === 0 ) {
				// It's a full path, convert to relative
				$original_file = str_replace( $upload_path . '/', '', $original_file );
			}

			// Collect all files to replace: main image + all intermediate sizes
			$files_to_replace = array( $original_file );

			// Get attachment metadata to find intermediate sizes
			$metadata = wp_get_attachment_metadata( $attachment->ID );
			if ( is_array( $metadata ) && isset( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
				$image_dir = dirname( $original_file );
				foreach ( $metadata['sizes'] as $size_name => $size_data ) {
					if ( isset( $size_data['file'] ) ) {
						$size_filename = $size_data['file'];
						
						// If the intermediate size file is WebP, infer the original format
						if ( preg_match( '/\.webp$/i', $size_filename ) ) {
							$size_filename = preg_replace( '/\.webp$/i', '.jpg', $size_filename );
						}
						
						$intermediate_file = $image_dir . '/' . $size_filename;
						$files_to_replace[] = $intermediate_file;
					}
				}
			}

			// Process each file (main + intermediate sizes)
			foreach ( $files_to_replace as $file_to_replace ) {
				// Track stats for this specific file
				$file_posts = 0;
				$file_postmeta = 0;
				$file_options = 0;
				$file_links = 0;

				// Build search and replace URLs using relative path from WordPress root
				// This pattern will match in any context: /path, path, https://domain/path, etc.
				$relative_path = str_replace( ABSPATH, '', $upload_path . '/' . $file_to_replace );
				$old_url = $relative_path;
				$new_url = str_replace( $file_to_replace, preg_replace( '/\.(jpg|jpeg|png|gif)$/i', '.webp', $file_to_replace ), $relative_path );

				// Update posts table - simple string replace is safe for post_content
				$posts = get_posts( array(
					'posts_per_page' => -1,
					'post_status'    => 'any',
					s      => "post_content LIKE '%" . esc_sql( $old_url ) . "%'",
				) );

				foreach ( $posts as $post ) {
					$new_content = str_replace( $old_url, $new_url, $post->post_content );
					if ( $new_content !== $post->post_content ) {
						wp_update_post( array(
							'ID'           => $post->ID,
							'post_content' => $new_content,
						) );
						$file_posts++;
						$stats['posts_updated']++;
					}
				}

				// Update postmeta - use safe serialization-aware replacement
				$postmeta_rows = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT meta_id, post_id, meta_value FROM $wpdb->postmeta WHERE meta_value LIKE %s",
						'%' . $wpdb->esc_like( $old_url ) . '%'
					)
				);

				if ( ! empty( $postmeta_rows ) ) {
					foreach ( $postmeta_rows as $row ) {
						$new_value = self::safely_replace_in_value( $row->meta_value, $old_url, $new_url );
						if ( $new_value !== $row->meta_value ) {
							update_post_meta( $row->post_id, get_post_meta_by_id( $row->meta_id )->meta_key, $new_value, $row->meta_value );
							$file_postmeta++;
							$stats['postmeta_updated']++;
						}
					}
				}

				// Update options table - use safe serialization-aware replacement
				$option_rows = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT option_id, option_name, option_value FROM $wpdb->options WHERE option_value LIKE %s",
						'%' . $wpdb->esc_like( $old_url ) . '%'
					)
				);

				if ( ! empty( $option_rows ) ) {
					foreach ( $option_rows as $row ) {
						$new_value = self::safely_replace_in_value( $row->option_value, $old_url, $new_url );
						if ( $new_value !== $row->option_value ) {
							update_option( $row->option_name, $new_value );
							$file_options++;
							$stats['options_updated']++;
						}
					}
				}

				// Update links table - use safe serialization-aware replacement
				if ( $wpdb->get_var( "SHOW TABLES LIKE '$wpdb->links'" ) ) {
					$link_rows = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT link_id, link_image FROM $wpdb->links WHERE link_image LIKE %s",
							'%' . $wpdb->esc_like( $old_url ) . '%'
						)
					);

					if ( ! empty( $link_rows ) ) {
						foreach ( $link_rows as $row ) {
							$new_value = self::safely_replace_in_value( $row->link_image, $old_url, $new_url );
							if ( $new_value !== $row->link_image ) {
								$wpdb->update( $wpdb->links, array( 'link_image' => $new_value ), array( 'link_id' => $row->link_id ) );
								$file_links++;
								$stats['links_updated']++;
							}
						}
					}
				}

				// Log this file's update counts
				WIC_File_Logger::log_database_update( $file_to_replace, $file_posts, $file_postmeta, $file_options, $file_links, 'primary' );
			}
		}

		// Log summary of all updates
		WIC_File_Logger::log_database_update( '', $stats['posts_updated'], $stats['postmeta_updated'], $stats['options_updated'], $stats['links_updated'], 'complete' );

		return $stats;
	}

	/**
	 * Update database references for a single attachment
	 * Called immediately after converting an image to keep DB in sync
	 *
	 * @param int $attachment_id Attachment ID to update references for
	 *
	 * @return array Update stats for this attachment
	 */
	public static function update_database_references_for_attachment( $attachment_id ) {
		$stats = array(
			'posts_updated' => 0,
			'postmeta_updated' => 0,
			'options_updated' => 0,
			'links_updated' => 0,
		);

		// Check if this attachment was actually converted
		$is_converted = get_post_meta( $attachment_id, '_webp_converted', true );
		if ( ! $is_converted ) {
			return $stats; // Not converted, nothing to update
		}

		// Get upload directory
		$upload_dir = wp_upload_dir();
		$upload_path = $upload_dir['basedir'];

		// Get the original file
		$original_file = get_post_meta( $attachment_id, '_webp_original_file', true );

		// If no original file, try to recover it (legacy images)
		if ( ! $original_file ) {
			$metadata = wp_get_attachment_metadata( $attachment_id );
			if ( ! is_array( $metadata ) || ! isset( $metadata['file'] ) ) {
				return $stats; // Can't determine original file
			}

			$current_file = $metadata['file'];

			// If metadata['file'] is .webp, infer original
			if ( preg_match( '/\.webp$/i', $current_file ) ) {
				$original_file = preg_replace( '/\.webp$/i', '.jpg', $current_file );
				// Verify WebP exists
				$webp_full_path = $upload_path . '/' . $current_file;
				if ( ! file_exists( $webp_full_path ) ) {
					return $stats; // WebP doesn't exist
				}
				// Store it for next time
				update_post_meta( $attachment_id, '_webp_original_file', $original_file );
			} else {
				// If current file is still in original format, use it
				if ( preg_match( '/\.(jpg|jpeg|png|gif)$/i', $current_file ) ) {
					$original_file = $current_file;
				} else {
					return $stats; // Unknown format
				}
			}
		}

		// Handle full path if needed
		if ( strpos( $original_file, $upload_path ) === 0 ) {
			$original_file = str_replace( $upload_path . '/', '', $original_file );
		}

		// Collect files: main + intermediate sizes
		$files_to_replace = array( $original_file );

		$metadata = wp_get_attachment_metadata( $attachment_id );
		if ( is_array( $metadata ) && isset( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
			$image_dir = dirname( $original_file );
			foreach ( $metadata['sizes'] as $size_name => $size_data ) {
				if ( isset( $size_data['file'] ) ) {
					$size_filename = $size_data['file'];

					// If intermediate is WebP, infer original format
					if ( preg_match( '/\.webp$/i', $size_filename ) ) {
						$size_filename = preg_replace( '/\.webp$/i', '.jpg', $size_filename );
					}

					$intermediate_file = $image_dir . '/' . $size_filename;
					$files_to_replace[] = $intermediate_file;
				}
			}
		}

		// Process each file
		foreach ( $files_to_replace as $file_to_replace ) {
			$file_posts = 0;
			$file_postmeta = 0;
			$file_options = 0;
			$file_links = 0;

			// Build search and replace URLs using relative path from WordPress root
			$relative_path = str_replace( ABSPATH, '', $upload_path . '/' . $file_to_replace );
			$old_url = $relative_path;
			$new_url = str_replace( $file_to_replace, preg_replace( '/\.(jpg|jpeg|png|gif)$/i', '.webp', $file_to_replace ), $relative_path );

			// Update posts table - simple string replace is safe for post_content
			$posts = get_posts( array(
				'posts_per_page' => -1,
				'post_status'    => 'any',
				's'              => esc_sql( $old_url ),
			) );

			foreach ( $posts as $post ) {
				$new_content = str_replace( $old_url, $new_url, $post->post_content );
				if ( $new_content !== $post->post_content ) {
					wp_update_post( array(
						'ID'           => $post->ID,
						'post_content' => $new_content,
					) );
					$file_posts++;
					$stats['posts_updated']++;
				}
			}

			// Update postmeta - use safe serialization-aware replacement
			global $wpdb;
			$postmeta_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT meta_id, post_id, meta_value FROM $wpdb->postmeta WHERE meta_value LIKE %s",
					'%' . $wpdb->esc_like( $old_url ) . '%'
				)
			);

			if ( ! empty( $postmeta_rows ) ) {
				foreach ( $postmeta_rows as $row ) {
					$new_value = self::safely_replace_in_value( $row->meta_value, $old_url, $new_url );
					if ( $new_value !== $row->meta_value ) {
						// Get the meta key for this row
						$meta_key = $wpdb->get_var( $wpdb->prepare(
							"SELECT meta_key FROM $wpdb->postmeta WHERE meta_id = %d",
							$row->meta_id
						) );
						update_post_meta( $row->post_id, $meta_key, $new_value, $row->meta_value );
						$file_postmeta++;
						$stats['postmeta_updated']++;
					}
				}
			}

			// Update options table - use safe serialization-aware replacement
			$option_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT option_id, option_name, option_value FROM $wpdb->options WHERE option_value LIKE %s",
					'%' . $wpdb->esc_like( $old_url ) . '%'
				)
			);

			if ( ! empty( $option_rows ) ) {
				foreach ( $option_rows as $row ) {
					$new_value = self::safely_replace_in_value( $row->option_value, $old_url, $new_url );
					if ( $new_value !== $row->option_value ) {
						update_option( $row->option_name, $new_value );
						$file_options++;
						$stats['options_updated']++;
					}
				}
			}

			// Update links table - use safe serialization-aware replacement
			if ( $wpdb->get_var( "SHOW TABLES LIKE '$wpdb->links'" ) ) {
				$link_rows = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT link_id, link_image FROM $wpdb->links WHERE link_image LIKE %s",
						'%' . $wpdb->esc_like( $old_url ) . '%'
					)
				);

				if ( ! empty( $link_rows ) ) {
					foreach ( $link_rows as $row ) {
						$new_value = self::safely_replace_in_value( $row->link_image, $old_url, $new_url );
						if ( $new_value !== $row->link_image ) {
							$wpdb->update( $wpdb->links, array( 'link_image' => $new_value ), array( 'link_id' => $row->link_id ) );
							$file_links++;
							$stats['links_updated']++;
						}
					}
				}
			}

			// Log this file update
			WIC_File_Logger::log_database_update( $file_to_replace, $file_posts, $file_postmeta, $file_options, $file_links, 'primary' );
		}

		return $stats;
	}

	/**
	 * Get backup folder size
	 *
	 * @return int Folder size in bytes
	 */
	public static function get_backup_folder_size() {
		$upload_dir = wp_upload_dir();
		$backup_dir = $upload_dir['basedir'] . '/original-backups';

		if ( ! is_dir( $backup_dir ) ) {
			return 0;
		}

		return self::get_directory_size( $backup_dir );
	}

	/**
	 * Recursively calculate directory size
	 *
	 * @param string $directory Directory path
	 *
	 * @return int Total size in bytes
	 */
	private static function get_directory_size( $directory ) {
		$size = 0;
		if ( ! is_dir( $directory ) ) {
			return $size;
		}

		$files = scandir( $directory );
		foreach ( $files as $file ) {
			if ( '.' === $file || '..' === $file ) {
				continue;
			}

			$file_path = $directory . DIRECTORY_SEPARATOR . $file;
			if ( is_dir( $file_path ) ) {
				$size += self::get_directory_size( $file_path );
			} elseif ( is_file( $file_path ) ) {
				$size += filesize( $file_path );
			}
		}

		return $size;
	}

	/**
	 * Clear backup folder
	 *
	 * @return bool Success status
	 */
	public static function clear_backup_folder() {
		$upload_dir = wp_upload_dir();
		$backup_dir = $upload_dir['basedir'] . '/original-backups';

		if ( ! is_dir( $backup_dir ) ) {
			return true;
		}

		self::delete_directory_recursive( $backup_dir );
		return true;
	}

	/**
	 * Recursively delete directory and its contents
	 *
	 * @param string $directory Directory path
	 *
	 * @return bool Success status
	 */
	private static function delete_directory_recursive( $directory ) {
		if ( ! is_dir( $directory ) ) {
			return false;
		}

		$files = scandir( $directory );
		foreach ( $files as $file ) {
			if ( '.' === $file || '..' === $file ) {
				continue;
			}

			$file_path = $directory . DIRECTORY_SEPARATOR . $file;
			if ( is_dir( $file_path ) ) {
				self::delete_directory_recursive( $file_path );
			} elseif ( is_file( $file_path ) ) {
				unlink( $file_path );
			}
		}

		return rmdir( $directory );
	}

	/**
	 * Format bytes to human-readable format
	 *
	 * @param int $bytes Number of bytes
	 *
	 * @return string Formatted size
	 */
	public static function format_bytes( $bytes ) {
		$units = array( 'B', 'KB', 'MB', 'GB' );
		$bytes = max( $bytes, 0 );
		$pow = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
		$pow = min( $pow, count( $units ) - 1 );
		$bytes /= 1 << ( 10 * $pow );

		return round( $bytes, 2 ) . ' ' . $units[ $pow ];
	}
}

<?php
/**
 * Admin Page Class
 * Handles the admin interface for WebP conversion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WIC_Admin_Page {

	/**
	 * Initialize admin page
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_wic_batch_convert', array( __CLASS__, 'ajax_batch_convert' ) );
		add_action( 'wp_ajax_wic_get_stats', array( __CLASS__, 'ajax_get_stats' ) );
		add_action( 'wp_ajax_wic_save_settings', array( __CLASS__, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_wic_clear_backups', array( __CLASS__, 'ajax_clear_backups' ) );
		add_action( 'wp_ajax_wic_clear_log', array( __CLASS__, 'ajax_clear_log' ) );
		add_action( 'wp_ajax_wic_update_db_refs', array( __CLASS__, 'ajax_update_db_refs' ) );
		add_action( 'wp_ajax_wic_configure_htaccess', array( __CLASS__, 'ajax_configure_htaccess' ) );

		// Media library integration
		add_filter( 'attachment_fields_to_edit', array( __CLASS__, 'add_media_field' ), 10, 2 );
		add_filter( 'bulk_actions-upload', array( __CLASS__, 'add_bulk_actions' ) );
		add_action( 'handle_bulk_actions-upload', array( __CLASS__, 'handle_bulk_actions' ), 10, 3 );
	}

	/**
	 * Add admin menu
	 */
	public static function add_admin_menu() {
		add_submenu_page(
			'tools.php',
			'Gliffen WebP Converter',
			'WebP Converter',
			'manage_options',
			'webp-converter',
			array( __CLASS__, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin scripts and styles
	 */
	public static function enqueue_scripts( $hook ) {
		// Enqueue on WebP converter settings page
		if ( 'tools_page_webp-converter' === $hook ) {
			wp_enqueue_script(
				'wic-batch-converter',
				WIC_PLUGIN_URL . 'admin/js/batch-converter.js',
				array( 'jquery' ),
				WIC_VERSION,
				true
			);

			wp_localize_script(
				'wic-batch-converter',
				'wicAjax',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce' => wp_create_nonce( 'wic_nonce' ),
					'plugin_url' => WIC_PLUGIN_URL,
				)
			);

			wp_enqueue_style(
				'wic-admin-style',
				WIC_PLUGIN_URL . 'admin/css/admin-style.css',
				array(),
				WIC_VERSION
			);
		}

		// Enqueue on media library page
		if ( 'upload.php' === $hook ) {
			wp_enqueue_script(
				'wic-media-library',
				WIC_PLUGIN_URL . 'admin/js/media-library.js',
				array( 'jquery' ),
				WIC_VERSION,
				true
			);

			wp_localize_script(
				'wic-media-library',
				'wicMediaAjax',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce' => wp_create_nonce( 'wic_nonce' ),
				)
			);

			wp_enqueue_style(
				'wic-media-style',
				WIC_PLUGIN_URL . 'admin/css/media-library.css',
				array(),
				WIC_VERSION
			);
		}
	}

	/**
	 * Render admin page
	 */
	public static function render_admin_page() {
		// Check user permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		// Ensure all defaults exist
		WIC_Settings::ensure_defaults();

		// Get current stats
		$converter = new WIC_Converter();
		$stats = $converter->get_conversion_stats();
		$backup_size = WIC_Redirect_Handler::get_backup_folder_size();
		$settings = WIC_Settings::get_all_settings();
		$htaccess_configured = WIC_Settings::get_option( 'htaccess_configured', false );

		?>
		<div class="wrap wic-admin-wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<!-- Status Summary -->
			<div class="wic-status-box">
				<h2>Conversion Status</h2>
				<div class="wic-stats-grid">
					<div class="stat-item">
						<span class="stat-label">Total Images</span>
						<span class="stat-value"><?php echo esc_html( $stats['total_images'] ); ?></span>
					</div>
					<div class="stat-item">
						<span class="stat-label">Converted</span>
						<span class="stat-value" id="converted-count"><?php echo esc_html( $stats['converted_count'] ); ?></span>
					</div>
					<div class="stat-item">
						<span class="stat-label">Remaining</span>
						<span class="stat-value" id="remaining-count"><?php echo esc_html( $stats['unconverted_count'] ); ?></span>
					</div>
					<div class="stat-item">
						<span class="stat-label">Space Saved</span>
						<span class="stat-value" id="space-saved"><?php echo esc_html( WIC_Redirect_Handler::format_bytes( $stats['total_original_size'] - $stats['total_webp_size'] ) ); ?></span>
					</div>
				</div>
			</div>

			<!-- Tab Navigation -->
			<div class="wic-tabs">
				<button class="wic-tab-button active" data-tab="conversion">Batch Conversion</button>
				<button class="wic-tab-button" data-tab="settings">Settings</button>
				<button class="wic-tab-button" data-tab="maintenance">Maintenance</button>
			</div>

			<!-- Batch Conversion Tab -->
			<div id="tab-conversion" class="wic-tab-content active">
				<h2>Batch Image Conversion</h2>
				<p>Convert all images in your media library to WebP format one at a time until the maximum process limit is reached.</p>

				<div class="wic-progress-box" style="display: none;">
					<div class="wic-progress-bar">
						<div id="progress-fill" class="progress-fill"></div>
					</div>
					<p id="progress-text">Starting conversion...</p>
				</div>

				<div class="wic-actions">
					<div style="display: flex; gap: 15px; align-items: center;">
						<button id="start-conversion-btn" class="button button-primary">
							Start Batch Conversion
						</button>
						<button id="stop-conversion-btn" class="button" style="display: none;">
							Stop Conversion
						</button>
						<div>
							<label for="max-images" style="display: block; margin-bottom: 5px;"><strong>Max Images to Process:</strong></label>
							<input type="number" id="max-images" min="1" max="10000" value="500" style="width: 120px; padding: 5px;" />
						</div>
					</div>
				</div>

				<p class="wic-info">
					<strong>Note:</strong> Images will be processed one at a time. Original files will be deleted after successful conversion. Adjust "Max Images to Process" to control how many images are converted in each session.
				</p>
			</div>

			<!-- Settings Tab -->
			<div id="tab-settings" class="wic-tab-content">
				<h2>Plugin Settings</h2>

				<form id="wic-settings-form" method="post">
					<?php wp_nonce_field( 'wic_nonce', 'wic_nonce' ); ?>

					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="auto-convert">Auto-Convert on Upload</label>
							</th>
							<td>
								<input type="checkbox" id="auto-convert" name="auto_convert_enabled" value="1" <?php checked( $settings['auto_convert_enabled'] ); ?> />
								<p class="description">Automatically convert images to WebP when uploaded to the media library. Original files will be deleted immediately.</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="auto-backup">Auto-Backup Original Files</label>
							</th>
							<td>
								<input type="checkbox" id="auto-backup" name="auto_backup_enabled" value="1" <?php checked( $settings['auto_backup_enabled'] ); ?> />
								<p class="description">Keep backup copies of original files before conversion. Backups are organized by directory structure under <code>/wp-content/uploads/original-backups/</code></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="webp-quality">WebP Compression Quality</label>
							</th>
							<td>
								<input type="range" id="webp-quality" name="webp_quality" min="1" max="100" value="<?php echo esc_attr( $settings['webp_quality'] ); ?>" step="1" />
								<span id="quality-display"><?php echo esc_html( $settings['webp_quality'] ); ?></span>%
								<p class="description">Lower values = smaller file size but lower quality. Default: 80 (recommended).</p>
							</td>
						</tr>
					</table>

					<div class="wic-actions">
						<button type="button" id="save-settings-btn" class="button button-primary">Save Settings</button>
					</div>
				</form>

				<!-- .htaccess Configuration -->
				<div class="wic-section">
					<h3>.htaccess Configuration</h3>
					<p>
						<?php
						if ( $htaccess_configured ) {
							echo '<span style="color: green;">✓ .htaccess rules are configured</span>';
						} else {
							echo '<span style="color: red;">✗ .htaccess rules are not configured</span>';
						}
						?>
					</p>
					<p class="description">Configure .htaccess rules to automatically redirect old image URLs to WebP equivalents.</p>
					<button type="button" id="configure-htaccess-btn" class="button button-primary">
						<?php echo $htaccess_configured ? 'Update .htaccess' : 'Configure .htaccess'; ?>
					</button>
				</div>
			</div>

			<!-- Maintenance Tab -->
			<div id="tab-maintenance" class="wic-tab-content">
				<h2>Maintenance & Storage</h2>

				<div class="wic-section">
					<h3>Backup Folder Management</h3>
					<p>
						<strong>Current backup folder size:</strong> 
						<span id="backup-size"><?php echo esc_html( WIC_Redirect_Handler::format_bytes( $backup_size ) ); ?></span>
					</p>
					<p class="description">
						The backup folder is located at <code>/wp-content/uploads/original-backups/</code> and mirrors your uploads directory structure. Original files are preserved with their original filenames for easy restoration. You can clear this folder to free up space.
					</p>
					<button type="button" id="clear-backups-btn" class="button button-secondary">Clear All Backups</button>
				</div>

				<div class="wic-section">
					<h3>Database References Update</h3>
					<p class="description">
						Manually update all image URL references in the database to point to WebP files. This includes posts, pages, metadata, and theme options.
					</p>
					<button type="button" id="update-db-refs-btn" class="button button-primary">Update Database References</button>
				</div>

				<div class="wic-section">
					<h3>Conversion Log</h3>
					<p class="description">View recent conversion activity and errors.</p>
					<div style="display: flex; gap: 10px; margin-bottom: 10px;">
						<button type="button" id="watch-log-btn" class="button button-secondary">Start Watching</button>
						<span id="watch-status" style="align-self: center; font-weight: bold; color: #999;"></span>
					</div>
					<textarea id="conversion-log" readonly style="width: 100%; height: 300px; font-family: monospace; font-size: 12px; border: 1px solid #ddd;">
<?php echo esc_textarea( WIC_File_Logger::get_log_contents() ); ?>
					</textarea>
					<br><br>
					<button type="button" id="clear-log-btn" class="button">Clear Log</button>
				</div>
			</div>
		</div>

		<script>
			(function($) {
				// Quality slider display
				$('#webp-quality').on('input', function() {
					$('#quality-display').text($(this).val());
				});

				// Tab switching
				$('.wic-tab-button').on('click', function() {
					var tab = $(this).data('tab');
					$('.wic-tab-content').removeClass('active');
					$('#tab-' + tab).addClass('active');
					$('.wic-tab-button').removeClass('active');
					$(this).addClass('active');
				});
			})(jQuery);
		</script>
		<?php
	}

	/**
	 * AJAX: Batch convert images
	 */
	public static function ajax_batch_convert() {
		check_ajax_referer( 'wic_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$total_converted_so_far = isset( $_POST['total_converted_so_far'] ) ? intval( $_POST['total_converted_so_far'] ) : 0;
		$max_images = isset( $_POST['max_images'] ) ? intval( $_POST['max_images'] ) : 500;

		// Check if we've already hit the max limit
		if ( $total_converted_so_far >= $max_images ) {
			wp_send_json_success( array(
				'converted' => 0,
				'failed' => 0,
				'total_converted' => $total_converted_so_far,
				'total_remaining' => 0,
				'total_converted_so_far' => $total_converted_so_far,
				'max_images' => $max_images,
				'progress' => 100,
				'done' => true,
				'message' => "Reached maximum image limit ({$max_images} images processed). Click 'Start Batch Conversion' again to process more.",
			) );
		}

		$converter = new WIC_Converter();
		$image = $converter->get_media_library_images();

		// No more unconverted images
		if ( ! $image ) {
			wp_send_json_success( array(
				'converted' => 0,
				'failed' => 0,
				'total_converted' => $total_converted_so_far,
				'total_remaining' => 0,
				'total_converted_so_far' => $total_converted_so_far,
				'max_images' => $max_images,
				'progress' => 100,
				'done' => true,
				'message' => '',
			) );
		}

		$quality = WIC_Settings::get_option( 'webp_quality', 80 );
		$backup = WIC_Settings::get_option( 'auto_backup_enabled', false );

		// Convert this image (returns count of individual sizes converted)
		$result = $converter->convert_attachment_with_sizes( $image->ID, $quality, $backup );

		$converted = 0;
		$failed = 0;
		if ( is_array( $result ) && $result['converted_count'] > 0 ) {
			$converted = 1; // Count as 1 attachment converted, not the number of sizes
			$failed = 0;

			// Immediately update database references for this image
			WIC_Redirect_Handler::update_database_references_for_attachment( $image->ID );
		} else {
			$failed = 1;
		}

		$new_total_converted = $total_converted_so_far + $converted;
		$hit_max_limit = $new_total_converted >= $max_images;

		// Check if there's another image waiting
		$next_image = $converter->get_media_library_images();
		$has_more = $next_image !== null && ! $hit_max_limit;

		wp_send_json_success( array(
			'converted' => $converted,
			'failed' => $failed,
			'total_converted' => $new_total_converted,
			'total_remaining' => $has_more ? 1 : 0, // If there's a next image, there's at least 1 remaining
			'total_converted_so_far' => $new_total_converted,
			'max_images' => $max_images,
			'progress' => ( $new_total_converted / $max_images ) * 100,
			'done' => $hit_max_limit || ! $has_more,
			'message' => $hit_max_limit && $has_more ? "Reached maximum image limit ({$max_images} images processed). Click 'Start Batch Conversion' again to process more." : '',
		) );
	}

	/**
	 * AJAX: Get stats
	 */
	public static function ajax_get_stats() {
		check_ajax_referer( 'wic_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$converter = new WIC_Converter();
		$stats = $converter->get_conversion_stats();
		$backup_size = WIC_Redirect_Handler::get_backup_folder_size();

		wp_send_json_success( array(
			'stats' => $stats,
			'backup_size' => WIC_Redirect_Handler::format_bytes( $backup_size ),
		) );
	}

	/**
	 * AJAX: Save settings
	 */
	public static function ajax_save_settings() {
		check_ajax_referer( 'wic_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$auto_convert = (bool) intval( $_POST['auto_convert_enabled'] ?? 0 );
		$auto_backup = (bool) intval( $_POST['auto_backup_enabled'] ?? 0 );
		$quality = isset( $_POST['webp_quality'] ) ? intval( $_POST['webp_quality'] ) : 80;

		WIC_Settings::update_option( 'auto_convert_enabled', $auto_convert );
		WIC_Settings::update_option( 'auto_backup_enabled', $auto_backup );
		WIC_Settings::update_option( 'webp_quality', max( 1, min( 100, $quality ) ) );

		wp_send_json_success( array( 'message' => 'Settings saved successfully!' ) );
	}

	/**
	 * AJAX: Clear backups
	 */
	public static function ajax_clear_backups() {
		check_ajax_referer( 'wic_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$result = WIC_Redirect_Handler::clear_backup_folder();

		if ( $result ) {
			wp_send_json_success( array( 'message' => 'Backup folder cleared!' ) );
		} else {
			wp_send_json_error( array( 'message' => 'Failed to clear backup folder.' ) );
		}
	}

	/**
	 * AJAX: Clear conversion log
	 */
	public static function ajax_clear_log() {
		check_ajax_referer( 'wic_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		WIC_File_Logger::clear_log();

		wp_send_json_success( array( 'message' => 'Conversion log cleared!' ) );
	}

	/**
	 * AJAX: Update database references
	 */
	public static function ajax_update_db_refs() {
		check_ajax_referer( 'wic_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$stats = WIC_Redirect_Handler::update_database_references();

		wp_send_json_success( array(
			'message' => 'Database references updated!',
			'stats' => $stats,
		) );
	}

	/**
	 * AJAX: Configure .htaccess
	 */
	public static function ajax_configure_htaccess() {
		check_ajax_referer( 'wic_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$result = WIC_Redirect_Handler::update_htaccess();

		if ( $result ) {
			wp_send_json_success( array( 'message' => '.htaccess configured successfully!' ) );
		} else {
			wp_send_json_error( array( 'message' => '.htaccess is not writable. Please check file permissions.' ) );
		}
	}

	/**
	 * Add conversion field to media modal
	 */
	public static function add_media_field( $fields, $post ) {
		// Only for image attachments
		if ( 0 !== strpos( $post->post_mime_type, 'image/' ) ) {
			return $fields;
		}

		$image_path = get_attached_file( $post->ID );
		if ( ! $image_path || ! file_exists( $image_path ) ) {
			return $fields;
		}

		$path_info = pathinfo( $image_path );
		$webp_path = $path_info['dirname'] . '/' . $path_info['filename'] . '.webp';
		$is_converted = file_exists( $webp_path );

		$fields['wic_convert'] = array(
			'label' => 'WebP Conversion',
			'input' => 'html',
			'html' => self::get_conversion_field_html( $post->ID, $is_converted ),
		);

		return $fields;
	}

	/**
	 * Get HTML for conversion field in media modal
	 */
	private static function get_conversion_field_html( $attachment_id, $is_converted ) {
		if ( $is_converted ) {
			return '<p style="color: green;"><strong>✓ Converted to WebP</strong></p>';
		}

		return sprintf(
			'<p><a href="#" class="wic-convert-single-btn" data-attachment-id="%d" style="color: #0073aa; text-decoration: none; font-weight: 500;">Convert this image to WebP →</a></p>',
			esc_attr( $attachment_id )
		);
	}

	/**
	 * Add bulk action to media library
	 */
	public static function add_bulk_actions( $bulk_actions ) {
		$bulk_actions['wic_convert'] = 'Convert to WebP';
		return $bulk_actions;
	}

	/**
	 * Handle bulk action form submission
	 */
	public static function handle_bulk_actions( $redirect_to, $doaction, $post_ids ) {
		if ( 'wic_convert' !== $doaction ) {
			return $redirect_to;
		}

		if ( empty( $post_ids ) || ! is_array( $post_ids ) ) {
			return $redirect_to;
		}

		// Process all images directly in PHP
		$converted = 0;
		$failed = 0;
		$quality = WIC_Settings::get_option( 'webp_quality', 80 );
		$backup = WIC_Settings::get_option( 'auto_backup_enabled', false );

		$converter = new WIC_Converter();

		foreach ( $post_ids as $attachment_id ) {
			// Convert attachment and all its intermediate sizes
			$result = $converter->convert_attachment_with_sizes( $attachment_id, $quality, $backup );

			if ( is_array( $result ) && $result['converted_count'] > 0 ) {
				$converted += $result['converted_count'];
				$failed += $result['failed_count'];

				// Immediately update database references for this image
				WIC_Redirect_Handler::update_database_references_for_attachment( $attachment_id );
			} else {
				$failed++;
			}
		}

		// Add result message to redirect URL
		$redirect_to = add_query_arg( 'wic_bulk_converted', $converted, $redirect_to );
		$redirect_to = add_query_arg( 'wic_bulk_failed', $failed, $redirect_to );

		return $redirect_to;
	}
}

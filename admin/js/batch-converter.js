(function($) {
	'use strict';

	let isConverting = false;
	let totalConvertedSoFar = 0;

	// Start conversion
	$('#start-conversion-btn').on('click', function() {
		if (isConverting) return;

		isConverting = true;
		totalConvertedSoFar = 0;

		$('#start-conversion-btn').hide();
		$('#stop-conversion-btn').show();
		$('.wic-progress-box').show();

		startBatchConversion();
	});

	// Stop conversion
	$('#stop-conversion-btn').on('click', function() {
		isConverting = false;
		$(this).hide();
		$('#start-conversion-btn').show();
		$('#progress-text').text('Conversion stopped.');
	});

	// Start batch conversion
	function startBatchConversion() {
		if (!isConverting) {
			return;
		}

		const maxImages = parseInt($('#max-images').val()) || 500;

		$.ajax({
			url: wicAjax.ajax_url,
			type: 'POST',
			data: {
				action: 'wic_batch_convert',
				nonce: wicAjax.nonce,
				total_converted_so_far: totalConvertedSoFar,
				max_images: maxImages
			},
			success: function(response) {
				if (response.success) {
					const data = response.data;

					// Update progress bar
					const progressPercent = Math.round(data.progress);
					$('#progress-fill').css('width', progressPercent + '%');

					// Update total converted so far
					totalConvertedSoFar = data.total_converted_so_far;

					// Update status text
					$('#progress-text').text(
						'Converted: ' + data.total_converted + 
						' | Remaining: ' + data.total_remaining + ' | Progress: ' + progressPercent + '%'
					);

					// Update stats on page
					$('#converted-count').text(data.total_converted);
					$('#remaining-count').text(data.total_remaining);

					if (data.done) {
						// Conversion complete or max limit reached
						if (data.message && data.message.length > 0) {
							// Max limit reached
							$('#progress-text').text(data.message);
						} else {
							// All images converted
							$('#progress-text').text('✓ All images converted successfully!');
						}
						$('#stop-conversion-btn').hide();
						$('#start-conversion-btn').show();
						isConverting = false;
						refreshStats();
					} else {
						// Continue with next image
						setTimeout(startBatchConversion, 500);
					}
				} else {
					showError(response.data.message);
					isConverting = false;
				}
			},
			error: function() {
				showError('An error occurred during conversion.');
				isConverting = false;
			}
		});
	}

	// Save settings
	$('#save-settings-btn').on('click', function() {
		const data = {
			action: 'wic_save_settings',
			nonce: wicAjax.nonce,
			auto_convert_enabled: $('#auto-convert').is(':checked') ? 1 : 0,
			auto_backup_enabled: $('#auto-backup').is(':checked') ? 1 : 0,
			webp_quality: $('#webp-quality').val()
		};

		$.ajax({
			url: wicAjax.ajax_url,
			type: 'POST',
			data: data,
			success: function(response) {
				if (response.success) {
					showSuccess('Settings saved successfully!');
				} else {
					showError('Failed to save settings.');
				}
			},
			error: function() {
				showError('An error occurred while saving settings.');
			}
		});
	});

	// Clear backups
	$('#clear-backups-btn').on('click', function() {
		if (!confirm('Are you sure you want to clear all backup files? This cannot be undone.')) {
			return;
		}

		$.ajax({
			url: wicAjax.ajax_url,
			type: 'POST',
			data: {
				action: 'wic_clear_backups',
				nonce: wicAjax.nonce
			},
			success: function(response) {
				if (response.success) {
					showSuccess('Backup folder cleared!');
					$('#backup-size').text('0 B');
				} else {
					showError(response.data.message);
				}
			},
			error: function() {
				showError('An error occurred while clearing backups.');
			}
		});
	});

	// Update database references
	$('#update-db-refs-btn').on('click', function() {
		if (!confirm('This will update all image URLs in your database. Continue?')) {
			return;
		}

		$(this).prop('disabled', true).text('Updating...');

		$.ajax({
			url: wicAjax.ajax_url,
			type: 'POST',
			data: {
				action: 'wic_update_db_refs',
				nonce: wicAjax.nonce
			},
			success: function(response) {
				if (response.success) {
					showSuccess(response.data.message);
					alert('Database References Updated:\n' +
						'Posts updated: ' + response.data.stats.posts_updated + '\n' +
						'Postmeta updated: ' + response.data.stats.postmeta_updated + '\n' +
						'Options updated: ' + response.data.stats.options_updated + '\n' +
						'Links updated: ' + response.data.stats.links_updated);
				} else {
					showError('Failed to update database references.');
				}
			},
			error: function() {
				showError('An error occurred while updating database references.');
			},
			complete: function() {
				$('#update-db-refs-btn').prop('disabled', false).text('Update Database References');
			}
		});
	});

	// Configure .htaccess
	$('#configure-htaccess-btn').on('click', function() {
		$(this).prop('disabled', true).text('Configuring...');

		$.ajax({
			url: wicAjax.ajax_url,
			type: 'POST',
			data: {
				action: 'wic_configure_htaccess',
				nonce: wicAjax.nonce
			},
			success: function(response) {
				if (response.success) {
					showSuccess(response.data.message);
					location.reload();
				} else {
					showError(response.data.message);
				}
			},
			error: function() {
				showError('An error occurred while configuring .htaccess.');
			},
			complete: function() {
				$('#configure-htaccess-btn').prop('disabled', false).text('Configure .htaccess');
			}
		});
	});

	// Refresh stats
	function refreshStats() {
		$.ajax({
			url: wicAjax.ajax_url,
			type: 'POST',
			data: {
				action: 'wic_get_stats',
				nonce: wicAjax.nonce
			},
			success: function(response) {
				if (response.success) {
					$('#converted-count').text(response.data.stats.converted_count);
					$('#remaining-count').text(response.data.stats.unconverted_count);
					$('#space-saved').text(
						formatBytes(response.data.stats.total_original_size - response.data.stats.total_webp_size)
					);
					$('#backup-size').text(response.data.backup_size);
				}
			}
		});
	}

	// Format bytes helper
	function formatBytes(bytes) {
		const units = ['B', 'KB', 'MB', 'GB'];
		bytes = Math.max(bytes, 0);
		const pow = Math.floor(bytes ? Math.log(bytes) / Math.log(1024) : 0);
		const displayPow = Math.min(pow, units.length - 1);
		const displayBytes = (bytes / Math.pow(1024, displayPow)).toFixed(2);
		return displayBytes + ' ' + units[displayPow];
	}

	// Show success message
	function showSuccess(message) {
		const notice = $('<div class="notice notice-success is-dismissible"><p>' + message + '</p></div>');
		$('.wic-admin-wrap').prepend(notice);
		setTimeout(() => notice.fadeOut(() => notice.remove()), 5000);
	}

	// Show error message
	function showError(message) {
		const notice = $('<div class="notice notice-error is-dismissible"><p>' + message + '</p></div>');
		$('.wic-admin-wrap').prepend(notice);
		setTimeout(() => notice.fadeOut(() => notice.remove()), 5000);
	}

	// Clear conversion log
	$('#clear-log-btn').on('click', function() {
		if (!confirm('Are you sure you want to clear the conversion log?')) {
			return;
		}

		$(this).prop('disabled', true).text('Clearing...');

		$.ajax({
			url: wicAjax.ajax_url,
			type: 'POST',
			data: {
				action: 'wic_clear_log',
				nonce: wicAjax.nonce
			},
			success: function(response) {
				if (response.success) {
					$('#conversion-log').val('');
					showSuccess(response.data.message);
				} else {
					showError('Failed to clear log.');
				}
			},
			error: function() {
				showError('An error occurred while clearing the log.');
			},
			complete: function() {
				$('#clear-log-btn').prop('disabled', false).text('Clear Log');
			}
		});
	});

	// Watch log in real-time
	let watchingLog = false;
	let watchInterval = null;
	let lastLogMtime = 0;

	$('#watch-log-btn').on('click', function() {
		watchingLog = !watchingLog;

		if (watchingLog) {
			$(this).text('Stop Watching').removeClass('button-secondary').addClass('button-primary');
			$('#watch-status').text('● LIVE').css('color', '#0a0');

			// Get the plugin URL from localized data
			const logUrl = wicAjax.plugin_url + 'get-log.php';

			// Start polling for log updates (every 5 seconds)
			watchInterval = setInterval(function() {
				$.ajax({
					url: logUrl,
					type: 'GET',
					data: {
						mtime: lastLogMtime
					},
					dataType: 'json',
					success: function(response) {
						if (response.changed && response.content) {
							$('#conversion-log').val(response.content);
							// Auto-scroll to bottom
							$('#conversion-log').scrollTop($('#conversion-log')[0].scrollHeight);
						}
						lastLogMtime = response.mtime;
					}
				});
			}, 5000); // Poll every 5 seconds

			// Get initial log
			$.ajax({
				url: logUrl,
				type: 'GET',
				dataType: 'json',
				success: function(response) {
					lastLogMtime = response.mtime;
					if (response.content) {
						$('#conversion-log').val(response.content);
						$('#conversion-log').scrollTop($('#conversion-log')[0].scrollHeight);
					}
				}
			});
		} else {
			$(this).text('Start Watching').addClass('button-secondary').removeClass('button-primary');
			$('#watch-status').text('').css('color', '#999');
			clearInterval(watchInterval);
		}
	});

})(jQuery);

(function($) {
	'use strict';

	// Single image conversion from media modal
	$(document).on('click', '.wic-convert-single-btn', function(e) {
		e.preventDefault();

		const link = $(this);
		const originalText = link.text();

		link.css('opacity', '0.5').css('pointer-events', 'none').text('Converting...');

		// For now, just show a message that bulk action should be used
		showMediaNotice('To convert individual images, please use the bulk action from the Media Library list view.', 'info');
		
		link.css('opacity', '1').css('pointer-events', 'auto').text(originalText);
	});

	// Handle bulk action result messages
	if (window.location.search.indexOf('wic_bulk_converted=') !== -1) {
		const urlParams = new URLSearchParams(window.location.search);
		const converted = urlParams.get('wic_bulk_converted');
		const failed = urlParams.get('wic_bulk_failed');

		if (converted > 0) {
			showMediaNotice('✓ Successfully converted ' + converted + ' image(s) to WebP!' + (failed > 0 ? ' (' + failed + ' failed)' : ''), 'success');
		} else if (failed > 0) {
			showMediaNotice('Conversion failed for ' + failed + ' image(s).', 'error');
		}
	}

	// Show notification in media library
	function showMediaNotice(message, type) {
		const noticeClass = type === 'info' ? 'notice-warning' : 'notice-' + type;
		const notice = $('<div class="notice ' + noticeClass + ' is-dismissible" style="margin: 20px; padding: 12px;">' +
			'<p>' + message + '</p>' +
			'<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>' +
			'</div>');

		$('#wpbody-content').prepend(notice);

		// Auto-dismiss after 5 seconds
		setTimeout(function() {
			notice.fadeOut(function() {
				notice.remove();
			});
		}, 5000);

		// Manual dismiss
		notice.on('click', '.notice-dismiss', function() {
			notice.fadeOut(function() {
				notice.remove();
			});
		});
	}

})(jQuery);

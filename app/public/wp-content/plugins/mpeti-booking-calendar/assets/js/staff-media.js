(function($) {
	'use strict';

	$(document).ready(function() {
		var photoUploadBtn = $('.mbc-upload-photo-btn');
		var photoRemoveBtn = $('.mbc-remove-photo-btn');
		var photoInput = $('#staff_photo');
		var photoPreview = $('.mbc-photo-preview');

		// Upload button click
		photoUploadBtn.on('click', function(e) {
			e.preventDefault();

			var mediaUploader = wp.media({
				title: 'Choose Staff Photo',
				button: {
					text: 'Use this photo'
				},
				multiple: false,
				library: {
					type: 'image'
				}
			});

			mediaUploader.on('select', function() {
				var attachment = mediaUploader.state().get('selection').first().toJSON();
				photoInput.val(attachment.id);
				photoPreview.html('<img src="' + attachment.url + '" style="max-width: 150px; height: auto; border-radius: 8px;" />');
				photoRemoveBtn.show();
			});

			mediaUploader.open();
		});

		// Remove button click
		photoRemoveBtn.on('click', function(e) {
			e.preventDefault();
			photoInput.val('');
			photoPreview.html('');
			photoRemoveBtn.hide();
		});
	});
})(jQuery);


(function($) {
	'use strict';

	$(document).ready(function() {
		var imageUploadBtn = $('.mbc-upload-image-btn');
		var imageRemoveBtn = $('.mbc-remove-image-btn');
		var imageInput = $('#service_image');
		var imagePreview = $('.mbc-image-preview');

		// Upload button click
		imageUploadBtn.on('click', function(e) {
			e.preventDefault();

			var mediaUploader = wp.media({
				title: 'Choose Service Image',
				button: {
					text: 'Use this image'
				},
				multiple: false,
				library: {
					type: 'image'
				}
			});

			mediaUploader.on('select', function() {
				var attachment = mediaUploader.state().get('selection').first().toJSON();
				imageInput.val(attachment.id);
				imagePreview.html('<img src="' + attachment.url + '" style="max-width: 300px; height: auto; border-radius: 8px; border: 1px solid #ddd;" />');
				imageRemoveBtn.show();
			});

			mediaUploader.open();
		});

		// Remove button click
		imageRemoveBtn.on('click', function(e) {
			e.preventDefault();
			imageInput.val('');
			imagePreview.html('');
			imageRemoveBtn.hide();
		});
	});
})(jQuery);


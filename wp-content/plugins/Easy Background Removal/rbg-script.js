jQuery(document).ready(function($) {
    $('.rbg-remove-bg').on('click', function(e) {
        e.preventDefault();
        const imageId = $(this).data('id');
        const $link = $(this);
        $link.text('Removing...');

        $.ajax({
            url: rbg_ajax_obj.ajax_url,
            method: 'POST',
            data: {
                action: 'rbg_remove_bg',
                image_id: imageId,
                _ajax_nonce: rbg_ajax_obj.nonce
            },
            success: function(response) {
                if (response.success) {
                    $link.text('Done ✅');
                    alert('New Image Completed' + response.data.url);
                } else {
                    $link.text('Error');
                    alert('Error:' + response.data);
                }
            },
            error: function(xhr, status, error) {
                $link.text('Error');
                alert('Ajax error: ' + error);
            }
        });
    });
});

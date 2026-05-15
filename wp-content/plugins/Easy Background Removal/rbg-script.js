jQuery(function ($) {
    'use strict';

    function getText(key, fallback) {
        return window.rbg_ajax_obj && window.rbg_ajax_obj[key] ? window.rbg_ajax_obj[key] : fallback;
    }

    function requestRemoveBg(imageId, onStart, onDone, onError) {
        if (!imageId) {
            onError(getText('error_text', 'Error'));
            return;
        }

        onStart();

        $.ajax({
            url: rbg_ajax_obj.ajax_url,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'rbg_remove_bg',
                image_id: imageId,
                _ajax_nonce: rbg_ajax_obj.nonce,
            },
        })
            .done(function (response) {
                if (response && response.success && response.data) {
                    onDone(response.data);
                    return;
                }

                onError(response && response.data ? response.data : getText('error_text', 'Error'));
            })
            .fail(function (xhr, status, error) {
                var message = error || getText('error_text', 'Error');
                if (xhr.responseJSON && xhr.responseJSON.data) {
                    message = xhr.responseJSON.data;
                }
                onError(message);
            });
    }

    function resultLink(data) {
        var url = data && data.url ? data.url : '';
        var editUrl = data && data.editUrl ? data.editUrl : url;
        if (!url) {
            return '';
        }

        return ' <a href="' + editUrl + '" target="_blank" rel="noopener noreferrer">' + getText('new_image_text', 'New image') + '</a>';
    }

    $(document).on('click', '.rbg-remove-bg', function (e) {
        e.preventDefault();

        var $link = $(this);
        var originalText = $link.text();
        var imageId = parseInt($link.data('id'), 10) || 0;

        requestRemoveBg(
            imageId,
            function () {
                $link.text(getText('removing_text', 'Removing...'));
            },
            function (data) {
                $link.text(getText('done_text', 'Done'));
                $link.after(resultLink(data));
            },
            function (message) {
                $link.text(originalText || getText('button_text', 'Remove BG'));
                window.alert(message);
            }
        );
    });

    function getImageEditorId($root) {
        var id = 0;

        $root.find('[id^="image-editor-"], [id^="imgedit-save-target-"], [onclick*="imageEdit"]').each(function () {
            var raw = this.id || $(this).attr('onclick') || '';
            var match = raw.match(/(?:image-editor-|imgedit-save-target-|imageEdit\.[a-zA-Z]+\()(\d+)/);
            if (match) {
                id = parseInt(match[1], 10) || 0;
                return false;
            }
        });

        if (!id) {
            id = parseInt($('.attachment.selected').data('id'), 10) || 0;
        }

        return id;
    }

    function injectEditImageButton() {
        $('.imgedit-wrap, .image-editor').each(function () {
            var $editor = $(this);
            if ($editor.find('.rbg-edit-remove-bg').length) {
                return;
            }

            var imageId = getImageEditorId($editor);
            var $menu = $editor.find('.imgedit-menu').first();
            if (!$menu.length) {
                $menu = $editor.find('.imgedit-submit').first();
            }
            if (!$menu.length) {
                return;
            }

            var $button = $('<button type="button" class="button rbg-edit-remove-bg"></button>')
                .text(getText('edit_button_text', 'Remove BG'))
                .attr('data-id', imageId);
            var $status = $('<span class="rbg-edit-remove-bg-status" aria-live="polite"></span>');

            $menu.append(' ', $button, ' ', $status);
        });
    }

    $(document).on('click', '.rbg-edit-remove-bg', function (e) {
        e.preventDefault();

        var $button = $(this);
        var $status = $button.siblings('.rbg-edit-remove-bg-status').first();
        var imageId = parseInt($button.attr('data-id'), 10) || getImageEditorId($button.closest('.imgedit-wrap, .image-editor'));

        requestRemoveBg(
            imageId,
            function () {
                $button.prop('disabled', true);
                $status.text(getText('removing_text', 'Removing...'));
            },
            function (data) {
                $status.html(getText('done_text', 'Done') + resultLink(data));
            },
            function (message) {
                $status.text(message);
            }
        );
    });

    injectEditImageButton();
    if (window.MutationObserver) {
        new MutationObserver(injectEditImageButton).observe(document.body, {
            childList: true,
            subtree: true,
        });
    }
});

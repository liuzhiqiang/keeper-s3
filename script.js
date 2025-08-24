jQuery(document).ready(function($) {
    $(document).on('change', '.s3keeper-storage-select', function() {
        var select = $(this);
        var postId = select.data('post-id');
        var newLocation = select.val();
        // 创建一个loading div
        var loadingDiv = $('<div class="s3keeper-loading">Updating...</div>');
        select.after(loadingDiv)


        $.ajax({
            url: s3keeper_ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 's3keeper_update_attachment_location',
                post_id: postId,
                new_location: newLocation,
                _wpnonce: s3keeper_ajax_object.nonce
            },
            beforeSend: function() {
                // 禁用 select
                select.prop('disabled', true);
                // 显示 loading
                loadingDiv.show()
            },
            success: function(response) {
                // 启用 select
                select.prop('disabled', false);
                // 隐藏loading
                loadingDiv.hide()
                // 使用 WordPress 的提示机制
                $('#s3keeper-admin-notice').remove();
                if (response.success) {
                    $('.wrap').prepend('<div id="s3keeper-admin-notice" class="notice notice-success is-dismissible"><p>' + (response.data.message || 'Storage location updated') + '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>');
                } else {
                    $('.wrap').prepend('<div id="s3keeper-admin-notice" class="notice notice-error is-dismissible"><p>' + (response.data.message || 'There was an error updating storage location') + '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>');
                }


            },
            error: function(jqXHR, textStatus, errorThrown) {
                // 启用 select
                select.prop('disabled', false);
                // 隐藏loading
                loadingDiv.hide()
                console.error("AJAX Error:", textStatus, errorThrown);
            },
            complete: function() {
                // 启用 select
                select.prop('disabled', false);
                // 隐藏 loading
                loadingDiv.hide()
            }
        });
    });

    // 监听 admin notice 的关闭按钮
    $(document).on('click', '.notice-dismiss', function() {
        $(this).closest('.notice').fadeOut(function() {
            $(this).remove();
        });
    });
});
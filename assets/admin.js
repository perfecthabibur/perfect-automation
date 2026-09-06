jQuery(function($) {
    var fieldIndex = wclsAdmin.fieldCount || 0;

    $('#wcls-add-field').on('click', function() {
        var html = '<div class="wcls-mapping-row" data-index="' + fieldIndex + '">' +
            '<input type="text" name="wcls_settings[field_mappings][' + fieldIndex + '][meta_key]" placeholder="Meta Key (e.g. cartflows_size)">' +
            '<select name="wcls_settings[field_mappings][' + fieldIndex + '][type]">' +
                '<option value="size">Size</option>' +
                '<option value="color">Color</option>' +
                '<option value="other">Other</option>' +
            '</select>' +
            '<button type="button" class="wcls-btn wcls-btn-danger wcls-remove-field">Remove</button>' +
        '</div>';
        $('#wcls-field-mappings').append(html);
        fieldIndex++;
    });

    $(document).on('click', '.wcls-remove-field', function() {
        $(this).closest('.wcls-mapping-row').remove();
    });

    $('#wcls-test-connection').on('click', function() {
        var $btn = $(this);
        var $result = $('#wcls-test-result');
        $btn.prop('disabled', true).text('Testing...');
        $result.hide();

        $.ajax({
            url: wclsAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wcls_test_connection',
                nonce: wclsAdmin.nonce
            },
            success: function(response) {
                $result.show();
                if (response.success) {
                    $result.removeClass('wcls-result-error').addClass('wcls-result-success').text(response.message);
                } else {
                    $result.removeClass('wcls-result-success').addClass('wcls-result-error').text(response.message);
                }
            },
            error: function() {
                $result.show().removeClass('wcls-result-success').addClass('wcls-result-error').text('Connection error. Please try again.');
            },
            complete: function() {
                $btn.prop('disabled', false).text('Test Connection');
            }
        });
    });

    $(document).on('click', '.wcls-retry-order', function() {
        var $btn = $(this);
        var orderId = $btn.data('order-id');
        $btn.prop('disabled', true).text('Retrying...');

        $.ajax({
            url: wclsAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wcls_retry_order',
                nonce: wclsAdmin.nonce,
                order_id: orderId
            },
            success: function(response) {
                if (response.success) {
                    $btn.closest('tr').find('.wcls-status-cell').html('<span class="wcls-status wcls-status-active">Synced</span>');
                    $btn.remove();
                } else {
                    $btn.text('Failed').prop('disabled', false);
                    alert(response.message || 'Retry failed');
                }
            },
            error: function() {
                $btn.text('Retry').prop('disabled', false);
                alert('Connection error');
            }
        });
    });
});

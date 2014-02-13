jQuery(document).ready(function($) {
    $('button#oink_test_connection_btn').bind('click', function() {
        $(this).find('span').html('<img src="'+$('input#oink_ajax_spinner_img_url').val()+'"/>');
        $.ajax({
            url:$('input#oink_ajax_action_test_connection_url').val(),
            data:{
                transactionServiceURL:$('input#woocommerce_virtual-piggy_transactionServiceURL').val(),
                merchantIdentifier:$('input#woocommerce_virtual-piggy_merchantIdentifier').val(),
                apiKey:$('input#woocommerce_virtual-piggy_apiKey').val()
            },
            success:function(data){setButtonState(data);}
        });
        return false;
    });
});

function setButtonState(data) {
    if((jQuery).parseJSON(data).success == 1) {
        if((jQuery)('button#oink_test_connection_btn').hasClass('oink_failure')) {
            (jQuery)('button#oink_test_connection_btn').removeClass('oink_failure')
        }
        if(!(jQuery)('button#oink_test_connection_btn').hasClass('oink_success')) {
            (jQuery)('button#oink_test_connection_btn').addClass('oink_success')
        }
        (jQuery)('button#oink_test_connection_btn').find('span').html('Successful! Test again?');
    }
    else {
        if((jQuery)('button#oink_test_connection_btn').hasClass('oink_success')) {
            (jQuery)('button#oink_test_connection_btn').removeClass('oink_success')
        }
        if(!(jQuery)('button#oink_test_connection_btn').hasClass('oink_failure')) {
            (jQuery)('button#oink_test_connection_btn').addClass('oink_failure')
        }
        (jQuery)('button#oink_test_connection_btn').find('span').html('Connection failed! Test again?');
    }
}
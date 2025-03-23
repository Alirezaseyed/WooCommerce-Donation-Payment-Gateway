jQuery(document).ready(function($) {
    if (typeof wc_donation_params === 'undefined') return;

    var checkStatus = function() {
        $.ajax({
            url: wc_donation_params.ajax_url,
            type: 'POST',
            data: {
                action: 'check_donation_status',
                order_id: wc_donation_params.order_id,
                tracking_code: wc_donation_params.tracking_code
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    if (response.data.status === 'completed') {
                        window.location.reload(); // رفرش صفحه بعد از موفقیت
                    }
                } else {
                    alert(response.data.message);
                }
            },
            error: function() {
                alert('خطا در بررسی وضعیت پرداخت.');
            }
        });
    };

    // چک خودکار هر 10 ثانیه (اختیاری)
    setInterval(checkStatus, 10000);

    // یا اضافه کردن دکمه دستی (اختیاری)
    $('form.checkout').append('<button type="button" id="check-donation-status">بررسی وضعیت پرداخت</button>');
    $('#check-donation-status').on('click', checkStatus);
});

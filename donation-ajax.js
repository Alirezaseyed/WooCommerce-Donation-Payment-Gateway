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
                    Swal.fire({
                        icon: response.data.status === 'completed' ? 'success' : 'info',
                        title: response.data.status === 'completed' ? 'پرداخت موفق' : 'وضعیت پرداخت',
                        text: response.data.message,
                        confirmButtonText: 'باشه'
                    }).then((result) => {
                        if (response.data.status === 'completed') {
                            window.location.reload();
                        }
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'خطا',
                        text: response.data.message,
                        confirmButtonText: 'باشه'
                    });
                }
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'خطا',
                    text: 'خطا در بررسی وضعیت پرداخت.',
                    confirmButtonText: 'باشه'
                });
            }
        });
    };

    // چک خودکار هر 10 ثانیه
    setInterval(checkStatus, 10000);

    // دکمه دستی برای بررسی وضعیت
    $('form.checkout').append('<button type="button" id="check-donation-status">بررسی وضعیت پرداخت</button>');
    $('#check-donation-status').on('click', checkStatus);
});

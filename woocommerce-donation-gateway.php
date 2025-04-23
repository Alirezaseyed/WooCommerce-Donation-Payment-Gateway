<?php
/**
 * Plugin Name: WooCommerce Donation Payment Gateway
 * Plugin URI: https://saadline.ir
 * Description: یک درگاه پرداخت سفارشی پیشرفته برای هدایت کاربران به صفحه پرداخت حمایتی با قابلیت AJAX، ادغام زرین‌پال، نوار پیشرفت و چند ارزی.
 * Version: 3.0
 * Author: Sedali
 * Author URI: https://saadline.ir
 * Text Domain: wc-donation-gateway
 * Domain Path: /languages
 * Requires Plugins: woocommerce
 * WC requires at least: 3.0
 * WC tested up to: 8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// بررسی فعال بودن ووکامرس
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', function() {
        echo '<div class="error"><p>' . __('پلاگین WooCommerce Donation Payment Gateway نیازمند افزونه ووکامرس است.', 'wc-donation-gateway') . '</p></div>';
    });
    return;
}

// اضافه کردن درگاه پرداخت به ووکامرس
add_filter('woocommerce_payment_gateways', 'add_custom_donation_gateway');
function add_custom_donation_gateway($gateways) {
    $gateways[] = 'WC_Custom_Donation_Gateway';
    return $gateways;
}

// تعریف کلاس درگاه پرداخت سفارشی
add_action('plugins_loaded', 'init_custom_donation_gateway');
function init_custom_donation_gateway() {
    class WC_Custom_Donation_Gateway extends WC_Payment_Gateway {

        public function __construct() {
            $this->id = 'custom_donation';
            $this->method_title = __('پرداخت حمایتی', 'wc-donation-gateway');
            $this->method_description = __('پرداخت حمایتی با امکان انتخاب مبلغ دلخواه، چند ارزی و ادغام با زرین‌پال.', 'wc-donation-gateway');
            $this->has_fields = true;
            $this->supports = array('products');

            $this->init_form_fields();
            $this->init_settings();

            $this->enabled = $this->get_option('enabled');
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->redirect_url = $this->get_option('redirect_url');
            $this->redirect_options = $this->get_option('redirect_options');
            $this->icon = $this->get_option('logo');
            $this->zarinpal_enabled = $this->get_option('zarinpal_enabled');
            $this->zarinpal_merchant_id = $this->get_option('zarinpal_merchant_id');
            $this->enable_custom_amount = $this->get_option('enable_custom_amount');
            $this->min_amount = $this->get_option('min_amount');
            $this->max_amount = $this->get_option('max_amount');
            $this->campaign_goal = $this->get_option('campaign_goal');
            $this->api_key = $this->get_option('api_key');
            $this->supported_currencies = $this->get_option('supported_currencies');

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
            add_action('wp_ajax_check_donation_status', array($this, 'check_donation_status'));
            add_action('wp_ajax_nopriv_check_donation_status', array($this, 'check_donation_status'));
            add_action('woocommerce_before_checkout_form', array($this, 'display_campaign_progress'));
        }

        // تنظیمات درگاه پرداخت در پنل ادمین
        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('فعال‌سازی', 'wc-donation-gateway'),
                    'type' => 'checkbox',
                    'label' => __('فعال کردن درگاه پرداخت حمایتی', 'wc-donation-gateway'),
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => __('عنوان درگاه', 'wc-donation-gateway'),
                    'type' => 'text',
                    'description' => __('عنوانی که در صفحه تسویه‌حساب نمایش داده می‌شود.', 'wc-donation-gateway'),
                    'default' => __('پرداخت حمایتی', 'wc-donation-gateway'),
                    'desc_tip' => true,
                ),
                'description' => array(
                    'title' => __('توضیحات', 'wc-donation-gateway'),
                    'type' => 'textarea',
                    'description' => __('توضیحاتی که در صفحه تسویه‌حساب نمایش داده می‌شود.', 'wc-donation-gateway'),
                    'default' => __('با انتخاب این گزینه به صفحه پرداخت حمایتی هدایت می‌شوید.', 'wc-donation-gateway'),
                    'desc_tip' => true,
                ),
                'redirect_url' => array(
                    'title' => __('لینک پیش‌فرض پرداخت', 'wc-donation-gateway'),
                    'type' => 'text',
                    'description' => __('لینک پیش‌فرض در صورتی که زرین‌پال غیرفعال باشد.', 'wc-donation-gateway'),
                    'default' => 'https://example.com/donation-page',
                    'desc_tip' => true,
                ),
                'redirect_options' => array(
                    'title' => __('گزینه‌های هدایت', 'wc-donation-gateway'),
                    'type' => 'textarea',
                    'description' => __('هر خط یک گزینه: نام|لینک (مثال: کمک نقدی|https://example.com/cash)', 'wc-donation-gateway'),
                    'default' => "کمک نقدی|https://example.com/cash\nکمک غیرنقدی|https://example.com/noncash",
                    'desc_tip' => true,
                ),
                'logo' => array(
                    'title' => __('لوگوی درگاه', 'wc-donation-gateway'),
                    'type' => 'text',
                    'description' => __('آدرس URL لوگو (مثلاً از رسانه‌های وردپرس)', 'wc-donation-gateway'),
                    'default' => '',
                    'desc_tip' => true,
                ),
                'zarinpal_enabled' => array(
                    'title' => __('فعال‌سازی زرین‌پال', 'wc-donation-gateway'),
                    'type' => 'checkbox',
                    'label' => __('فعال کردن درگاه زرین‌پال', 'wc-donation-gateway'),
                    'default' => 'no',
                    'desc_tip' => true,
                ),
                'zarinpal_merchant_id' => array(
                    'title' => __('شناسه درگاه زرین‌پال', 'wc-donation-gateway'),
                    'type' => 'text',
                    'description' => __('شناسه درگاه (Merchant ID) زرین‌پال', 'wc-donation-gateway'),
                    'default' => '',
                    'desc_tip' => true,
                ),
                'enable_custom_amount' => array(
                    'title' => __('فعال‌سازی مبلغ دلخواه', 'wc-donation-gateway'),
                    'type' => 'checkbox',
                    'label' => __('اجازه دادن به کاربران برای وارد کردن مبلغ دلخواه', 'wc-donation-gateway'),
                    'default' => 'yes',
                    'desc_tip' => true,
                ),
                'min_amount' => array(
                    'title' => __('حداقل مبلغ (تومان)', 'wc-donation-gateway'),
                    'type' => 'number',
                    'description' => __('حداقل مبلغ مجاز برای پرداخت', 'wc-donation-gateway'),
                    'default' => 1000,
                    'desc_tip' => true,
                    'custom_attributes' => array('step' => '100', 'min' => '0'),
                ),
                'max_amount' => array(
                    'title' => __('حداکثر مبلغ (تومان)', 'wc-donation-gateway'),
                    'type' => 'number',
                    'description' => __('حداکثر مبلغ مجاز برای پرداخت', 'wc-donation-gateway'),
                    'default' => 10000000,
                    'desc_tip' => true,
                    'custom_attributes' => array('step' => '100', 'min' => '0'),
                ),
                'campaign_goal' => array(
                    'title' => __('هدف کمپین (تومان)', 'wc-donation-gateway'),
                    'type' => 'number',
                    'description' => __('مبلغ هدف برای نمایش نوار پیشرفت', 'wc-donation-gateway'),
                    'default' => 100000000,
                    'desc_tip' => true,
                ),
                'api_key' => array(
                    'title' => __('کلید API', 'wc-donation-gateway'),
                    'type' => 'text',
                    'description' => __('کلید API برای ایمن‌سازی REST API', 'wc-donation-gateway'),
                    'default' => wp_generate_uuid4(),
                    'desc_tip' => true,
                ),
                'supported_currencies' => array(
                    'title' => __('ارزهای پشتیبانی‌شده', 'wc-donation-gateway'),
                    'type' => 'multiselect',
                    'description' => __('ارزهایی که کاربران می‌توانند انتخاب کنند.', 'wc-donation-gateway'),
                    'options' => array(
                        'IRR' => __('ریال ایران', 'wc-donation-gateway'),
                        'USD' => __('دلار آمریکا', 'wc-donation-gateway'),
                        'EUR' => __('یورو', 'wc-donation-gateway'),
                    ),
                    'default' => array('IRR'),
                    'desc_tip' => true,
                ),
            );
        }

        // نمایش فیلدهای پرداخت در صفحه تسویه‌حساب
        public function payment_fields() {
            if ($this->description) {
                echo wpautop(wp_kses_post($this->description));
            }

            // نمایش گزینه‌های هدایت
            $options = explode("\n", $this->redirect_options);
            if (count($options) > 1 && $this->zarinpal_enabled !== 'yes') {
                echo '<label>' . esc_html__('لطفاً گزینه پرداخت را انتخاب کنید:', 'wc-donation-gateway') . '</label><br>';
                echo '<select name="donation_redirect_option" class="wc-donation-select">';
                foreach ($options as $option) {
                    $option = trim($option);
                    if (empty($option)) continue;
                    list($name, $url) = explode('|', $option, 2);
                    echo '<option value="' . esc_url($url) . '">' . esc_html($name) . '</option>';
                }
                echo '</select><br>';
            }

            // نمایش فیلد مبلغ دلخواه
            if ($this->enable_custom_amount === 'yes') {
                echo '<label>' . esc_html__('مبلغ دلخواه:', 'wc-donation-gateway') . '</label><br>';
                echo '<input type="number" name="donation_custom_amount" class="wc-donation-amount" min="' . esc_attr($this->min_amount) . '" max="' . esc_attr($this->max_amount) . '" step="100" placeholder="' . esc_attr__('مبلغ را وارد کنید', 'wc-donation-gateway') . '" /><br>';
            }

            // نمایش انتخاب ارز
            if (!empty($this->supported_currencies) && count($this->supported_currencies) > 1) {
                echo '<label>' . esc_html__('ارز پرداخت:', 'wc-donation-gateway') . '</label><br>';
                echo '<select name="donation_currency" class="wc-donation-currency">';
                foreach ($this->supported_currencies as $currency) {
                    echo '<option value="' . esc_attr($currency) . '">' . esc_html($currency) . '</option>';
                }
                echo '</select>';
            }
        }

        // اعتبارسنجی فیلدها
        public function validate_fields() {
            if ($this->enable_custom_amount === 'yes' && empty($_POST['donation_custom_amount'])) {
                wc_add_notice(__('لطفاً مبلغ دلخواه را وارد کنید.', 'wc-donation-gateway'), 'error');
                return false;
            }

            if ($this->enable_custom_amount === 'yes') {
                $amount = floatval($_POST['donation_custom_amount']);
                if ($amount < $this->min_amount || $amount > $this->max_amount) {
                    wc_add_notice(sprintf(__('مبلغ وارد شده باید بین %s و %s تومان باشد.', 'wc-donation-gateway'), $this->min_amount, $this->max_amount), 'error');
                    return false;
                }
            }

            return true;
        }

        // اعتبارسنجی لینک پیش‌فرض
        public function validate_redirect_url_field($key, $value) {
            if (empty($value) || !filter_var($value, FILTER_VALIDATE_URL)) {
                WC_Admin_Settings::add_error(__('لینک صفحه پرداخت معتبر نیست. لطفاً یک URL صحیح وارد کنید.', 'wc-donation-gateway'));
                return $this->get_option('redirect_url');
            }
            return $value;
        }

        // اعتبارسنجی گزینه‌های هدایت
        public function validate_redirect_options_field($key, $value) {
            $lines = explode("\n", $value);
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                $parts = explode('|', $line, 2);
                if (count($parts) < 2 || !filter_var($parts[1], FILTER_VALIDATE_URL)) {
                    WC_Admin_Settings::add_error(sprintf(__('گزینه "%s" نامعتبر است. هر خط باید شامل نام و یک URL صحیح باشد.', 'wc-donation-gateway'), $line));
                    return $this->get_option('redirect_options');
                }
            }
            return $value;
        }

        // پردازش پرداخت
        public function process_payment($order_id) {
            $order = wc_get_order($order_id);
            $custom_amount = !empty($_POST['donation_custom_amount']) ? floatval($_POST['donation_custom_amount']) : 0;
            $currency = !empty($_POST['donation_currency']) ? sanitize_text_field($_POST['donation_currency']) : 'IRR';
            $tracking_code = wp_generate_uuid4();

            // به‌روزرسانی مبلغ سفارش
            if ($custom_amount > 0) {
                foreach ($order->get_items() as $item_id => $item) {
                    $item->set_subtotal($custom_amount);
                    $item->set_total($custom_amount);
                    $item->save();
                }
                $order->calculate_totals();
            }

            // ذخیره اطلاعات
            $order->update_meta_data('_donation_tracking_code', $tracking_code);
            $order->update_meta_data('_donation_currency', $currency);
            $order->update_status('pending', __('در انتظار تأیید پرداخت حمایتی', 'wc-donation-gateway'));

            // پردازش پرداخت با زرین‌پال
            if ($this->zarinpal_enabled === 'yes' && !empty($this->zarinpal_merchant_id)) {
                $amount = $order->get_total();
                if ($currency !== 'IRR') {
                    $amount = $this->convert_currency($amount, $currency); // تبدیل ارز
                }

                $response = wp_remote_post('https://api.zarinpal.com/pg/v4/payment/request.json', array(
                    'body' => json_encode(array(
                        'merchant_id' => $this->zarinpal_merchant_id,
                        'amount' => $amount,
                        'currency' => $currency === 'IRR' ? 'IRR' : 'Toman',
                        'callback_url' => add_query_arg('wc_donation_order', $order_id, home_url('/wc-donation-callback')),
                        'description' => sprintf(__('پرداخت حمایتی برای سفارش #%s', 'wc-donation-gateway'), $order_id),
                    )),
                    'headers' => array('Content-Type' => 'application/json'),
                ));

                if (is_wp_error($response)) {
                    wc_add_notice(__('خطا در ارتباط با زرین‌پال.', 'wc-donation-gateway'), 'error');
                    return;
                }

                $body = json_decode(wp_remote_retrieve_body($response), true);
                if ($body['data']['code'] === 100) {
                    $redirect_url = 'https://www.zarinpal.com/pg/StartPay/' . $body['data']['authority'];
                    $order->update_meta_data('_zarinpal_authority', $body['data']['authority']);
                } else {
                    wc_add_notice(__('خطا در ایجاد درخواست پرداخت.', 'wc-donation-gateway'), 'error');
                    return;
                }
            } else {
                $redirect_url = !empty($_POST['donation_redirect_option']) ? esc_url($_POST['donation_redirect_option']) : $this->get_option('redirect_url');
                $redirect_url = add_query_arg('tracking', $tracking_code, $redirect_url);
            }

            $order->add_order_note(sprintf(__('کاربر به صفحه پرداخت هدایت شد: %s (کد رهگیری: %s)', 'wc-donation-gateway'), $redirect_url, $tracking_code));
            $order->save();

            WC()->session->set('donation_tracking_code', $tracking_code);
            WC()->session->set('donation_order_id', $order_id);

            return array(
                'result' => 'success',
                'redirect' => $redirect_url
            );
        }

        // تبدیل ارز (مثال ساده)
        private function convert_currency($amount, $currency) {
            $rates = array(
                'IRR' => 1,
                'USD' => 0.000024, // 1 IRR = 0.000024 USD
                'EUR' => 0.000022, // 1 IRR = 0.000022 EUR
            );
            return $amount * ($rates[$currency] ?? 1);
        }

        // بررسی وضعیت پرداخت
        public function check_donation_status() {
            $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
            $tracking_code = isset($_POST['tracking_code']) ? sanitize_text_field($_POST['tracking_code']) : '';

            if (!$order_id || !$tracking_code) {
                wp_send_json_error(array('message' => __('اطلاعات نامعتبر است.', 'wc-donation-gateway')));
            }

            $order = wc_get_order($order_id);
            if (!$order || $order->get_meta('_donation_tracking_code') !== $tracking_code) {
                wp_send_json_error(array('message' => __('سفارش یافت نشد یا کد رهگیری اشتباه است.', 'wc-donation-gateway')));
            }

            $status = $order->get_status();
            wp_send_json_success(array(
                'status' => $status,
                'message' => $status === 'completed' ? __('پرداخت با موفقیت انجام شد.', 'wc-donation-gateway') : __('در انتظار تأیید پرداخت.', 'wc-donation-gateway')
            ));
        }

        // نمایش نوار پیشرفت کمپین
        public function display_campaign_progress() {
            if ($this->campaign_goal <= 0) return;

            global $wpdb;
            $total_donations = $wpdb->get_var(
                "SELECT SUM(pm.meta_value)
                FROM {$wpdb->postmeta} pm
                JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                WHERE pm.meta_key = '_order_total'
                AND p.post_status = 'wc-completed'
                AND p.ID IN (
                    SELECT post_id FROM {$wpdb->postmeta}
                    WHERE meta_key = '_payment_method'
                    AND meta_value = 'custom_donation'
                )"
            );

            $progress = ($total_donations / $this->campaign_goal) * 100;
            $progress = min($progress, 100);

            echo '<div class="wc-donation-progress">';
            echo '<h3>' . esc_html__('پیشرفت کمپین', 'wc-donation-gateway') . '</h3>';
            echo '<div class="progress-bar" style="width: 100%; background: #eee; height: 20px; border-radius: 5px;">';
            echo '<div style="width: ' . esc_attr($progress) . '%; background: #28a745; height: 100%; border-radius: 5px;"></div>';
            echo '</div>';
            echo '<p>' . sprintf(__('جمع‌آوری‌شده: %s از %s تومان', judgement
                    'wc-donation-gateway'), number_format($total_donations), number_format($this->campaign_goal)) . '</p>';
            echo '</div>';
        }

        // بارگذاری اسکریپت‌ها
        public function enqueue_scripts() {
            if (is_checkout()) {
                wp_enqueue_script('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', array(), '11.0', true);
                wp_enqueue_script('wc-donation-ajax', plugin_dir_url(__FILE__) . 'donation-ajax.js', array('jquery', 'sweetalert2'), '2.0', true);
                wp_localize_script('wc-donation-ajax', 'wc_donation_params', array(
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'order_id' => WC()->session->get('donation_order_id', 0),
                    'tracking_code' => WC()->session->get('donation_tracking_code', ''),
                ));
            }
        }
    }
}

// هندل کردن callback زرین‌پال
add_action('init', 'wc_donation_handle_zarinpal_callback');
function wc_donation_handle_zarinpal_callback() {
    if (!isset($_GET['wc_donation_order']) || !isset($_GET['Authority'])) return;

    $order_id = intval($_GET['wc_donation_order']);
    $order = wc_get_order($order_id);
    if (!$order || $order->get_payment_method() !== 'custom_donation') return;

    $gateway = new WC_Custom_Donation_Gateway();
    $authority = sanitize_text_field($_GET['Authority']);
    $response = wp_remote_post('https://api.zarinpal.com/pg/v4/payment/verify.json', array(
        'body' => json_encode(array(
            'merchant_id' => $gateway->zarinpal_merchant_id,
            'authority' => $authority,
            'amount' => $order->get_total(),
        )),
        'headers' => array('Content-Type' => 'application/json'),
    ));

    if (is_wp_error($response)) {
        $order->update_status('failed', __('خطا در تأیید پرداخت زرین‌پال.', 'wc-donation-gateway'));
        wp_redirect($order->get_checkout_payment_url());
        exit;
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    if ($body['data']['code'] === 100) {
        $order->update_status('completed', __('پرداخت زرین‌پال تأیید شد.', 'wc-donation-gateway'));
        wp_redirect($order->get_checkout_order_received_url());
    } else {
        $order->update_status('failed', __('پرداخت زرین‌پال ناموفق بود.', 'wc-donation-gateway'));
        wp_redirect($order->get_checkout_payment_url());
    }
    exit;
}

// REST API برای به‌روزرسانی وضعیت
add_action('rest_api_init', 'register_donation_status_endpoint');
function register_donation_status_endpoint() {
    register_rest_route('wc-donation/v1', '/status', array(
        'methods' => 'POST',
        'callback' => 'update_donation_status',
        'permission_callback' => 'validate_api_key',
    ));
}

function validate_api_key($request) {
    $gateway = new WC_Custom_Donation_Gateway();
    $api_key = $request->get_header('X-API-Key');
    return $api_key === $gateway->api_key;
}

function update_donation_status($request) {
    $tracking_code = sanitize_text_field($request->get_param('tracking_code'));
    $status = sanitize_text_field($request->get_param('status'));

    $orders = wc_get_orders(array(
        'meta_key' => '_donation_tracking_code',
        'meta_value' => $tracking_code,
        'limit' => 1,
    ));

    if (empty($orders)) {
        return new WP_Error('invalid_tracking', __('کد رهگیری نامعتبر است.', 'wc-donation-gateway'), array('status' => 404));
    }

    $order = $orders[0];
    if ($status === 'success') {
        $order->update_status('completed', __('پرداخت حمایتی تأیید شد.', 'wc-donation-gateway'));
    } else {
        $order->update_status('failed', __('پرداخت حمایتی ناموفق بود.', 'wc-donation-gateway'));
    }

    return rest_ensure_response(array(
        'success' => true,
        'message' => __('وضعیت سفارش به‌روزرسانی شد.', 'wc-donation-gateway'),
    ));
}

// استایل‌ها
add_action('wp_enqueue_scripts', 'wc_donation_gateway_styles');
function wc_donation_gateway_styles() {
    if (is_checkout()) {
        wp_add_inline_style('woocommerce-general', '
            .wc-donation-select, .wc-donation-amount, .wc-donation-currency {
                width: 100%; padding: 8px; margin: 5px 0; border: 1px solid #ccc; border-radius: 4px;
            }
            .wc-donation-amount { max-width: 200px; }
            #check-donation-status { padding: 10px; background: #0073aa; color: #fff; border: none; border-radius: 4px; cursor: pointer; }
            #check-donation-status:hover { background: #005177; }
            .wc-donation-progress { margin-bottom: 20px; }
        ');
    }
}
?>

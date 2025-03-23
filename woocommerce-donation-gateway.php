<?php
/**
 * Plugin Name: WooCommerce Donation Payment Gateway
 * Plugin URI: https://saadline.ir
 * Description: یک درگاه پرداخت سفارشی برای هدایت کاربران به صفحه پرداخت حمایتی با قابلیت AJAX.
 * Version: 2.1
 * Author: Sedali
 * Author URI: https://saadline.ir
 * Text Domain: wc-donation-gateway
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

// بررسی فعال بودن ووکامرس
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
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
            $this->method_description = __('پس از تکمیل سفارش، به صفحه پرداخت حمایتی منتقل خواهید شد.', 'wc-donation-gateway');
            $this->has_fields = true;

            $this->init_form_fields();
            $this->init_settings();

            $this->enabled = $this->get_option('enabled');
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->redirect_url = $this->get_option('redirect_url');
            $this->redirect_options = $this->get_option('redirect_options');
            $this->icon = $this->get_option('logo');

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
            add_action('wp_ajax_check_donation_status', array($this, 'check_donation_status'));
            add_action('wp_ajax_nopriv_check_donation_status', array($this, 'check_donation_status'));
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
                    'description' => __('لینک پیش‌فرض در صورتی که گزینه‌ای انتخاب نشود.', 'wc-donation-gateway'),
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
            );
        }

        // نمایش فیلدهای پرداخت در صفحه تسویه‌حساب
        public function payment_fields() {
            if ($this->description) {
                echo wpautop(wp_kses_post($this->description));
            }

            $options = explode("\n", $this->redirect_options);
            if (count($options) > 1) {
                echo '<label>' . esc_html__('لطفاً گزینه پرداخت را انتخاب کنید:', 'wc-donation-gateway') . '</label><br>';
                echo '<select name="donation_redirect_option" class="wc-donation-select">';
                foreach ($options as $option) {
                    $option = trim($option);
                    if (empty($option)) continue;
                    list($name, $url) = explode('|', $option, 2);
                    echo '<option value="' . esc_url($url) . '">' . esc_html($name) . '</option>';
                }
                echo '</select>';
            }
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

        // پردازش پرداخت و هدایت کاربر
        public function process_payment($order_id) {
            $order = wc_get_order($order_id);
            $redirect_url = !empty($_POST['donation_redirect_option']) ? esc_url($_POST['donation_redirect_option']) : $this->get_option('redirect_url');

            // تولید کد رهگیری
            $tracking_code = wp_generate_uuid4(); // کد منحصربه‌فرد
            $redirect_url = add_query_arg('tracking', $tracking_code, $redirect_url);

            // ثبت کد رهگیری در سفارش
            $order->update_meta_data('_donation_tracking_code', $tracking_code);
            $order->update_status('pending', __('در انتظار تأیید پرداخت حمایتی', 'wc-donation-gateway'));
            $order->add_order_note(sprintf(__('کاربر به صفحه پرداخت حمایتی هدایت شد: %s (کد رهگیری: %s)', 'wc-donation-gateway'), $redirect_url, $tracking_code));
            $order->save();

            return array(
                'result' => 'success',
                'redirect' => $redirect_url
            );
        }

        // بارگذاری اسکریپت‌های AJAX
        public function enqueue_scripts() {
            if (is_checkout() && $this->id === WC()->session->get('chosen_payment_method')) {
                wp_enqueue_script('wc-donation-ajax', plugin_dir_url(__FILE__) . 'donation-ajax.js', array('jquery'), '1.0', true);
                wp_localize_script('wc-donation-ajax', 'wc_donation_params', array(
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'order_id' => WC()->checkout()->get_value('order_id'),
                    'tracking_code' => WC()->session->get('donation_tracking_code'),
                ));
            }
        }

        // تابع AJAX برای بررسی وضعیت
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
    }
}

// REST API برای دریافت وضعیت از صفحه مقصد (اختیاری برای درگاه خارجی)
add_action('rest_api_init', 'register_donation_status_endpoint');
function register_donation_status_endpoint() {
    register_rest_route('wc-donation/v1', '/status', array(
        'methods' => 'POST',
        'callback' => 'update_donation_status',
        'permission_callback' => '__return_true', // در عمل باید کلید API چک بشه
    ));
}

function update_donation_status($request) {
    $tracking_code = sanitize_text_field($request->get_param('tracking_code'));
    $status = sanitize_text_field($request->get_param('status')); // 'success' یا 'failed'

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

// استایل ساده برای انتخاب‌گر
add_action('wp_enqueue_scripts', 'wc_donation_gateway_styles');
function wc_donation_gateway_styles() {
    if (is_checkout()) {
        wp_add_inline_style('woocommerce-general', '.wc-donation-select { width: 100%; padding: 8px; margin-top: 5px; }');
    }
}

<?php
/**
 * Plugin Name: WooCommerce Donation Payment Gateway
 * Plugin URI: https://example.com
 * Description: یک درگاه پرداخت سفارشی برای هدایت کاربران به صفحه پرداخت حمایتی.
 * Version: 1.0
 * Author: Sedali
 * Author URI: https://saadline.ir
 */

if (!defined('ABSPATH')) {
    exit;
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
            $this->method_title = __('پرداخت حمایتی', 'woocommerce');
            $this->method_description = __('پس از تکمیل سفارش، به صفحه پرداخت حمایتی منتقل خواهید شد.', 'woocommerce');
            $this->title = __('پرداخت حمایتی', 'woocommerce');
            $this->has_fields = false;

            $this->init_form_fields();
            $this->init_settings();

            $this->enabled = $this->get_option('enabled');
            $this->title = $this->get_option('title');
            $this->redirect_url = $this->get_option('redirect_url');

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        }

        // تنظیمات درگاه پرداخت در پنل ادمین
        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('فعال‌سازی', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('فعال کردن درگاه پرداخت حمایتی', 'woocommerce'),
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => __('عنوان درگاه', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('عنوانی که در صفحه تسویه‌حساب نمایش داده می‌شود.', 'woocommerce'),
                    'default' => __('پرداخت حمایتی', 'woocommerce'),
                    'desc_tip' => true,
                ),
                'redirect_url' => array(
                    'title' => __('لینک صفحه پرداخت', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('لینک صفحه‌ای که کاربران پس از کلیک روی دکمه پرداخت به آن هدایت می‌شوند.', 'woocommerce'),
                    'default' => 'https://example.com/donation-page',
                    'desc_tip' => true,
                ),
            );
        }

        // وقتی دکمه پرداخت کلیک می‌شود، کاربر را به لینک حمایتی هدایت کن
        public function process_payment($order_id) {
            $order = wc_get_order($order_id);
            $redirect_url = $this->get_option('redirect_url'); 

            return array(
                'result' => 'success',
                'redirect' => $redirect_url
            );
        }
    }
}

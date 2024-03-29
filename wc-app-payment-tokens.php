<?php
/**
 * Plugin Name:     WooCommerce Stripe Payment TOkens
 * Description:     Add Api Endpoint to create Payment tokens.
 * Version:         0.1
 * Author:          OUSS
 *
 * @author          OUSS
 * @copyright       Copyright (c) 2019
 *
 */


// Exit if accessed directly
if (!defined('ABSPATH')) exit;

if (!class_exists('WC_APP_Payment_Tokens')) {

    class WC_APP_Payment_Tokens
    {

        private static $instance;

        public static function instance()
        {
            if (self::$instance === null) {
                self::$instance = new self();
                self::$instance->includes();
            }

            return self::$instance;
        }

        /**
         * Include necessary files
         *
         * @access      private
         * @return      void
         * @since       0.1.0
         */
        private function includes()
        {

            require_once plugin_dir_path(__FILE__) . 'inc/class-wc-api-payments-tokens.php';

        }

    }
} // End if class_exists check


/**
 * The main function responsible for returning the instance
 *
 * @return      WS_APP_Payment_Tokens::instance()
 *
 * @since       0.1.0
 */
function wc_app_payment_tokens_load()
{
    return WC_APP_Payment_Tokens::instance();
}

add_action('plugins_loaded', 'wc_app_payment_tokens_load');


/**
 *
 * Fix Subscription Renewal :
 *  => In the `_stripe_source_id` meta data AND in the table `woocommerce_payment_tokens` we store the payment method ID ('pm_xxx...'), not the Source ID like Woocommerce do : 'src_xxx...'
 *  => In the case of a payment intent we have to change the object sended to stripe and add the correct attribute
 *  => To do this :
 *      1/ we use the filter `wc_stripe_generate_payment_request` to add an attribute payment_method
 *      2/ With the filter `woocommerce_stripe_request_body` before sending the request if we have a `payment_method` we remove `source` attribute
 */

add_filter('wc_stripe_generate_payment_request', 'filter_wc_stripe_generate_payment_request_payment_method', 3, 10);
function filter_wc_stripe_generate_payment_request_payment_method($post_data, $order, $source)
{
    if ($source->source && substr($source->source, 0, 2) === "pm") {
        $post_data['payment_method'] = $source->source;
    }

    return $post_data;
}

add_filter('woocommerce_stripe_request_body', 'filter_woocommerce_stripe_request_body_payment_method', 3, 10);
function filter_woocommerce_stripe_request_body_payment_method($request, $api)
{
    /*if ($api === "payment_intents" && $request['payment_method'] && substr($request['payment_method'], 0, 2) === "pm") {
        unset($request['source']);
    }*/

    if ($api === "payment_intents" && $request['source'] && substr($request['source'], 0, 2) === "pm") {
        $request['payment_method'] = $request['source'];
        unset($request['source']);
    }

    return $request;
}

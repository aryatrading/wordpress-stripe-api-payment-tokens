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

function remove_woocommerce_subscription_validate_payment_meta_filter()
{
    global $stripe_subs_compat;
    global $stripe_sepa_subs_compat;
    remove_filter('woocommerce_subscription_validate_payment_meta', array($stripe_subs_compat, 'validate_subscription_payment_meta'), 100, 2);
    remove_filter('woocommerce_subscription_validate_payment_meta', array($stripe_sepa_subs_compat, 'validate_subscription_payment_meta'), 100, 2);
}

add_action('after_setup_theme', 'remove_woocommerce_subscription_validate_payment_meta_filter');

add_filter('woocommerce_subscription_validate_payment_meta', 'custom_woocommerce_subscription_validate_payment_meta', 999, 2);
function custom_woocommerce_subscription_validate_payment_meta($payment_method_id, $payment_meta)
{
    if ($this->id === $payment_method_id) {

        if (!isset($payment_meta['post_meta']['_stripe_customer_id']['value']) || empty($payment_meta['post_meta']['_stripe_customer_id']['value'])) {
            throw new Exception(__('A "Stripe Customer ID" value is required.', 'woocommerce-gateway-stripe'));
        } elseif (0 !== strpos($payment_meta['post_meta']['_stripe_customer_id']['value'], 'cus_')) {
            throw new Exception(__('Invalid customer ID. A valid "Stripe Customer ID" must begin with "cus_".', 'woocommerce-gateway-stripe'));
        }

        if (
            (!empty($payment_meta['post_meta']['_stripe_source_id']['value'])
                && 0 !== strpos($payment_meta['post_meta']['_stripe_source_id']['value'], 'card_'))
            && (!empty($payment_meta['post_meta']['_stripe_source_id']['value'])
                && 0 !== strpos($payment_meta['post_meta']['_stripe_source_id']['value'], 'src_'))
            && (!empty($payment_meta['post_meta']['_stripe_source_id']['value'])
                && 0 !== strpos($payment_meta['post_meta']['_stripe_source_id']['value'], 'pm_'))) {

            throw new Exception(__('Invalid source ID. A valid source "Stripe Source ID" must begin with "src_" or "card_" or "pm_".', 'woocommerce-gateway-stripe'));
        }
    }
}
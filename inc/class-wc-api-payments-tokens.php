<?php
// Exit if accessed directly
if (!defined('ABSPATH')) exit;

if (!class_exists('WC_API_Payment_Tokens')) {

    class WC_API_Payment_Tokens
    {

        private static $instance;

        public static function instance()
        {
            if (self::$instance === null) {
                self::$instance = new self();
                self::$instance->hooks();
            }

            return self::$instance;
        }

        public function hooks()
        {

            add_action('rest_api_init', array($this, 'wc_rest_payment_endpoints'));
        }

        public function wc_rest_payment_endpoints()
        {

            /**
             * Handle Payment Method request.
             */
            register_rest_route('wc/v3', 'stripe-payment-tokens', array(
                'methods' => 'POST',
                'callback' => array($this, 'wc_rest_payment_endpoint_handler'),
            ));

        }

        public function wc_rest_payment_endpoint_handler($request = null)
        {
            $response = array();
            $parameters = $request->get_params();

            $token_id = sanitize_text_field($parameters['token']);
            $user_id = sanitize_text_field($parameters['user_id']);
            $gateway_id = sanitize_text_field($parameters['gateway_id']);
            $last4 = sanitize_text_field($parameters['last4']);
            $expiry_year = sanitize_text_field($parameters['expiry_year']);
            $expiry_month = sanitize_text_field($parameters['expiry_month']);
            $card_type = sanitize_text_field($parameters['card_type']);

            $error = new WP_Error();

            if (empty($token_id)) {
                $error->add(400, __("token is required.", 'wc-rest-payment-tokens'), array('status' => 400));
                return $error;
            }
            if (empty($user_id)) {
                $error->add(401, __("User ID 'user_id' is required.", 'wc-rest-payment-tokens'), array('status' => 400));
                return $error;
            }

            if (empty($gateway_id)) {
                $error->add(402, __("Gateway 'gateway_id' is required.", 'wc-rest-payment-tokens'), array('status' => 400));
                return $error;
            }

            // Build the token
            $token = new WC_Payment_Token_CC();
            $token->set_token($token_id); // Token comes from payment processor
            $token->set_gateway_id($gateway_id);
            $token->set_last4($last4);
            $token->set_expiry_year($expiry_year);
            $token->set_expiry_month($expiry_month);
            $token->set_card_type($card_type);
            $token->set_user_id($user_id);
            // Save the new token to the database
            $token->save();
            // Set this token as the users new default token
            WC_Payment_Tokens::set_users_default($user_id, $token->get_id());

            $response['code'] = 200;
            $response['message'] = __("Your Payment tokens was added Successful", "wc-rest-payment-tokens");

            return new WP_REST_Response($response, 123);
        }
    }

    $WC_PaymentTokens = new WC_API_Payment_Tokens();
    $WC_PaymentTokens->instance();

} // End if class_exists check
<?php
/**
 * Plugin Name: WooCommerce Paysius
 * Plugin URI: http://claudiosmweb.com/
 * Description: WooCommerce Paysius is a bitcoin payment gateway for WooCommerce
 * Author: claudiosanches
 * Author URI: http:/claudiosmweb.com/
 * Version: 1.2
 * License: GPLv2 or later
 * Text Domain: wcpaysius
 * Domain Path: /languages/
 */

/**
 * WooCommerce fallback notice.
 */
function wcpaysius_woocommerce_fallback_notice() {
    $html = '<div class="error">';
        $html .= '<p>' . __( 'WooCommerce Paysius Gateway depends on the last version of <a href="http://wordpress.org/extend/plugins/woocommerce/">WooCommerce</a> to work!', 'wcpaysius' ) . '</p>';
    $html .= '</div>';

    echo $html;
}

/**
 * Load functions.
 */
add_action( 'plugins_loaded', 'wcpaysius_gateway_load', 0 );

function wcpaysius_gateway_load() {

    if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
        add_action( 'admin_notices', 'wcpaysius_woocommerce_fallback_notice' );

        return;
    }

    /**
     * Load textdomain.
     */
    load_plugin_textdomain( 'wcpaysius', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

    /**
     * Add the gateway to WooCommerce.
     *
     * @access public
     * @param array $methods
     * @return array
     */
    add_filter( 'woocommerce_payment_gateways', 'wcpaysius_add_gateway' );

    function wcpaysius_add_gateway( $methods ) {
        $methods[] = 'WC_Paysius_Gateway';
        return $methods;
    }

    /**
     * WC Paysius Gateway Class.
     *
     * Built the Paysius method.
     */
    class WC_Paysius_Gateway extends WC_Payment_Gateway {

        /**
         * Gateway's Constructor.
         *
         * @return void
         */
        public function __construct() {
            global $woocommerce;

            $this->id                  = 'paysius';
            $this->icon                = plugins_url( 'images/bitcoin.png', __FILE__ );
            $this->has_fields          = false;
            $this->setdetails_url      = 'https://paysius.com:53135/sci/setdetails';
            $this->getorderaddress_url = 'https://paysius.com:53135/sci/getorderaddress';
            $this->getdetails_url      = 'https://paysius.com:53135/sci/getdetails';
            $this->method_title        = __( 'Paysius', 'wcpaysius' );

            // Load the form fields.
            $this->init_form_fields();

            // Load the settings.
            $this->init_settings();

            // Define user setting variables.
            $this->title          = $this->settings['title'];
            $this->description    = $this->settings['description'];
            $this->app_key        = $this->settings['app_key'];
            $this->app_secret     = $this->settings['app_secret'];
            $this->debug          = $this->settings['debug'];

            // Actions.
            add_action( 'woocommerce_api_wc_paysius_gateway', array( &$this, 'check_ipn_response' ) );
            add_action( 'valid_paysius_ipn_request', array( &$this, 'successful_request' ) );
            add_action( 'woocommerce_receipt_paysius', array( &$this, 'receipt_page' ) );
            if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
                add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
            } else {
                add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
            }

            // Valid for use.
            $this->enabled = ( 'yes' == $this->settings['enabled'] ) && ! empty( $this->app_key ) && ! empty( $this->app_secret ) && $this->is_valid_for_use();

            // Checking if app_key is not empty.
            if ( empty( $this->app_key ) ) {
                add_action( 'admin_notices', array( &$this, 'app_key_missing_message' ) );
            }

            // Checking if app_secret is not empty.
            if ( empty( $this->app_secret ) ) {
                add_action( 'admin_notices', array( &$this, 'app_secret_missing_message' ) );
            }

            // Active logs.
            if ( 'yes' == $this->debug ) {
                $this->log = $woocommerce->logger();
            }
        }

        /**
         * Checking if this gateway is enabled and available in the user's country.
         *
         * @return bool
         */
        public function is_valid_for_use() {
            if ( ! in_array( get_woocommerce_currency(), array( 'BTC', 'USD' ) ) ) {
                return false;
            }

            return true;
        }

        /**
         * Admin Panel Options
         * - Options for bits like 'title' and availability on a country-by-country basis.
         *
         * @since 1.0.0
         */
        public function admin_options() {

            ?>
            <h3><?php _e( 'Paysius standard', 'wcpaysius' ); ?></h3>
            <p><?php _e( 'Paysius standard works by sending the user to Paysius to enter their payment information.', 'wcpaysius' ); ?></p>
            <table class="form-table">
                <?php $this->generate_settings_html(); ?>
            </table> <!-- /.form-table -->
            <?php
        }

        /**
         * Start Gateway Settings Form Fields.
         *
         * @return void
         */
        public function init_form_fields() {

            $this->form_fields = array(
                'enabled' => array(
                    'title' => __( 'Enable/Disable', 'wcpaysius' ),
                    'type' => 'checkbox',
                    'label' => __( 'Enable Paysius standard', 'wcpaysius' ),
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => __( 'Title', 'wcpaysius' ),
                    'type' => 'text',
                    'description' => __( 'This controls the title which the user sees during checkout.', 'wcpaysius' ),
                    'default' => __( 'Paysius', 'wcpaysius' )
                ),
                'description' => array(
                    'title' => __( 'Description', 'wcpaysius' ),
                    'type' => 'textarea',
                    'description' => __( 'This controls the description which the user sees during checkout.', 'wcpaysius' ),
                    'default' => __( 'Pay with BitCoin', 'wcpaysius' )
                ),
                'app_key' => array(
                    'title' => __( 'Application Key', 'wcpaysius' ),
                    'type' => 'text',
                    'description' => __( 'Please enter your Paysius Application Key.', 'wcpaysius' ) . ' ' . sprintf( __( 'You can to get this information in: %sPaysius Account%s.', 'wcpaysius' ), '<a href="http://paysius.com/user/me/edit/application" target="_blank">', '</a>' ),
                    'default' => ''
                ),
                'app_secret' => array(
                    'title' => __( 'Application Secret', 'wcpaysius' ),
                    'type' => 'text',
                    'description' => __( 'Please enter your Paysius Application Secret.', 'wcpaysius' ) . ' ' . sprintf( __( 'You can to get this information in: %sPaysius Account%s.', 'wcpaysius' ), '<a href="http://paysius.com/user/me/edit/application" target="_blank">', '</a>' ),
                    'default' => ''
                ),
                'testing' => array(
                    'title' => __( 'Gateway Testing', 'wcpaysius' ),
                    'type' => 'title',
                    'description' => '',
                ),
                'debug' => array(
                    'title' => __( 'Debug Log', 'wcpaysius' ),
                    'type' => 'checkbox',
                    'label' => __( 'Enable logging', 'wcpaysius' ),
                    'default' => 'no',
                    'description' => __( 'Log Paysius events, such as API requests, inside <code>woocommerce/logs/paysius.txt</code>', 'wcpaysius'  ),
                )
            );
        }

        /**
         * Generate the args to form.
         *
         * @param  array $order Order data.
         * @return array
         */
        public function get_form_args( $order ) {

            $args = array(
                'total'     => (float) $order->order_total,
                'curcode'   => get_woocommerce_currency(),
                'returnURL' => esc_url( $this->get_return_url( $order ) ),
                'cancelURL' => esc_url( $order->get_cancel_order_url() )
            );

            $args = apply_filters( 'woocommerce_paysius_args', $args );

            return $args;
        }

        /**
         * Generate the form.
         *
         * @param mixed $order_id
         * @return string
         */
        public function generate_form( $order_id ) {

            $order = new WC_Order( $order_id );

            $args = $this->get_form_args( $order );

            if ( 'yes' == $this->debug ) {
                $this->log->add( 'paysius', 'Payment arguments for order #' . $order_id . ': ' . print_r( $args, true ) );
            }

            $details = $this->set_details( $args['total'], $args['curcode'] );

            if ( isset( $details->uuid ) ) {

                $btc_order = $this->get_order_address( $details->uuid );

                if ( $btc_order ) {
                    $html = '<p>' . sprintf( __( 'To finish your order, please send <strong>%1$s BTC</strong> to the following Bitcoin address: <strong><a href="bitcoin:%2$s?amount=%1$s&amp;label=%3$s%4$s">%2$s</a></strong>. Once the payment is received, your order will be complete.', 'wcpaysius' ), $btc_order->btc, $btc_order->address, __( 'Order%20ID:%20', 'wcpaysius' ), $order->id ) . '</p>';

                        $html .= '<a id="submit-payment" href="' . $args['returnURL'] . '" class="button alt">' . __( 'Payment done, close the order', 'wcpaysius' ) . '</a> <a class="button cancel" href="' . esc_url( $order->get_cancel_order_url() ) . '">' . __( 'Cancel order &amp; restore cart', 'wcpaysius' ) . '</a>';

                        if ( 'yes' == $this->debug ) {
                            $this->log->add( 'paysius', 'Payment link generated with success from Paysius' );
                        }

                        update_post_meta( $order->id, 'paysius_id', esc_attr( $details->uuid ) );

                    return $html;
                } else {
                    if ( 'yes' == $this->debug ) {
                        $this->log->add( 'paysius', 'Get order address error.' );
                    }

                    return $this->btc_order_error( $order );
                }

            } else {
                if ( 'yes' == $this->debug ) {
                    $this->log->add( 'paysius', 'Set details error.' );
                }

                return $this->btc_order_error( $order );
            }

        }

        /**
         * Order error button.
         *
         * @param  object $order Order data.
         *
         * @return string        Error message and cancel button.
         */
        protected function btc_order_error( $order ) {

            // Display message if there is problem.
            $html = '<p>' . __( 'An error has occurred while processing your payment, please try again. Or contact us for assistance.', 'wcpaysius' ) . '</p>';

            $html .='<a class="button cancel" href="' . esc_url( $order->get_cancel_order_url() ) . '">' . __( 'Click to try again', 'wcpaysius' ) . '</a>';

            return $html;
        }

        /**
         * Process the payment and return the result.
         *
         * @param int $order_id
         * @return array
         */
        public function process_payment( $order_id ) {

            $order = new WC_Order( $order_id );

            return array(
                'result'    => 'success',
                'redirect'  => add_query_arg( 'order', $order->id, add_query_arg( 'key', $order->order_key, get_permalink( woocommerce_get_page_id( 'pay' ) ) ) )
            );
        }

        /**
         * Output for the order received page.
         *
         * @return void
         */
        public function receipt_page( $order ) {
            echo $this->generate_form( $order );
        }

        /**
         * Create HMAC.
         *
         * @param  objetct $params Object with gateway items.
         * @param  string $secret  Application Secret.
         *
         * @return string          Hash HMAC.
         */
        protected function serial_HMAC_encode( $params, $secret ) {

            // Check for bad values.
            if ( empty( $params ) || empty( $secret ) || ! is_object( $params ) ) {
                if ( 'yes' == $this->debug ) {
                    $this->log->add( 'paysius', 'Serial HTML Encode error' );
                }

                return false;
            }

            // Encode.
            $message = json_encode( $params );

            // Generate hmac.
            $hmac = hash_hmac( 'sha512', $message, $secret );

            return $hmac;
        }

        /**
         * Parse response.
         *
         * @param  string $raw    Json data.
         * @param  string $secret Application Secret.
         *
         * @return object         Response.
         */
        protected function parse_response( $raw, $secret ) {

            $response = json_decode( $raw );

            // Verify response is a JSON encoded object.
            if( ! is_object( $response ) || empty( $secret ) ) {
                if ( 'yes' == $this->debug ) {
                    $this->log->add( 'paysius', 'Parse response error' );
                }

                return false;
            }

            // Check for errors.
            if ( property_exists( $response, 'ERRORCODE' ) ) {
                return $response;
            }

            // Check authenticity of HMAC.
            $hmac = $this->serial_HMAC_encode( $response->response, $secret );
            if ( $hmac != $response->hmac ) {
                if ( 'yes' == $this->debug ) {
                    $this->log->add( 'paysius', 'HMAC incorrect' );
                }

                return false;
            }

            return $response->response;
        }

        /**
         * Send To Gateway
         *
         * @param  objetct $params Object with gateway items.
         * @param  string  $gate   Gateway URL.
         *
         * @return object          Response as Object.
         */
        protected function send_to_gateway( $params, $gate ) {

            $params->key = $this->app_key;

            // Add HMAC.
            $hmac = $this->serial_HMAC_encode( $params, $this->app_secret );

            if ( ! $hmac && 'yes' == $this->debug ) {
                $this->log->add( 'paysius', 'Error hashing data for post' );
            }

            $params->hmac = $hmac;

            $postdata = http_build_query( $params, '', '&' );

            // Built wp_remote_post params.
            $send_params = array(
                'body'       => $postdata,
                'sslverify'  => false,
                'timeout'    => 30,
                'port'       => '53135',
                'method'     => 'POST'
            );

            $response = wp_remote_post( $gate, $send_params );

            if ( 'yes' == $this->debug ) {
                $this->log->add( 'paysius', 'Payment wp_remote_post response: ' . print_r( $response, true ) );
            }

            // Check to see if the request was valid.
            if ( ! is_wp_error( $response ) && 200 ==$response['response']['code'] ) {

                $body = $this->parse_response( $response['body'], $this->app_secret );

                if ( ! $body ) {
                    if ( 'yes' == $this->debug ) {
                        $this->log->add( 'paysius', 'Trouble response from Paysius' );
                    }

                    return false;
                }

                if ( 'yes' == $this->debug ) {
                    $this->log->add( 'paysius', 'Received valid response from Paysius' );
                }

                return $body;
            } else {
                if ( 'yes' == $this->debug ) {
                    $this->log->add( 'paysius', 'Received invalid response from Paysius.' );
                }
            }

            return false;
        }

        /**
         * Set initial order details.
         *
         * @param float  $total     Order total.
         * @param string $curcode   ISO 4217 currency code.
         * @param string $returnURL Return url for your cart
         * @param string $cancelURL Cancel url for yout cart.
         *
         * @return Object           Order ID or error.
         */
        protected function set_details( $total, $curcode, $returnURL = '', $cancelURL = '' ) {

            // Check for bad values before sending to gateway.
            if( empty( $total ) || ! is_numeric( $total ) || empty( $curcode ) || strlen( $curcode ) != 3 ) {
                if ( 'yes' == $this->debug ) {
                    $this->log->add( 'paysius', 'Malformed Request.' );
                }

                return false;
            }

            $gate              = $this->setdetails_url;
            $params            = new stdClass();
            $params->total     = (string) $total;
            $params->curcode   = $curcode;
            $params->returnURL = $returnURL;
            $params->cancelURL = $cancelURL;

            return $this->send_to_gateway( $params, $gate );
        }

        /**
         * Get a Bitcoin payment address for the order.
         *
         * @param  string $uuid Order ID.
         *
         * @return object order details or error code.
         */
        protected function get_order_address( $uuid ) {

            // Check for bad values before sending to gateway.
            if ( empty( $uuid ) ) {
                if ( 'yes' == $this->debug ) {
                    $this->log->add( 'paysius', 'Malformed Request.' );
                }

                return false;
            }

            $gate         = $this->getorderaddress_url;
            $params       = new stdClass();
            $params->uuid = $uuid;

            return $this->send_to_gateway( $params, $gate );
        }

        /**
         * Check ipn validation.
         *
         * @param  array $data IPN request.
         *
         * @return mixed
         */
        public function check_ipn_request_is_valid( $data ) {

            if ( ! isset( $data['hmac'] ) ) {
                return false;
            }

            if ( 'yes' == $this->debug ) {
                $this->log->add( 'paysius', 'Checking IPN request...' );
            }

            // Separate HMAC from response.
            $post_hmac = $data['hmac'];
            unset( $data['hmac'] );

            // Check authenticity of HMAC.
            $hmac = $this->serial_HMAC_encode( (object) $data, $this->app_secret );
            if ( $hmac != $post_hmac ) {
                return false;
            }

            if ( 'yes' == $this->debug ) {
                $this->log->add( 'paysius', 'IPN Response: ' . print_r( (object) $data, true ) );
            }

            return $data;
        }

        /**
         * Check API Response.
         *
         * @return void
         */
        public function check_ipn_response() {

            @ob_clean();

            $data = $this->check_ipn_request_is_valid( $_POST );

            if ( $data ) {

                header( 'HTTP/1.0 200 OK' );

                do_action( 'valid_paysius_ipn_request', $data );

            } else {

               wp_die( __( 'Paysius Request Failure', 'wcpaysius' ) );

            }
        }

        /**
         * Get the details of the order specified by id.
         *
         * @param  string $uuid Order ID
         * @return object       SCI Response.
         */
        protected function get_details( $uuid ) {

            // Check for bad values before sending to gateway.
            if ( empty( $uuid ) ) {
                if ( 'yes' == $this->debug ) {
                    $this->log->add( 'paysius', 'Malformed Request' );
                }

                return false;
            }

            $gate         = $this->getdetails_url;
            $params       = new stdClass();
            $params->uuid = $uuid;

            return $this->send_to_gateway( $params, $gate );
        }

        /**
         * Successful Payment!
         *
         * @param array $posted
         * @return void
         */
        public function successful_request( $posted ) {
            global $wpdb;

            $uuid = strip_tags( stripslashes( $posted['uuid'] ) );

            $data = $this->get_details( $uuid );

            $get_id = $wpdb->get_row( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'paysius_id' AND meta_value = '$uuid'" );

            if ( 'yes' == $this->debug ) {
                $this->log->add( 'paysius', 'Query log: ' . print_r( $get_id, true ) );
            }

            if ( isset( $get_id->post_id ) ) {
                $order_id = $get_id->post_id;

                $order = new WC_Order( $order_id );

                // Checks whether the invoice number matches the order.
                // If true processes the payment.
                if ( $order->id === $order_id ) {

                    if ( 'yes' == $this->debug ) {
                        $this->log->add( 'paysius', 'Payment status from order #' . $order->id . ': ' . $data->status );
                    }

                    switch ( $data->status ) {
                        case '3':

                            // Order details.
                            if ( ! empty( $data->total ) ) {
                                update_post_meta(
                                    $order_id,
                                    __( 'Total', 'wcpaysius' ),
                                    $data->total
                                );
                            }
                            if ( ! empty( $data->btc ) ) {
                                update_post_meta(
                                    $order_id,
                                    __( 'BTC', 'wcpaysius' ),
                                    $data->btc
                                );
                            }
                            if ( ! empty( $data->curcode ) ) {
                                update_post_meta(
                                    $order_id,
                                    __( 'Curcode', 'wcpaysius' ),
                                    $data->curcode
                                );
                            }
                            if ( ! empty( $data->notes ) ) {
                                update_post_meta(
                                    $order_id,
                                    __( 'Notes', 'wcpaysius' ),
                                    $data->notes
                                );
                            }

                            // Payment completed.
                            $order->add_order_note( __( 'Payment has been verified by Paysius.', 'wcpaysius' ) );
                            $order->payment_complete();

                            break;
                        case '2':
                            $order->add_order_note( __( 'Awaiting payment from the customer.', 'wcpaysius' ) );

                            break;

                        default:
                            // No action xD.
                            break;
                    }
                }
            }
        }

        /**
         * Adds error message when not configured the app_key.
         *
         * @return string Error Mensage.
         */
        public function app_key_missing_message() {
            $html = '<div class="error">';
                $html .= '<p>' . sprintf( __( '<strong>Gateway Disabled</strong> You should inform your Application Key in Paysius. %sClick here to configure!%s', 'wcpaysius' ), '<a href="' . get_admin_url() . 'admin.php?page=woocommerce_settings&amp;tab=payment_gateways">', '</a>' ) . '</p>';
            $html .= '</div>';

            echo $html;
        }

        /**
         * Adds error message when not configured the app_secret.
         *
         * @return String Error Mensage.
         */
        public function app_secret_missing_message() {
            $html = '<div class="error">';
                $html .= '<p>' . sprintf( __( '<strong>Gateway Disabled</strong> You should inform your Application Secret in Paysius. %sClick here to configure!%s', 'wcpaysius' ), '<a href="' . get_admin_url() . 'admin.php?page=woocommerce_settings&amp;tab=payment_gateways">', '</a>' ) . '</p>';
            $html .= '</div>';

            echo $html;
        }

    } // class WC_Paysius_Gateway.
} // function wcpaysius_gateway_load.

/**
 * Adds support to legacy IPN.
 *
 * @return void
 */
function wcpaysius_legacy_ipn() {
    if ( isset( $_POST['uuid'] ) && ! isset( $_GET['wc-api'] ) ) {
        global $woocommerce;

        $woocommerce->payment_gateways();

        do_action( 'woocommerce_api_wc_paysius_gateway' );
    }
}

add_action( 'init', 'wcpaysius_legacy_ipn' );

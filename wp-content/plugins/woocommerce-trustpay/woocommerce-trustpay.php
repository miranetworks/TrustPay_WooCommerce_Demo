<?php
/*
Plugin Name: WooCommerce TrustPay
Plugin URI: https://github.com/trustpay
Description: Pluging to offer a Trustpay payment option for woocommerce shopping cart
Author: TrustPay
Version: 1.0
Author URI: http://www.trsutpay.biz
License: GPLv2 or later
Text Domain: wctrustpay
Domain Path: /languages/ 
*/

/*
 *
 * https://my.trustpay.biz/TrustPayWebClient/Transact?vendor_id=ap.a097b5e4-f985-4054-a2c8-75db128b7a6a&appuser=Test+Test&currency=ZAR
 * &amount=3.00&txid=2336
 * &fail=http%3A%2F%2Flocalhost%2Fwoocommerce%2F%3Fpage_id%3D7%26order-pay%3D2336%26pay_for_order%3Dtrue%26key%3Dwc_order_537350ed10f77
 * &success=http%3A%2F%2Flocalhost%2Fwoocommerce%2F%3Fpage_id%3D7%26order-received%3D2336%26key%3Dwc_order_537350ed10f77
 * &cancel=http%3A%2F%2Flocalhost%2Fwoocommerce%2F%3Fpage_id%3D6%26cancel_order%3Dtrue%26order%3Dwc_order_537350ed10f77%26order_id%3D2336%26redirect%26_wpnonce%3D288f739079
 * &message=New+order+from+Demo+Online+Store-WooCommerce&istest=yes
 */


/**
* Check if WooCommerce is active
**/
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    
    // Put your plugin code here.
    add_action( 'plugins_loaded', 'wctrustpay_gateway_load', 0 );
    
    /**
    * WooCommerce fallback notice.
    */
   function wctrustpay_woocommerce_fallback_notice() {
       $html = '<div class="error">';
           $html .= '<p>' . __( 'WooCommerce TrustPay Gateway depends on the last version of <a href="http://wordpress.org/extend/plugins/woocommerce/">WooCommerce</a> to work!', 'wctrustpay' ) . '</p>';
       $html .= '</div>';
       echo $html;
   }

    function wctrustpay_gateway_load() {

	  if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
	      add_action( 'admin_notices', 'wctrustpay_woocommerce_fallback_notice' );
	      return;
	  }

	  /**
	  * Load textdomain.
	  */
	  load_plugin_textdomain( 'wctrustpay', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

	  /**
	  * Add the gateway to WooCommerce.
	  *
	  * @access public
	  * @param array $methods
	  * @return array
	  */
	  
	  function wctrustpay_add_gateway( $methods ) {
	      $methods[] = 'WC_Trustpay_Gateway';
	      return $methods;
	  }
          add_filter( 'woocommerce_payment_gateways', 'wctrustpay_add_gateway' );


	  /**
	  * WC Trustpay Gateway Class.
	  *
	  * Built the Trustpay method.
	  */
	  class WC_Trustpay_Gateway extends WC_Payment_Gateway {
	      /**
	      * Gateway's Constructor.
	      *
	      * @return void
	      */
	      public function __construct() {
		  global $woocommerce;
		  $this->id                  = 'trustpay';
		  $this->icon                = plugins_url( 'images/trustpay.png', __FILE__ );
		  $this->has_fields          = false;
                  $this->url                 = 'https://my.trustpay.biz/TrustPayWebClient/Transact?';
		  $this->defaultsuccessUrl   = str_replace('https:', 'http:', add_query_arg('wc-trustpay', 'trustpay_success_result', home_url('/')));
                  $this->defaultfailureUrl   = str_replace('https:', 'http:', add_query_arg('wc-trustpay', 'trustpay_failure_result', home_url('/')));
		  $this->method_title        = __( 'Trustpay', 'wctrustpay' );
                  $this->response_url        = add_query_arg( 'wc-api', 'WC_Trustpay_Gateway', home_url( '/' ) );
                  
		  // Load the form fields.
		  $this->init_form_fields();

		  // Load the settings.
		  $this->init_settings();

		  // Define user setting variables.
		  $this->title              = $this->settings['title'];
		  $this->description        = $this->settings['description'];
		  $this->app_key            = $this->settings['app_key'];
		  $this->debug              = $this->settings['debug'];
                  $this->successpostbackurl = $this->settings['successpostbackurl'];
		  $this->failurepostbackurl = $this->settings['failurepostbackurl'];
                  //$this->pendingpostbackurl = $this->settings['pendingpostbackurl'];
		  
                  
                  // Actions.
		  add_action( 'woocommerce_api_wc_trustpay_gateway', array( &$this, 'check_ipn_response' ) );
		  //add_action( 'valid_trustpay_ipn_request', array( &$this, 'successful_request' ) );
		  add_action( 'woocommerce_receipt_trustpay', array( &$this, 'receipt_page' ) );
		  
		  if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
		      add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
		  } else {
		      add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
		  }

		  // Valid for use.
		  if ( ! $this->is_valid_for_use() )
			$this->enabled = false;
                  
		  // Checking if vendor_key/app_key is not empty.
		  if ( empty( $this->app_key ) ) {
		      add_action( 'admin_notices', array( &$this, 'vendor_key_missing_message' ) );
		  }

		  // Active logs.
		  if ( 'yes' == $this->debug ) {
		      $this->log = new WC_Logger();
		  }
	      }

	      /**
	      * Checking if this gateway is enabled and available in the user's country.
	      *
	      * @return bool
	      */
	      public function is_valid_for_use() {
		  $is_available = false;                 
                  if ($this->enabled == 'yes' && $this->settings['app_key'] != '')
			$is_available = true;
                  return $is_available;
	      }

	      /**
	      * Admin Panel Options
	      * - Options for bits like 'title' and availability on a country-by-country basis.
	      *
	      * @since 1.0.0
	      */
	      public function admin_options() {
		  ?>
		  <h3><?php _e( 'Trustpay', 'wctrustpay' ); ?></h3>
		  <p><?php _e( 'Trustpay Gateway works by sending the user to Trustpay to enter their payment information.', 'wctrustpay' ); ?></p>
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
			  'title' => __( 'Enable/Disable', 'wctrustpay' ),
                          'label' => __( 'Enable Trustpay Gateway', 'wctrustpay' ),
			  'type' => 'checkbox',
			  'default' => 'yes',
                          'description' => __( 'This controls whether the plugin is active on the checkout page.', 'wctrustpay' )
		      ),
		      'title' => array(
			  'title' => __( 'Title', 'wctrustpay' ),
			  'type' => 'text',
			  'description' => __( 'This controls the title which the user sees during checkout.', 'wctrustpay' ),
			  'default' => __( 'Trustpay', 'wctrustpay' ),
                          'desc_tip' => true
		      ),
		      'description' => array(
			  'title' => __( 'Description', 'wctrustpay' ),
			  'type' => 'textarea',
			  'description' => __( 'This controls the description which the user sees during checkout.', 'wctrustpay' ),
			  'default' => __( 'Pay with TrustPay Methods', 'wctrustpay' ),
                          'desc_tip' => true
		      ),
		      'app_key' => array(
			  'title' => __( 'Vendor Key', 'wctrustpay' ),
			  'type' => 'text',
			  'description' => __( 'Please enter your Trustpay Vendor Key.', 'wctrustpay' ) . ' ' . sprintf( __( 'You can to get this information in: %sTrustPay Account%s.', 'wctrustpay' ), '<a href="https://my.trustpay.biz" target="_blank">', '</a>' ),
			  'default' => ''
		      ),
                      'successpostbackurl' => array(
                          'title' => __('Success Postback URL', 'wctrustpay'),
                          'type' => 'text',
                          'description' => __('Please enter the Success Postback URL.', 'wctrustpay'),
                          'default' => __( $this->defaultsuccessUrl ),
                          'placeholder' => __('Leave blank for default woocommerce success url'),
                          'desc_tip' => true
                      ),
                      'failurepostbackurl' => array(
                          'title' => __('Failure Postback URL', 'wctrustpay'),
                          'type' => 'text',
                          'description' => __('Please enter the Failure Postback URL.', 'wctrustpay'),
                          'default' => __( $this->defaultfailureUrl ),
                          'placeholder' => __('Leave blank for default woocommerce fail url'),
                          'desc_tip' => true
                      ),
                      /*
                      'pendingpostbackurl' => array(
                          'title' => __('Transaction Pending Postback URL', 'wctrustpay'),
                          'type' => 'text',
                          'description' => __('For CarrierBilling transactions, please enter the pending holder URL', 'wctrustpay'),
                          'default' => __( $this->defaultpendingUrl ),
                          'placeholder' => __('Leave blank for default woocommerce pending url'),
                          'desc_tip' => true
                      ),*/
		      'testing' => array(
			  'title' => __( 'Gateway Testing', 'wctrustpay' ),
			  'type' => 'title',
			  'description' => '',
                          'desc_tip' => true
		      ),
                      'istest' => array(
			  'title' => __( 'Test Mode', 'wctrustpay' ),
                          'label' => __( 'Enable Development/Test Mode', 'wctrustpay' ),
			  'type' => 'checkbox',
			  'default' => 'yes',
                          'description' => __( 'This sets the payment gateway in development mode.', 'wctrustpay' ),
                          'desc_tip' => true
		      ),
		      'debug' => array(
			  'title' => __( 'Debug Log', 'wctrustpay' ),
			  'type' => 'checkbox',
			  'label' => __( 'Enable logging', 'wctrustpay' ),
			  'default' => 'no',
			  'description' => __( 'Log Trustpay events, such as API requests, inside <code>woocommerce/logs/trustpay.txt</code>', 'wctrustpay'  )
		      )
		  );
	      }
              
	      /**
	      * Process the payment and return the result.
	      *
	      * @param int $order_id
	      * @return array
	      */
	      public function process_payment( $order_id ) {
                  //global $woocommerce;
		  $order = new WC_Order( $order_id );
                  return array(
                        'result' 	=> 'success',
                        'redirect'	=> $order->get_checkout_payment_url( true )
                  );
	      }

	      /**
	      * Output for the order received page.
	      *
	      * @return void
	      */
	      public function receipt_page( $order ) {
                  echo $this->generate_truspay_form( $order );
	      }
	      
	      /**
	      * Adds error message when not configured the app_key.
	      *
	      * @return string Error Mensage.
	      */
	      public function vendor_key_missing_message() {
		  $html = '<div class="error">';
		      $html .= '<p>' . sprintf( __( '<strong>Gateway Disabled</strong> You should inform your Vendor Key in Trustpay. %sClick here to configure!%s', 'wctrustpay' ), '<a href="' . get_admin_url() . 'admin.php?page=woocommerce_settings&amp;tab=payment_gateways">', '</a>' ) . '</p>';
		  $html .= '</div>';

		  echo $html;
	      }
                            
              public function generate_truspay_form( $order_id ) {
                global $woocommerce;
		$order = new WC_Order( $order_id );
		
                //prepare the success order fallback url
                if (empty($this->settings['successpostbackurl'])){
                    $successUrl = $this->get_return_url( $order );
                }else{
                    $successUrl = $this->settings['successpostbackurl'];
                }
                
                //prepare the fail/cancel order fallback url
                if (empty($this->settings['failurepostbackurl'])){
                    $cancelUrl = $order->get_cancel_order_url();
                }else{
                    $cancelUrl = $this->settings['failurepostbackurl'];
                }
                
                $this->data_to_send = array(
                    // TrustPay Account related details
                    'vendor_id' => $this->settings['app_key'],
                    'appuser'   => $order->billing_first_name. ' ' .$order->billing_last_name,
                    'currency'  => get_option( 'woocommerce_currency' ),
                    'amount'    => $order->order_total,
                    'txid'      => (string)$order->id,
                    'fail'      => $cancelUrl,
                    'success'   => $successUrl,
                    'message'   => sprintf( __( 'New order from %s', 'wctrustpay' ), get_bloginfo( 'name' ) ),
                    'istest'    => $this->settings['istest']
	   	);
		$trustpay_args_array = array();
		foreach ($this->data_to_send as $key => $value) {
			$trustpay_args_array[] = '<input type="hidden" name="'.$key.'" value="'.$value.'" />';
		}
		return '<form action="' . $this->url . '" method="get" id="trustpay_payment_form">
                            ' . implode('', $trustpay_args_array) . '
                            <input type="submit" class="button-alt" id="submit_trustpay_payment_form" value="' . __( 'Pay via TrustPay', 'wctrustpay' ) . '" /> <a class="button cancel" href="' . $order->get_cancel_order_url() . '">' . __( 'Cancel order &amp; restore cart', 'wctrustpay' ) . '</a>
                            <script type="text/javascript">
                                jQuery(function(){
                                    jQuery("body").block(
                                    {
                                        message: "<img src=\"' . $woocommerce->plugin_url() . '/assets/images/ajax-loader.gif\" alt=\"Redirecting...\" />' . __( 'Thank you for your order. We are now redirecting you to TrustPay to make payment.', 'wctrustpay' ) . '",
                                        overlayCSS:
                                        {
                                            background: "#fff",
                                            opacity: 0.6
                                        },
                                        css: {
                                            padding:        20,
                                            textAlign:      "center",
                                            color:          "#555",
                                            border:         "3px solid #aaa",
                                            backgroundColor:"#fff",
                                            cursor:         "wait"
                                        }
                                    });
                                    jQuery( "#submit_trustpay_payment_form" ).click();
                                });
                            </script>
                    </form>';
                }
	  }
      }    
}
?>
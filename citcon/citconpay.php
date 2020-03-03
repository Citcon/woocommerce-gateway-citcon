<?php
/**
 * Plugin Name: CitconPay Gateway for WooCommerce
 * Plugin Name:
 * Description: Allows you to use AliPay and WechatPay through CitconPay Gateway
 * Version: 1.1.0
 * Author: citcon
 * Author URI: http://www.citcon.com
 *
 * @package CitconPay Gateway for WooCommerce
 * @author citconpay
 */

add_action('plugins_loaded', 'init_woocommerce_citconpay', 0);

function init_woocommerce_citconpay() {

    if ( ! class_exists( 'WC_Payment_Gateway' ) ) { return; }

	class woocommerce_citconpay extends WC_Payment_Gateway{

		public function __construct() {

			global $woocommerce;

			$plugin_dir = plugin_dir_url(__FILE__);

	        $this->id               = 'citconpay';
	        $this->icon     		= apply_filters( 'woocommerce_citconpay_icon', ''.$plugin_dir.'citconpay_methods.png' );
	        $this->has_fields       = true;

	        $this->init_form_fields();
	        $this->init_settings();

	        // variables
	        $this->title            = $this->settings['title'];
			$this->token			= $this->settings['token'];
			$this->mode             = $this->settings['mode'];
            $this->supports           = array(
                'products',
                'refunds',
            );
			if (isset($this->settings['currency'])){
                $this->currency         = $this->settings['currency'];
            }
	        $this->notify_url   	= add_query_arg( 'wc-api', 'wc_citconpay', home_url( '/' ) );
			if( $this->mode == 'test' ){
				$this->gateway_url = 'https://uat.citconpay.com/chop/chop';
			}else if( $this->mode == 'live' ){
				$this->gateway_url = 'https://citconpay.com/chop/chop';
			}

	        // actions
			add_action( 'woocommerce_receipt_citconpay', array( $this, 'receipt_page' ) );
	        add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_api_wc_citconpay', array( $this, 'check_ipn_response' ) );

			if ( !$this->is_valid_for_use() ) {
				$this->enabled = false;
			}
		}

		/**
		 * get_icon function.
		 *
		 * @access public
		 * @return string
		 */
		function get_icon() {
			global $woocommerce;
			$icon = '';
			if ( $this->icon ) {
				$icon = '<img src="' . $this->force_ssl( $this->icon ) . '" alt="' . $this->title . '" />';
			}
			return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );
		}

	     /**
	     * Check if this gateway is enabled and available in the user's country
	     */
	    function is_valid_for_use() {
	        if (!in_array(get_option('woocommerce_currency'), array('USD')))
	        	return false;

	        return true;
	    }

	    /**
	    * Admin Panel Options
	    **/
	    public function admin_options()
	    {
			?>
	        <h3><?php _e('CitconPay', 'woocommerce'); ?></h3>
	        <p><?php _e('CitconPay Gateway supports AliPay and WeChatPay.', 'woocommerce'); ?></p>
			<table class="form-table">
	        <?php
	    		if ( $this->is_valid_for_use() ) :

	    			// Generate the HTML For the settings form.
	    			$this->generate_settings_html();

	    		else :

	    			?>
	            		<div class="inline error"><p><strong><?php _e( 'Gateway Disabled', 'woothemes' ); ?></strong>: <?php _e( 'CitconPay does not support your store currency.', 'woothemes' ); ?></p></div>
	        		<?php

	    		endif;
	        ?>
	        </table><!--/.form-table-->
	        <?php
		}

	    /**
	    * Initialise CitconPay Settings Form Fields
	    */
	    public function init_form_fields() {

			//  array to generate admin form
	        $this->form_fields = array(
	        	'enabled' => array(
	            				'title' => __( 'Enable/Disable', 'woocommerce' ),
			                    'type' => 'checkbox',
			                    'label' => __( 'Enable CitconPay', 'woocommerce' ),
			                    'default' => 'yes'
							),
				'title' => array(
			                    'title' => __( 'Title', 'woocommerce' ),
			                    'type' => 'text',
			                    'description' => __('This is the title displayed to the user during checkout.', 'woocommerce' ),
			                    'default' => __( 'CitconPay', 'patsatech-woo-citconpay-server' )
			                ),
				'token' => array(
								'title' => __( 'API Token', 'woocommerce' ),
								'type' => 'text',
								'description' => __( 'API Token', 'woocommerce' ),
								'default' => ''
				),
				'mode' => array(
								'title' => __('Mode', 'woocommerce'),
			                    'type' => 'select',
			                    'options' => array(
													'test' => 'Test',
													'live' => 'Live'
													),
			                    'default' => 'live',
								'description' => __( 'Test or Live', 'woocommerce' )
							)
				);
		}

		/**
		 * Generate the citconpayserver button link
		 **/
	    public function generate_citconpay_form( $order_id ) {
			global $woocommerce;
	        $order = new WC_Order( $order_id );

			wc_enqueue_js('
					jQuery("body").block({
							message: "<img src=\"'.esc_url( $woocommerce->plugin_url() ).'/assets/images/ajax-loader.gif\" alt=\"Redirecting...\" style=\"float:left; margin-right: 10px;\" />'.__('Thank you for your order. We are now redirecting you to verify your card.', 'woothemes').'",
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
						        cursor:         "wait",
						        lineHeight:		"32px"
						    }
						});
					jQuery("#submit_citconpay_payment_form").click();
				');

				return '<form action="'.esc_url( get_transient('citconpay_next_url') ).'" method="post" id="citconpay_payment_form">
						<input type="submit" class="button alt" id="submit_citconpay_payment_form" value="'.__('Submit', 'woothemes').'" /> <a class="button cancel" href="'.esc_url( $order->get_cancel_order_url() ).'">'.__('Cancel order', 'woothemes').'</a>
					</form>';

		}

		/**
		*
	    * process payment
	    *
	    */
	    function process_payment( $order_id ) {
			global $woocommerce;

	        $order = new WC_Order( $order_id );

	        $time_stamp = date("YmdHis");
	        $orderid = $time_stamp . "-" . $order_id;

	        $nhp_arg[]=array();
			$currency = get_option('woocommerce_currency');
	        $nhp_arg['currency']=$currency;
			$oder_total =  ( WC()->version < '2.7.0' ) ? $order->order_total : $order->get_total();
			if($currency != 'JPY') {
				$nhp_arg['amount']=$oder_total * 100;
			} else {
				$nhp_arg['amount']=$oder_total;
			}
	        $nhp_arg['ipn_url']=$this->notify_url;
	        $nhp_arg['callback_url_success']=$order->get_checkout_order_received_url();
	        //$nhp_arg['show_url']=$order->get_cancel_order_url();
	        $nhp_arg['reference']=$orderid;
	        $nhp_arg['payment_method']=$_POST['vendor'];
	        //$nhp_arg['terminal']=$this->terminal;
	        $nhp_arg['note']=$order_id;
                $nbp_arg['allow_duplicates']='yes';


	        $post_values = "";
	        foreach( $nhp_arg as $key => $value ) {
	            $post_values .= "$key=" . $value . "&";
	        }
	        $post_values = rtrim( $post_values, "& " );

	        $response = wp_remote_post($this->gateway_url, array(
											'body' => $post_values,
											'method' => 'POST',
	                						'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded', 'Authorization' => 'Bearer '.$this->token ),
											'sslverify' => FALSE
											));

			if (!is_wp_error($response)) {
                $resp = $response['body'];
                $result = json_decode($resp);
                $redirect = wc_get_cart_url();;
                if ($result->{'result'} == 'success') {
                    $redirect = $result->{'url'};
                } else {
                    wc_add_notice(__('Error has occurred', 'woocommerce'), apply_filters('woocommerce_cart_updated_notice_type', 'error'));
                }
                return array(
                    'result' => 'success',
                    'redirect' => $redirect
                );
            } else {
                $woocommerce->add_error(__('Gateway Error.', 'woocommerce'));
            }
		}
		/**
		 * Payment form on checkout page
		 */
		function payment_fields() {
				global $woocommerce;
				?>
				<?php if ($this->description) : ?><p><?php echo $this->description; ?></p><?php endif; ?>
				<fieldset>
				<legend><label>Method of payment<span class="required">*</span></label></legend>
				<ul class="wc_payment_methods payment_methods methods">
					<li class="wc_payment_method">
						<input id="citconpay_pay_method_alipay" class="input-radio" name="vendor" checked="checked" value="alipay" data-order_button_text="" type="radio" required>
						<label for="citconpay_pay_method_alipay"> AliPay </label>
					</li>
					<li class="wc_payment_method">
						<input id="citconpay_pay_method_wechatpay" class="input-radio" name="vendor" value="wechatpay" data-order_button_text="" type="radio" required>
						<label for="citconpay_pay_method_wechatpay"> WechatPay </label>
				   </li>
				   <!--<li class="wc_payment_method">
						<input id="citconpay_pay_method_unionpay" class="input-radio" name="vendor" value="upop" data-order_button_text="" type="radio" required>
						<label for="citconpay_pay_method_unionpay"> Unionpay </label>
				   </li>
				   <li class="wc_payment_method">
						<input id="citconpay_pay_method_cc" class="input-radio" name="vendor" value="cc" data-order_button_text="" type="radio" required>
						<label for="citconpay_pay_method_cc"> Credit Card </label>
				   </li>-->
				</ul>
				<div class="clear"></div>
				</fieldset>
				<?php
		 }
		/**
		 * receipt_page
		 **/
		function receipt_page( $order ) {
			global $woocommerce;
			echo '<p>'.__('Thank you for your order.', 'woothemes').'</p>';

			echo $this->generate_citconpay_form( $order );

		}

		private function force_ssl($url){
			if ( 'yes' == get_option( 'woocommerce_force_ssl_checkout' ) ) {
				$url = str_replace( 'http:', 'https:', $url );
			}
			return $url;
		}

		function check_ipn_response() {
            global $woocommerce;
            @ob_clean();
            //$note = $_REQUEST['note'];
            $transactionId = $_REQUEST['id'];
            $status=$_REQUEST['notify_status'];
            $reference=$_REQUEST['reference'];
            $order_ids=explode('-', $reference);
            $wc_order   = new WC_Order( absint( $order_ids[1] ) );
            if(!$this->validateSignature()){
                wp_die( "Invalid signature." );
            }

            if($status == 'success'){
            	$wc_order->payment_complete($transactionId);
            	$woocommerce->cart->empty_cart();
            	//wp_redirect( $this->get_return_url( $wc_order ) ); //no need to redirect because it is aync notification
                exit;
            }else{
            	wp_die( "Payment failed. Please try again." );
            }
        }
        protected function validateSignature()
        {
            $fields = $_REQUEST['fields'];
            $data['fields'] = $fields;
            $tok = strtok($fields, ",");
            while ($tok !== false) {
                $data[$tok] = $_REQUEST[$tok];
                $tok = strtok(",");
            }
            ksort($data);
            $flat_reply = "";
            foreach ($data as $key => $value) {
                $flat_reply = $flat_reply . "$key=$value&";
            }
            $flat_reply = $flat_reply . "token={$this->token}";
            $signature = md5($flat_reply);
            return ($signature == $_REQUEST['sign']);
        }
        /**
         * Can the order be refunded
         *
         * @param  WC_Order $order Order object.
         * @return bool
         */
        public function can_refund_order( $order ) {
            $has_api_creds = !empty($this->token);
            return $order && $order->get_transaction_id() && $has_api_creds;
        }

        /**
         * Process a refund if supported.
         *
         * @param  int    $order_id Order ID.
         * @param  float  $amount Refund amount.
         * @param  string $reason Refund reason.
         * @return bool|WP_Error
         */
        public function process_refund( $order_id, $amount = null, $reason = '' ) {
            $order = wc_get_order( $order_id );

            if ( ! $this->can_refund_order( $order ) ) {
                return new WP_Error( 'error', __( 'Refund failed.', 'woocommerce' ) );
            }

            $request = [
                'amount' => $amount * 100,
                'currency' => $order->get_currency(),
                'transaction_id' => $order->get_transaction_id(),
                'reason' => $reason
            ];

            $post_values = http_build_query($request);

            if( $this->mode == 'test' ){
                $this->gateway_url_refund = 'https://uat.citconpay.com/chop/refund';
            }else if( $this->mode == 'live' ){
                $this->gateway_url_refund = 'https://citconpay.com/chop/refund';
            }

            $result = wp_remote_post($this->gateway_url_refund, array(
                'body' => $post_values,
                'method' => 'POST',
                'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded', 'Authorization' => 'Bearer '.$this->token ),
                'sslverify' => FALSE
            ));

            if ( is_wp_error( $result ) ) {
                return new \WP_Error( 'error', $result->get_error_message() );
            }
            $result = json_decode($result['body']);
            switch ( strtolower( $result->status ) ) {
                case 'success':
                    $order->add_order_note(
                    /* translators: 1: Refund amount, 2: Refund ID */
                        sprintf( __( 'Refunded %1$s - Refund ID: %2$s', 'woocommerce' ), $amount, $result->id )
                    );
                    return true;
            }

            return false;
        }
	}

	/**
	 * Add the gateway to WooCommerce
	 **/
	function add_citconpay_gateway( $methods )
	{
	    $methods[] = 'woocommerce_citconpay';
	    return $methods;
	}
	add_filter('woocommerce_payment_gateways', 'add_citconpay_gateway' );
}

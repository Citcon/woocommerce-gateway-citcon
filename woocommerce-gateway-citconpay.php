<?php
/**
 * Plugin Name: CitconPay Gateway for WooCommerce
 * Plugin Name:
 * Description: Allows you to use AliPay, WechatPay and UnionPay through CitconPay Gateway
 * Version: 1.6.0
 * Author: citcon
 * Author URI: http://www.citcon.com
 *
 * @package CitconPay Gateway for WooCommerce
 * @author citcon
 */



add_action('before_woocommerce_init', 'rudr_cart_checkout_blocks_compatibility' );
add_action('plugins_loaded', 'init_woocommerce_citconpay', 0);

define("WC_GATEWAY_CITCON_VERSION", "1.6.0");
define("WC_GATEWAY_CITCON_LOG" , "[wc-citcon]");

define( 'WC_GATEWAY_CITCON_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
define( 'WC_GATEWAY_CITCON_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );


function rudr_cart_checkout_blocks_compatibility() {
    if( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                'cart_checkout_blocks',
                __FILE__,
                true // true (compatible, default) or false (not compatible)
            );
    }
}



require_once dirname( __FILE__ ) . '/vendor-config.php';

use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;


function init_woocommerce_citconpay() {

	if (!class_exists('WC_Payment_Gateway')) {
		return;
	}

	class WC_Gateway_Citconpay extends WC_Payment_Gateway {

        const ID = 'citconpay';

        public $token;
        public $mode;
        public $notify_url;
        public $gateway_url_payment;
        public $gateway_url_refund;


		public function __construct() {

			global $woocommerce;
			$this->id = 'citconpay';
            
            // $this->icon = apply_filters('woocommerce_citconpay_icon', WC_GATEWAY_CITCON_URL . 'citconpay_methods.png');
            $this->icon = apply_filters('woocommerce_citconpay_icon', WC_GATEWAY_CITCON_URL . '/images/citcon-pay-logo.svg');

            $this->method_title       = __( 'CitconPay', 'woocommerce' );
            $this->method_description = sprintf(
                /* translators: 1: html starting code 2: html end code */
                    __(
                        '%1$sCitconPay%2$s Gateway supports AliPay, WeChatPay, Union Pay and Paypal.',
                        'woocommerce'
                    ),
                    '<a href="http://citcon.com/">',
                    '</a>'
                );

			$this->has_fields = true;
			$this->init_form_fields();
			$this->init_settings();

			$this->token = $this->settings['token'];
			$this->mode = $this->settings['mode'];
           
            // variables
            $this->title = $this->settings['title'];
			$this->supports = array(
				'products',
				'refunds',
			);
			if (isset($this->settings['currency'])) {
				$this->currency = $this->settings['currency'];
			}
			$this->notify_url = add_query_arg('wc-api', 'wc_citconpay', home_url('/'));
            $this->gateway_url_payment = get_api_url($this->mode, "chop");
            $this->gateway_url_refund = get_api_url($this->mode, "refund");
            

			if (!$this->is_valid_for_use()) {
				$this->enabled = "no";
			}


			// actions
			add_action('woocommerce_update_options_payment_gateways', array($this, 'process_admin_options'));
			add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
			add_action('woocommerce_api_wc_citconpay', array($this, 'check_ipn_response'));

            add_action( 'wp_enqueue_scripts', array( $this, 'init_website_assets' ) );
            add_action( 'enqueue_block_assets', array( $this, 'init_website_assets' ) );


            add_action( 'woocommerce_rest_checkout_process_payment_with_context', array( $this, 'checkout_process_payment_with_context' ) );


		}


        /**
         * Process payment with context
         * Note: This function is used for the REST API to process payment with context.
         * It is not used in the web checkout process.
         * @param array $context The context for the payment process.
         */
        function checkout_process_payment_with_context($context) {
           
           // check payment
        }
        

		/**
		 * Check if this gateway is enabled and available in the user's country
		 */
		public function is_valid_for_use() {
            return has_support_currency(get_option('woocommerce_currency'));
		}

		/**
		 * Admin Panel Options
		 **/
		public function admin_options() {
			?>
			<h3><?php echo esc_html__('CitconPay', 'woocommerce'); ?></h3>
			<p><?php echo esc_html__('CitconPay Gateway supports AliPay, WeChatPay, Union Pay and Paypal.', 'woocommerce'); ?></p>
			<table class="form-table">
				<?php
				if ($this->is_valid_for_use()) :
					// Generate the HTML For the settings form.
					$this->generate_settings_html();
				else :
					?>
					<div class="inline error">
						<p>
							<strong><?php echo esc_html__('Gateway Disabled', 'woothemes'); ?></strong>:
							<?php echo esc_html__('CitconPay does not support your store currency.', 'woothemes'); ?>
						</p>
					</div>
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
					'title' => __('Enable/Disable', 'woocommerce'),
					'type' => 'checkbox',
					'label' => __('Enable CitconPay', 'woocommerce'),
					'default' => 'yes'
				),
				'title' => array(
					'title' => __('Title', 'woocommerce'),
					'type' => 'text',
					'description' => __('This is the title displayed to the user during checkout.', 'woocommerce'),
					'default' => __('CitconPay', 'patsatech-woo-citconpay-server')
				),
				'token' => array(
					'title' => __('API Token', 'woocommerce'),
					'type' => 'text',
					'description' => __('API Token', 'woocommerce'),
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
					'description' => __('Test or Live', 'woocommerce')
				),
            );

            $title_list = get_title_list();
            $this->form_fields['selectedMethod'] = [
                'title' => __('Default Payment Method', 'woocommerce'),
                'type' => 'select',
                'options' => $title_list,
                'default' => array_keys($title_list)[0],
                'description' => __('Select a default payment method', 'woocommerce')
            ];

            $vendor_list = get_form_fields();
            foreach ($vendor_list as $key => $value) {
                $this->form_fields[$key] = $value;
            }


            


		}


        function get_posted_vendor() {
            $vendor = null;
            if (isset($_POST['vendor'])) {
                $vendor = sanitize_key($_POST['vendor']);
            } elseif (isset($_REQUEST['vendor'])) {
                $vendor = sanitize_key($_REQUEST['vendor']);
            } elseif (isset($_POST['payment_data'])) {
                $data = json_decode(stripslashes($_POST['payment_data']), true);
                if (isset($data['vendor'])) {
                    $vendor = sanitize_key($data['vendor']);
                }
            }
            return $vendor;
        }

		/**
		 *
		 * Process payment
		 *
		 */
		public function process_payment( $order_id) {
			global $woocommerce;

            $vendor = $this->get_posted_vendor();


			$order = new WC_Order($order_id);
            $paymentData = $order->get_data();

            $time_stamp = gmdate('YmdHis');
			$orderid = $time_stamp . '-' . $order_id;
			$currency = get_option('woocommerce_currency');
            $factor = get_currency_unit_conversion_factor($currency);

			$nhp_arg = [];
			$nhp_arg['currency'] = $currency;

            $oder_total = $order->get_total();

            $nhp_arg['amount'] = $oder_total * $factor;

			$nhp_arg['ipn_url'] = urlencode($this->notify_url);
            $result_url = urlencode($order->get_checkout_order_received_url());
			$nhp_arg['callback_url_success'] = $nhp_arg['callback_url_fail'] = $nhp_arg['mobile_result_url'] = $result_url;
            
            // cancel to cart
            $nhp_arg['callback_url_cancel'] = urlencode($order->get_cancel_order_url());

            // cancel to checkout
            // $nhp_arg['callback_url_cancel'] = urlencode($order->get_checkout_payment_url());

			//$nhp_arg['show_url']=$order->get_cancel_order_url();
			$nhp_arg['reference'] = $orderid;

			if ( !wp_verify_nonce('', 'woocommerce-process_checkout')
				&& !empty($vendor)
				&& sanitize_key($vendor) ) {
				$nhp_arg['payment_method'] = sanitize_key($_POST['vendor']);
			}
			//$nhp_arg['terminal']=$this->terminal;
			$nhp_arg['note'] = $order_id;
			$nhp_arg['allow_duplicates'] = 'yes';
            $nhp_arg['source'] = 'woocommerce';

            $ext_arg = [
				"app_version" => WC_GATEWAY_CITCON_VERSION,
				"woocommerce" => WC_VERSION,
				"wordpress" => $GLOBALS["wp_version"]
			];
			$nhp_arg['ext'] = urlencode(json_encode($ext_arg));

            $vendor = get_vendor_by($vendor);
            if (isset($vendor) && isset($vendor->processPaymentBody)) {
                $handleParams = $vendor->processPaymentBody;
                $nhp_arg = $handleParams($nhp_arg, $order, $this->settings);
            }

			$post_values = '';
			foreach ($nhp_arg as $key => $value) {
				$post_values .= "$key=" . $value . '&';
			}
			$post_values = rtrim($post_values, '& ');

			$this->wc_citcon_log('[pay request] '.$post_values);


			$response = wp_remote_post($this->gateway_url_payment, array(
				'body' => $post_values,
				'method' => 'POST',
				'headers' => array('Content-Type' => 'application/x-www-form-urlencoded', 'Authorization' => 'Bearer ' . $this->token),
				'sslverify' => false
			));

            if (!is_wp_error($response)) {
				$resp = $response['body'];
				$result = json_decode($resp);
				$this->wc_citcon_log('[pay response] '.json_encode($resp));
				$redirect = wc_get_cart_url();
				$successResult = 'success';
				if ($result->{'result'} == $successResult) {
					$redirect = $result->{'url'};
				} else {
					wc_add_notice(__('Error has occurred', 'woocommerce'), apply_filters('woocommerce_cart_updated_notice_type', 'error'));
				}
				return array(
					'result' => 'success',
					'redirect' => $redirect
				);
            } else {
                $this->wc_citcon_log('[pay error] '. $response->get_error_message());
                if ( is_callable( array( $woocommerce, 'add_error' ) ) ) {
                    $woocommerce->add_error(__('Gateway Error.', 'woocommerce'));
                } else {
                    wc_add_notice( __('Gateway Error.', 'woocommerce') );
                }
			}
		}

        /**
         * Check If The Gateway Is Available For Use.
         *
         * @return bool
         */
        public function is_available() {
            return $this->enabled === "yes";
        }

		/**
		 * Payment form on checkout page, front page
		 */
		public function payment_fields() {
			global $woocommerce;
			if ($this->description) :
				?>
				<p><?php esc_html_e($this->description); ?></p>
				<?php
			endif;
			?>
			<fieldset>
				<legend>
                    <label>
                        <?php esc_html_e('Method of payment'); ?>
                        <span class="required">*</span>
                    </label>
                    </legend>
                        <ul class="wc_payment_methods payment_methods methods">
                            <?php 
                            foreach (get_vendor_list() as $key => $value) {
                                $method = $value -> method;
                                $title = $value -> title;
                                $currency = get_option('woocommerce_currency');
                                $icon = $value -> icon;
                                $icons = $value -> icons;
                                $icon_height = $value -> icon_height;

                                if (strcmp($this->settings[$method],'yes')==0 && in_array($currency, $value -> currency)) { ?>
                                    <li class="wc_payment_method">
                                        <div style="display: flex; align-items: center;">
                                            <input id="citconpay_pay_method_<?php echo $method; ?>" 
                                                    class="input-radio" 
                                                    name="vendor" 
                                                    value="<?php echo $method; ?>"
                                                    data-order_button_text="" 
                                                    type="radio" required 
                                                <?php if (strcmp($this->settings['selectedMethod'],$method)==0) { ?>
                                                    checked="checked"
                                                <?php } ?>
                                                >

                                            <label for="citconpay_pay_method_<?php echo $method; ?>">
                                                <?php if (isset($icons) && is_array($icons)) { ?>
                                                    <div class="citconpay-icons" style="display: flex; align-items: center; "
                                                    title="<?php esc_html_e($title); ?>"
                                                    >
                                                        <?php foreach ($icons as $ico) { ?>
                                                            <img src="<?php echo $ico; ?>"
                                                                style="height: <?php echo $icon_height; ?>; margin-left: -2px; margin-right: 6px;"
                                                            />
                                                        <?php } ?>
                                                    </div>
                                                <?php } else { ?>

                                                    <img src="<?php echo $icon; ?>" 
                                                    style="height: <?php echo $icon_height; ?>; margin-left: -2px;" alt="Citcon Pay"
                                                    title="<?php esc_html_e($title); ?>"
                                                    />
                                                    <!-- <?php esc_html_e($title); ?>  -->

                                                <?php } ?>

                                            </label>
                                            
                                        </div>
                                    </li>
                                <?php } ?>
                            <?php } ?>
                        </ul>
                    <div class="clear">
                </div>
			</fieldset>
			<?php
		}

		private function force_ssl( $url) {
			if ( 'yes' == get_option('woocommerce_force_ssl_checkout') ) {
				$url = str_replace('http:', 'https:', $url);
			}
			return $url;
		}

        function check_ipn_response() {
            global $woocommerce;
			@ob_clean();
            if ( !isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] != 'POST' ) {
                wp_die('Invalid request method.');
            }

            // if ( !isset($_SERVER['CONTENT_TYPE']) || strpos($_SERVER['CONTENT_TYPE'], 'application/json') === false ) {
            //     wp_die('Invalid content type.');
            // }

            // Read the raw POST data
            if ( !function_exists('file_get_contents') ) {
                wp_die('file_get_contents function is not available.');
            }

            $json_data = json_decode(file_get_contents('php://input'), true);
            $request_data = !empty($json_data) ? $json_data : $_REQUEST;

            $this->wc_citcon_log('[ipn request data] ' . print_r($request_data, true));
            $this->wc_citcon_log('[ipn request headers] ' . print_r(getallheaders(), true));

			// $this->wc_citcon_log('[ipn notification] '.json_encode($request_data));

            $transaction_id = $request_data['id'];
            $reference = $request_data['reference'];
            $status = $request_data['notify_status'];
            $amount = $request_data['amount'];
            $currency = $request_data['currency'];

            $order_ids = explode('-', $reference);
			$wc_order = new WC_Order(absint($order_ids[1]));
			if (!$this->validateSignature($request_data)) {
				wp_die('Invalid signature.');
			}

            $factor = get_currency_unit_conversion_factor($currency);

            if ($status == 'success') {
				$wc_order->payment_complete($transaction_id); // This will ensure stock reductions are made, and the status is changed to the correct value.
				$wc_order->add_order_note(
                    sprintf( __( 'A payment of $%1$s %2$s was processed on CitconPay.', 'woocommerce' )
                     , number_format($amount / $factor, 2, '.', '')
                     , $currency )
                    );

				// $woocommerce->cart->empty_cart();
				//wp_redirect( $this->get_return_url( $wc_order ) ); //no need to redirect because it is async notification

                exit;
            } 

            // If we reach here, the payment was not successful
            // $wc_order->update_status('failed', __('Payment failed on CitconPay.', 'woocommerce'));

            wp_die('Payment failed. Please try again.');

        }

        protected function validateSignature($request_data) {
            $fields = $request_data['fields'];
            $sign = $request_data['sign'];
			$data['fields'] = $fields;
			$tok = strtok($fields, ',');
			while (false !== $tok) {
				if (isset($request_data[$tok])) {
					$data[$tok] = sanitize_text_field($request_data[$tok]);
				}
				$tok = strtok(',');
			}
			ksort($data);
			$flat_reply = '';
			foreach ($data as $key => $value) {
				$flat_reply = $flat_reply . "$key=$value&";
			}
			$flat_reply = $flat_reply . "token={$this->token}";
			$signature = md5($flat_reply);
			return ( $signature == $sign );
		}

		/**
		 * Can the order be refunded
		 *
		 * @param WC_Order $order Order object.
		 * @return bool
		 */
		public function can_refund_order( $order) {
			$has_api_creds = !empty($this->token);
			return $order && $order->get_transaction_id() && $has_api_creds;
		}
        

		/**
		 * Process a refund if supported.
		 *
		 * @param int $order_id Order ID.
		 * @param float $amount Refund amount.
		 * @param string $reason Refund reason.
		 * @return bool|WP_Error
		 */
		public function process_refund( $order_id, $amount = null, $reason = '') {
			$order = wc_get_order($order_id);

			if (!$this->can_refund_order($order)) {
				return new WP_Error('error', __('Refund failed.', 'woocommerce'));
			}

            $currency = $order->get_currency();
            $factor = get_currency_unit_conversion_factor($currency);

			$request = array(
				'amount' => $amount * $factor,
				'currency' => $order->get_currency(),
				'transaction_id' => $order->get_transaction_id(),
				'reason' => isset($reason) ? $reason: 'No reason was given.', // ppcp will not success if empty reason
			);

			$post_values = http_build_query($request);

			$this->wc_citcon_log('[refund request] '.$post_values);
			$result = wp_remote_post($this->gateway_url_refund, array(
				'body' => $post_values,
				'method' => 'POST',
				'headers' => array('Content-Type' => 'application/x-www-form-urlencoded', 'Authorization' => 'Bearer ' . $this->token),
				'sslverify' => false
			));

			if (is_wp_error($result)) {
				return new \WP_Error('error', $result->get_error_message());
			}
			$this->wc_citcon_log('[refund response] '.json_encode($result));
			$result = json_decode($result['body']);
			switch (strtolower($result->status)) {
				case 'success':
					$order->add_order_note(
					/* translators: 1: Refund amount, 2: Refund ID */
						sprintf(__('Refunded %1$s - Refund ID: %2$s', 'woocommerce'), $amount, $result->id)
					);
					return true;
			}

			return false;
		}

		private function wc_citcon_log($message) {
			error_log(WC_GATEWAY_CITCON_LOG . " $message");
		}


        /**
		 * Note: Hooked onto the "wp_enqueue_scripts" Action to avoid the WordPress Notice warnings
		 *
		 * @since   1.6.0
		 * @see     self::__construct()     For hook attachment.
		 */
		public function init_website_assets() {

			if ( is_checkout() && $this->enabled === 'yes' && $this->is_available() ) {
                wp_enqueue_style(
                    'citconpay-css',
                    WC_GATEWAY_CITCON_URL . '/assets/css/citconpay.css',
                    [],
                    WC_GATEWAY_CITCON_VERSION
                );
			}
		}
	}

	/**
	 * Add the gateway to WooCommerce
	 **/
	function add_citconpay_gateway( $methods) {
		$methods[] = 'WC_Gateway_Citconpay';
		return $methods;
	}

	add_filter('woocommerce_payment_gateways', 'add_citconpay_gateway');



    
    function add_woocommerce_blocks_support() {
        if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
            require_once __DIR__ . '/class-wc-gateway-citcon-blocks-support.php';
            add_action(
                'woocommerce_blocks_payment_method_type_registration',
                function ( PaymentMethodRegistry $payment_method_registry ) {
                    $payment_method_registry->register( new WC_Gateway_Citcon_Blocks_Support() );
                }
            );
        }
    }

	add_action( 'woocommerce_blocks_loaded', 'add_woocommerce_blocks_support' );


    

}

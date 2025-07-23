<?php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
// use WC_Gateway_Citconpay;
require_once dirname( __FILE__ ) . '/vendor-config.php';

/**
 * Citcon payment method integration
 *
 * @since 1.6.0
 */
final class WC_Gateway_Citcon_Blocks_Support extends AbstractPaymentMethodType {
	/**
	 * Name of the payment method.
	 *
	 * @var string
	 */
	protected $name = "citconpay";

   

	
	/**
	 * Initializes the payment method type.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_'.$this->name.'_settings', array() );
	}

	

	/**
	 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
	 *
	 * @return boolean
	 */
	public function is_active() {
		return filter_var( $this->get_setting( 'enabled', false ), FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Return enable_for_methods option.
	 *
	 * @return array Array of shipping methods (string ids) that allow COD. (If empty, all support COD.)
	 */
	private function get_enable_for_methods() {
		$enable_for_methods = $this->get_setting( 'enable_for_methods', [] );
		if ( '' === $enable_for_methods ) {
			return [];
		}
		return $enable_for_methods;
	}


    /**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		$asset_path   = WC_GATEWAY_CITCON_PATH . '/build/wc-payment-method-citcon-blocks.asset.php';
		$version      = WC_GATEWAY_CITCON_VERSION;
		$dependencies = array();
		if ( file_exists( $asset_path ) ) {
			$asset        = require $asset_path;
			$version      = is_array( $asset ) && isset( $asset['version'] )
				? $asset['version']
				: $version;
			$dependencies = is_array( $asset ) && isset( $asset['dependencies'] )
				? $asset['dependencies']
				: $dependencies;
		}
		wp_register_script(
			'wc-payment-method-citcon-blocks', 
			WC_GATEWAY_CITCON_URL . '/build/wc-payment-method-citcon-blocks.js',
			$dependencies,
			$version,
			true
		);

		return [ 'wc-payment-method-citcon-blocks' ];

	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @return array
	 */
    public function get_payment_method_data() {
        return [
            'title'       => $this->get_setting( 'title', 'CitconPay' ),
            'description' => $this->get_setting( 'description', 'Pay with CitconPay' ),
            'vendors'     => get_vendor_list(),
            'selectedMethod'     => $this->get_setting( 'selectedMethod', '' ),
            'supports'    => ['products'],
            'currency'    => get_woocommerce_currency(),
        ];
    }
}

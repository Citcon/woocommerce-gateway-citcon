<?php


class Vendor {

    /**
     * Payment method title for the frontend.
     * 
     * @var string
     */
    public $title;

    /**
     * Payment gateway method currency
     * 
     * @var string
     */
    public $currency;

     /**
     * Payment gateway method country
     * 
     * @var string
     */
    public $country;

    /**
	 * Yes or no based on whether the method is enabled.
	 *
	 * @var string
	 */
	public $enabled = 'no';

    /**
     * Payment gateway method name
     * 
     * @var array
     */
    public $method;

    public $checked;

    public $icon;

    public $icon_height = '30';

    public $hide_form_title = 'yes';

    public function __construct($data) { 
        $this->title = $data['title'];
        $this->currency = $data['currency'];
        $this->country = $data['country'];
        $this->enabled = $data['enabled'];
        $this->method = $data['method'];
        $this->checked = $data['checked'];
        $this->icon = $data['icon'];

        if (isset($data['hide_form_title'])) {
            $this->hide_form_title = $data['hide_form_title'];
        }

        if (isset($data['icon_height'])) {
            $this->icon_height = $data['icon_height'];
        }

        if (isset($data['processPaymentBody'])) {
            $this->processPaymentBody = $data['processPaymentBody'];
        }

    }

    public function get_form_fields () { 
        $forms = [ 
            'type' => 'checkbox',
            'label' => __($this -> title, 'woocommerce'),
            'default' => 'yes'
        ];

        if ($this -> hide_form_title ==='no') {
            $forms['title'] = __('Enable/Disable', 'woocommerce');
        }

        return $forms;
    }

}

$cc_vendors = [
   new Vendor([
        'title' => 'Alipay',
        'currency' => ['USD', 'CAD'],
        'country' => '',
        'enabled' => 'no', 
        'method' => 'alipay',
        'checked' => 'yes',
        'hide_form_title' => 'no',
        'icon' => 'images/alipay-logo.png',
    ]),
    
    new Vendor([
        'method' => 'wechatpay',
        'title' => 'WeChat Pay',
        'currency' => ['USD', 'CAD'],
        'country' => '',
        'enabled' => 'no', 
        'checked' => 'no',
        'icon' => 'images/wechatpay-logo.png',
    ]),
    
    new Vendor([
        'method' => 'upop',
        'title' => 'Union Pay',
        'currency' => ['USD', 'CAD'],
        'country' => '',
        'enabled' => 'no', 
        'checked' => 'no',
        'icon' => 'images/unionpay2-logo.png',
    ]),
        
    new Vendor([
        'method' => 'paypal',
        'title' => 'Paypal',
        'currency' => ['USD'],
        'country' => '',
        'enabled' => 'no', 
        'checked' => 'no',
        'icon' => 'images/paypal-logo.png',
        'processPaymentBody' => function ($params, $order) {
            $params['country'] = 'US';
            $params['auto_capture'] = 'true';
            // return $params;
            return process_billing_address($params, $order);
        }
    ]),

    new Vendor([
        'method' => 'venmo',
        'title' => 'Venmo',
        'currency' => ['USD'],
        'country' => '',
        'enabled' => 'no', 
        'checked' => 'no',
        'icon' => 'images/venmo-logo.png',
        'icon_height' => '20',
        'processPaymentBody' => function ($params, $order) {
            $params['country'] = 'US';
            $params['auto_capture'] = 'true';
            // return $params;
            return process_billing_address($params, $order);
        },
    ]),

    new Vendor([
        'method' => 'cashapppay',
        'title' => 'Cash App',
        'currency' => ['USD'],
        'country' => '',
        'enabled' => 'no', 
        'checked' => 'no',
        'icon' => 'images/cashapp-logo.png',
        'icon_height' => '22',
        'processPaymentBody' => function ($params) {
            $params['country'] = 'US';
            $params['auto_capture'] = 'true';
            return $params;
        },
    ]),

];

function get_country_state_name($country, $state) {
    $countries_obj = new WC_Countries();
    $country_states_array = $countries_obj->get_states();
    $state_name = $country_states_array[$country][$state];
    $len_index = strpos($state_name, ' / ');
    if (isset($state_name) && $len_index !== false) {
        return substr($state_name, 0, $len_index);
    } else {
        return $state_name;
    }

}

function process_billing_address($params, $order) {
    $data = $order -> data;

    if (isset($data['billing'])) {
        $_billing = $data['billing'];
        $_billing_country = $order->get_billing_country();
        $_billing_state = get_country_state_name($_billing_country, $order->get_billing_state());

        $params['billing_address[first_name]']  = $order->get_billing_first_name();
        $params['billing_address[last_name]']   = $order->get_billing_last_name();
        $params['billing_address[country]']     = $_billing_country;
        $params['billing_address[state]']       = $_billing_state;
        $params['billing_address[city]']        = $order->get_billing_city();
        $params['billing_address[street]']      = $order->get_billing_address_1();
        $params['billing_address[street2]']     = $order->get_billing_address_2();
        $params['billing_address[zip]']         = $order->get_billing_postcode();
        $params['billing_address[phone]']       = $order->get_billing_phone();
        $params['billing_address[email]']       = $order->get_billing_email();
    }

    $goods = [];
    $items = $order -> items;
    // $_total_goods_tax_amount = 0;

    if (isset($items) && count($items) > 0) {
        foreach ($items as $item) { // [WC_Order_Item_Product]
            $name = mb_substr( $item->get_name(), 0, 127 );
            $quantity =  (int) $item -> get_quantity();
            $product = $item -> get_product(); // WC_Product_Simple

            $price_without_tax = (float) $order->get_item_subtotal( $item, false );
		    $unit_amount = $price_without_tax * 100;
            
            // physical, digital, donation
            $product_type = $product instanceof WC_Product && $product->is_virtual() ? 'digital' : 'physical';

            $sku = $product instanceof WC_Product ? $product->get_sku() : '';
            $total_tax = (float) $item -> get_total_tax();
            
            // $_total_goods_tax_amount += $total_tax;
            
            $unit_tax_amount = $total_tax / $quantity * 100;
            $total_discount_amount = ((float) $item -> get_subtotal() -  (float) $item -> get_total()) * 100;

            array_push($goods, [
                'sku'                   => $sku,
                'name'                  => $name,
                'quantity'              => $quantity,
                'product_type'          => $product_type,
                'unit_amount'           => floor($unit_amount),
                'unit_tax_amount'       => floor($unit_tax_amount),
                'total_discount_amount' => floor($total_discount_amount),
            ]);
        }

    }

    $shipping = [];
    if (isset($data['shipping'])) {
        $_shipping = $data['shipping'];
        $total_fee_tax = array_sum(
                array_map(
                    function ( WC_Order_Item_Fee $fee ): float {
                        return (float) $fee->get_total_tax();
                    },
                    $order -> get_fees()
                )
            );
        $_shipping_amount = ($order->get_shipping_total() + $order->get_shipping_tax() + $order->get_total_fees() + $total_fee_tax) * 100;
        // $_shipping_amount = ($order->get_shipping_total() + $order->get_total_tax() + $order->get_total_fees() - $_total_goods_tax_amount) * 100;

        $_shipping_country = $order->get_shipping_country();
        $_shipping_state = get_country_state_name($_shipping_country, $order->get_shipping_state());
        $shipping = [
            'amount'        => floor($_shipping_amount),
            'first_name'    => $order->get_shipping_first_name(),
            'last_name'     => $order->get_shipping_last_name(),
            'country'       => $_shipping_country,
            'state'         => $_shipping_state,
            'city'          => $order->get_shipping_city(),
            'street'        => $order->get_shipping_address_1(),
            'street2'       => $order->get_shipping_address_2(),
            'zip'           => $order->get_shipping_postcode(),               
            'type'          => 'SHIPPING', // shipping, pickup_in_person, default is shipping
        ];
    }
        
    $goods = ['data' => $goods, 'shipping' => $shipping];

    $goods = verify_and_smooth_amount($order, $goods);

    $params['goods'] = json_encode($goods);

    return $params;
}

function verify_and_smooth_amount($order, $goods) {
    /*
    case: 
    payment's amount should equal with sum(
          goods.data.unit_amount * goods.data.quantity 
        + goods.data.unit_tax_amount * goods.data.quantity 
        - goods.data.total_discount_amount
    ) 
    + goods.shipping.amount
     */

    $_amount = $order->get_total() * 100;

    $_shipping_amount = $goods['shipping']['amount'];
    $_total_unit_amount = 0;
    $_total_goods_tax_amount = 0;
    $_total_discount_amount = 0;
    foreach ($goods['data'] as $item) {
        $_total_unit_amount += $item['unit_amount'] * $item['quantity'];
        $_total_goods_tax_amount += $item['unit_tax_amount'] * $item['quantity'];
        $_total_discount_amount += $item['total_discount_amount'];
    }

    $_total_goods_amount_with_tax = $_total_unit_amount + $_total_goods_tax_amount - $_total_discount_amount;
    $_total_goods_amount = $_total_goods_amount_with_tax + $_shipping_amount;

    // smooth amount
    if ($_total_goods_amount != $_amount) {
        $goods['shipping']['amount'] = $_amount - $_total_goods_amount_with_tax;
    }

    return $goods;
}

function get_vendor_list() {
    global $cc_vendors;
    return $cc_vendors;
}

function get_form_fields() {
    $list = [];
    foreach (get_vendor_list() as $vendor) {
        $list[$vendor->method] = $vendor->get_form_fields();
    }
    return $list;
}

function get_title_list() {
    $list = [];
    foreach (get_vendor_list() as $vendor) {
        $list[$vendor->method] = $vendor->title;
    }
    return $list;
}

function get_api_url($mode, $method) {
    if ($mode === 'test') {
        return 'https://uat.citconpay.com/chop/'.$method;
    } else {
        return 'https://citconpay.com/chop/'.$method;
    }
}

function get_vendor_by($method) {
    foreach (get_vendor_list() as $vendor) {
        if ($method === $vendor -> method) {
            return $vendor;
        }
    }
    return null;
}

function has_support_currency($currency) {
    foreach (get_vendor_list() as $vendor) {
        if (in_array($currency, $vendor -> currency)) {
            return true;
        }
    }
    return false;
}
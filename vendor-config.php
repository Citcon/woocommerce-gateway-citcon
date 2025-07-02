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
    $goods = [];
    $data = [];
    
    $order_data = $order -> data;
    $factor = get_currency_unit_conversion_factor($order->get_currency());

    $_billing = $order_data['billing'];
    if (isset($_billing)) {
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

    $params['consumer[reference]'] = get_reference_code($order->get_id());

    $items = $order -> get_items(); // [WC_Order_Item_Product]

    // goods item
    if (isset($items) && count($items) > 0) {
        foreach ($items as $item) { // [WC_Order_Item_Product]
            $name = mb_substr( $item->get_name(), 0, 127 );
            $quantity =  (int) $item -> get_quantity();
            $product = $item -> get_product(); // WC_Product_Simple

            $price_without_tax = (float) $order->get_item_subtotal( $item, false );
		    $unit_amount = $price_without_tax * $factor;
            
            // physical, digital, donation
            $product_type = $product instanceof WC_Product && $product->is_virtual() ? 'digital' : 'physical';

            $sku = $product instanceof WC_Product ? $product->get_sku() : '';
            $total_tax_amount = ((float) ($item -> get_total_tax())) * $factor;
            
            $unit_tax_amount = $total_tax_amount / $quantity;
            $total_discount_amount = ((float) ($item -> get_subtotal()) - (float) ($item -> get_total())) * $factor;

            array_push($data, [
                'sku'                   => $sku,
                'name'                  => $name,
                'quantity'              => $quantity,
                'product_type'          => $product_type,
                'unit_amount'           => floor($unit_amount),
                'unit_tax_amount'       => floor($unit_tax_amount),
                'total_tax_amount'      => round($total_tax_amount),
                'total_discount_amount' => round($total_discount_amount),
            ]);
        }
    }

    // fees
    $order_fees = $order->get_fees();
    if (isset($order_fees)) {
        foreach ($order_fees as $fee) { // [WC_Order_Item_Fee]
            array_push($data, [
                'sku'                   => '',
                'name'                  => $fee -> get_name(),
                'quantity'              => 1,
                'product_type'          => 'physical',
                'unit_amount'           => round($fee -> get_amount() * $factor),
                'unit_tax_amount'       => round($fee -> get_total_tax() * $factor),
                'total_tax_amount'      => round($fee -> get_total_tax() * $factor),
                'total_discount_amount' => 0,
            ]);
        }
    }

    $goods['data'] = $data;

    // when there is an item that is not virtual, there is a shipping address
    if (has_physical_goods($order)) {
        $shipping = [];
        $_shipping = $order_data['shipping'];
        if (isset($_shipping)) {
            // $_shipping_amount = ($order->get_shipping_total() + $order->get_shipping_tax()) * $factor;

            $_shipping_amount = ($order->get_shipping_total()) * $factor;
            $_shipping_tax_amount = ($order->get_shipping_tax()) * $factor;

            $_shipping_country = $order->get_shipping_country();
            $_shipping_state = get_country_state_name($_shipping_country, $order->get_shipping_state());
            $shipping = [
                'first_name'    => $order->get_shipping_first_name(),
                'last_name'     => $order->get_shipping_last_name(),
                'amount'        => floor($_shipping_amount),
                'tax_amount'    => floor($_shipping_tax_amount),
                'phone'         => $order->get_shipping_phone() ?: null,
                'email'         => !empty($_shipping->email) ? $_shipping->email : null,
                'country'       => $_shipping_country,
                'city'          => $order->get_shipping_city(),
                'state'         => $_shipping_state,
                'street'        => $order->get_shipping_address_1(),
                'street2'       => $order->get_shipping_address_2(),
                'zip'           => $order->get_shipping_postcode(),
                'type'          => 'SHIPPING', // shipping, pickup_in_person, default is shipping
            ];

            $shipping = array_filter($shipping, function($v) { return !is_null($v); });

            $goods['shipping'] = $shipping;
        }
    }

    // $goods = verify_and_smooth_amount($order, $goods, $factor);

    $params['goods'] = json_encode($goods);

    return $params;
}

function verify_and_smooth_amount($order, $goods, $factor) {
    /*
    case:
    payment's amount should equal with:
    sum(
          goods.data.unit_amount * goods.data.quantity
        + goods.data.unit_tax_amount * goods.data.quantity
        - goods.data.total_discount_amount
    )
    + goods.shipping.amount
     */


    $order_total = round($order->get_total() * $factor);
    $order_total_tax = round($order->get_total_tax() * $factor);
    $shipping_amount = isset($goods['shipping']['amount']) ? round($goods['shipping']['amount']) : 0;

    $total_tax_sum = 0;
    foreach ($goods['data'] as $item) {
        // if (isset($item['total_tax_amount'])) {
        //     $total_tax_sum += $item['total_tax_amount'];
        // }
        
        $total_tax_sum += $item['quantity'] * $item['unit_tax_amount'];
    }

    $shipping_tax = ($order -> get_shipping_tax()) * $factor;
    $tax_diff = $order_total_tax - $total_tax_sum - $shipping_tax;

    if (abs($tax_diff) > 0 || $shipping_tax > 0) {
        $target_index = null;
        foreach ($goods['data'] as $idx => $item) {
            if ($item['quantity'] == 1) {
                $target_index = $idx;
                break;
            }
        }

        if ($target_index !== null) {
            $goods['data'][$target_index]['unit_tax_amount'] += $tax_diff + $shipping_tax;
            $goods['data'][$target_index]['total_tax_amount'] = $goods['data'][$target_index]['unit_tax_amount'];
        } else {
            // method 1:
            // insert new one item

            // method 2:    
            // combine all item which quantity > 1

            $merge_method = 2;

            if ($merge_method == 1) {
                array_push( $goods['data'], [
                    'sku' => '',
                    'name' => 'Adjustment Amount',
                    'quantity' => 1,
                    'product_type' => 'physical',
                    'unit_amount' => $tax_diff + $shipping_tax,
                    'unit_tax_amount' => 0,
                    'total_tax_amount' => 0,
                    'total_discount_amount' => 0,
                ]);
            } else {
                foreach ($goods['data'] as $idx => $item) {
                    $quantity = $item['quantity'];
                    if ($quantity > 1) {
                        $goods['data'][$idx]['unit_amount'] *= $quantity;
                        $goods['data'][$idx]['unit_tax_amount'] = $goods['data'][$idx]['total_tax_amount'];
                        $goods['data'][$idx]['quantity'] = 1;
                    }
                }

                // add shipping tax to the last item
                $last_index = count($goods['data']) - 1;
                if ($last_index >= 0) {
                    $goods['data'][$last_index]['unit_tax_amount'] += $shipping_tax;
                    $goods['data'][$last_index]['total_tax_amount'] = $shipping_tax;
                }
            }
        }

        if (isset($goods['shipping']['amount'])) {
            $goods['shipping']['amount'] -= $shipping_tax;
        }
    }

    return $goods;
}

function has_physical_goods($order) {
    $items = $order -> get_items(); // [WC_Order_Item_Product]
    if (isset($items) && count($items) > 0) {
        foreach ($items as $item) { // [WC_Order_Item_Product]
            $product = $item -> get_product(); // WC_Product_Simple
            if ($product instanceof WC_Product && !$product->is_virtual()) {
                return true;
            }
        }
    }
    return false;
}

function get_reference_code($order_id) {
    if ( is_user_logged_in() && get_current_user_id()) {
        return $order_id . 'at' . get_current_user_id();
    } else {
        $timestamp = time();
        return $order_id . 'at' . $timestamp;
    }
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

function get_currency_unit_conversion_factor($currency) {
    if (in_array($currency, ['KRW','JPY'])) {
        return 1;
    }
    return 100;
}

function has_support_currency($currency) {
    foreach (get_vendor_list() as $vendor) {
        if (in_array($currency, $vendor -> currency)) {
            return true;
        }
    }
    return false;
}
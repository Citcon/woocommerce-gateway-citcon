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


    public function __construct($data) { 
        $this->title = $data['title'];
        $this->currency = $data['currency'];
        $this->country = $data['country'];
        $this->enabled = $data['enabled'];
        $this->method = $data['method'];
        $this->checked = $data['checked'];
        $this->icon = $data['icon'];
        if (isset($data['icon_height'])) {
            $this->icon_height = $data['icon_height'];
        }

        if (isset($data['processPaymentBody'])) {
            $this->processPaymentBody = $data['processPaymentBody'];
        }

    }

    public function get_form_fields () { 
        return [ 
            'title' => __('Enable/Disable', 'woocommerce'),
            'type' => 'checkbox',
            'label' => __($this -> title, 'woocommerce'),
            'default' => 'yes'
        ];
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
        'processPaymentBody' => function ($params) {
            $params['country'] = 'US';
            $params['auto_capture'] = 'true';
            return $params;
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
        'processPaymentBody' => function ($params) {
            $params['country'] = 'US';
            $params['auto_capture'] = 'true';
            return $params;
        },
    ]),

    // new Vendor([
    //     'method' => 'cashapppay',
    //     'title' => 'Cash App Pay',
    //     'currency' => ['USD'],
    //     'country' => '',
    //     'enabled' => 'no', 
    //     'checked' => 'no',
    //     'icon' => 'images/cashapp-logo.png',
    //     'icon_height' => '22',
    //     'processPaymentBody' => function ($params) {
    //         $params['country'] = 'US';
    //         $params['auto_capture'] = 'true';
    //         return $params;
    //     },
    // ]),

];

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
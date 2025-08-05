import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { useState, useEffect } from '@wordpress/element';
import { getSetting } from '@woocommerce/settings';
import { __ } from '@wordpress/i18n';


const name = 'citconpay';
const settings = getSetting( `${ name }_data`, {} );
const DEFAULT_VENDOR = settings.selectedMethod;

const CitconPayContent = ({ selectedVendor, onChange }) => (
  <div className="wc-citconpay">
    {(!settings.vendors || settings.vendors.length === 0) ? (
      <div className="citconpay-no-vendors">
        {__('No payment methods are currently available. Please contact the store administrator.', 'woocommerce-gateway-citconpay')}
      </div>
    ) : (
        <>
            <fieldset>
                <legend>
                    {__('Method of payment', 'woocommerce-gateway-citconpay')} <span className="required">*</span>
                </legend>
                <ul className="wc_payment_methods payment_methods methods">
                    {(settings.vendors || []).map(vendor => (
                    <li className="wc_payment_method" key={vendor.method}>
                        <div className="citconpay-method-row">
                        <input
                            id={`citconpay_pay_method_${vendor.method}`}
                            className="input-radio"
                            name="citconpay_vendor"
                            value={vendor.method}
                            type="radio"
                            required
                            checked={selectedVendor === vendor.method}
                            onChange={() => onChange(vendor.method)}
                        />
                        
                        <label htmlFor={`citconpay_pay_method_${vendor.method}`}>
                            {vendor.icons && Array.isArray(vendor.icons) ? (
                            <div className="citconpay-icons" title={vendor.title}>
                                {vendor.icons.map((icon, i) => (
                                <img
                                    key={i}
                                    src={icon}
                                    className="citconpay-icon"
                                    alt={vendor.title}
                                    style={vendor.icon_height ? { height: `${vendor.icon_height}` } : undefined}
                                    />
                                ))}
                            </div>
                            ) : (
                            <img
                                src={vendor.icon}
                                className="citconpay-icon"
                                alt={vendor.title}
                                title={vendor.title}
                                style={vendor.icon_height ? { height: `${vendor.icon_height}` } : undefined}
                                />
                            )}
                            {/* <span className="citconpay-label">{vendor.title}</span> */}
                        </label>
                        </div>
                    </li>
                    ))}
                </ul>
                <div className="clear"></div>
            </fieldset>

            {selectedVendor && (
                <div className="citconpay-instructions">
                    <p>
                    {__('Pay with ', 'woocommerce-gateway-citconpay')}
                    <strong>
                        {(() => {
                        const vendorObj = (settings.vendors || []).find(v => v.method === selectedVendor);
                        return vendorObj ? vendorObj.title : '';
                        })()}
                    </strong>
                    </p>
                </div>
            )}
        </>
    )}

    
  </div>
);


const CitconPayBlock = (props) => {
  const [selectedVendor, setSelectedVendor] = useState(DEFAULT_VENDOR);

  // only one, selected it
  useEffect(() => {
    if (settings.vendors && settings.vendors.length === 1) {
      setSelectedVendor(settings.vendors[0].method);
    }
  }, []);

  const { eventRegistration, emitResponse } = props || {};
  useEffect(() => {
    if (!eventRegistration || !emitResponse) return;
    const unsubscribe = eventRegistration.onPaymentSetup(async () => {
      if (!selectedVendor) {
        return {
          type: emitResponse.responseTypes.ERROR,
          message: 'Please select a payment method.',
        };
      }
      return {
        type: emitResponse.responseTypes.SUCCESS,
        meta: {
          paymentMethodData: {
            vendor: selectedVendor,
          },
        },
      };
    });
    return () => unsubscribe && unsubscribe();
  }, [selectedVendor, eventRegistration, emitResponse]);

  return (
    <CitconPayContent
      selectedVendor={selectedVendor}
      onChange={setSelectedVendor}
    />
  );
};


registerPaymentMethod({
  name,
  label: settings.title || __('CitconPay', 'woocommerce-gateway-citconpay'),
  ariaLabel: settings.title || __('CitconPay', 'woocommerce-gateway-citconpay'),
  content: <CitconPayBlock />,
  edit: <CitconPayBlock />,
  canMakePayment: () => true,
  supports: {
    features: ['products'],
  },

  getPaymentData: () => CitconPayBlock.getPaymentData(),


});
<?php
class Billmate_Checkout_Helper_Data extends Mage_Core_Helper_Abstract
{

    const BM_CHECKOUT_ENABLED_PATH = 'payment/bmcheckout/enable';

    const BM_CHECKOUT_LOG_FILE = 'bm_connection.log';

    /**
     * @var
     */
	protected $_InvoicePriceIncludesTax;

    /**
     * @var Billmate_Connection_Helper_Data
     */
	protected $connectionHelper;


	public function __construct()
    {
        $this->connectionHelper = Mage::helper('bmconnection');
    }

    /**
     * @param bool $ssl
     * @param bool $debug
     *
     * @return Billmate
     */
    public function getBillmate()
    {
        return $this->connectionHelper->getBmProvider();
    }

    /**
     * @return bool
     */
    public function isBMCheckoutActive()
    {
        return (bool)$this->getConfigValue(self::BM_CHECKOUT_ENABLED_PATH);
    }

    /**
     * @return string
     */
	public function getMethodName()
    {
        return Billmate_Checkout_Model_Methods_Bmcheckout::METHOD_CODE;
    }

    /**
     * @return string
     */
    public function getDefaultPostcode()
    {
        $postCode = Mage::getStoreConfig('shipping/origin/postcode');
        if ($postCode) {
            return $postCode;
        }
        return self::DEF_POST_CODE;
    }

    /**
     * @return string
     */
    public function getContryId()
    {
        return  Mage::getStoreConfig('general/country/default');
    }

    /**
     * @return string
     */
    public function getDefaultShipping()
    {
        $shippingMethodCode = Mage::getStoreConfig('billmate/checkout/shipping_method');
        $allowedShippingMethods = $this->getAllowedShippingMethods();
        if (!in_array($shippingMethodCode, $allowedShippingMethods)) {
            return current($allowedShippingMethods);
        }
        return $shippingMethodCode;
    }

    /**
     * @return array
     */
    protected function getAllowedShippingMethods()
    {
        $checkoutSession = Mage::getSingleton('checkout/session');
        $shippingAddress = $checkoutSession->getQuote()->getShippingAddress();

        $shippingRates = $shippingAddress
            ->setCollectShippingRates(true)
            ->collectShippingRates()
            ->getAllShippingRates();

        foreach ($shippingRates as $rate) {
            $this->shippingRatesCodes[] = $rate->getCode();
        }

        return $this->shippingRatesCodes;
    }

    /**
     * @param        $amount
     * @param string $thousand
     * @param string $decimal
     *
     * @return mixed|string
     */
	public function replaceSeparator($amount, $thousand = '.', $decimal = ',')
    {
		return $this->convert2Decimal($amount);
	}

    /**
     * @param $amount
     *
     * @return mixed|string
     */
	public function convert2Decimal($amount)
    {
		if( empty( $amount)) {
			return '';
		}
		$dotPosition = strpos($amount, '.');
		$CommaPosition = strpos($amount, ',');
		if( $dotPosition > $CommaPosition ){
			return str_replace(',', '', $amount);
		}else{
			$data = explode(',', $amount);
			$data[1] = empty($data[1])?'':$data[1];
			$data[0] = empty($data[0])?'':$data[0];
			$p = str_replace( '.' ,'', $data[0]);
			return $p.'.'.$data[1];
		}
	}



    /**
     * @return bool
     */
    public function isOneStepCheckout()
    {
        return (bool) Mage::getStoreConfig(
            'onestepcheckout/general/rewrite_checkout_links'
        );
    }

    /**
     * @param $base
     * @param $address
     * @param $taxClassId
     *
     * @return array
     */
    public function getInvoiceFeeArray($base, $address, $taxClassId)
    {
        //Get the correct rate to use
        $store = Mage::app()->getStore();
        $calc = Mage::getSingleton('tax/calculation');
        $rateRequest = $calc->getRateRequest(
            $address, $address, $taxClassId, $store
        );
        $taxClass = (int) Mage::getStoreConfig('payment/billmatecheckout/tax_class');;
        $rateRequest->setProductClassId($taxClass);
        $rate = $calc->getRate($rateRequest);
        //Get the vat display options for products from Magento tax settings
        $VatOptions = Mage::getStoreConfig(
            "tax/calculation/price_includes_tax", $store->getId()
        );

        if ($VatOptions == 1) {
            //Catalog prices are set to include taxes
            $value = $calc->calcTaxAmount($base, $rate, false, false);
            $excl = $base;
            return array(
                'excl' => $excl,
                'base_excl' => $this->calcBaseValue($excl),
                'incl' => $base + $value,
                'base_incl' => $this->calcBaseValue($base + $value),
                'taxamount' => $value,
                'base_taxamount' => $this->calcBaseValue($value),
                'rate' => $rate
            );
        }
        //Catalog prices are set to exclude taxes
        $value = $calc->calcTaxAmount($base, $rate, false, false);
        $incl = ($base + $value);

        return array(
            'excl' => $base,
            'base_excl' => $this->calcBaseValue($base),
            'incl' => $incl,
            'base_incl' => $this->calcBaseValue($incl),
            'taxamount' => $value,
            'base_taxamount' => $this->calcBaseValue($value),
            'rate' => $rate
        );
    }
    /**
     * Try to calculate the value of the invoice fee with the base currency
     * of the store if the purchase was done with a different currency.
     *
     * @param float $value value to calculate on
     *
     * @return float
     */
    protected function calcBaseValue($value)
    {
        $baseCurrencyCode = Mage::app()->getStore()->getBaseCurrencyCode();
        $currentCurrencyCode = Mage::app()->getStore()->getCurrentCurrencyCode();
		$value = Mage::helper('directory')->currencyConvert($value,$baseCurrencyCode,$currentCurrencyCode);
	    return $value;
    }

    /**
     * @param $store
     *
     * @return int
     */
	public function getInvoiceTaxClass($store)
    {
        return (int)Mage::getStoreConfig(
            'payment/billmatecheckout/tax_class',
            $store
        );
    }

    /**
     * @param $path
     *
     * @return mixed
     */
    public function getConfigValue($path)
    {
        return  Mage::getStoreConfig($path);
    }

    /**
     * @param null $store
     *
     * @return mixed
     */
    public function InvoicePriceIncludesTax($store = null)
    {
        $storeId = Mage::app()->getStore($store)->getId();
        $this->_InvoicePriceIncludesTax[$storeId] = true;
        return $this->_InvoicePriceIncludesTax[$storeId];
    }

    /**
     * @param      $price
     * @param null $includingTax
     * @param null $shippingAddress
     * @param null $ctc
     * @param null $store
     *
     * @return float
     */
    public function getInvoicePrice($price, $includingTax = null, $shippingAddress = null, $ctc = null, $store = null)
    {
        $billingAddress = false;
        if ($shippingAddress && $shippingAddress->getQuote() && $shippingAddress->getQuote()->getBillingAddress()) {
            $billingAddress = $shippingAddress->getQuote()->getBillingAddress();
        }
        
        $calc = Mage::getSingleton('tax/calculation');
        $taxRequest = $calc->getRateRequest(
            $shippingAddress,
            $billingAddress,
            $shippingAddress->getQuote()->getCustomerTaxClassId(),
            $store
        );
        $taxRequest->setProductClassId($this->getInvoiceTaxClass($store));
        $rate = $calc->getRate($taxRequest);
        $tax = $calc->calcTaxAmount($price, $rate, $this->InvoicePriceIncludesTax($store), true);
        
        if ($this->InvoicePriceIncludesTax($store)) {
            return $includingTax ? $price : $price - $tax;
        } else {
            return $includingTax ? $price + $tax : $price;
        }
    }

    /**
     * @param null $store
     *
     * @return int
     */
    public function getInvoiceFeeDisplayType($store = null)
    {
        $storeId = Mage::app()->getStore($store)->getId();
        return $this->_shippingPriceDisplayType[$storeId] = Mage_Tax_Model_Config::DISPLAY_TYPE_BOTH;
    }

    /**
     * @return bool
     */
    public function displayInvoiceFeeIncludingTax()
    {
        return $this->getInvoiceFeeDisplayType() == Mage_Tax_Model_Config::DISPLAY_TYPE_INCLUDING_TAX;
    }

    /**
     * @return bool
     */
    public function displayInvoiceFeeExcludingTax()
    {
        return $this->getInvoiceFeeDisplayType() == Mage_Tax_Model_Config::DISPLAY_TYPE_EXCLUDING_TAX;
    }

    /**
     * @return bool
     */
    public function displayInvoiceBothPrices()
    {
        return $this->getInvoiceFeeDisplayType() == Mage_Tax_Model_Config::DISPLAY_TYPE_BOTH;
    }

    /**
     * @return bool
     */
    public function isAllowedBackEvents()
    {
        return $this->connectionHelper->isPushEvents();
    }

    const DEF_POST_CODE = '12345';

    /**
     * @var array
     */
    protected $bundleArr = array();

    /**
     * @var int
     */
    protected $totalValue = 0;

    /**
     * @var int
     */
    protected $totalTax = 0;

    /**
     * @var array
     */
    protected $discounts = array();

    /**
     * @var array
     */
    protected $paymentMethodMap = [
        1 => 'billmateinvoice',
        4 => 'billmatepartpayment',
        8 => 'billmatecardpay',
        16 => 'billmatebankpay'
    ];

    /**
     * @var array
     */
    protected $shippingRatesCodes = [];

    /**
     * @param $eid
     * @param $secret
     *
     * @return bool
     */
    public function verifyCredentials($eid,$secret)
    {
        $billmate = new Billmate_Billmate($eid, $secret, true, false,false);

        $additionalinfo['PaymentData'] = array(
            "currency"=> 'SEK',//SEK
            "country"=> 'se',//Sweden
            "language"=> 'sv',//Swedish
        );

        $result = $billmate->GetPaymentPlans($additionalinfo);
        if(isset($result['code']) && $result['code'] == '9013'){
            return false;
        }
        return true;

    }

    /**
     * @param $pno
     *
     * @return mixed
     */
    public function getAddress($pno)
    {
        $billmate = $this->getBillmate();

        $values = array(
            'pno' => $pno
        );

        return $billmate->getAddress($values);
    }

    /**
     * @param $quote
     *
     * @return array
     */
    public function prepareArticles($quote)
    {
        $bundleArr     = array();
        $totalValue    = 0;
        $totalTax      = 0;
        $discountAdded = false;
        $configSku     = false;
        $discounts     = array();
        foreach ($quote->getAllItems() as $_item) {
            if (in_array($_item->getParentItemId(), $bundleArr)) {
                continue;
            }

            if ($_item->getProductType() == 'bundle' && $_item->getProduct()->getPriceType() == 1) {
                $bundleArr[] = $_item->getId();
            }

            if ($_item->getProductType() == 'configurable') {
                $configSku = $_item->getSku();
                $cp = $_item->getProduct();
                $sp = Mage::getModel('catalog/product')->loadByAttribute('sku', $_item->getSku());

                $price = $_item->getCalculationPrice();
                $percent = $_item->getTaxPercent();

                $discount = 0.0;
                $discountAmount = 0;

                $total = ($discountAdded) ? (int)round((($price * $_item->getQty() - $discountAmount) * 100)) : (int)round($price * 100) * $_item->getQty();
                $article[] = array(
                    'quantity' => (int)$_item->getQty(),
                    'artnr' => $_item->getProduct()->getSKU(),
                    'title' => addslashes($cp->getName() . ' - ' . $sp->getName()),
                    'aprice' => (int)round($price * 100, 0),
                    'taxrate' => (float)$percent,
                    'discount' => $discount,
                    'withouttax' => $total
                );

                $temp = $total;
                $totalValue += $temp;
                $totalTax += $temp * ($percent / 100);
                if (isset($discounts[$percent])) {
                    $discounts[$percent] += $temp;
                } else {
                    $discounts[$percent] = $temp;
                }

            }
            if ($_item->getSku() == $configSku) {
                continue;
            }

            if ($_item->getProductType() == 'bundle' && $_item->getProduct()->getPriceType() == 0) {

                $percent = $_item->getTaxPercent();
                $article[] = array(
                    'quantity' => (int)$_item->getQty(),
                    'artnr' => $_item->getProduct()->getSKU(),
                    'title' => addslashes($_item->getName()),
                    // Dynamic pricing set price to zero
                    'aprice' => (int)0,
                    'taxrate' => (float)$percent,
                    'discount' => 0.0,
                    'withouttax' => (int)0

                );
            } else {
                $percent = $_item->getTaxPercent();
                $price = $_item->getCalculationPrice();
                $discount = 0.0;
                $discountAmount = 0;
                $parentItem = $_item->getParentItem();
                if ($parentItem) {
                    $qty = $parentItem->getQty();
                } else {
                    $qty = $_item->getQty();
                }

                $total = ($discountAdded) ? (int)round((($price * $qty - $discountAmount) * 100)) : (int)round($price * 100) * $qty;
                $article[] = array(
                    'quantity' => (int)$qty,
                    'artnr' => $_item->getProduct()->getSKU(),
                    'title' => addslashes($_item->getName()),
                    'aprice' => (int)round($price * 100, 0),
                    'taxrate' => (float)$percent,
                    'discount' => $discount,
                    'withouttax' => $total

                );
                $temp = $total;
                $totalValue += $temp;
                $totalTax += $temp * ($percent / 100);
                if (isset($discounts[$percent])) {
                    $discounts[$percent] += $temp;
                } else {
                    $discounts[$percent] = $temp;
                }
            }
        }
        $totals = $quote->getTotals();

        if (isset($totals['discount'])) {
            foreach ($discounts as $percent => $amount)
            {
                $discountPercent           = $amount / $totalValue;
                $floor                     = 1 + ($percent / 100);
                $marginal                  = 1 / $floor;
                $discountAmount            = $discountPercent * $totals['discount']->getValue();
                $article[] = array(
                    'quantity'   => (int) 1,
                    'artnr'      => 'discount',
                    'title'      => Mage::helper('payment')->__('Discount') . ' ' . $this->__('%s Vat', $percent),
                    'aprice'     => round(($discountAmount * $marginal) * 100),
                    'taxrate'    => (float) $percent,
                    'discount'   => 0.0,
                    'withouttax' => round(($discountAmount * $marginal) * 100),

                );
                $totalValue                += (1 * round($discountAmount * $marginal * 100));
                $totalTax                  += (1 * round(($discountAmount * $marginal) * 100) * ($percent / 100));
            }
        }

        return array(
            'articles' => $article,
            'totalValue' => $totalValue,
            'totalTax' => $totalTax,
            'discounts' => $discounts
        );
    }

    /**
     * @return string
     */
    public function getTermsUrl()
    {
        $termsPageId = Mage::getStoreConfig('billmate/checkout/terms_page');
        $termPageUrl = Mage::helper('cms/page')->getPageUrl($termsPageId);
        return $termPageUrl;
    }

    /**
     * @return mixed
     */
    public function getPrivacyUrl()
    {
        $privacyPolicyPageId = Mage::getStoreConfig('billmate/checkout/privacy_policy_page');
        $privacyPolicyPageUrl= Mage::helper('cms/page')->getPageUrl($privacyPolicyPageId);
        return $privacyPolicyPageUrl;
    }

    /**
     *
     * @return string
     */
    public function getPaymentMethodCode()
    {
        return Billmate_Checkout_Model_Methods_Bmcheckout::METHOD_CODE;
    }

    /**
     * @param $billmateStatus
     *
     * @return string
     */
    public function getAdaptedStatus($billmateStatus)
    {
        return strtolower($billmateStatus);
    }

    /**
     * @return string
     */
    public function getBillmateCheckoutOrderStatus()
    {
        return Mage::getStoreConfig('payment/billmatecheckout/order_status');
    }

    /**
     * @param $message
     */
    public function addLog($message)
    {
       $this->connectionHelper->addLog($message, self::BM_CHECKOUT_LOG_FILE);
    }
}

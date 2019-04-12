<?php
class Billmate_Checkout_Helper_Data extends Mage_Core_Helper_Abstract
{
    const BM_CHECKOUT_ENABLED_PATH = 'payment/bmcheckout/enable';

    const BM_CHECKOUT_TAX_CLASS_PATH = 'payment/bmcheckout/tax_class';

    const BM_CHECKOUT_TERMS_PAGE_PATH = 'payment/bmcheckout/terms_page';

    const BM_CHECKOUT_ORDER_STATUS_PATH = 'payment/bmcheckout/order_status';

    const BM_CHECKOUT_PRIVACY_POLICY_PATH = 'payment/bmcheckout/privacy_policy_page';

    const BM_CHECKOUT_SHIPPING_METHOD_PATH = 'payment/bmcheckout/shipping_method';

    const GENERAL_COUNTRY_ID_PATH = 'general/country/default';

    const GENERAL_SIPPING_POSTCODE_PATH = 'shipping/origin/postcode';

    const BM_CHECKOUT_LOG_FILE = 'bm_connection.log';

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
    protected $shippingRatesCodes = [];

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
        $postCode = $this->getConfigValue(self::GENERAL_SIPPING_POSTCODE_PATH);
        if ($postCode) {
            return $postCode;
        }
        return self::DEF_POST_CODE;
    }

    /**
     * @return int
     */
    public function getContryId()
    {
        return $this->getConfigValue(self::GENERAL_COUNTRY_ID_PATH);
    }

    /**
     * @return int
     */
    public function getCheckoutTaxClass()
    {
        return (int)$this->getConfigValue(self::BM_CHECKOUT_TAX_CLASS_PATH);
    }

    /**
     * @return string
     */
    public function getDefaultShipping()
    {
        $shippingMethodCode = $this->getConfigValue(self::BM_CHECKOUT_SHIPPING_METHOD_PATH);
        $allowedShippingMethods = $this->getAllowedShippingMethods();
        if (!in_array($shippingMethodCode, $allowedShippingMethods)) {
            return current($allowedShippingMethods);
        }
        return $shippingMethodCode;
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
        $taxClass = $this->getCheckoutTaxClass();
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
    public function replaceSeparator($amount)
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

    /**
     * @param $quote
     *
     * @return array
     */
    public function prepareArticles($quote)
    {
        return $this->connectionHelper->prepareArticles($quote);
    }

    /**
     * @return string
     */
    public function getTermsUrl()
    {
        $termsPageId = $this->getConfigValue(self::BM_CHECKOUT_TERMS_PAGE_PATH);
        $termPageUrl = Mage::helper('cms/page')->getPageUrl($termsPageId);
        return $termPageUrl;
    }

    /**
     * @return mixed
     */
    public function getPrivacyUrl()
    {
        $privacyPolicyPageId = $this->getConfigValue(self::BM_CHECKOUT_PRIVACY_POLICY_PATH);
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
        return $this->getConfigValue(self::BM_CHECKOUT_ORDER_STATUS_PATH);
    }

    /**
     * @param $message
     */
    public function addLog($message)
    {
       $this->connectionHelper->addLog($message, self::BM_CHECKOUT_LOG_FILE);
    }

    /**
     * @return string | null
     */
    public function getBillmateHash()
    {
        return Mage::getSingleton('checkout/session')->getBillmateHash();
    }
}

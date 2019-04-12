<?php

class Billmate_Checkout_Model_Checkout extends Billmate_Checkout_Model_Payment_GatewayCore
{
    const METHOD_CODE = 93;

    public function getBmIframeUrl()
    {
        $billmateHash = $this->getHelper()->getBillmateHash();
        try {
            if (!$billmateHash) {
                $bmResponse = $this->init();
                if (isset($bmResponse['code'])) {
                    throw new Exception(
                        $bmResponse['message']
                    );
                }
                $this->getCheckoutSession()->setBillmateInvoiceId($bmResponse['number']);
                $bmIframeUrl = $bmResponse['url'];
            } else {
                $bmConnection = $this->getBMConnection();
                $bmResponse = $bmConnection->getCheckout(
                    ['PaymentData' => ['hash' => $billmateHash]]
                );
                $quote = $this->getCheckoutSession()->getQuote();
                $total = $quote->getGrandTotal() * 100;

                if (isset($bmResponse['code'])) {
                    throw new Exception(
                        $bmResponse['message']
                    );
                }

                if (
                    isset($bmResponse['Cart']['Total']['withtax'])
                    && $bmResponse['Cart']['Total']['withtax'] != $total
                ) {
                    $updateResponse = $this->updateCheckout();
                    if (!isset($updateResponse['data']['code'])) {
                        $bmResponse = $bmConnection->getCheckout(
                            ['PaymentData' => ['hash' => $billmateHash]]
                        );
                    }
                }

                $bmIframeUrl = $bmResponse['PaymentData']['url'];
            }
        }catch (Exception $e) {
            $this->getCheckoutSession()->addError($e->getMessage());
            $bmIframeUrl = '';
        }
        return $bmIframeUrl;
    }

    /**
     * @return array
     */
    public function init()
    {
        $quote = $this->getQuote();

        $orderValues = array();
        $orderValues['CheckoutData'] = array(
            'windowmode' => 'iframe',
            'sendreciept' => 'yes'
        );

        $termsUrl = $this->helper->getTermsUrl();
        if ($termsUrl) {
            $orderValues['CheckoutData']['terms'] = $termsUrl;
        }

        $privacyPolicyUrl = $this->helper->getPrivacyUrl();
        if ($privacyPolicyUrl) {
            $orderValues['CheckoutData']['privacyPolicy'] = $privacyPolicyUrl;
        }

        if (!$quote->getReservedOrderId()) {
            $quote->reserveOrderId();
        }

        $orderValues['PaymentData'] = $this->getPaymentData();
        $callbackUrls = $this->getCallbackUrls();
        $orderValues['PaymentData'] = array_merge($orderValues['PaymentData'],$callbackUrls);
        $orderValues['PaymentData']['returnmethod'] = (Mage::app()->getStore()->isCurrentlySecure()) ? 'POST' : 'GET';

        $preparedArticle = $this->helper->prepareArticles($quote);

        $totalTax = $preparedArticle['totalTax'];
        $totalValue = $preparedArticle['totalValue'];
        $orderValues['Articles'] = $preparedArticle['articles'];

        $shippingCostData = $this->getShippingCostData();
        if ($shippingCostData) {
            $orderValues['Cart']['Shipping'] = $shippingCostData;
            $totalValue += $shippingCostData['withouttax'];
            $totalTax += ($shippingCostData['withouttax']) * ($shippingCostData['taxrate'] / 100);
        }

        $shippingHandData = $this->getShippingHandData();
        if ($shippingHandData) {
            $orderValues['Cart']['Handling'] = $shippingHandData;
            $totalValue += $shippingHandData['withouttax'];
            $totalTax += ($shippingHandData['withouttax']) * ($shippingHandData['taxrate'] / 100);
        }

        $round = round($quote->getGrandTotal() * 100) - round($totalValue +  $totalTax);

        $orderValues['Cart']['Total'] = array(
            'withouttax' => round($totalValue),
            'tax' => round($totalTax),
            'rounding' => round($round),
            'withtax' =>round($totalValue + $totalTax +  $round)
        );

        $billmateConnection = $this->getBMConnection();
        $result = $billmateConnection->initCheckout($orderValues);

        if (!isset($result['code'])) {
            $url = $result['url'];
            $parts = explode('/',$url);
            $sum = count($parts);
            $hash = ($parts[$sum-1] == 'test') ? str_replace('\\','',$parts[$sum-2]) : str_replace('\\','',$parts[$sum-1]);
            $quote->setBillmateHash($hash);
            $quote->save();
            Mage::getSingleton('checkout/session')->setBillmateHash($hash);
        }
        return $result;

    }

    /**
     * @return array
     */
    public function updateCheckout()
    {
        $billmateConnection = $this->getBMConnection();
        $quote = $this->getQuote();

        $orderValues = $billmateConnection->getCheckout(array('PaymentData' => array('hash' => Mage::getSingleton('checkout/session')->getBillmateHash())));

        $previousTotal = $orderValues['Cart']['Total']['withtax'];

        if (!$quote->getReservedOrderId()) {
            $quote->reserveOrderId();
        }

        if(!isset($orderValues['PaymentData']) || (isset($orderValues['PaymentData']) && !is_array($orderValues['PaymentData']))) {
            $orderValues['PaymentData'] = array();
        }
        $paymentData = $this->getPaymentData();
        $orderValues['PaymentData']['currency'] = $paymentData['currency'];
        $orderValues['PaymentData']['language'] = $paymentData['language'];
        $orderValues['PaymentData']['country'] = $paymentData['country'];
        $orderValues['PaymentData']['orderid'] = $paymentData['orderid'];

        unset($orderValues['Articles']);
        
        $preparedArticle = Mage::helper('bmcheckout')->prepareArticles($quote);
        $totalTax = $preparedArticle['totalTax'];
        $totalValue = $preparedArticle['totalValue'];
        $orderValues['Articles'] = $preparedArticle['articles'];

        unset($orderValues['Cart']['Shipping']);
        unset($orderValues['Cart']['Handling']);
        unset($orderValues['Customer']);

        $shippingCostData = $this->getShippingCostData();
        if ($shippingCostData) {
            $orderValues['Cart']['Shipping'] = $shippingCostData;
            $totalValue += $shippingCostData['withouttax'];
            $totalTax += ($shippingCostData['withouttax']) * ($shippingCostData['taxrate'] / 100);
        }

        $shippingHandData = $this->getShippingHandData();
        if ($shippingHandData) {
            $orderValues['Cart']['Handling'] = $shippingHandData;
            $totalValue += $shippingHandData['withouttax'];
            $totalTax += ($shippingHandData['withouttax']) * ($shippingHandData['taxrate'] / 100);
        }

        $round = round($quote->getGrandTotal() * 100) - round($totalValue +  $totalTax);

        $orderValues['Cart']['Total'] = array(
            'withouttax' => round($totalValue),
            'tax' => round($totalTax),
            'rounding' => round($round),
            'withtax' =>round($totalValue + $totalTax +  $round)
        );

        $result = $billmateConnection->updateCheckout($orderValues);
        if ($previousTotal != $orderValues['Cart']['Total']['withtax']) {
            $result['update_checkout'] = true;
            $result['data'] = $orderValues;
        } else {
            $result['update_checkout'] = false;
            $result['data'] = array();

        }
        return $result;
    }

    /**
     * @return array
     */
    protected function getCallbackUrls()
    {
        $quote = $this->getQuote();
        $urls = [
            'accepturl' => Mage::getUrl('billmatecheckout/callback/accept',[
                '_query' => ['billmate_checkout' => true,'billmate_quote_id' => $quote->getId()],
                '_secure' => true
            ]),
            'cancelurl' => Mage::getUrl('billmatecheckout/callback/cancel', array('_secure' => true)),
            'callbackurl' => Mage::getUrl('billmatecheckout/callback/callback',[
                '_query' => ['billmate_quote_id' => $quote->getId(),'billmate_checkout' => true],
                '_secure' => true
            ])
        ];
        return $urls;
    }

    /**
     * @return Mage_Core_Model_Message_Collection
     */
    public function getMessages()
    {
        return $this->getCheckoutSession()->getMessages(true);
    }
}
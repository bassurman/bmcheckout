<?php

class Billmate_Checkout_Block_Checkout extends Mage_Core_Block_Template
{
    /**
     * @return string
     */
    public function getCheckoutUrl()
    {
        if (Mage::getSingleton('checkout/session')->getBillmateHash()) {
            $billmate = Mage::helper('bmcheckout')->getBillmate();
            $checkout = $billmate->getCheckout(array('PaymentData' => array('hash' => Mage::getSingleton('checkout/session')->getBillmateHash())));
            $quote = Mage::getSingleton('checkout/session')->getQuote();
            $total = $quote->getGrandTotal();
            if ($checkout['Cart']['Total']['withtax'] != $total) {
                $result = Mage::getModel('bmcheckout/checkout')->updateCheckout();
                if(!isset($result['data']['code'])){
                    $checkout = $billmate->getCheckout(array('PaymentData' => array('hash' => Mage::getSingleton('checkout/session')->getBillmateHash())));
                }
            }
            if(!isset($checkout['code'])){
                return $checkout['PaymentData']['url'];
            }
        } else {
            $checkout = Mage::getModel('bmcheckout/checkout')->init();
            Mage::getSingleton('checkout/session')->setBillmateInvoiceId($checkout['number']);
            Mage::log('checkout'.print_r($checkout,true));
            if(!isset($checkout['code'])){
                return $checkout['url'];
            }
        }
    }
}
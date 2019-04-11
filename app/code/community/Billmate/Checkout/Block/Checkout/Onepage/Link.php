<?php

class Billmate_Checkout_Block_Checkout_Onepage_Link extends Mage_Checkout_Block_Onepage_Link
{

    /**
     * @return string
     */
    public function getCheckoutUrl()
    {
        /** @var @ $helper Billmate_Checkout_Helper_Url*/
        $helper = Mage::helper('bmcheckout/url');
        if ($helper->isBMCheckoutActive()) {
            return $helper->getBMCheckoutUrl();
        }else{
            return parent::getCheckoutUrl();
        }
    }
}
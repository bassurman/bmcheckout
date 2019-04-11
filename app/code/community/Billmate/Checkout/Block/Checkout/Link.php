<?php

class Billmate_Checkout_Block_Checkout_Link extends Mage_Core_Block_Template
{
    /**
     * @return string
     */
    public function getCheckoutUrl()
    {
        return Mage::helper('bmcheckout/url')->getBMCheckoutUrl();
    }

}
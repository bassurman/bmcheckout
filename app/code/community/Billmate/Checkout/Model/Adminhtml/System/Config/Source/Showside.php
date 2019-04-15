<?php
class Billmate_Checkout_Model_Adminhtml_System_Config_Source_Showside
{
    /**
     * @return array|null
     */
    public function toOptionArray()
    {
        $options = [
          '0' => $this->getHelper()->__('Left Side'),
          '1' => $this->getHelper()->__('Right Side')
        ];
        return $options;
    }

    /**
     * @return Billmate_Checkout_Helper_Data
     */
    protected function getHelper()
    {
        return Mage::helper('bmcheckout');
    }

}
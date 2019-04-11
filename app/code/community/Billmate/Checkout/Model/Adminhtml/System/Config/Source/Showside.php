<?php
class Billmate_Checkout_Model_Adminhtml_System_Config_Source_Showside
{
    /**
     * @return array|null
     */
    public function toOptionArray()
    {
        $options = [
          'left_side' => $this->getHelper()->__('Left Side'),
          'right_side' => $this->getHelper()->__('Right Side')
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
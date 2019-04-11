<?php

class Billmate_Checkout_Model_Adminhtml_System_Config_Source_Country extends Mage_Adminhtml_Model_System_Config_Source_Country
{
    protected $allowedCountries = ['SE'];
    /**
     * @param bool $isMultiselect
     *
     * @return mixed
     */
    public function toOptionArray($isMultiselect=false)
    {
        if (!$this->_options) {
            $this->_options = Mage::getResourceModel('directory/country_collection')->loadData()->toOptionArray(false);
        }

        $options = $this->_options;
        if (!$isMultiselect) {
            array_unshift($options, array(
                'value'=>'',
                'label'=> Mage::helper('adminhtml')->__('--Please Select--')
                )
            );
        }
        foreach ($options as $key => $col ) {
            if (!in_array( $col['value'], $this->allowedCountries)) {
                unset($options[$key]);
            }
        }
        return $options;
    }
}

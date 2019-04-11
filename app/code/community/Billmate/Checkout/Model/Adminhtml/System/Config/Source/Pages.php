<?php

class Billmate_Checkout_Model_Adminhtml_System_Config_Source_Pages
{
    protected $pages;

    /**
     * @return array|null
     */
    public function toOptionArray()
    {
        if (is_null($this->pages)) {
            $cms_pages = $this->getCMSPagesCollection();
            $pages = array();
            foreach($cms_pages as $page) {
                $pages[$page->getPageId()] = $page->getTitle();
            }
            $this->pages = $pages;
        }
        return $this->pages;
    }

    /**
     * @return object
     */
    protected function getCMSPagesCollection()
    {
        return Mage::getModel('cms/page')->getCollection();
    }
}

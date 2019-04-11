<?php

require_once Mage::getModuleDir('controllers', 'Mage_Checkout') . DS . 'OnepageController.php';

class Billmate_Checkout_OnepageController extends Mage_Checkout_OnepageController
{
    /**
     * @var Billmate_Checkout_Helper_Data
     */
    protected $helper;

    public function __construct(
        Zend_Controller_Request_Abstract $request,
        Zend_Controller_Response_Abstract $response,
        array $invokeArgs = array()
    ) {
        $this->helper = Mage::helper('bmcheckout');
        parent::__construct($request, $response, $invokeArgs);
    }

    public function indexAction()
    {
        if ($this->getHelper()->isBMCheckoutActive()) {
            return $this->_redirect('billmatecheckout', array('_secure'=>true));
        }
        parent::indexAction();
    }

    /**
     * @return Billmate_Checkout_Helper_Data
     */
    public function getHelper()
    {
        return $this->helper;
    }
}

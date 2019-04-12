<?php
require_once Mage::getModuleDir('controllers', 'Mage_Checkout') . DS . 'OnepageController.php';
$pathCustomPay = Mage::getModuleDir('controllers', 'Billmate_CustomPay') . DS . 'OnepageController.php';
if (file_exists($pathCustomPay)) {
    require_once $pathCustomPay;
    abstract class Billmate_Checkout_Adapter extends Billmate_CustomPay_OnepageController{}
} else {
    abstract class Billmate_Checkout_Adapter extends Mage_Checkout_OnepageController{}
}

class Billmate_Checkout_OnepageController extends Billmate_Checkout_Adapter
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
            return $this->_forward('index','index','billmatecheckout');
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

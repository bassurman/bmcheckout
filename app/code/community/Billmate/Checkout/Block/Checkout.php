<?php

class Billmate_Checkout_Block_Checkout extends Mage_Core_Block_Template
{
    /**
     * @var false|Billmate_Checkout_Model_Checkout
     */
    protected $bmCheckoutModel;

    public function __construct(array $args = array())
    {
        parent::__construct($args);
        $this->bmCheckoutModel = Mage::getModel('bmcheckout/checkout');
    }

    /**
     * @return string
     */
    public function getBmIframeUrl()
    {
        return $this->getBmCheckoutModel()->getBmIframeUrl();
    }

    /**
     * @return string
     */
    public function getMessagesBlock()
    {
        $messages = $this->getBmCheckoutModel()->getMessages();
        $messageBlock = $this->getLayoutBlockMessage();

        foreach ($messages->getItems() as $_message) {
            $messageBlock->addMessage($_message);
        }

        return $messageBlock->toHtml();
    }

    /**
     * @return Mage_Core_Block_Messages
     */
    protected function getLayoutBlockMessage()
    {
        $messageBlock = $this->getLayout()->createBlock(
            'core/messages','billmate_messages'
        );
        return $messageBlock;
    }

    /**
     * @return Billmate_Checkout_Model_Checkout|false
     */
    public function getBmCheckoutModel()
    {
        return $this->bmCheckoutModel;
    }

    /**
     * @return string
     */
    public function getJsConfig()
    {
        $secureParams = [
            '_secure' => true
        ];
        $configs = [
           'address_url' => $this->getUrl('billmatecheckout/index/updateaddress',$secureParams),
           'payment_url' => $this->getUrl('billmatecheckout/index/updatepaymentmethod',$secureParams),
           'shipping_url' => $this->getUrl('billmatecheckout/index/updateshippingmethod',$secureParams),
           'order_url' => $this->getUrl('billmatecheckout/index/createorder',$secureParams),
           'totals_url' => $this->getUrl('billmatecheckout/index/updatetotals',$secureParams),
           'discount_url' => $this->getUrl('billmatecheckout/index/setdiscount',$secureParams),
           'checkout_url' => $this->getUrl('billmatecheckout',$secureParams),
        ];
        return json_encode($configs);
    }
}
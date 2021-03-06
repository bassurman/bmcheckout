<?php
class Billmate_Checkout_Model_Checkout_Order extends Varien_Object
{
    /**
     * @var null | Mage_Sales_Model_Order
     */
    protected $_order = null;

    /**
     * @var null|Mage_Sales_Model_Quote
     */
    protected $_quote = null;

    /**
     * @var array
     */
    protected $bmRequestData;

    /**
     * @var Billmate_Checkout_Helper_Data
     */
    protected $helper;


    public function __construct()
    {
        $this->helper = Mage::helper('bmcheckout');
    }

    /**
     * @param $quote Mage_Sales_Model_Quote
     *
     * @return bool|false|Mage_Sales_Model_Order
     */
    public function place($quote = null)
    {
        if (is_null($quote)) {
            $quote = $this->getActualQuote();
        }

        /** @var  $quote Mage_Sales_Model_Quote */
        $orderModel = Mage::getModel('sales/order');
        $orderModel->load($quote->getId(), 'quote_id');
        if ($orderModel->getId()) {
            $this->_quote = $quote;
            $this->_order = $orderModel;
            return $orderModel;
        }
        $quote->collectTotals();
        $service = Mage::getModel('sales/service_quote',$quote);
        $service->submitAll();
        Mage::getSingleton('checkout/session')->setLastQuoteId($quote->getId())
            ->setLastSuccessQuoteId($quote->getId())
            ->clearHelperData();
        $order = $service->getOrder();
        if($order){
            Mage::getSingleton('checkout/session')->setLastOrderId($order->getId())
                ->setLastRealOrderId($order->getIncrementId());

        }
        $quote->setIsActive(false)->save();
        if ($order) {
            $this->_quote = $quote;
            $this->_order = $order;
            return $order;
        }
        return false;
    }

    /**
     * @param $paymentMethodStatus
     * @param $data
     *
     * @return bool
     */
    public function updateOrder($paymentMethodStatus, $data)
    {
        $order = $this->getOrder();
        $quote = $this->getQuote();
        $redirectToSuccess = false;
        $order->addStatusHistoryComment(
            Mage::helper('payment')->__(
                'Order processing completed <br/> Billmate status: %s <br/>Transaction ID: %s', $data['status'], $data['number']
            ));
        $order->setState('new', $paymentMethodStatus, '', false);
        $order->setCustomerIsGuest(($quote->getCustomerId() == NULL) ? 1 : 0);
        $order->save();
        if ($order->getStatus() != $paymentMethodStatus) {
            $this->addTransaction($order, $data);
            $this->sendNewOrderMail($order);
        } else {
            $redirectToSuccess = true;
        }
        return $redirectToSuccess;
    }

    /**
     * @param $order
     * @param $data
     */
    public function addTransaction($order, $data)
    {
        $payment = $order->getPayment();
        $info = $payment->getMethodInstance()->getInfoInstance();
        $info->setAdditionalInformation('', $data['number']);

        $payment->setTransactionId($data['number']);
        $payment->setIsTransactionClosed(0);
        $transaction = $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH, null, false, false);
        $transaction->setOrderId($order->getId())->setIsClosed(0)->setTxnId($data['number'])->setPaymentId($payment->getId())
            ->save();
        $payment->save();
    }

    /**
     * @param $order
     */
    public function sendNewOrderMail($order)
    {
        $magentoVersion = Mage::getVersion();
        $isEE = Mage::helper('core')->isModuleEnabled('Enterprise_Enterprise');
        if (version_compare($magentoVersion, '1.9.1', '>=') && !$isEE)
            $order->queueNewOrderEmail();
        else
            $order->sendNewOrderEmail();
    }

    /**
     * @return Mage_Sales_Model_Quote
     */
    protected function getActualQuote()
    {
        $bmRequestData = $this->getBmRequestData();
        $method = $this->helper->getPaymentMethodCode();

        $quote = $this->getQuote();
        $quote->setIsBmCheckout(true);
        $quote->getPayment()->importData(['method' => $method]);
        $quote->getPayment()->setAdditionalInformation(
            Billmate_Checkout_Model_Methods_Bmcheckout::BM_ADDITIONAL_INFO_CODE,
            $bmRequestData['PaymentData']['method_name']
        );
        $quote->getPayment()->setAdditionalInformation(
            Billmate_Checkout_Model_Methods_Bmcheckout::BM_INVOICE_NUMBER_CODE_PARAM,
            $bmRequestData['invoice_number']
        );
        $quote->save();
        return $quote;
    }

    /**
     * @return Mage_Sales_Model_Order|null
     */
    public function getOrder()
    {
        return $this->_order;
    }

    /**
     * @param $quote
     *
     * @return $this
     */
    public function setQuote($quote)
    {
        $this->_quote = $quote;
        return $this;
    }

    /**
     * @return Mage_Sales_Model_Quote|null
     */
    public function getQuote()
    {
        return $this->_quote;
    }

    /**
     * @param $requestData
     *
     * @return $this
     */
    public function setBmRequestData($requestData)
    {
        $this->bmRequestData = $requestData;
        return $this;
    }

    public function getBmRequestData()
    {
        return $this->bmRequestData;
    }
}
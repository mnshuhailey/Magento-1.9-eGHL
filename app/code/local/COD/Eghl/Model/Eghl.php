<?php
class COD_Eghl_Model_Eghl extends Mage_Payment_Model_Method_Abstract {
	
	const CGI_URL = 'https://securepay.e-ghl.com/IPG/payment.aspx';
    const CGI_URL_TEST = 'https://test2pay.ghl.com/IPGSG/Payment.aspx';
	
	protected $_code = 'Eghl';
	protected $_canCapture = true;
	protected $_formBlockType = 'Eghl/form';
	protected $_isInitializeNeeded      = true;	
	protected $_allowCurrencyCode = array('MYR','SGD','THB','CNY','PHP','INR','IDR','USD','EUR','AUD','NZD','GBP','JPY','KRW','VND','HKD','TWD','BND');
	/*
    Currency Code Currency
        MYR Malaysia Ringgit
        SGD Singapore Dollar
        THB Thai Baht
        CNY China Yuan (Ren Min Bi)
        PHP Philippine Peso
    */
    public function getUrl()
    {
    	$url = $this->getConfigData('cgi_url');
    	
    	if($url == '0')
    	{
    		$url = self::CGI_URL;
    	}
		else if($url == '1')
		{
			$url = self::CGI_URL_TEST;
		}
    	
    	return $url;
    }//end function getUrl
	
	public function getSession()
    {
        return Mage::getSingleton('Eghl/Eghl_session');
    }//end function getSession
	
	public function getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }//end function getCheckout
	
	public function getQuote()
    {
        return $this->getCheckout()->getQuote();
    }//end function getQuote
	
	public function getCheckoutFormFields($order_id=Null,$multiShipping=false)
	{
		$order = Mage::getSingleton('sales/order');
		if(is_null($order_id)){
			$order_id = $this->getCheckout()->getLastRealOrderId();
		}
		$order->loadByIncrementId($order_id);
		
		$currency_code = $order->getBaseCurrencyCode();
		//$currency_code =  Mage::app()->getStore()->getCurrentCurrencyCode();
        
		if($multiShipping){
			$SiblingsTotalAmount = 0;
			$hasSiblings = false;
			$commentsObject = $order->getStatusHistoryCollection(true);
 
			foreach ($commentsObject as $commentObj) {
				$comment = $commentObj->getComment();
				if(strpos($comment,'Siblings: ')!==False){
					$hasSiblings = true;
					$sibling_ids = explode('#',str_replace('Siblings: ','',$comment));
					break;
				}
			}
			$s_order = Mage::getSingleton('sales/order');
			foreach($sibling_ids as $sibling_id){
				$s_order->load($sibling_id);
				$SiblingsTotalAmount += floatval($s_order->getGrandTotal());
			}
			$order = Mage::getSingleton('sales/order');
			$order->loadByIncrementId($order_id);
			if($hasSiblings){
				$grandTotalAmount = sprintf('%.2f', $order->getGrandTotal()+floatval($SiblingsTotalAmount));
			}
			else{
				$grandTotalAmount = sprintf('%.2f', $order->getGrandTotal());
			}
		}
		else{
			$grandTotalAmount = sprintf('%.2f', $order->getGrandTotal());
		}

		$orderId = $order->getIncrementId();
		$item_names = array();
		$items = $order->getItemsCollection();
		foreach ($items as $item){
			$item_name = $item->getName();
 		  	$qty = number_format($item->getQtyOrdered(), 0, '.', ' ');
			$item_names[] = $item_name . ' x ' . $qty;
		}	
		$COD_args['item_name'] 	= sprintf( __('Order %s '), $orderId ) . " - " . implode(', ', $item_names);

		$PaymentDesc = '';
		foreach($order->getAllVisibleItems() as $value) {
		  $PaymentDesc.=$value->getName().",";
		}		
    $PaymentDesc = str_replace('"', ' ', $PaymentDesc);
    $PaymentDesc = str_replace('#', ' ', $PaymentDesc);

    
		$payment_action = $this->getConfigData('payment_action');
        $PymtMethod = $this->getConfigData('paymentmethod');
        $ServiceID = $this->getConfigData('merchant_id');
        $orderReferenceValue = $order_id;
        $PaymentID = time();//$payment_action . $PymtMethod . $orderReferenceValue . $ServiceID;
        // $postbackground = $this->getConfigData('postbackground');
        $postbackground = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB).'receive_data/receive_back.php';
        $callback = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB).'receive_data/call_back.php';
		if($multiShipping){
			$postbackground.='?multishipping=1';
			$callback.='?multishipping=1';
		}
        $m_password = $this->getConfigData('m_password');
        //mage magento can't use i dont know why'
        //$ip = Mage::helper('core/http')->getRemoteAddr(true);
        
        $ip = $_SERVER['REMOTE_ADDR'] ;
        
        /* Check if the customer is logged in or not */
        if (Mage::getSingleton('customer/session')->isLoggedIn()) 
		{
            /* Get the customer data */
            $customer = Mage::getSingleton('customer/session')->getCustomer();
            /* Get the customer's full name */
            $fullname = $customer->getName();
            /* Get the customer's first name */
            $firstname = $customer->getFirstname();
            /* Get the customer's last name */
            $lastname = $customer->getLastname();
            /* Get the customer's email address */
            $email = $customer->getEmail();
			$tel   = $order->getBillingAddress()->getTelephone();
        }
		else
		{
			/* Get the guest data */
			$order = Mage::getSingleton('sales/order');
			/* Get the customer's full name */
			$fullname = $order->getBillingAddress()->getName(); 
			/* Get the customer's first name */
			$firstname = $order->getBillingAddress()->getFirstname();
			/* Get the customer's last name */
			$lastname = $order->getBillingAddress()->getLastname();
			/* Get the customer's email address */
			$email = $order->getBillingAddress()->getEmail();
			 /* Get the customer's telephone number */
			$tel   = $order->getBillingAddress()->getTelephone();
		}
	
        
		/*
        Hash Key = Password + ServiceID + PaymentID + MerchantReturnURL + Amount + CurrencyCode + CustIP + PageTimeout
        */
		$fields = array(
            'TransactionType'           => $payment_action,
            'PymtMethod'                => $PymtMethod,
            'ServiceID'                 => $ServiceID,
            'PaymentID'                 => $PaymentID,
            'OrderNumber'               => $orderReferenceValue,
            'PaymentDesc'               => $PaymentDesc,
            'MerchantReturnURL'         => $postbackground,
            'MerchantCallBackURL'       => $callback,
            'Amount'                    => $grandTotalAmount,
            'CurrencyCode'              => $currency_code,
            'HashValue'                 => hash('sha256', $m_password . $ServiceID . $PaymentID . $postbackground . $callback . $grandTotalAmount . $currency_code . $ip . 600),
            'CustIP'                    => $ip,
            'CustName'                  => $fullname,
            'CustEmail'                 => $email,
            'CustPhone'                 => $tel,
            'PageTimeout'               => 600

		);

		$filtered_fields = array();
        foreach ($fields as $k=>$v) {
            $value = str_replace("&","and",$v);
            $filtered_fields[$k] =  $value;
        }
        return $filtered_fields;
	}//end function getCheckoutFormFields
	
    // Instantiate state and set it to state object
    public function initialize($paymentAction, $stateObject)
    {
        $state = Mage_Sales_Model_Order::STATE_PENDING_PAYMENT;
        $stateObject->setState($state);
        $stateObject->setStatus('pending_payment');
        $stateObject->setIsNotified(false);
    }//end function initialize	
	
	public function createFormBlock($name)
    {
        $block = $this->getLayout()->createBlock('Eghl/form', $name)
            ->setMethod('Eghl')
            ->setPayment($this->getPayment())
            ->setTemplate('Eghl/form.phtml');

        return $block;
    }//end function createFormBlock
	
	public function validate()
    {
        parent::validate();
        $currency_code =  Mage::app()->getStore()->getCurrentCurrencyCode();
        if (!in_array($currency_code,$this->_allowCurrencyCode)) {
            Mage::throwException(Mage::helper('Eghl')->__('Selected currency code ('.$currency_code.') is not compatabile with COD'));
        }
        return $this;
    }//end function validate
	
	public function onOrderValidate(Mage_Sales_Model_Order_Payment $payment)
    {
       return $this;
    }//end function onOrderValidate

    public function onInvoiceCreate(Mage_Sales_Model_Invoice_Payment $payment)
    {
		return $this;
	}//end function onInvoiceCreate
	
	public function getOrderPlaceRedirectUrl() {
		return Mage::getUrl('Eghl/Eghl/redirect');
	}//end function getOrderPlaceRedirectUrl
}
?>
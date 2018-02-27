<?php
/*
*COD Eghl Controller
*By: COD
*/

class COD_Eghl_EghlController extends Mage_Core_Controller_Front_Action {

	public function redirectAction() {
        $session = Mage::getSingleton('checkout/session');
		$session->setEghlQuoteId($session->getQuoteId());
		
		Mage::log('EghlController:redirectAction');
		
		//Get the current order and send out new order email
		$order = Mage::getModel('sales/order')->loadByIncrementId($session->getLastRealOrderId());
		
		$emailAddress = $order->getCustomerEmail();
		if ($emailAddress != null && strlen($emailAddress) > 0)
		{
			$order->sendNewOrderEmail();
		}
			
        $this->getResponse()->setBody($this->getLayout()->createBlock('Eghl/redirect')->toHtml());
        $session->unsQuoteId();		
	}
	
	public function cancelAction()
    {
        $session = Mage::getSingleton('checkout/session');
        $session->setQuoteId($session->getEghlQuoteId(true));
        
        if ($session->getLastRealOrderId()) {
            $order = Mage::getModel('sales/order')->loadByIncrementId($session->getLastRealOrderId());
            if ($order->getId()) {
                $order->cancel()->save();
            }
        }
        $this->_redirect('checkout/cart');
     }

    public function successAction()
    {
        $session = Mage::getSingleton('checkout/session');
        $session->setQuoteId($session->getEghlQuoteId(true));
        
        Mage::getSingleton('checkout/session')->getQuote()->setIsActive(false)->save();
		
        $order = Mage::getModel('sales/order');
        $order->load(Mage::getSingleton('checkout/session')->getLastOrderId());
    
	    $order->save();
        
        if($order->getId()){
            $order->sendNewOrderEmail();
        }

        Mage::getSingleton('checkout/session')->unsQuoteId();
		
    	
        $this->_redirect('checkout/onepage/success');
    }
    
    public function failureAction()
    {
    	$session = Mage::getSingleton('checkout/session');
        $session->setQuoteId($session->getEghlQuoteId(true));
        
        if ($session->getLastRealOrderId()) {
            $order = Mage::getModel('sales/order')->loadByIncrementId($session->getLastRealOrderId());
            if ($order->getId()) {
                $order->cancel()->save();
            }
        }
        $this->_redirect('checkout/onepage/failure');
    }
}
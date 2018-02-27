<?php

// Include Magento application
require_once("../app/Mage.php");
umask(0);

//load Magento application base "default" folder
$app = Mage::app();

/**
 *
 */
function getSiblings($orderObj) {
	$commentsObject = $orderObj->getStatusHistoryCollection(true);

	foreach ($commentsObject as $commentObj) {
		$comment = $commentObj->getComment();
		if(strpos($comment,'Siblings: ')!==False){
			return  explode('#',str_replace('Siblings: ','',$comment));
		}
	}

	return array();
}

/**
 * Function update siblings
 * Usage: to handle multi-shipping 
 */
function updateSiblings($orderObj, $statusId, $comment='', $siblingsIds){
	$s_order = Mage::getSingleton('sales/order');

	foreach($siblingsIds as $sibling_id){
		$s_order->load($sibling_id);

		if ($statusId) {
			$s_order->setState($statusId, true, $comment, true)->save();
		} else {
			$historyItem = $s_order->addStatusHistoryComment($comment)->save();
			$historyItem->setIsCustomerNotified(1)->save();

			$s_order->sendOrderUpdateEmail(true, $comment);
		}
	}
}

function createTxn($orderNumber,$paymentID){
	/**
	 * Get the resource model
	 */
	$resource = Mage::getSingleton('core/resource');
	
	/**
	 * Retrieve the write connection
	 */
	$writeConnection = $resource->getConnection('core_write');
	
	/**
	 * Get the table name
	 */
	$table = $resource->getTableName('sales_payment_transaction');
	
	$query = "INSERT INTO {$table} (order_id, payment_id, txn_id, txn_type, created_at) VALUES ('$orderNumber','$orderNumber','$paymentID','Capture','".gmdate('Y-m-d H:i:s')."')";
	
	/**
	 * Execute the query
	 */
	$writeConnection->query($query);
}

//Receive POSTED variables from the gateway
$txnType = $_REQUEST['TransactionType'];
$pymtMethod = $_REQUEST['PymtMethod'];
$serviceID = $_REQUEST['ServiceID'];
$paymentID = $_REQUEST['PaymentID'];
$orderNumber = $_REQUEST['OrderNumber'];
$amount = $_REQUEST['Amount'];
$currencyCode = $_REQUEST['CurrencyCode'];
$hashValue2 = $_REQUEST['HashValue2'];
$txnID = $_REQUEST['TxnID'];
$issuingBank = $_REQUEST['IssuingBank'];
$txnStatus = $_REQUEST['TxnStatus'];
$authCode = $_REQUEST['AuthCode'];
$bankRefNo = $_REQUEST['BankRefNo'];
$txnMessage = $_REQUEST['TxnMessage'];
$param6 = $_REQUEST['Param6'];
$param7 = $_REQUEST['Param7'];

//check Hash Value
$m_password = Mage::getStoreConfig('payment/Eghl/m_password', $app->getStore()->getStoreId());

$hashchk = hash('sha256', $m_password . $txnID . $serviceID . $paymentID . $txnStatus . $amount . $currencyCode . $authCode . $orderNumber . $param6 . $param7);

$order = Mage::getSingleton('sales/order');

$payment = new Mage_Sales_Model_Order_Payment();
$payment->setOrder($order);

$order->loadByIncrementId($orderNumber);
$dbCur = $order->getBaseCurrencyCode();
$dbAmt = sprintf('%.2f', $order->getGrandTotal());

$configPymtMethod = Mage::getStoreConfig('payment/Eghl/paymentmethod', $app->getStore()->getStoreId());
//$cmp = $txnType . $configPymtMethod . $orderNumber . $serviceID;

//if ($paymentID == $cmp && $hashValue2 == $hashchk) {
if ($hashValue2 == $hashchk) {
    if ($pymtMethod == "CC") {
        $payMethod = "Credit Card";
    } elseif ($pymtMethod == "DD") {
        $payMethod = "Direct Debit";
    } elseif ($pymtMethod == "WA") {
        $payMethod = "Wallet";
    } elseif ($pymtMethod == "OTC") {
        $payMethod = "Over The Counter";
    }
    //-------
	// Proceed only if
	// txnType = SALE AND
	// Order state is pending
    if ($txnType == 'SALE' && ($order->getState()==Mage_Sales_Model_Order::STATE_PENDING_PAYMENT || $order->getState()==Mage_Sales_Model_Order::STATE_NEW || $order->getState()==Mage_Sales_Model_Order::STATE_CANCELED)) {
    	$siblingIds = getSiblings($order);

		if ($txnStatus == '0') {
			// Modified on 8 Aug 2016, changed $dbCur to $currencyCode
			$comment = "Received through EGHL Payment (Callback Response): " . $currencyCode . $dbAmt . " " . "[Transaction ID:" . $txnID . "]" . " [Payment method: " . $payMethod . "]";
			$order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true, $comment, true)->save();			
			if( $order->canInvoice() && ($order->hasInvoices() < 1))
			{
				$invoice = $order->prepareInvoice();
				$invoice->register()->capture();
				Mage::getModel('core/resource_transaction')
						->addObject($invoice)
						->addObject($invoice->getOrder())
						->save();
						
				createTxn($order->getData('entity_id'),$paymentID);
				// On successful payment send invoice email
				$invoice->sendEmail(true,'eGHL generated invoice');
			}
			
			$order->sendOrderUpdateEmail(true, $comment);
			updateSiblings($order, Mage_Sales_Model_Order::STATE_PROCESSING, $comment, $siblingIds);
		} elseif ($txnStatus == '1') {
			if($txnMessage=="Buyer cancelled"){
				$comment = "Payment Cancelled by shopper, Transaction Declined.(Callback Response)";
				$order->setState(Mage_Sales_Model_Order::STATE_CANCELED, true, $comment, true)->save();
				//$order->sendOrderUpdateEmail(true, $comment);
			}
			else{
				$comment = "Payment Failed, Transaction Declined. (Callback Response)";
				$order->setState(Mage_Sales_Model_Order::STATE_CANCELED, true, $comment, true)->save();
				//$order->sendOrderUpdateEmail(true, $comment);
			}
			
			updateSiblings($order, Mage_Sales_Model_Order::STATE_CANCELED, $comment, $siblingIds);
		}
		elseif($vars['TxnStatus']=='2') // Pending Response
		{
			$comment = "Payment Pending. (Callback Response)";
			$order->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, true, $comment, true)->save();
			$order->sendOrderUpdateEmail(true, $comment);
			updateSiblings($order, Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, $comment, $siblingIds);
		}
	}
	elseif ($txnType == 'REFUND' && $txnStatus == '0') {

		$partialRefundText 	= 'Received partial refund';// Text to be shown in comment for partial refund
		$shouldUpdateStatus	= true;	// For partial refund, set to true will change status to cancel upon receiving full refund

		$siblingIds = getSiblings($order);
		$siblingsCount = count($siblingIds);
		$hasSiblings = (isset($siblingsCount) && $siblingsCount > 0);

		$totalDBAmount = $dbAmt;
		if ($hasSiblings) {
			$totalDBAmount = 0;

			$s_order = Mage::getSingleton('sales/order');
			foreach($siblingsIds as $sibling_id){
				$s_order->load($sibling_id);
				$totalDBAmount += floatval($s_order->getGrandTotal());
			}
			
			$order = Mage::getSingleton('sales/order');
			$order->loadByIncrementId($orderNumber);
		}

		$isPartialRefund = ($totalDBAmount != $amount);

		// Calculate total refund
		if ($isPartialRefund) {

			$commentsObject = $order->getStatusHistoryCollection(true);

			$totalRefunded = $amount;
			foreach ($commentsObject as $commentObj) {
				$comment = $commentObj->getComment();

				if(strpos($comment, $partialRefundText) !== false){
					$value = substr($comment, strpos($comment, $currencyCode) + strlen($currencyCode), strlen($amount));
					$totalRefunded += floatval($value);
				}
			}
		}

		$comment = $isPartialRefund?$partialRefundText:"Received refund";
		$comment .= " through eGHL(Callback Response): " . $currencyCode . $amount . " " . "[Transaction ID:" . $txnID . "]" . " [Payment method: " . $payMethod . "]";

		$isFullyRefunded = ($totalRefunded >= $totalDBAmount);

		if ($isFullyRefunded && $shouldUpdateStatus) {
			$order->setState(Mage_Sales_Model_Order::STATE_CANCELED, true, $comment, true)->save();
			$order->sendOrderUpdateEmail(true, $comment);

			if ($hasSiblings) {
				updateSiblings($order,Mage_Sales_Model_Order::STATE_CANCELED,$comment, $siblingIds);
			}
		} else {
			$historyItem = $order->addStatusHistoryComment($comment)->save();
			$historyItem->setIsCustomerNotified(1)->save();

			$order->sendOrderUpdateEmail(true, $comment);

			if ($hasSiblings) {
				updateSiblings($order, null, $comment, $siblingIds);
			}
		}
	}
	echo "ok";
}
?>

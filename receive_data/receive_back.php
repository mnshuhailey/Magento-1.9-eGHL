<?php

// Include Magento application
require_once("../app/Mage.php");
umask(0);

//load Magento application base "default" folder
$app = Mage::app();

function updateSiblings($order_obj,$status_id,$comment=''){
	$hasSiblings = false;
	$commentsObject = $order_obj->getStatusHistoryCollection(true);

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
		$s_order->setState($status_id, true, $comment, true)->save();
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
	
	echo $query;
	
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
	// Order state is pending payment
	if ($txnType == 'SALE' && ($order->getState()==Mage_Sales_Model_Order::STATE_PENDING_PAYMENT || $order->getState()==Mage_Sales_Model_Order::STATE_NEW || $order->getState()==Mage_Sales_Model_Order::STATE_CANCELED)) {
		 if ($txnStatus == '0') {
			// Modified on 8 Aug 2016, changed $dbCur to $currencyCode
			$comment = "Received through EGHL Payment (Redirect Response): " . $currencyCode . $dbAmt . " " . "[Transaction ID:" . $txnID . "]" . " [Payment method: " . $payMethod . "]";
			$order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true, $comment, true)->save();
			updateSiblings($order,Mage_Sales_Model_Order::STATE_PROCESSING,$comment);
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
			if( isset($_REQUEST['multishipping']) && '1'==$_REQUEST['multishipping'] ){
				header('Location: ../checkout/Multishipping/success/');
			}
			else{
				header('Location: ../checkout/onepage/success/');
			}
			
		} elseif ($txnStatus == '1') {
			if($txnMessage=="Buyer cancelled"){
				$comment = "Payment Cancelled by shopper, Transaction Declined.(Redirect Response)";
				$order->setState(Mage_Sales_Model_Order::STATE_CANCELED, true, $comment, true)->save();
				//$order->sendOrderUpdateEmail(true, $comment);
				updateSiblings($order,Mage_Sales_Model_Order::STATE_CANCELED,$comment);
			}
			else{
				$comment = "Payment Failed, Transaction Declined. (Redirect Response)";
				$order->setState(Mage_Sales_Model_Order::STATE_CANCELED, true, $comment, true)->save();
				//$order->sendOrderUpdateEmail(true, $comment);
				updateSiblings($order,Mage_Sales_Model_Order::STATE_CANCELED,$comment);
			}
			
			header('Location: ../checkout/onepage/failure/');
		}
		elseif ($txnStatus == '2') {
			$comment = "Payment Pending. (Callback Response)";
			$order->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, true, $comment, true)->save();
			$order->sendOrderUpdateEmail(true, $comment);
			updateSiblings($order,Mage_Sales_Model_Order::STATE_PENDING_PAYMENT,$comment);
			echo "	<center>
						<a title='Visit Home' href=".Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB).">
							<img src='".Mage_Page_Block_Html_Header::getSkinUrl(Mage::getStoreConfig('design/header/logo_src'))."' alt='site logo'/>
						</a>
						<h1>Your Payment is still Pending for the order #$orderNumber!</h1><br/>
					</center>";
		}
	}
	elseif($order->getState()==Mage_Sales_Model_Order::STATE_PROCESSING){
		if( isset($_REQUEST['multishipping']) && '1'==$_REQUEST['multishipping'] ){
			header('Location: ../checkout/Multishipping/success/');
		}
		else{
			header('Location: ../checkout/onepage/success/');
		}
	}
	elseif($order->getState()==Mage_Sales_Model_Order::STATE_CANCELED){
		header('Location: ../checkout/onepage/failure/');
	}
}
?>

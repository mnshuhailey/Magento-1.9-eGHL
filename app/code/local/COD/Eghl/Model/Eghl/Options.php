<?php

class COD_Eghl_Model_Eghl_Options
{
    public function paymentMethods()
    {
        return array(
            array('value' => 'ANY', 'label' => Mage::helper()->__('All payment method(s)')),
            array('value' => 'CC', 'label' => Mage::helper()->__('Credit Card')),
            array('value' => 'DD', 'label' => Mage::helper()->__('Direct Debit')),
            array('value' => 'WA', 'label' => Mage::helper()->__('e-Wallet')),
    );
  }
}
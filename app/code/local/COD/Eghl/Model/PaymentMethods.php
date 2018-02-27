<?php

class COD_Eghl_Model_PaymentMethods
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array('value' => 'ANY', 'label' => Mage::helper('adminhtml')->__('All payment method(s)')),
            array('value' => 'CC', 'label' => Mage::helper('adminhtml')->__('Credit Card')),
            array('value' => 'DD', 'label' => Mage::helper('adminhtml')->__('Direct Debit')),
            array('value' => 'WA', 'label' => Mage::helper('adminhtml')->__('e-Wallet')),
    );
  }
}

<?php


class COD_Eghl_Block_Form extends Mage_Payment_Block_Form
{
    protected function _construct()
    {
        $this->setTemplate('Eghl/form.phtml');
        parent::_construct();
    }//end function _construct
}//end class COD_Eghl_Block_Form

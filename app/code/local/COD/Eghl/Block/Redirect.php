<?php


class COD_Eghl_Block_Redirect extends Mage_Core_Block_Abstract
{
    protected function _toHtml()
    {
        $Eghl = Mage::getModel('Eghl/Eghl');

        $form = new Varien_Data_Form();
        $form->setAction($Eghl->getUrl())
            ->setId('Eghl_checkout')
            ->setName('Eghl_checkout')
            ->setMethod('post')
            ->setUseContainer(true);
        foreach ($Eghl->getCheckoutFormFields() as $field=>$value) {
            $form->addField($field, 'hidden', array('name'=>$field, 'value'=>$value));
        }
		
		$idSuffix = Mage::helper('core')->uniqHash();
        $submitButton = new Varien_Data_Form_Element_Submit(array(
            'value'    => $this->__('Click here if you are not redirected within 10 seconds...'),
        ));
        $id = "submit_to_eghl_button_{$idSuffix}";
        $submitButton->setId($id);
		
        $form->addElement($submitButton);
        $html = '<html><body>';
        $html.= $this->__('You will be redirected to EGHL Payment Gateway in a few seconds.');
        $html.= $form->toHtml();
        $html.= '<script type="text/javascript">document.getElementById("Eghl_checkout").submit();</script>';
        $html.= '</body></html>';

        return $html;
    }//end function _toHtml
}//end class COD_Eghl_Block_Redirect

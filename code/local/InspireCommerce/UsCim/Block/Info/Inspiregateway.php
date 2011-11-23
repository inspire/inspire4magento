<?php

class InspireCommerce_UsCim_Block_Info_Inspiregateway extends Mage_Payment_Block_Info_Cc {
/*
  protected function _prepareSpecificInformation($transport = null) {
    if (null !== $this->_paymentSpecificInformation) {
      return $this->_paymentSpecificInformation;
    }
    $info = $this->getInfo();
    $transport = new Varien_Object(array(Mage::helper('payment')->__('Name on the Card') => $info->getCcOwner(),));
    $transport = parent::_prepareSpecificInformation($transport);
    if (!$this->getIsSecureMode()) {
      $transport->addData(array(
        Mage::helper('payment')->__('Expiration Date') => $this->_formatCardDate(
          $info->getCcExpYear(), $this->getCcExpMonth()
        ),
        Mage::helper('payment')->__('Credit Card Number') => $info->getCcNumber(),
      ));
    }
    return $transport;
  }
*/
}

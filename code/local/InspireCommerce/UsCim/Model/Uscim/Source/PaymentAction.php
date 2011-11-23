<?php

class InspireCommerce_UsCim_Model_Uscim_Source_PaymentAction {
  public function toOptionArray() {
    return array(
      array(
        'value' => InspireCommerce_UsCim_Model_Uscim::ACTION_AUTHORIZE_CAPTURE,
        'label' => Mage::helper('UsCim')->__('Sale')
      ),
      array(
        'value' => InspireCommerce_UsCim_Model_Uscim::ACTION_AUTHORIZE,
        'label' => Mage::helper('UsCim')->__('Authorize Only')
      ),
    );
  }
}

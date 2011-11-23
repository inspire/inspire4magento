<?php

class InspireCommerce_UsCim_Model_Uscim_Source_Cctype extends Mage_Payment_Model_Source_Cctype {
  public function getAllowedTypes() {
    return array('VI', 'MC', 'AE', 'DI', 'OT');
  }
}

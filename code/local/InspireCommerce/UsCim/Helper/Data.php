<?php

class InspireCommerce_UsCim_Helper_Data extends Mage_Core_Helper_Abstract {

  
public function getCcHash($customerId) {

    if(!$customerId) return false;

    $uscim=mage::getModel('uscim/uscim');
    
    $cc_hash = null;
    $request = Mage::getModel('uscim/uscim_request');
    $request->setUsername($uscim->getConfigData('username'))
            ->setPassword($uscim->getConfigData('password'))
            ->setReportType('customer_vault')
            ->setCustomerVaultId($customerId)
    ;

  //  Mage::log($request->getData());

    $result = Mage::getModel('uscim/uscim_result');
    $client = new Varien_Http_Client();
    $uri=$uscim->getConfigData('query_url');
    $client->setUri($uri);// ? $uri : self::CGI_URL);
    $client->setConfig(array('maxredirects'=>0, 'timeout'=>30));
    $client->setParameterPost($request->getData());
    $client->setMethod(Zend_Http_Client::POST);

    try {
      $response = $client->request();
    }
    catch (Exception $e) {
      $result->setResponseCode(-1)
             ->setResponseReasonCode($e->getCode())
             ->setResponseReasonText($e->getMessage());

      Mage::throwException(
        Mage::helper('UsCim')->__('Gateway request error: %s', $e->getMessage())
      );
    }

    $responseBody = $response->getBody();

  /*  print_r($responseBody);
    exit;*/


    $xml_response=simplexml_load_string($responseBody);

    //Mage::log($xml_response);
    $ccNode = $xml_response->xpath('//cc_number');
    if (count($ccNode) == 1) $cc_hash = (string)$ccNode[0];

   // Mage::log("this is the hash " . $cc_hash) ;

    return $cc_hash;
  }


}

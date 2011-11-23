<?php

class InspireCommerce_UsCim_Model_Uscim extends Mage_Payment_Model_Method_Cc
{
  const CGI_URL = 'https://secure.inspiregateway.net/api/transact.php';
  const REQUEST_METHOD_CC     = 'creditcard';
  const REQUEST_METHOD_ECHECK = 'ECHECK';

  const REQUEST_TYPE_AUTH_CAPTURE = 'sale';
  const REQUEST_TYPE_AUTH_ONLY    = 'auth';

  const REQUEST_TYPE_CAPTURE_ONLY = 'capture';
  const REQUEST_TYPE_CREDIT       = 'credit';
  const REQUEST_TYPE_VOID         = 'void';
  const REQUEST_TYPE_REFUND       = 'refund';
  const REQUEST_TYPE_PRIOR_AUTH_CAPTURE = 'PRIOR_AUTH_CAPTURE';

  const ECHECK_ACCT_TYPE_CHECKING = 'CHECKING';
  const ECHECK_ACCT_TYPE_BUSINESS = 'BUSINESSCHECKING';
  const ECHECK_ACCT_TYPE_SAVINGS  = 'SAVINGS';

  const ECHECK_TRANS_TYPE_CCD = 'CCD';
  const ECHECK_TRANS_TYPE_PPD = 'PPD';
  const ECHECK_TRANS_TYPE_TEL = 'TEL';
  const ECHECK_TRANS_TYPE_WEB = 'WEB';

  const RESPONSE_DELIM_CHAR = '&';

  const RESPONSE_CODE_APPROVED = 1;
  const RESPONSE_CODE_DECLINED = 2;
  const RESPONSE_CODE_ERROR    = 3;
  const RESPONSE_CODE_HELD     = 4;

  protected $_code  = 'uscim';

  protected $_isGateway               = true;
  protected $_canAuthorize            = true;
  protected $_canCapture              = true;
  protected $_canCapturePartial       = true;
  protected $_canRefund               = true;
  protected $_canVoid                 = true;
  protected $_canUseInternal          = true;
  protected $_canUseCheckout          = true;
  protected $_canUseForMultishipping  = true;
  protected $_canSaveCc               = true;

  #protected $_formBlockType = 'payment/form_cc';
  #protected $_infoBlockType = 'payment/info_cc';
  protected $_formBlockType = 'payment/form_inspiregateway';
  protected $_infoBlockType = 'payment/info_inspiregateway';


  protected $_saveInVault = "no";

  /*public function setSaveInVault($value){
      Mage::unregister("save_in_vault");
      Mage::register('save_in_vault', $value);
      $this->_saveInVault = $value;
  }

   public function setUseSaved($value){
      Mage::unregister("use_saved");
      Mage::register('use_saved', $value);
      $this->_saveInVault = $value;
  }*/

  public function getUseSaved(){
      $post = Mage::app()->getRequest()->getPost('payment');
      if($post["use_saved"]=="yes"): return true; else: return false; endif;
      
  }

  public function getSaveInVault(){
      $post = Mage::app()->getRequest()->getPost('payment');
      if($post["save_in_vault"]): return true; else: return false; endif;

  }



  public function assignData($data) {
    parent::assignData($data);
    $this->setSaveInVault($data->getSaveInVault());
    $this->setUseSaved($data->getUseSaved());
    $info = $this->getInfoInstance();
    $info->setSaveInVault($data->getSaveInVault());
    $info->setUseSaved($data->getUseSaved());
   // Mage::register('save_in_vault', $data->getSaveInVault());
    //Mage::register('use_saved', $data->getUseSaved());
    return $this;
  }


  public function validate() {

    /* Mage::log("function validate " . $this->getSaveInVault()) ;
     Mage::log("function validate " . $this->getUseSaved()) */;
    if ($this->getUseSaved() ) {
      return $this;
    }
    return parent::validate();
  }

  public function authorize(Varien_Object $payment, $amount) {
    $error = false;

    
    if($amount>0){
      $payment->setAnetTransType(self::REQUEST_TYPE_AUTH_ONLY);
      $payment->setAmount($amount);

      $request = $this->_buildRequest($payment);
      $result  = $this->_postRequest($request);
      $payment->setCcApproval($result->getAuthcode())
              ->setLastTransId($result->getTransactionid())
              ->setCcTransId($result->getTransactionid())
              ->setCcAvsStatus($result->getAvsresponsecode())
              ->setIsTransactionClosed(0)
              ->setCcCidStatus($result->getCardCodeResponseCode());



      switch ($result->getResponse()) {
        case self::RESPONSE_CODE_APPROVED:
          $payment->setStatus(self::STATUS_APPROVED);
          
      $use_saved = $this->getUseSaved();
      $save_in_vault = $this->getSaveInVault();
      if ($save_in_vault and !$use_saved ) {
        $this->saveCC($payment);
      }
          
          
          break;

        case self::RESPONSE_CODE_DECLINED:
          $error = Mage::helper('UsCim')->__('Payment authorization transaction has been declined.');
          break;

        default:
          $error = Mage::helper('UsCim')->__('PlaneAuthorize::Payment authorization error. ' . $result->getResponsetext());
          break;

      }

    }
    else {
      $error = Mage::helper('UsCim')->__('Invalid amount for authorization.');
    }

    if ($error !== false) {
      mage::log("Error during InspireGateway payment transaction: ".$error);
      Mage::throwException($error);
    }

    return $this;

  }

  public function capture(Varien_Object $payment, $amount) {
    $error = false;

   

    if ($payment->getCcTransId()) {
      $payment->setAnetTransType(self::REQUEST_TYPE_CAPTURE_ONLY);
    }
    else {
      $payment->setAnetTransType(self::REQUEST_TYPE_AUTH_CAPTURE);
    }

    $payment->setAmount($amount);

    $request= $this->_buildRequest($payment);
    $result = $this->_postRequest($request);

   // Mage::log($result);

    if ($result->getResponse() == self::RESPONSE_CODE_APPROVED) {
      $payment->setStatus(self::STATUS_APPROVED);
      $payment->setLastTransId($result->getTransactionid());

      $use_saved = $this->getUseSaved();
      $save_in_vault = $this->getSaveInVault();
      if ($save_in_vault and !$use_saved ) {
        $this->saveCC($payment);
      }
    }
    else {
      if ($result->getResponsetext()) {
        $error = $result->getResponsetext();
      }
      else {
        $error = Mage::helper('UsCim')->__('Error in capturing the payment');
      }
    }

    if ($error !== false) {
      Mage::throwException($error);
    }

    return $this;

  }
  public function void(Varien_Object $payment) {   
    $error = false;
    if($payment->getVoidTransactionId()){
      $payment->setAnetTransType(self::REQUEST_TYPE_VOID);
      $request = $this->_buildRequest($payment);
      $request->setTransactionid($payment->getVoidTransactionId());
      $result = $this->_postRequest($request);
      if($result->getResponseCode()==self::RESPONSE_CODE_APPROVED){
        $payment->setStatus(self::STATUS_SUCCESS );
      }
      else {
        $payment->setStatus(self::STATUS_ERROR);
        $error = $result->getResponseText();
      }
    }
    else {
      $payment->setStatus(self::STATUS_ERROR);
      $error = Mage::helper('UsCim')->__('PlanetAuthorize:Void Invalid transaction id');
    }
    if ($error !== false) {
      Mage::throwException($error);
    }
    return $this; 

  }

  public function refund(Varien_Object $payment, $amount) {
    Mage::log("refund");
    $error = false;

    Mage::log("before refund online");

    if ($payment->getRefundTransactionId() && $amount>0) {


        Mage::log("will refund online");

      $payment->setAnetTransType(self::REQUEST_TYPE_REFUND);
      $request = $this->_buildRequest($payment);
      $request->setTransactionid($payment->getRefundTransactionId());
      $result = $this->_postRequest($request);




      if ($result->getResponse()==self::RESPONSE_CODE_APPROVED) {
        $payment->setStatus(self::STATUS_SUCCESS);
      }
      else {
        $error = $result->getResponseText();
      }

    }
    else {
      $error = Mage::helper('UsCim')->__('Error in refunding the payment');
    }

    if ($error !== false) {
      Mage::throwException($error);
    }
    return $this; 

  }

  protected function saveCC(Varien_Object $payment) {
    $order = $payment->getOrder();
    $this->setStore($order->getStoreId());        
    $request = Mage::getModel('uscim/uscim_request');


    /* checking if customer vault already exists */

    $ccHash = Mage::app()->getLayout()->getBlockSingleton("uscim/form_inspiregateway")->getCcHash($order->getCustomerId());

    if($ccHash){ $action = "update_customer"; }else{
        $action = "add_customer";
    }




    $billing = $order->getBillingAddress();
    $request->setCustomerVault($action) #setType($payment->getAnetTransType())
            ->setUsername($this->getConfigData('username'))
            ->setPassword($this->getConfigData('password'))
            ->setFirstname($billing->getFirstname())
            ->setLastname($billing->getLastname())
            ->setCompany($billing->getCompany())
            ->setCcnumber($payment->getCcNumber())
            ->setCcexp(sprintf('%02d-%04d',$payment->getCcExpMonth(),$payment->getCcExpYear()))
            #->setCvv($payment->getCcCid())
            ->setAddress($billing->getStreet(1))
            ->setCity($billing->getCity())
            ->setState($billing->getRegion())
            ->setZip($billing->getPostcode())
            ->setCountry($billing->getCountry())
            ->setCustomerVaultId($billing->getCustomerId())
    ;

    $result = Mage::getModel('uscim/uscim_result');
    $client = new Varien_Http_Client();
    $uri = $this->getConfigData('cgi_url');
    $client->setUri($uri ? $uri : self::CGI_URL);
    $client->setConfig(array('maxredirects'=>0, 'timeout'=>30));


   // Mage::log($request->getData());

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

   // Mage::log($responseBody);

    $r = explode(self::RESPONSE_DELIM_CHAR, $responseBody);
    $response_array= array();
    foreach($r as $value) {
      $temp = array();
      $temp = explode("=",$value);
      $response_array[$temp[0]]=$temp[1];
    }
   

  }

  protected function _buildRequest(Varien_Object $payment) {


     $use_saved = $this->getUseSaved();

    $order = $payment->getOrder();
    $this->setStore($order->getStoreId());        
    if (!$payment->getAnetTransMethod()) {
      $payment->setAnetTransMethod(self::REQUEST_METHOD_CC);
    }
    $request = Mage::getModel('uscim/uscim_request');

    $request->setType($payment->getAnetTransType());
    $request->setUsername($this->getConfigData('username'));
    $request->setPassword($this->getConfigData('password'));
    $request->setPayment($payment->getAnetTransMethod());

    if ($order && $order->getIncrementId()) {
      $request->setOrderid($order->getIncrementId());
    }

    Mage::log($order->getIncrementId());

    if($payment->getAmount()){
      $request->setAmount($payment->getAmount(),2);
      $request->setXCurrencyCode($order->getBaseCurrencyCode());
    }

    switch ($payment->getAnetTransType()) {
      case self::REQUEST_TYPE_CREDIT:
      case self::REQUEST_TYPE_VOID:
      case self::REQUEST_TYPE_PRIOR_AUTH_CAPTURE:
        $request->setTransactionid($payment->getCcTransId());
        break;

      case self::REQUEST_TYPE_CAPTURE_ONLY:
        $request->setXAuthCode($payment->getCcAuthCode());
        break;
    }

    if (!empty($order)) {
      $request->setOrderis($order->getIncrementId());

      $billing = $order->getBillingAddress();
      if (!empty($billing)) {
        $request->setFirstname($billing->getFirstname())
                ->setLastname($billing->getLastname())
                ->setCompany($billing->getCompany())
                ->setAddress($billing->getStreet(1))
                ->setCity($billing->getCity())
                ->setState($billing->getRegion())
                ->setZip($billing->getPostcode())
                ->setCountry($billing->getCountry())
                ->setPhone($billing->getTelephone())
                ->setFax($billing->getFax())
                ->setIpaddress($order->getRemoteIp())
                ->setEmail($billing->getEmail());
      }

      $shipping = $order->getShippingAddress();
      if (!empty($shipping)) {
        $request->setShippingFirstname($shipping->getFirstname())
                ->setShippingLastname($shipping->getLastname())
                ->setShippingCompany($shipping->getCompany())
                ->setShippingAddress($shipping->getStreet(1))
                ->setShippingCity($shipping->getCity())
                ->setShippingState($shipping->getRegion())
                ->setXShippingZip($shipping->getPostcode())
                ->setShippingCountry($shipping->getCountry());
      }

      $request->setPonumber($payment->getPoNumber())
              ->setTax($shipping->getTaxAmount())
              ->setShipping($shipping->getShippingAmount());
    }

    switch ($payment->getAnetTransMethod()) {
      case self::REQUEST_METHOD_CC:
        $use_saved = $this->getUseSaved();
        if ($use_saved ) {

            $request->setCustomerVaultId($order->getCustomerId());
        }
        else {
          if($payment->getCcNumber()){
            $request->setCcnumber($payment->getCcNumber())
                    ->setCcexp(sprintf('%02d-%04d', $payment->getCcExpMonth(), $payment->getCcExpYear()))
                    ->setCvv($payment->getCcCid());
          }
        }
        break;

      case self::REQUEST_METHOD_ECHECK:
        $request->setCheckaba($payment->getEcheckRoutingNumber())
                ->setCheckname($payment->getEcheckBankName())
                ->setAccount($payment->getEcheckAccountNumber())
                ->setCheckHolderType($payment->getEcheckAccountType())
                ->setXBankAcctName($payment->getEcheckAccountName())
                ->setAccountType($payment->getEcheckType());
        break;
      }
    return $request;
  }

  protected function _postRequest(Varien_Object $request) {
    $result = Mage::getModel('uscim/uscim_result');
    $client = new Varien_Http_Client();
    $uri = $this->getConfigData('cgi_url');
    $client->setUri($uri ? $uri : self::CGI_URL);
    $client->setConfig(array('maxredirects'=>0, 'timeout'=>30));
    $client->setParameterPost($request->getData());
    $client->setMethod(Zend_Http_Client::POST);

    //  Mage::log("before debug");

   // if ($this->getConfigData('debug')) {
      foreach( $request->getData() as $key => $value ) {
        $requestData[] = strtoupper($key) . '=' . $value;
      }

      $requestData = join('&', $requestData);


Mage::log($requestData);


     /* $debug = Mage::getModel('uscim/uscim_debug')
                ->setRequestBody($requestData)
                ->setRequestSerialized(serialize($request->getData()))
                ->setRequestDump(print_r($request->getData(),1))
                ->save();
    }*/

    try {
      $response = $client->request();
    }
    catch (Exception $e) {
      $result->setResponseCode(-1)
             ->setResponseReasonCode($e->getCode())
             ->setResponseReasonText($e->getMessage());

      if (!empty($debug)) {
        $debug->setResultSerialized(serialize($result->getData()))
              ->setResultDump(print_r($result->getData(),1))
              ->save();
      }
      Mage::throwException(
        Mage::helper('UsCim')->__('Gateway request error: %s', $e->getMessage())
      );
    }

    $responseBody = $response->getBody();
    $r = explode(self::RESPONSE_DELIM_CHAR, $responseBody);

    Mage::log("result of the request");

    Mage::log($r);

    $response_array= array();
    foreach($r as $value) {
      $temp = array();
      $temp = explode("=",$value);
      $response_array[$temp[0]]=$temp[1];
    }
    
    
    
    if ($r) {
      $result->setResponse($response_array['response'])
             ->setResponsetext($response_array['responsetext'])
             ->setAuthcode($response_array['authcode'])
             ->setTransactionid($response_array['transactionid'])
             ->setAvsresponse($response_array['avsresponse'])
             ->setCvvresponse($response_array['cvvresponse'])
             ->setOrderid($response_array['orderid'])
             ->setType($response_array['type'])
             ->setResponseCode($response_array['response_code']);
    }
    else {
      Mage::throwException(
        Mage::helper('UsCim')->__('Error in payment gateway')
      );
    }

    if (!empty($debug)) {
      $debug->setResponseBody($responseBody)
            ->setResultSerialized(serialize($result->getData()))
            ->setResultDump(print_r($result->getData(),1))
            ->save();
    }

    return $result;
  }








}

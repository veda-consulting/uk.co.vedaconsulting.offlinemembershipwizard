<?php

require_once 'CRM/Core/Page.php';

class CRM_Offlinemembershipwizard_Utils {

  // Additional payment options
  public static $additionalPaymentOptions = array(
    'cheque' => 'Cheque',
    'cash' => 'Cash',
  );
  CONST CIVICRM_PAYMENT_INSTRUMENT_ID_CASH = 3;
  CONST CIVICRM_PAYMENT_INSTRUMENT_ID_CHEQUE = 4;
  CONST SEPA_DD_FIRST_TRANSACTION_OP_NAME = 'FRST';
  CONST PAYMENTPROCESSOR_CUSTOMDETAILS_TABLE = 'civicrm_payment_processor_warning_message';
	/**
   * CiviCRM API wrapper
   *
   * @param string $entity
   * @param string $action
   * @param string $params
   *
   * @return array of API results
   */
  public static function CiviCRMAPIWrapper($entity, $action, $params) {

    if (empty($entity) || empty($action) || empty($params)) {
      return;
    }

    try {
      $result = civicrm_api3($entity, $action, $params);
    }
    catch (Exception $e) {
      CRM_Core_Error::debug_log_message('CiviCRM API Call Failed');
      CRM_Core_Error::debug_var('CiviCRM API Call Error', $e);
      return;
    }

    return $result;
  }

  /**
   * Function to get additional field option
   *
   * @return array $field
   */
  public static function getOptionsValues($optionGroupName) {

    if (empty($optionGroupName)) {
      return;
    }

    $options = array();

    $ovParams = array('sequential' => 1, 'option_group_id' => $optionGroupName);
    $ovResult = self::CiviCRMAPIWrapper('OptionValue', 'get', $ovParams);

    foreach($ovResult['values'] as $value) {
      $options[$value['value']] = $value['label'];
    }

    return $options;
  }

  /**
   * Function to save distribution value for membership
   *
   * @return array $field
   */
  public static function saveDistributionType($memId, $distributionTypeValue) {

    if (empty($memId) || empty($distributionTypeValue)) {
      return;
    }

    $selectSql = "SELECT * FROM civicrm_value_membership_distribution_details WHERE entity_id = %1";
    $selectParams = array(
      1 => array($memId, 'Integer'),
    );
    $selectDao = CRM_Core_DAO::executeQuery($selectSql, $selectParams);
    if ($selectDao->fetch()) {
      $updateSql = "UPDATE civicrm_value_membership_distribution_details SET distribution_type = %2 WHERE entity_id = %1";
      $updateParams = array(
        1 => array($memId, 'Integer'),
        2 => array($distributionTypeValue, 'String'),
      );
      CRM_Core_DAO::executeQuery($updateSql, $updateParams);
    } else {
      $insertSql = "INSERT INTO civicrm_value_membership_distribution_details SET entity_id = %1, distribution_type = %2";
      $insertParams = array(
        1 => array($memId, 'Integer'),
        2 => array($distributionTypeValue, 'String'),
      );
      CRM_Core_DAO::executeQuery($insertSql, $insertParams);
    }
  }

  /**
   * Compose line items from the selected price fields
   *
   * @return array lineitems
   */
  public static function prepareLineItems($membership, $discountPriceSet = array(), $applyRingingDiscount = FALSE) {

    if (empty($membership)) {
      return;
    }

    // Get the selected priceset
    $priceSetId = $membership['price_set_id'];
    if (empty($priceSetId)) {
      return;
    }

    // Get all financial types
    $financialTypes = CRM_Contribute_BAO_Contribution::buildOptions('financial_type_id');

    $priceFields = CRM_Offlinemembershipwizard_Utils::CiviCRMAPIWrapper('PriceField', 'get', array(
      'sequential' => 1,
      'price_set_id' => $priceSetId,
    ));

    $lineItems = array();
    $totalAmount = 0;

    foreach($priceFields['values'] as $priceField) {
      $fieldId = 'price_'.$priceField['id'];

      // Check if this price field is selected
      if (!isset($membership[$fieldId]) || empty($membership[$fieldId])) {
        continue;
      }
      $priceFieldValue = $membership[$fieldId];

      $priceFieldId = $priceField['id'];
      $priceFieldLabel = $priceField['label'];

      $priceValueArray = array();
      if (!is_array($priceFieldValue)) {
        $priceValueArray[$priceFieldValue] = $priceFieldValue;
      }
      else {
        $priceValueArray = $priceFieldValue;
      }

      if ($priceField['html_type'] == 'Text') {
        $priceFieldValueResult = CRM_Offlinemembershipwizard_Utils::CiviCRMAPIWrapper('PriceFieldValue', 'get', array(
          'sequential' => 1,
          'price_field_id' => $priceFieldId,
        ));
        $priceFieldValueDetails = $priceFieldValueResult['values'][0];
        $lineItems[] = array(
          'pricefield_id' => $priceField['id'],
          'pricefield_label' => $priceField['label'],
          'pricefieldvalue_id' => $priceFieldValueDetails['id'],
          'pricefieldvalue_label' => $priceFieldValueDetails['label'],
          'amount' => $priceFieldValue,
          'financial_type_id' => $priceFieldValueDetails['financial_type_id'],
          'membership_type_id' => isset($priceFieldValueDetails['membership_type_id'])? $priceFieldValueDetails['membership_type_id']: '',
          'financial_type' => $financialTypes[$priceFieldValueDetails['financial_type_id']],
        );
        $totalAmount += $priceFieldValue;
      }
      else {
        foreach ($priceValueArray as $pfvKey => $pfvValue) {
          if (empty($discountPriceSet)) {
            $priceFieldValueResult = CRM_Offlinemembershipwizard_Utils::CiviCRMAPIWrapper('PriceFieldValue', 'get', array(
              'sequential' => 1,
              'id' => $pfvKey,
            ));

            $priceFieldValueDetails = $priceFieldValueResult['values'][0];
          } else {
            $priceFieldValueDetails = $discountPriceSet['fields'][$priceFieldId]['options'][$pfvKey];
          }

          $lineItems[] = array(
            'pricefield_id' => $priceField['id'],
            'pricefield_label' => $priceField['label'],
            'pricefieldvalue_id' => $priceFieldValueDetails['id'],
            'pricefieldvalue_label' => $priceFieldValueDetails['label'],
            'amount' => $priceFieldValueDetails['amount'],
            'financial_type_id' => $priceFieldValueDetails['financial_type_id'],
            'membership_type_id' => $priceFieldValueDetails['membership_type_id'],
            'financial_type' => $financialTypes[$priceFieldValueDetails['financial_type_id']],
          );
          $totalAmount += $priceFieldValueDetails['amount'];
        }
      }
    }

    return array($lineItems, $totalAmount);
  }

  /**
   * Function to get logged in user's Contact ID
   */
  public static function getLoggedInUserContactID() {
    // Get logged in user ID
    $session =& CRM_Core_Session::singleton( );
    return $session->get( 'userID' );
  }

  /**
   * Function to get logged in user's Contact ID
   */
  public static function getLoggedInUserContactName() {
    // Get logged in user ID
    $session =& CRM_Core_Session::singleton( );
    $contactId =  $session->get( 'userID' );

    $contactParams = array(
      'id' => $contactId
    );

    $contactResult = CRM_Offlinemembershipwizard_Utils::CiviCRMAPIWrapper('Contact', 'get', $contactParams);
    return $contactResult['values'][$contactId]['display_name'];
  }

  /**
   * Function to get billing details for a contact
   */
  public static function getBillingDetailsForContact($contactId, $isBilling = TRUE) {
    if (empty($contactId)) {
      return;
    }

    $billingDetails = array();
    // Get contact details
    $contactParams = array(
      'id' => $contactId,
    );
    $contactDetails = self::CiviCRMAPIWrapper('Contact', 'getsingle', $contactParams);
    $billingDetails['billing_first_name'] = $contactDetails['first_name'];
    $billingDetails['billing_middle_name'] = $contactDetails['middle_name'];
    $billingDetails['billing_last_name'] = $contactDetails['last_name'];

    // Get contact billing address
    $addressParams = array(
      'contact_id' => $contactId,
      'sequential' => 1,
    );

    //MV #4441 if there is no information in the billing address fields the main address fields should be populated on the form.
    if (!$isBilling) {
      $addressParams['is_primary'] = 1;
    }
    else{
      $addressParams['is_billing'] = 1;
    }

    $addressDetails = self::CiviCRMAPIWrapper('Address', 'get', $addressParams);
    if (!empty($addressDetails['values'][0])) {
      $addressResult = $addressDetails['values'][0];
      $billingDetails['billing_street_address-5'] = isset($addressResult['street_address']) ? $addressResult['street_address']:'';
      $billingDetails['billing_city-5'] = isset($addressResult['city']) ? $addressResult['city']:'';
      $billingDetails['billing_country_id-5'] = isset($addressResult['country_id']) ? $addressResult['country_id']:'';
      $billingDetails['billing_state_province_id-5'] = isset($addressResult['state_province_id']) ? $addressResult['state_province_id']:'';
      $billingDetails['billing_postal_code-5'] = isset($addressResult['postal_code']) ? $addressResult['postal_code']:'';
    } elseif ($isBilling) {
      //MV #4441 using this same funation to get main address
      $billingDetails = self::getBillingDetailsForContact($contactId, FALSE);
    } else {
      // If billing address is not availble
      // Set default country and state
      $config = CRM_Core_Config::singleton();
      $billingDetails['billing_country_id-5'] = $config->defaultContactCountry;
      $billingDetails['billing_state_province_id-5'] = $config->defaultContactStateProvince;
    }

    return $billingDetails;
  }

  /**
   * Function to insert/update warning message of payment processors
   */
  public static function setProcessorWarningMessage($processorId, $warningMessage = '') {
    $updated = FALSE;

    if (!$processorId) {
      CRM_Core_Error::debug_var('ProcessorId not received to set warningMessage ', $warningMessage);
      return $updated;
    }

    $customDetailsTable = self::PAYMENTPROCESSOR_CUSTOMDETAILS_TABLE;

    // First check if warning message already exists for the payment processor
    $sql_params = array(
      1 => array($processorId, 'String'),
      2 => array($warningMessage, 'String')
    );
    $checkSql =  "SELECT * FROM {$customDetailsTable} WHERE payment_processor_id = %1";
    $checkDao = CRM_Core_DAO::executeQuery($checkSql, $sql_params);

    // update the existing row, if already found, else create new row
    if ($checkDao->fetch()) {
      $sql = "UPDATE {$customDetailsTable} SET warning_message = %2 WHERE payment_processor_id = %1";
    } else {
      $sql = "INSERT INTO {$customDetailsTable} (payment_processor_id, warning_message)
      VALUES (%1, %2)";
    }
    $dao = CRM_Core_DAO::executeQuery($sql, $sql_params);

    $updated = TRUE;
    return $updated;
  }
}

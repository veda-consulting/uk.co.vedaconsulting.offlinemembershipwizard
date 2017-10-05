<?php

require_once 'CRM/Core/Form.php';

/**
 * Form controller class
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC43/QuickForm+Reference
 */
class CRM_Offlinemembershipwizard_Form_Wizard_Confirm extends CRM_Core_Form {
  protected $_paymentProcessor = NULL;
  function preProcess() {
    parent::preProcess( );
  }
  
  public function getTitle() {
    return ts('Confirmation');
  }

  function buildQuickForm() {
    $membership = $this->get('membership');
    $contribution = $this->get('contribution');

    $contributionDetails = array();
    $frequencies = CRM_Core_OptionGroup::values('recur_frequency_units', FALSE, FALSE, FALSE, NULL, 'name');
    $contributionDetails['frequency'] = ucwords($contribution['frequency_unit']);
    $contributionDetails['amount'] = CRM_Utils_Money::format($contribution['amount'], $contribution['currency']);
    $contributionDetails['number_of_payments'] = $contribution['number_of_payments'];

    $discountPriceSet = $this->get('priceSet');
    list($lineItems, $totalAmount) = CRM_Offlinemembershipwizard_Utils::prepareLineItems($membership, $discountPriceSet);

    $this->assign('lineItems', $lineItems);
    $this->assign('totalAmount', $totalAmount);
    $this->assign('contributionDetails', $contributionDetails);

    //$cbase = new CRM_Contribute_Form_ContributionBase();
    $this->_params = $contribution;
    $this->_amount = $contribution['amount'];
    if ($membership['payment_processor'] == 'cheque' OR $membership['payment_processor'] == 'cash') {

    } else {
      $this->_contributeMode = 'direct';
      $paymentProcessors = CRM_Financial_BAO_PaymentProcessor::getPaymentProcessors();
      if (isset($paymentProcessors[$membership['payment_processor']])) {
        $this->_paymentProcessor = $paymentProcessors[$membership['payment_processor']];
      }
      $this->_bltID = 5;

      $this->assignBillingName($this->_params);
      $this->assign('address', CRM_Utils_Address::getFormattedBillingAddressFieldsFromParameters(
        $this->_params,
        $this->_bltID
      ));

      $assignCCInfo = FALSE;
      if ($this->_amount > 0.0) {
        $assignCCInfo = TRUE;
      }

      //MV #4443, Payment fields are construct from SEPA getPaymentFormFieldsMetadata() based on SEPA setting.
      //For UK Account & sort code, we dont get field `bank_identification_number`, `bank_account_number` and `bank_name`
      if (method_exists('CRM_Sepa_Logic_Settings', 'getSetting')) {
        $ukbankacsc = CRM_Sepa_Logic_Settings::getSetting("is_ukbank_acsc");
        if ($ukbankacsc && isset($this->_params['ukbank_account_number'])) {
          $this->assign('ukbankacsc', $ukbankacsc);
          $this->assign('ukbank_sort_code', $this->_params['ukbank_sort_code']);
          $this->assign('ukbank_account_number', $this->_params['ukbank_account_number']);
          //Set null params to avoid warnings
          $this->_params['bank_identification_number'] = NULL;
          $this->_params['bank_name'] = NULL;
          $this->_params['bank_account_number'] = NULL;
        }
      }

      if ($this->_contributeMode == 'direct' && $assignCCInfo) {
        if ($this->_paymentProcessor &&
          $this->_paymentProcessor['payment_type'] & CRM_Core_Payment::PAYMENT_TYPE_DIRECT_DEBIT
        ) {
          $this->assign('account_holder', $this->_params['account_holder']);
          $this->assign('bank_identification_number', $this->_params['bank_identification_number']);
          $this->assign('bank_name', $this->_params['bank_name']);
          $this->assign('bank_account_number', $this->_params['bank_account_number']);
        }
        else {
          $date = CRM_Utils_Date::format(CRM_Utils_Array::value('credit_card_exp_date', $this->_params));
          $date = CRM_Utils_Date::mysqlToIso($date);
          $this->assign('credit_card_exp_date', $date);
          $this->assign('credit_card_number',
            CRM_Utils_System::mungeCreditCard(CRM_Utils_Array::value('credit_card_number', $this->_params))
          );
        }
      }
    }

    $this->assign('paymentProcessor', $this->_paymentProcessor);

    $this->addButtons(array(
      array(
        'type' => 'back',
        'name' => ts('Back'),
      ),
      array(
        'type' => 'next',
        'name' => ts('Submit'),
        'isDefault' => TRUE,
      ),
    ));

    parent::buildQuickForm();
  }

  function postProcess() {

    $membership = $this->get('membership');
    $contribution = $this->get('contribution');
    $currentDate = date('YmdHis');
    $paymentProcessorId = '';

    //Update billing address
    $billingAddressParams = array();
    // Check if billing address exists for the contact
    $addressParams = array(
      'contact_id' => $membership['contactID'],
      'is_billing' => 1,
      'sequential' => 1,
    );
    $addressDetails = CRM_Offlinemembershipwizard_Utils::CiviCRMAPIWrapper('Address', 'get', $addressParams);
    if (!empty($addressDetails['values'][0])) {
      // Billing address already exists, so get the address id
      $billingAddressParams['id'] = $addressDetails['values'][0]['id'];
    }
    // Prepare billing address
    $billingAddressParams['contact_id'] = $membership['contactID'];
    $billingAddressParams['is_billing'] = 1; // Is Billing
    $billingAddressParams['location_type_id'] = 5; // Location - Billing
    $billingAddressParams['street_address'] = isset($contribution['billing_street_address-5']) ? $contribution['billing_street_address-5']:'';
    $billingAddressParams['city'] = isset($contribution['billing_city-5']) ? $contribution['billing_city-5']:'';
    $billingAddressParams['country_id'] = isset($contribution['billing_country_id-5']) ? $contribution['billing_country_id-5']:'';
    $billingAddressParams['state_province_id'] = isset($contribution['billing_state_province_id-5']) ? $contribution['billing_state_province_id-5']:'';
    $billingAddressParams['postal_code'] = isset($contribution['billing_postal_code-5']) ? $contribution['billing_postal_code-5']:'';
    $addressResult = CRM_Offlinemembershipwizard_Utils::CiviCRMAPIWrapper('Address', 'create', $billingAddressParams);

    // Prepare payment params
    $this->_params['contactID'] = $membership['contactID'];
    $this->_params['amount'] = $contribution['totalAmount'];
    $this->_params['currencyID'] = $contribution['currency'];
    $form->_params['receive_date'] = $currentDate;
    $this->_params['is_pay_later'] = 0;
    if ($membership['payment_processor'] == 'cheque') {
      $this->_params['is_pay_later'] = 1;
    }
    $this->_params['description'] = $membership['source'];
    $this->_params['payment_processor_id'] = 0;
    if (!empty($membership['payment_processor'])) {
      $this->_params['payment_processor_id'] = $paymentProcessorId = $membership['payment_processor'];
      $this->_params['payment_processor'] = $membership['payment_processor'];
    }
    
    $campaignId = '';
    if (isset($membership['campaign_id'])) {
      $campaignId = $membership['campaign_id'];
    }
    //print_r ($membership);exit;
    //print_r ($contribution);

    $checkNumber =  '';
    $paymentInstrumentId = $membership['payment_instrument_id'];
    if ($membership['payment_processor'] == 'cheque') {
      $checkNumber = $contribution['check_number'];
    }

    /*if ($membership['payment_processor'] == 'cheque' OR $membership['payment_processor'] == 'cash') {
      switch ($membership['payment_processor']) {
        case 'cheque':
          $paymentInstrumentId = CRM_Offlinemembershipwizard_Utils::CIVICRM_PAYMENT_INSTRUMENT_ID_CHEQUE;
          break;
        
        case 'cash':
          $paymentInstrumentId = CRM_Offlinemembershipwizard_Utils::CIVICRM_PAYMENT_INSTRUMENT_ID_CASH;
          break;  

        default:
          $paymentInstrumentId = CRM_Offlinemembershipwizard_Utils::CIVICRM_PAYMENT_INSTRUMENT_ID_CASH;
          break;
      }
      $checkNumber = $contribution['check_number'];
    } else {
      $paymentProcessors = CRM_Financial_BAO_PaymentProcessor::getPaymentProcessors();
      $this->_paymentProcessor = $paymentProcessors[$membership['payment_processor']];
      $paymentInstrumentId = $this->_paymentProcessor['payment_instrument_id'];
      $paymentProcessorId = $this->_paymentProcessor['id'];
    }*/
    
    // Create recurring record
    $trxn_id = $invoice_id = md5(uniqid(rand(), TRUE ));
    
    $contribRecurParams = array(
      'contact_id' => $membership['contactID'],
      'amount' => $contribution['amount'],
      'currency' => $contribution['currency'],
      'frequency_unit' => $contribution['frequency_unit'],
      'frequency_interval' => 1,
      'start_date' => $currentDate,
      'create_date' => $currentDate,
      'modified_date' => $currentDate,
      'trxn_id' => $trxn_id,
      'invoice_id' => $invoice_id,
      'contribution_status_id' => 5, // In Progress
      'is_test' => 0,
      'cycle_day' => '',
      'auto_renew' => '',
      'payment_processor_id' => $paymentProcessorId,
      'payment_instrument_id' => $paymentInstrumentId,
      'financial_type_id' => 2, // Member dues
      'campaign_id' => $campaignId,
    );

    // Create recurring record
    $recurResult = CRM_Offlinemembershipwizard_Utils::CiviCRMAPIWrapper('ContributionRecur', 'create', $contribRecurParams);

    // Update contribution with contribution_recur_id
    $recurId = '';
    if (!empty($recurResult['id'])) {
      /*CRM_Offlinemembershipwizard_Utils::CiviCRMAPIWrapper('contribution', 'create', array(
          'id' => $id,
          'invoice_id' => $invoice_id,
          'trxn_id' => $trxn_id,
          'contribution_recur_id' => $recurResult['id'],
        ));*/
      $recurId = $recurResult['id'];
      $this->_params['contributionRecurID'] = $recurResult['id'];
    }

    $discountPriceSet = $this->get('priceSet');
    list($lineItems, $totalAmount) = CRM_Offlinemembershipwizard_Utils::prepareLineItems($membership, $discountPriceSet);
    $membershipLabels = array();
    foreach($lineItems as $lineItem) {
      $membershipLabels[] = $lineItem['pricefieldvalue_label'];
    }

    $admin = CRM_Offlinemembershipwizard_Utils::getLoggedInUserContactName();
    $membershipLabel = implode(', ', $membershipLabels);
    $generalLabel = " : Offline Membership Wizard (by {$admin})";
    $membershipLabel .= $generalLabel;

    // Record first payment
    if ($contribution['record_contribution'] == 1) {
      // Prepare contribution params
      $contributionParams = array(
        'contact_id' => $membership['contactID'],
        'financial_type_id' => 2, // Member dues
        'payment_instrument_id' => $paymentInstrumentId,
        'receive_date' => $currentDate,
        'total_amount' => $contribution['amount'],
        'trxn_id' => $trxn_id,
        'invoice_id' => $invoice_id,
        'currency' => $contribution['currency'],
        'contribution_status_id' => 1, // Completed
        'source' => $membershipLabel,
        'contribution_recur_id' => $recurId,
        'check_number' => $checkNumber,
        'is_test' => 0,
        'campaign_id' => $campaignId,
        'payment_processor' => $paymentProcessorId,
      );

      $contribAction = 'create';

      $contribResult = CRM_Offlinemembershipwizard_Utils::CiviCRMAPIWrapper('Contribution', $contribAction, $contributionParams);

      if (!empty($contribResult['id'])) {
        $this->_params['contributionID'] = $this->_params['contribution_id'] = $contribResult['id'];
      }

      // Get the default line item created when contribution is created
      $getLineItemParams = array(
        'entity_table' => 'civicrm_contribution',
        'entity_id' => $contribResult['id'],
        'contribution_id' => $contribResult['id'],
      );
      $getLineItemResult = CRM_Offlinemembershipwizard_Utils::CiviCRMAPIWrapper('LineItem', 'get', $getLineItemParams);

      // Delete the default line item created when contribution is created
      $delLineItemParams = array(
        'id' => $getLineItemResult['id'],
      );
      $delLineItemResult = CRM_Offlinemembershipwizard_Utils::CiviCRMAPIWrapper('LineItem', 'delete', $delLineItemParams);
    }
    //if (!empty($this->_paymentProcessor['object'])) {
    //  $contribAction = 'transact';
    //}

    $this->setVar('_contactID', $membership['contactID']);
    $paymentParams = $this->_params;
    $paymentParams['is_recur'] = 1;

    if (!empty($membership['payment_processor'])) {
      $paymentParams['payment_processor_mode'] = empty($contributionParams['is_test']) ? 'live' : 'test';
      $paymentProcessor = CRM_Financial_BAO_PaymentProcessor::getPayment($paymentParams['payment_processor'], $paymentParams['payment_processor_mode']);
      $paymentProcessor['object']->doPayment($paymentParams);
    }

    // Update mandate as RCUR and FRST
    // First get mandate reference from recurring contribution
    if ($this->_paymentProcessor['payment_processor_type'] == 'SEPA_Direct_Debit') {
      if (!empty($recurId)) {
        $recurParams = array(
          'id' => $recurId,
          'sequential' => 1,
        );
        $recurResult = CRM_Offlinemembershipwizard_Utils::CiviCRMAPIWrapper('ContributionRecur' , 'get', $recurParams);
        $recurDetails = $recurResult['values'][0];
        if (!empty($recurDetails['trxn_id'])) {
          $mandateRef = $recurDetails['trxn_id'];

          // Get mandate ID using ref
          $mandateParams = array(
            'reference' => $mandateRef,
            'sequential' => 1,
          );
          $mandateResult = CRM_Offlinemembershipwizard_Utils::CiviCRMAPIWrapper('SepaMandate', 'get', $mandateParams);
          $mandateDetails = $mandateResult['values'][0];

          // Update mandate
          if (!empty($mandateDetails['id'])) {
            $mandateParams = array(
              'id' => $mandateDetails['id'],
              'entity_id' => $recurId, // Contribution Recurring id
              'contact_id' => $membership['contactID'], // Contact id
              'type' => 'RCUR', // Set as Recurring
              'status' => 'FRST', // Set as first payment
              'iban' => $contribution['bank_account_number'], // IBAN
              'bic' => $contribution['bank_identification_number'], // BIC
            );
            $mandateResult = CRM_Offlinemembershipwizard_Utils::CiviCRMAPIWrapper('SepaMandate', 'create', $mandateParams);

            // Create activity for DD notification letter
            CRM_Sepautils_Utils::createActivityForMandate($mandateDetails['id']);
          }
        }
      }
    }

    // Prepare membership params
    $membershipParams = array(
      'contact_id' => $membership['contactID'],
      //'join_date' => $dates['join_date'],
      //'start_date' => $dates['start_date'],
      //'end_date' => $dates['end_date'],
      'source' => $membershipLabel,
      'status_id' => 1, // New
      'is_test' => 0,
      'contribution_recur_id' => $recurId,
      'campaign_id' => $campaignId,
    );

    $noOfPayments = $contribution['number_of_payments'];

    $AllLineItemsParams = array();
    $this->_memTypeSelected = array();
    foreach($lineItems as $lineItem) {

      $memResult = array();

      // Check if line item is linked with membership
      if (!empty($lineItem['membership_type_id'])) {
        // Calculate start_date and end_date for membership type
        $dates = CRM_Member_BAO_MembershipType::getDatesForMembershipType($lineItem['membership_type_id'], $membership['join_date']);

        $membershipParams['source'] = substr($lineItem['pricefieldvalue_label'].$generalLabel, 0, 127);//This field has a maxlength of 128 characters
        $membershipParams['membership_type_id'] = $lineItem['membership_type_id'];
        $membershipParams['join_date'] = $dates['join_date'];
        $membershipParams['start_date'] = $dates['start_date'];
        $membershipParams['end_date'] = $dates['end_date'];
        $membershipParams['campaign_id'] = $campaignId;

        if (isset($membership['is_override']) && $membership['is_override'] == 1) {
          $membershipParams['status_id'] = $membership['status_id'];
          $membershipParams['is_override'] = 1;
        }

        // Create membership record
        $memResult = CRM_Offlinemembershipwizard_Utils::CiviCRMAPIWrapper('Membership', 'create', $membershipParams);

        // for cividiscount to validate mem types
        if (!empty($memResult['id']) && empty($this->_id)) {
          // we going to link discount code with only first membership for now
          $this->_memTypeSelected[] = $lineItem['membership_type_id'];
          $this->_id = $memResult['id'];
        }
      }

      // Flag to create line item
      // Some line items are created when membership/contribution is created
      $createLineItem = TRUE;

      // Update membership line item with contribution id
      if (!empty($memResult['id']) &&  isset($contribResult['id']) && !empty($contribResult['id'])) {
        $getMemLineItemSql = "SELECT * FROM civicrm_line_item where entity_table  = 'civicrm_membership' AND entity_id = %1 AND contribution_id IS NULL";
        $getMemLineItemParams = array(
          '1' => array($memResult['id'], 'Integer'),
        );
        $getMemLineItemObj = CRM_Core_DAO::executeQuery($getMemLineItemSql, $getMemLineItemParams);
        if ($getMemLineItemObj->fetch()) {

          $memLineItemAmount = $lineItem['amount'] / $noOfPayments;
          $memLineItemAmount = CRM_Utils_Money::format($memLineItemAmount, NULL, NULL, TRUE);

          $updateMemLineItemSql = "UPDATE civicrm_line_item SET contribution_id = %1, line_total = %2, label = %3 WHERE id = %4";
          $updateMemLineItemParams = array(
            '1' => array($contribResult['id'], 'Integer'),
            '2' => array($memLineItemAmount, 'String'),
            '3' => array($lineItem['pricefieldvalue_label'], 'String'),
            '4' => array($getMemLineItemObj->id, 'Integer'),
          );
          CRM_Core_DAO::executeQuery($updateMemLineItemSql, $updateMemLineItemParams);

          $createLineItem = FALSE;
        }
      }

      $lineItemAmount = $lineItem['amount'] / $noOfPayments;
      $lineItemAmount = CRM_Utils_Money::format($lineItemAmount, NULL, NULL, TRUE);

      // Create line items
      if ($createLineItem && !empty($memResult['id']) && !empty($contribResult['id'])) {
        $lineItemParams = array(
          'entity_table' => 'civicrm_membership',
          'entity_id' => $memResult['id'],
          'contribution_id' => $contribResult['id'],
          'price_field_id' => $lineItem['pricefield_id'],
          'label' => $lineItem['pricefieldvalue_label'],
          'qty' => '1.00',
          'unit_price' => $lineItem['amount'],
          'line_total' => $lineItemAmount,
          'price_field_value_id' => $lineItem['pricefieldvalue_id'],
          'financial_type_id' => $lineItem['financial_type_id']
        );

        // This is a donation
        if (empty($lineItem['membership_type_id'])) {
          $lineItemParams['entity_id'] = $contribResult['id'];
          $lineItemParams['entity_table'] = 'civicrm_contribution';
        }
        $lineItemResult = CRM_Offlinemembershipwizard_Utils::CiviCRMAPIWrapper('LineItem', 'create', $lineItemParams);
        $AllLineItemsParams[] = $lineItemParams;
      }


      // Create membership payment
      if (!empty($memResult['id']) && !empty($contribResult['id'])) {
        $memPaymentParams = array(
          'membership_id' => $memResult['id'],
          'contribution_id' => $contribResult['id'],
        );
        CRM_Offlinemembershipwizard_Utils::CiviCRMAPIWrapper('MembershipPayment', 'create', $memPaymentParams);
      }
    }

    $subStartDay = date('d', strtotime($dates['start_date']));
    $subStartDate = date('YmdHis', strtotime($dates['start_date']));
    // Create subscription
    $subscriptionParams = array(
      'frequency_unit' => $contribution['frequency_unit'],
      'frequency_interval' => 1,
      'frequency_day' => $subStartDay,
      'installments' => $contribution['number_of_payments'],
      'financial_type_id' => 2,
      'initial_reminder_day' => 5,
      'max_reminders' => 1,
      'additional_reminder_day' => 5,
      'contribution_page_id' => '',
      'campaign_id' => '',
      'amount' => $contribution['totalAmount'],
      'currency' => $contribution['currency'],
      'original_installment_amount' => $contribution['amount'],
      'create_date' => $currentDate,
      'scheduled_date' => $currentDate,
      'start_date' => $subStartDate,
      'acknowledge_date' => 'NULL',
      'cancel_date' => 'NULL',
      'contact_id' => $membership['contactID'],
      'is_pledge_pending' => '',
      'status_id' => 5, // In progress
    );
    $subscription = CRM_Subscription_BAO_Subscription::create($subscriptionParams);

    // Mark first subscription payment as completed
    if (!empty($contribResult['id'])) {
      $updatePaymentParams = array(
        'subscription_id' => $subscription->id,
        'contribution_id' => $contribResult['id'],
      );

      CRM_Civisubscription_Utils::updateSubscriptionPayment($subscription->id, $contribResult['id']);
    }

    // Create order
    $orderParams = array(
      'subscription_id' => $subscription->id,
      'contribution_recur_id' => $recurId,
      'amount' => $contribution['totalAmount'],
      'currency' => $contribution['currency'],
      'create_date' => $currentDate,
      'status_id' => 1, // Completed
      'is_test' => 0,
      'line_items' => $AllLineItemsParams,
    );
    $order = CRM_Subscription_BAO_Order::create($orderParams);

    $url = CRM_Utils_System::url('civicrm/contact/view', 'reset=1&cid='.$membership['contactID'].'&selectedChild=member');
    $session = CRM_Core_Session::singleton();
    $session->pushUserContext($url);
  }
}


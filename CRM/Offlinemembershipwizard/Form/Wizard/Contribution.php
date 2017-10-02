<?php

require_once 'CRM/Core/Form.php';

/**
 * Form controller class
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC43/QuickForm+Reference
 */
class CRM_Offlinemembershipwizard_Form_Wizard_Contribution extends CRM_Core_Form {
  function preProcess() {
    parent::preProcess( );
  }
  
  public function getTitle() {
    return ts('Payment Details');
  }

  function buildQuickForm() {

    $membership = $this->get('membership');
    $discountPriceSet = $this->get('priceSet');
    list($lineItems, $totalAmount) = CRM_Offlinemembershipwizard_Utils::prepareLineItems($membership, $discountPriceSet);

    $this->assign('lineItems', $lineItems);
    $this->assign('totalAmount', $totalAmount);
    $this->addElement('hidden', 'totalAmount', $totalAmount);

    $defaults = array();
    $defaults = CRM_Offlinemembershipwizard_Utils::getBillingDetailsForContact($membership['contactID']);
    $this->assign('defaultCountry', $defaults['billing_country_id-5']);
    $this->assign('defaultStateProvince', $defaults['billing_state_province_id-5']);
    //print_r ($defaults);exit;
    
    $frequencyUnit = $this->add('select', 'frequency_unit',
      NULL,
      array('' => ts('- select -')) + CRM_Core_OptionGroup::values('recur_frequency_units', FALSE, FALSE, FALSE, NULL, 'name'),
      TRUE, NULL
    );

    // Display memberships is contact summary?
    $this->addElement(
      'checkbox', 
      'record_contribution', 
      ts('Record Contribution?')
    );

    // add form elements
    $this->add(
      'select', // field type
      'frequency_unit', // field name
      ts('Frequency'), // field label
      array('' => ts('- select -')) + CRM_Core_OptionGroup::values('recur_frequency_units', FALSE, FALSE, FALSE, NULL, 'name'),
      TRUE // is required
    );

    $this->addMoney('amount',
      ts('Amount'),
      TRUE,
      array('size' => 5),
      TRUE, 'currency', NULL, FALSE
    );

    // Account number field
    $this->addElement(
      'text',
      'number_of_payments',
      ts('Number of Payments'),
      array('size' => 2),
      true
    );

    $this->addButtons(array(
      array(
        'type' => 'back',
        'name' => ts('Back'),
      ),
      array(
        'type' => 'submit',
        'name' => ts('Next >>'),
        'isDefault' => TRUE,
      ),
    ));

    if (!empty($defaults)) {
      $this->setDefaults($defaults);
      //$this->freeze(array('amount'));
    }

    $attributes = CRM_Core_DAO::getAttribute('CRM_Contribute_DAO_Contribution');

    // If paying by cheque add check_number field
    if ($membership['payment_processor'] == 'cheque') {
      $this->assign('paymentMethod', 'cheque');
      $this->add('text', 'check_number', ts('Check Number'), $attributes['check_number']);
    }

    if (!empty($membership['payment_processor'])) {

      $this->setVar('_paymentProcessorID', $membership['payment_processor']);
      $this->assign('paymentProcessorID', $membership['payment_processor']);
      $this->_defaults['payment_processor_id'] = $membership['payment_processor'];

      $paymentProcessors = CRM_Financial_BAO_PaymentProcessor::getPaymentProcessors();
      $this->_paymentProcessor = $paymentProcessors[$membership['payment_processor']];
      $this->_bltID = 5;

      // Build payment processor form
      CRM_Core_Payment_ProcessorForm::buildQuickForm($this);

      $smarty = CRM_Core_Smarty::singleton();
      //$billingFields = CRM_Core_Payment_Form::getBillingAddressFields($processor, 5);
      //print_r ($billingFields);exit;
      //CRM_Core_Payment_Form::setPaymentFieldsByProcessor($this, $processor);
      $this->assignBillingType();
      CRM_Core_Payment_Form::setPaymentFieldsByProcessor($this, $this->_paymentProcessor);
    }


    $this->addFormRule(array('CRM_Offlinemembershipwizard_Form_Wizard_Contribution', 'formRule'), $this);

    parent::buildQuickForm();
  }

  /**
   * Validation.
   */
  public static function formRule($params, $files, $self) {
    $errors = array();

    // Validate bank account if SEPA payment processor  
    //if ($self->_paymentProcessor['payment_processor_type'] == 'SEPA_Direct_Debit') {
    if (isset($self->_paymentProcessor) &&
        $self->_paymentProcessor['payment_type'] & CRM_Core_Payment::PAYMENT_TYPE_DIRECT_DEBIT
      ) {
      $ukbankacsc = CRM_Sepa_Logic_Settings::getSetting("is_ukbank_acsc");
      if ($ukbankacsc) {
        $accountNum = CRM_Utils_Array::value('ukbank_account_number', $params);
        $sortCode   = CRM_Utils_Array::value('ukbank_sort_code', $params);
        $result = CRM_Sepa_Logic_Verification::verifyAccountSortCode($accountNum, $sortCode);
        if ($result['is_error'] == 1) {
          $errors['ukbank_account_number'] = $result['error']['msg'];
          $errors['ukbank_sort_code']      = $result['error']['msg'];
        }
      }
    }

    return empty($errors) ? TRUE : $errors;
  }

  function postProcess() {
    $values = $this->exportValues();
    if (!isset($values['record_contribution'])) {
      $values['record_contribution'] = 0;
    }

    if (isset($this->_paymentProcessor) &&
        $this->_paymentProcessor['payment_type'] & CRM_Core_Payment::PAYMENT_TYPE_DIRECT_DEBIT
      ) {
      $ukbankacsc = CRM_Sepa_Logic_Settings::getSetting("is_ukbank_acsc");
      if ($ukbankacsc) {
        $accountNum = CRM_Utils_Array::value('ukbank_account_number', $values);
        $sortCode   = CRM_Utils_Array::value('ukbank_sort_code', $values);
        $result = CRM_Sepa_Logic_Verification::verifyAccountSortCode($accountNum, $sortCode);
        if ($result['is_error'] != 1) {
          $values['bank_account_number'] = $result['fields']['IBAN'];
          $values['bank_identification_number'] = $result['fields']['BankBIC'];
          $values['bank_name'] = $result['fields']['Bank'];
        }
      }
    }

    $this->set("contribution", $values);

    $url = CRM_Utils_System::url('civicrm/member/offlinemembershipwizard', '_qf_Confirm_display=true&qfKey='.$values['qfKey']);

    CRM_Utils_System::redirect($url);
  }
}

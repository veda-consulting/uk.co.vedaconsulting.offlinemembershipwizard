<?php

require_once 'CRM/Core/Form.php';

/**
 * Form controller class
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC43/QuickForm+Reference
 */
class CRM_Offlinemembershipwizard_Form_Wizard_Membership extends CRM_Offlinemembershipwizard_Form {
  protected $_memType = NULL;

  protected $_onlinePendingContributionId;

  public $_mode;

  public $_contributeMode = 'direct';

  protected $_recurMembershipTypes;

  protected $_memTypeSelected;

  /**
   * Display name of the member.
   *
   * @var string
   */
  protected $_memberDisplayName = NULL;

  /**
   * email of the person paying for the membership (used for receipts)
   */
  protected $_memberEmail = NULL;

  /**
   * Contact ID of the member.
   *
   * @var int
   */
  public $_contactID;

  /**
   * Display name of the person paying for the membership (used for receipts)
   *
   * @var string
   */
  protected $_contributorDisplayName = NULL;

  /**
   * email of the person paying for the membership (used for receipts)
   */
  protected $_contributorEmail = NULL;

  /**
   * email of the person paying for the membership (used for receipts)
   *
   * @var int
   */
  protected $_contributorContactID = NULL;

  /**
   * ID of the person the receipt is to go to.
   *
   * @var int
   */
  protected $_receiptContactId = NULL;

  /**
   * Keep a class variable for ALL membership IDs so
   * postProcess hook function can do something with it
   *
   * @var array
   */
  protected $_membershipIDs = array();

  /**
   * An array to hold a list of date fields on the form
   * so that they can be converted to ISO in a consistent manner
   *
   * @var array
   */
  protected $_dateFields = array(
    'receive_date' => array('default' => 'now'),
  );

  /**
   * Form preProcess function.
   *
   * @throws \Exception
   */
  public function preProcess() {
    // This string makes up part of the class names, differentiating them (not sure why) from the membership fields.
    $this->assign('formClass', 'membership');
    parent::preProcess();
    // get price set id.
    $this->_priceSetId = CRM_Utils_Array::value('priceSetId', $_GET);
    $this->set('priceSetId', $this->_priceSetId);
    $this->assign('priceSetId', $this->_priceSetId);

    if ($this->_action & CRM_Core_Action::ADD) {
      if ($this->_contactID) {
        //check whether contact has a current membership so we can alert user that they may want to do a renewal instead
        $contactMemberships = array();
        $memParams = array('contact_id' => $this->_contactID);
        CRM_Member_BAO_Membership::getValues($memParams, $contactMemberships, TRUE);
        $cMemTypes = array();
        foreach ($contactMemberships as $mem) {
          $cMemTypes[] = $mem['membership_type_id'];
        }
        if (count($cMemTypes) > 0) {
          $memberorgs = CRM_Member_BAO_MembershipType::getMemberOfContactByMemTypes($cMemTypes);
          $mems_by_org = array();
          foreach ($contactMemberships as $mem) {
            $mem['member_of_contact_id'] = CRM_Utils_Array::value($mem['membership_type_id'], $memberorgs);
            if (!empty($mem['membership_end_date'])) {
              $mem['membership_end_date'] = CRM_Utils_Date::customformat($mem['membership_end_date']);
            }
            $mem['membership_type'] = CRM_Core_DAO::getFieldValue('CRM_Member_DAO_MembershipType',
              $mem['membership_type_id'],
              'name', 'id'
            );
            $mem['membership_status'] = CRM_Core_DAO::getFieldValue('CRM_Member_DAO_MembershipStatus',
              $mem['status_id'],
              'label', 'id'
            );
            $mem['renewUrl'] = CRM_Utils_System::url('civicrm/contact/view/membership',
              "reset=1&action=renew&cid={$this->_contactID}&id={$mem['id']}&context=membership&selectedChild=member"
              . ($this->_mode ? '&mode=live' : '')
            );
            $mem['membershipTab'] = CRM_Utils_System::url('civicrm/contact/view',
              "reset=1&force=1&cid={$this->_contactID}&selectedChild=member"
            );
            $mems_by_org[$mem['member_of_contact_id']] = $mem;
          }
          $this->assign('existingContactMemberships', $mems_by_org);
        }
      }
      else {
        // In standalone mode we don't have a contact id yet so lookup will be done client-side with this script:
        $resources = CRM_Core_Resources::singleton();
        $resources->addScriptFile('civicrm', 'templates/CRM/Member/Form/MembershipStandalone.js');
        $passthru = array(
          'typeorgs' => CRM_Member_BAO_MembershipType::getMembershipTypeOrganization(),
          'memtypes' => CRM_Core_PseudoConstant::get('CRM_Member_BAO_Membership', 'membership_type_id'),
          'statuses' => CRM_Core_PseudoConstant::get('CRM_Member_BAO_Membership', 'status_id'),
        );
        $resources->addSetting(array('existingMems' => $passthru));
      }
    }

    if (!$this->_memType) {
      $params = CRM_Utils_Request::exportValues();
      if (!empty($params['membership_type_id'][1])) {
        $this->_memType = $params['membership_type_id'][1];
      }
    }
    // when custom data is included in this page
    if (!empty($_POST['hidden_custom'])) {
      CRM_Custom_Form_CustomData::preProcess($this, NULL, $this->_memType, 1, 'Membership', $this->_id);
      CRM_Custom_Form_CustomData::buildQuickForm($this);
      CRM_Custom_Form_CustomData::setDefaultValues($this);
    }

    $this->setPageTitle(ts('Membership Details'));
  }

  public function getTitle() {
    return ts('Membership Details');
  }

  /**
   * Build the form object.
   */
  public function buildQuickForm() {

    CRM_Utils_System::setTitle(ts('Offline Membership Wizard'));

    $this->assign('taxRates', json_encode(CRM_Core_PseudoConstant::getTaxRates()));

    $this->assign('currency', CRM_Core_Config::singleton()->defaultCurrencySymbol);
    $invoiceSettings = Civi::settings()->get('contribution_invoice_settings');
    $invoicing = CRM_Utils_Array::value('invoicing', $invoiceSettings);
    if (isset($invoicing)) {
      $this->assign('taxTerm', CRM_Utils_Array::value('tax_term', $invoiceSettings));
    }
    // build price set form.
    $buildPriceSet = FALSE;
    if ($this->_priceSetId || !empty($_POST['price_set_id'])) {
      if (!empty($_POST['price_set_id'])) {
        $buildPriceSet = TRUE;
      }
      $getOnlyPriceSetElements = TRUE;
      if (!$this->_priceSetId) {
        $this->_priceSetId = $_POST['price_set_id'];
        $getOnlyPriceSetElements = FALSE;
      }

      $this->set('priceSetId', $this->_priceSetId);
      CRM_Price_BAO_PriceSet::buildPriceSet($this);

      $optionsMembershipTypes = array();
      foreach ($this->_priceSet['fields'] as $pField) {
        if (empty($pField['options'])) {
          continue;
        }
        foreach ($pField['options'] as $opId => $opValues) {
          $optionsMembershipTypes[$opId] = CRM_Utils_Array::value('membership_type_id', $opValues, 0);
        }
      }

      $this->assign('autoRenewOption', CRM_Price_BAO_PriceSet::checkAutoRenewForPriceSet($this->_priceSetId));

      $this->assign('optionsMembershipTypes', $optionsMembershipTypes);
      $this->assign('contributionType', CRM_Utils_Array::value('financial_type_id', $this->_priceSet));

      // get only price set form elements.
      if ($getOnlyPriceSetElements) {
        return;
      }
    }

    // use to build form during form rule.
    $this->assign('buildPriceSet', $buildPriceSet);

    if ($this->_action & CRM_Core_Action::ADD) {
      $buildPriceSet = FALSE;
      $priceSets = CRM_Price_BAO_PriceSet::getAssoc(FALSE, 'CiviMember');
      if (!empty($priceSets)) {
        $buildPriceSet = TRUE;
      }

      if ($buildPriceSet) {
        $this->add('select', 'price_set_id', ts('Price set'),
          array(
            '' => ts('Choose price set'),
          ) + $priceSets,
          NULL, array('onchange' => "buildAmount( this.value );")
        );
      }
      $this->assign('hasPriceSets', $buildPriceSet);
    }

    //need to assign custom data type and subtype to the template
    $this->assign('customDataType', 'Membership');
    $this->assign('customDataSubType', $this->_memType);

    if ($this->_action & CRM_Core_Action::DELETE) {
      $this->addButtons(array(
        array(
          'type' => 'next',
          'name' => ts('Delete'),
          'spacing' => '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;',
          'isDefault' => TRUE,
        ),
        array(
          'type' => 'cancel',
          'name' => ts('Cancel'),
        ),
      ));
      return;
    }

    if ($this->_context == 'standalone') {
      $this->addEntityRef('contact_id', ts('Contact'), array(
        'create' => TRUE,
        'api' => array('extra' => array('email')),
      ), TRUE);
    }

    $selOrgMemType[0][0] = $selMemTypeOrg[0] = ts('- select -');

    // Throw status bounce when no Membership type or priceset is present
    if (CRM_Financial_BAO_FinancialType::isACLFinancialTypeStatus()
      && empty($this->allMembershipTypeDetails) && empty($priceSets)
    ) {
      CRM_Core_Error::statusBounce(ts('You do not have all the permissions needed for this page.'));
    }
    // retrieve all memberships
    $allMembershipInfo = array();
    foreach ($this->allMembershipTypeDetails as $key => $values) {
      if ($this->_mode && empty($values['minimum_fee'])) {
        continue;
      }
      else {
        $memberOfContactId = CRM_Utils_Array::value('member_of_contact_id', $values);
        if (empty($selMemTypeOrg[$memberOfContactId])) {
          $selMemTypeOrg[$memberOfContactId] = CRM_Core_DAO::getFieldValue('CRM_Contact_DAO_Contact',
            $memberOfContactId,
            'display_name',
            'id'
          );

          $selOrgMemType[$memberOfContactId][0] = ts('- select -');
        }
        if (empty($selOrgMemType[$memberOfContactId][$key])) {
          $selOrgMemType[$memberOfContactId][$key] = CRM_Utils_Array::value('name', $values);
        }
      }
      $totalAmount = CRM_Utils_Array::value('minimum_fee', $values);
      //CRM-18827 - override the default value if total_amount is submitted
      if (!empty($this->_submitValues['total_amount'])) {
        $totalAmount = $this->_submitValues['total_amount'];
      }
      // build membership info array, which is used when membership type is selected to:
      // - set the payment information block
      // - set the max related block
      $allMembershipInfo[$key] = array(
        'financial_type_id' => CRM_Utils_Array::value('financial_type_id', $values),
        'total_amount' => CRM_Utils_Money::format($totalAmount, NULL, '%a'),
        'total_amount_numeric' => $totalAmount,
        'auto_renew' => CRM_Utils_Array::value('auto_renew', $values),
        'has_related' => isset($values['relationship_type_id']),
        'max_related' => CRM_Utils_Array::value('max_related', $values),
      );
    }

    $this->assign('allMembershipInfo', json_encode($allMembershipInfo));

    // show organization by default, if only one organization in
    // the list
    if (count($selMemTypeOrg) == 2) {
      unset($selMemTypeOrg[0], $selOrgMemType[0][0]);
    }
    //sort membership organization and type, CRM-6099
    natcasesort($selMemTypeOrg);
    foreach ($selOrgMemType as $index => $orgMembershipType) {
      natcasesort($orgMembershipType);
      $selOrgMemType[$index] = $orgMembershipType;
    }

    $memTypeJs = array(
      'onChange' => "buildMaxRelated(this.value,true); CRM.buildCustomData('Membership', this.value);",
    );

    if (!empty($this->_recurPaymentProcessors)) {
      $memTypeJs['onChange'] = "" . $memTypeJs['onChange'] . "buildAutoRenew(this.value, null, '{$this->_mode}');";
    }

    $this->add('text', 'max_related', ts('Max related'),
      CRM_Core_DAO::getAttribute('CRM_Member_DAO_Membership', 'max_related')
    );

    $sel = &$this->addElement('hierselect',
      'membership_type_id',
      ts('Membership Organization and Type'),
      $memTypeJs
    );

    $sel->setOptions(array($selMemTypeOrg, $selOrgMemType));
    $elements = array();
    if ($sel) {
      $elements[] = $sel;
    }

    $this->applyFilter('__ALL__', 'trim');

    if ($this->_action & CRM_Core_Action::ADD) {
      $this->add('text', 'num_terms', ts('Number of Terms'), array('size' => 6));
    }

    $this->addDate('join_date', ts('Member Since'), FALSE, array('formatType' => 'activityDate'));
    $this->addDate('start_date', ts('Start Date'), FALSE, array('formatType' => 'activityDate'));
    $endDate = $this->addDate('end_date', ts('End Date'), FALSE, array('formatType' => 'activityDate'));
    if ($endDate) {
      $elements[] = $endDate;
    }

    $this->add('text', 'source', ts('Source'),
      CRM_Core_DAO::getAttribute('CRM_Member_DAO_Membership', 'source')
    );

    //CRM-7362 --add campaigns.
    $campaignId = NULL;
    if ($this->_id) {
      $campaignId = CRM_Core_DAO::getFieldValue('CRM_Member_DAO_Membership', $this->_id, 'campaign_id');
    }
    CRM_Campaign_BAO_Campaign::addCampaign($this, $campaignId);

    // Add appeal/medium/channel
    $this->addElement('select', 'campaign_appeal', ts('Appeal'), array('' => ts('- select - ')));
    $this->addElement('select', 'campaign_channel', ts('Channel'), array('' => ts('- select - ')));
    $this->addElement('select', 'campaign_medium', ts('Medium'), array('' => ts('- select - ')));

    if (!$this->_mode) {
      $this->add('select', 'status_id', ts('Membership Status'),
        array('' => ts('- select -')) + CRM_Member_PseudoConstant::membershipStatus(NULL, NULL, 'label')
      );
      $statusOverride = $this->addElement('checkbox', 'is_override',
        ts('Status Override?'), NULL,
        array('onClick' => 'showHideMemberStatus()')
      );
      if ($statusOverride) {
        $elements[] = $statusOverride;
      }

      $this->addElement('checkbox', 'record_contribution', ts('Record Membership Payment?'));

      $this->add('text', 'total_amount', ts('Amount'));
      $this->addRule('total_amount', ts('Please enter a valid amount.'), 'money');

      $this->addDate('receive_date', ts('Received'), FALSE, array('formatType' => 'activityDateTime'));

      $paymentInstruments = CRM_Contribute_PseudoConstant::paymentInstrument();

      //MV #4446 disable First recurring DD payment method
      if ($FRST = CRM_Core_OptionGroup::getValue('payment_instrument', CRM_Offlinemembershipwizard_Utils::SEPA_DD_FIRST_TRANSACTION_OP_NAME, 'name')) {
        unset($paymentInstruments[$FRST]);
      }

      if ($paymentInstruments) {
        $this->add('select', 'payment_instrument_id', ts('Payment Method'),
          array(
            '' => ts('Choose payment method'),
          ) + $paymentInstruments,
          TRUE, array('onchange' => "buildPaymentProcessor( this.value );")
        );
      }

      $paymentProcessors = CRM_Financial_BAO_PaymentProcessor::getAllPaymentProcessors('live');
      $paymentProcessor = $paymentMethodProcessor = $warningMessages = array();
      if (!empty($paymentProcessors)) {
        foreach ($paymentProcessors as $id => $processor) {
          if ($id != 0 && $processor['name'] != 'Manual') {
            $paymentProcessor[$id] = $processor['name'];
            $paymentMethodProcessor[$processor['payment_instrument_id']][$id] = $processor['name'];
            // GK 21092017 - get warning messages of all payment processors from the custom details table
            $customDetailsTable = CRM_Offlinemembershipwizard_Utils::PAYMENTPROCESSOR_CUSTOMDETAILS_TABLE;
            $sql_params = array(
              1 => array($id, 'String')
            );
            $sql = "SELECT * FROM {$customDetailsTable} WHERE payment_processor_id = %1";
            $dao = CRM_Core_DAO::executeQuery($sql, $sql_params);

            $warningMessage = '';
            if ($dao->fetch()) {
              $warningMessage = $dao->warning_message;
            }
            $warningMessages[$id] = $warningMessage;
          }
        }
      }
      $this->assign('paymentMethodProcessor', json_encode($paymentMethodProcessor));
      // Additional payment options (Ex: Cheque, Cash)
      // $paymentProcessor = $paymentProcessor + CRM_Offlinemembershipwizard_Utils::$additionalPaymentOptions;
      $this->addRadio('payment_processor', ts('Payment Processor'), $paymentProcessor, array('allowClear' => TRUE));

      // GK 21092017 - add warning messages of all payment processors
      $this->assign('warningMessages', json_encode($warningMessages));

      /*$this->add('select', 'payment_instrument_id',
        ts('Payment Method'),
        array('' => ts('- select -')) + CRM_Contribute_PseudoConstant::paymentInstrument(),
        TRUE, array('onChange' => "return showHideByValue('payment_instrument_id','4','checkNumber','table-row','select',false);")
      );*/
      $this->add('text', 'trxn_id', ts('Transaction ID'));
      $this->addRule('trxn_id', ts('Transaction ID already exists in Database.'),
        'objectExists', array(
          'CRM_Contribute_DAO_Contribution',
          $this->_id,
          'trxn_id',
        )
      );

      $allowStatuses = array();
      $statuses = CRM_Contribute_PseudoConstant::contributionStatus();
      if ($this->_onlinePendingContributionId) {
        $statusNames = CRM_Contribute_PseudoConstant::contributionStatus(NULL, 'name');
        foreach ($statusNames as $val => $name) {
          if (in_array($name, array(
            'In Progress',
            'Overdue',
          ))
          ) {
            continue;
          }
          $allowStatuses[$val] = $statuses[$val];
        }
      }
      else {
        $allowStatuses = $statuses;
      }
      $this->add('select', 'contribution_status_id',
        ts('Payment Status'), $allowStatuses
      );
      $this->add('text', 'check_number', ts('Check Number'),
        CRM_Core_DAO::getAttribute('CRM_Contribute_DAO_Contribution', 'check_number')
      );
    }
    else {
      //add field for amount to allow an amount to be entered that differs from minimum
      $this->add('text', 'total_amount', ts('Amount'));
    }
    $this->add('select', 'financial_type_id',
      ts('Financial Type'),
      array('' => ts('- select -')) + CRM_Financial_BAO_FinancialType::getAvailableFinancialTypes($financialTypes, $this->_action)
    );

    $this->addElement('checkbox', 'is_different_contribution_contact', ts('Record Payment from a Different Contact?'));

    $this->addSelect('soft_credit_type_id', array('entity' => 'contribution_soft'));
    $this->addEntityRef('soft_credit_contact_id', ts('Payment From'), array('create' => TRUE));

    $this->addElement('checkbox',
      'send_receipt',
      ts('Send Confirmation and Receipt?'), NULL,
      array('onclick' => "showEmailOptions()")
    );

    $this->add('select', 'from_email_address', ts('Receipt From'), $this->_fromEmails);

    $this->add('textarea', 'receipt_text', ts('Receipt Message'));

    // Retrieve the name and email of the contact - this will be the TO for receipt email
    if ($this->_contactID) {
      list($this->_memberDisplayName,
        $this->_memberEmail
        ) = CRM_Contact_BAO_Contact_Location::getEmailDetails($this->_contactID);

      $this->assign('emailExists', $this->_memberEmail);
      $this->assign('displayName', $this->_memberDisplayName);
    }

    $isRecur = FALSE;
    if ($this->_action & CRM_Core_Action::UPDATE) {
      $recurContributionId = CRM_Core_DAO::getFieldValue('CRM_Member_DAO_Membership', $this->_id,
        'contribution_recur_id'
      );
      if ($recurContributionId && !CRM_Member_BAO_Membership::isSubscriptionCancelled($this->_id)) {
        $isRecur = TRUE;
        if (CRM_Member_BAO_Membership::isCancelSubscriptionSupported($this->_id)) {
          $this->assign('cancelAutoRenew',
            CRM_Utils_System::url('civicrm/contribute/unsubscribe', "reset=1&mid={$this->_id}")
          );
        }
        foreach ($elements as $elem) {
          $elem->freeze();
        }
      }
    }
    $this->assign('isRecur', $isRecur);

    $this->addFormRule(array('CRM_Offlinemembershipwizard_Form_Wizard_Membership', 'formRule'), $this);
    $mailingInfo = Civi::settings()->get('mailing_backend');
    $this->assign('isEmailEnabledForSite', ($mailingInfo['outBound_option'] != 2));

    $this->addElement('hidden', 'contactID', $this->_contactID);

    parent::buildQuickForm();
  }

  /**
   * Validation.
   *
   * @param array $params
   *   (ref.) an assoc array of name/value pairs.
   *
   * @param array $files
   * @param CRM_Member_Form_Membership $self
   *
   * @throws CiviCRM_API3_Exception
   * @return bool|array
   *   mixed true or array of errors
   */
  public static function formRule($params, $files, $self) {
    $errors = array();

    $priceSetId = self::getPriceSetID($params);
    $priceSetDetails = self::getPriceSetDetails($params);

    $selectedMemberships = self::getSelectedMemberships($priceSetDetails[$priceSetId], $params);

    if (!empty($params['price_set_id'])) {
      CRM_Price_BAO_PriceField::priceSetValidation($priceSetId, $params, $errors);

      $priceFieldIDS = self::getPriceFieldIDs($params, $priceSetDetails[$priceSetId]);

      if (!empty($priceFieldIDS)) {
        $ids = implode(',', $priceFieldIDS);

        $count = CRM_Price_BAO_PriceSet::getMembershipCount($ids);
        foreach ($count as $occurrence) {
          if ($occurrence > 1) {
            $errors['_qf_default'] = ts('Select at most one option associated with the same membership type.');
          }
        }
      }
      // Return error if empty $self->_memTypeSelected
      if (empty($errors) && empty($selectedMemberships)) {
        $errors['_qf_default'] = ts('Select at least one membership option.');
      }
      //if (!$self->_mode && empty($params['record_contribution'])) {
      //  $errors['record_contribution'] = ts('Record Membership Payment is required when you use a price set.');
      //}
    }
    else {
      if (empty($params['membership_type_id'][1])) {
        $errors['membership_type_id'] = ts('Please select a membership type.');
      }
      $numterms = CRM_Utils_Array::value('num_terms', $params);
      if ($numterms && intval($numterms) != $numterms) {
        $errors['num_terms'] = ts('Please enter an integer for the number of terms.');
      }

      if (($self->_mode || isset($params['record_contribution'])) && empty($params['financial_type_id'])) {
        $errors['financial_type_id'] = ts('Please enter the financial Type.');
      }
    }

    if (!empty($errors) && (count($selectedMemberships) > 1)) {
      $memberOfContacts = CRM_Member_BAO_MembershipType::getMemberOfContactByMemTypes($selectedMemberships);
      $duplicateMemberOfContacts = array_count_values($memberOfContacts);
      foreach ($duplicateMemberOfContacts as $countDuplicate) {
        if ($countDuplicate > 1) {
          $errors['_qf_default'] = ts('Please do not select more than one membership associated with the same organization.');
        }
      }
    }

    if (!empty($errors)) {
      return $errors;
    }

    //if (!empty($params['record_contribution']) && empty($params['payment_instrument_id'])) {
      //$errors['payment_instrument_id'] = ts('Payment Method is a required field.');
    //}

    if (!empty($params['is_different_contribution_contact'])) {
      if (empty($params['soft_credit_type_id'])) {
        $errors['soft_credit_type_id'] = ts('Please Select a Soft Credit Type');
      }
      if (empty($params['soft_credit_contact_id'])) {
        $errors['soft_credit_contact_id'] = ts('Please select a contact');
      }
    }

    if (!empty($params['payment_processor_id'])) {
      // validate payment instrument (e.g. credit card number)
      CRM_Core_Payment_Form::validatePaymentInstrument($params['payment_processor_id'], $params, $errors, NULL);
    }

    $joinDate = NULL;
    if (!empty($params['join_date'])) {

      $joinDate = CRM_Utils_Date::processDate($params['join_date']);

      foreach ($selectedMemberships as $memType) {
        $startDate = NULL;
        if (!empty($params['start_date'])) {
          $startDate = CRM_Utils_Date::processDate($params['start_date']);
        }

        // if end date is set, ensure that start date is also set
        // and that end date is later than start date
        $endDate = NULL;
        if (!empty($params['end_date'])) {
          $endDate = CRM_Utils_Date::processDate($params['end_date']);
        }

        $membershipDetails = CRM_Member_BAO_MembershipType::getMembershipTypeDetails($memType);

        if ($startDate && CRM_Utils_Array::value('period_type', $membershipDetails) == 'rolling') {
          if ($startDate < $joinDate) {
            $errors['start_date'] = ts('Start date must be the same or later than Member since.');
          }
        }

        if ($endDate) {
          if ($membershipDetails['duration_unit'] == 'lifetime') {
            // Check if status is NOT cancelled or similar. For lifetime memberships, there is no automated
            // process to update status based on end-date. The user must change the status now.
            $result = civicrm_api3('MembershipStatus', 'get', array(
              'sequential' => 1,
              'is_current_member' => 0,
            ));
            $tmp_statuses = $result['values'];
            $status_ids = array();
            foreach ($tmp_statuses as $cur_stat) {
              $status_ids[] = $cur_stat['id'];
            }
            if (empty($params['status_id']) || in_array($params['status_id'], $status_ids) == FALSE) {
              $errors['status_id'] = ts('Please enter a status that does NOT represent a current membership status.');
              $errors['is_override'] = ts('This must be checked because you set an End Date for a lifetime membership');
            }
          }
          else {
            if (!$startDate) {
              $errors['start_date'] = ts('Start date must be set if end date is set.');
            }
            if ($endDate < $startDate) {
              $errors['end_date'] = ts('End date must be the same or later than start date.');
            }
          }
        }

        // Default values for start and end dates if not supplied on the form.
        $defaultDates = CRM_Member_BAO_MembershipType::getDatesForMembershipType($memType,
          $joinDate,
          $startDate,
          $endDate
        );

        if (!$startDate) {
          $startDate = CRM_Utils_Array::value('start_date',
            $defaultDates
          );
        }
        if (!$endDate) {
          $endDate = CRM_Utils_Array::value('end_date',
            $defaultDates
          );
        }

        //CRM-3724, check for availability of valid membership status.
        if (empty($params['is_override']) && !isset($errors['_qf_default'])) {
          $calcStatus = CRM_Member_BAO_MembershipStatus::getMembershipStatusByDate($startDate,
            $endDate,
            $joinDate,
            'today',
            TRUE,
            $memType,
            $params
          );
          if (empty($calcStatus)) {
            $url = CRM_Utils_System::url('civicrm/admin/member/membershipStatus', 'reset=1&action=browse');
            $errors['_qf_default'] = ts('There is no valid Membership Status available for selected membership dates.');
            $status = ts('Oops, it looks like there is no valid membership status available for the given membership dates. You can <a href="%1">Configure Membership Status Rules</a>.', array(1 => $url));
            if (!$self->_mode) {
              $status .= ' ' . ts('OR You can sign up by setting Status Override? to true.');
            }
            CRM_Core_Session::setStatus($status, ts('Membership Status Error'), 'error');
          }
        }
      }
    }
    else {
      $errors['join_date'] = ts('Please enter the Member Since.');
    }

    if (isset($params['is_override']) &&
      $params['is_override'] && empty($params['status_id'])
    ) {
      $errors['status_id'] = ts('Please enter the status.');
    }

    //total amount condition arise when membership type having no
    //minimum fee
    if (isset($params['record_contribution'])) {
      //if (CRM_Utils_System::isNull($params['total_amount'])) {
        //$errors['total_amount'] = ts('Please enter the contribution.');
      //}
    }

    // validate contribution status for 'Failed'.
    if ($self->_onlinePendingContributionId && !empty($params['record_contribution']) &&
      (CRM_Utils_Array::value('contribution_status_id', $params) ==
        array_search('Failed', CRM_Contribute_PseudoConstant::contributionStatus(NULL, 'name'))
      )
    ) {
      $errors['contribution_status_id'] = ts('Please select a valid payment status before updating.');
    }

    return empty($errors) ? TRUE : $errors;
  }

  /**
   * Extract price set fields and values from $params.
   *
   * @param array $params
   * @param array $priceSet
   *
   * @return array
   */
  public static function getPriceFieldIDs($params, $priceSet) {
    $priceFieldIDS = array();
    if (isset($priceSet['fields']) && is_array($priceSet['fields'])) {
      foreach ($priceSet['fields'] as $fieldId => $field) {
        if (!empty($params['price_' . $fieldId])) {
          if (is_array($params['price_' . $fieldId])) {
            foreach ($params['price_' . $fieldId] as $priceFldVal => $isSet) {
              if ($isSet) {
                $priceFieldIDS[] = $priceFldVal;
              }
            }
          }
          elseif (!$field['is_enter_qty']) {
            $priceFieldIDS[] = $params['price_' . $fieldId];
          }
        }
      }
    }
    return $priceFieldIDS;
  }

  /**
   * Get selected membership type from the form values.
   *
   * @param array $priceSet
   * @param array $params
   *
   * @return array
   */
  public static function getSelectedMemberships($priceSet, $params) {
    $memTypeSelected = array();
    $priceFieldIDS = self::getPriceFieldIDs($params, $priceSet);
    if (isset($params['membership_type_id']) && !empty($params['membership_type_id'][1])) {
      $memTypeSelected = array($params['membership_type_id'][1] => $params['membership_type_id'][1]);
    }
    else {
      foreach ($priceFieldIDS as $priceFieldId) {
        if ($id = CRM_Core_DAO::getFieldValue('CRM_Price_DAO_PriceFieldValue', $priceFieldId, 'membership_type_id')) {
          $memTypeSelected[$id] = $id;
        }
      }
    }
    return $memTypeSelected;
  }

  function postProcess() {
    $values = $this->exportValues();
    $campaignFields = array('campaign_appeal', 'campaign_channel', 'campaign_medium');
    foreach($campaignFields as $campaignField) {
      if (isset($_POST[$campaignField]) && !empty($_POST[$campaignField])) {
        $values[$campaignField] = $_POST[$campaignField];
      }
    }
    $this->set("membership", $values);
    if (!empty($this->_priceSet)) {
      // if present, has discount applied
      $this->set("priceSet", $this->_priceSet);
    }
  }

  /**
   * Get the fields/elements defined in this form.
   *
   * @return array (string)
   */
  function getRenderableElementNames() {
    // The _elements list includes some items which should not be
    // auto-rendered in the loop -- such as "qfKey" and "buttons".  These
    // items don't have labels.  We'll identify renderable by filtering on
    // the 'label'.
    $elementNames = array();
    foreach ($this->_elements as $element) {
      /** @var HTML_QuickForm_Element $element */
      $label = $element->getLabel();
      if (!empty($label)) {
        $elementNames[] = $element->getName();
      }
    }
    return $elementNames;
  }
}

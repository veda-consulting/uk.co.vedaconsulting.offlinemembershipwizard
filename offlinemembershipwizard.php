<?php

require_once 'offlinemembershipwizard.civix.php';

/**
 * Implementation of hook_civicrm_config
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function offlinemembershipwizard_civicrm_config(&$config) {
  _offlinemembershipwizard_civix_civicrm_config($config);
}

/**
 * Implementation of hook_civicrm_xmlMenu
 *
 * @param $files array(string)
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function offlinemembershipwizard_civicrm_xmlMenu(&$files) {
  _offlinemembershipwizard_civix_civicrm_xmlMenu($files);
}

/**
 * Implementation of hook_civicrm_install
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function offlinemembershipwizard_civicrm_install() {
  _offlinemembershipwizard_civix_civicrm_install();

  // Create log table for the newly created table
  $schema = new CRM_Logging_Schema();
  $schema->fixSchemaDifferences();
}

/**
 * Implementation of hook_civicrm_uninstall
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function offlinemembershipwizard_civicrm_uninstall() {
  _offlinemembershipwizard_civix_civicrm_uninstall();
}

/**
 * Implementation of hook_civicrm_enable
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function offlinemembershipwizard_civicrm_enable() {
  _offlinemembershipwizard_civix_civicrm_enable();
}

/**
 * Implementation of hook_civicrm_disable
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function offlinemembershipwizard_civicrm_disable() {
  _offlinemembershipwizard_civix_civicrm_disable();
}

/**
 * Implementation of hook_civicrm_upgrade
 *
 * @param $op string, the type of operation being performed; 'check' or 'enqueue'
 * @param $queue CRM_Queue_Queue, (for 'enqueue') the modifiable list of pending up upgrade tasks
 *
 * @return mixed  based on op. for 'check', returns array(boolean) (TRUE if upgrades are pending)
 *                for 'enqueue', returns void
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function offlinemembershipwizard_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _offlinemembershipwizard_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implementation of hook_civicrm_managed
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function offlinemembershipwizard_civicrm_managed(&$entities) {
  _offlinemembershipwizard_civix_civicrm_managed($entities);
}

/**
 * Implementation of hook_civicrm_caseTypes
 *
 * Generate a list of case-types
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function offlinemembershipwizard_civicrm_caseTypes(&$caseTypes) {
  _offlinemembershipwizard_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implementation of hook_civicrm_alterSettingsFolders
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function offlinemembershipwizard_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _offlinemembershipwizard_civix_civicrm_alterSettingsFolders($metaDataFolders);
}


/**
 * Implementation of hook_civicrm_navigationMenu
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_navigationMenu
 */
/*function offlinemembershipwizard_civicrm_navigationMenu( &$params ) {
  // get the id of Memberships Menu
  $memMenuId = CRM_Core_DAO::getFieldValue('CRM_Core_BAO_Navigation', 'Memberships', 'id', 'name');

  // skip adding menu if there is no Membership menu
  if ($memMenuId) {
    // get the maximum key under Membership menu
    $maxKey = max( array_keys($params[$memMenuId]['child']));
    $params[$memMenuId]['child'][$maxKey+1] =  array (
      'attributes' => array (
        'label'      => 'Membership Wizard',
        'name'       => 'Offline_Membership_Wizard',
        'url'        => 'civicrm/member/offlinemembershipwizard?reset=1',
        'permission' => 'access CiviMember',
        'operator'   => NULL,
        'separator'  => 2,
        'parentID'   => $memMenuId,
        'navID'      => $maxKey+1,
        'active'     => 1
      )
    );
  }
}*/

function offlinemembershipwizard_civicrm_alterContent(&$content, $context, $tplName, &$object ) {

  /*if ($tplName == 'CRM/Member/Page/Tab.tpl' && isset($_GET['snippet']) && !empty($object->_contactId)) {
    $actionLinkPattern = "'<div class=\"action-link\">(.*?)</div>'si";
    // Get action links
    // So that we can add new link 'Membership Wizard' to the actions links
    preg_match($actionLinkPattern, $content, $match);
    if ($match) {
      $url = CRM_Utils_System::url('civicrm/member/offlinemembershipwizard', 'reset=1&action=add&cid='.$object->_contactId.'&context=membership');
      $memWizardLink = <<<EOD
<a accesskey="N" href="{$url}" target="_blank" class="button"><span><i class="crm-i fa-plus-circle"></i> Membership Wizard</span></a>
EOD;
      $actionLinks = $memWizardLink.$match[1];
      $content = preg_replace($actionLinkPattern, $actionLinks, $content);
    }
  }*/

  if ($tplName == 'CRM/Contact/Page/View/Summary.tpl') {

    $contactId = $object->getVar('_contactId');

    $actionLinkPattern = "'<ul id=\"actions\">(.*?)</ul>'si";
    // Get action links
    // So that we can add new link 'Membership Wizard' to the actions links
    preg_match($actionLinkPattern, $content, $match);
    if ($match) {
      $url = CRM_Utils_System::url('civicrm/member/offlinemembershipwizard', 'reset=1&action=add&cid='.$contactId.'&context=membership');
      $memWizardLink = <<<EOD
<li>
<a accesskey="N" href="{$url}" class="button"><span><i class="crm-i fa-plus-circle"></i> Membership Wizard</span></a>
</li>
EOD;
      $actionLinks = $memWizardLink.$match[1];
      $content = preg_replace($actionLinkPattern, $actionLinks, $content);
    }
  }
}

/**
 * Implements hook_civicrm_buildForm().
 *
 * Set a default value for an event price set field.
 *
 * @param string $formName
 * @param CRM_Core_Form $form
 */
// GK 21092017 - Add warning message field in payment processor wizard
function offlinemembershipwizard_civicrm_buildForm($formName, &$form) {

  // Add a custom message field on payment processor wizard
  if ($formName == 'CRM_Admin_Form_PaymentProcessor') {
    $templatePath = realpath(dirname(__FILE__)."/templates");
    // Add the custom message field element in the form only while adding / updating a payment processor
    if ( $form->getAction() == CRM_Core_Action::ADD || $form->getAction() == CRM_Core_Action::UPDATE ) {
      $form->add('textarea', "warning_message", ts('Warning message'), array('rows' => 3, 'cols' => 40));
      // insert required template block
      CRM_Core_Region::instance('page-body')->add(array(
        'template' => "{$templatePath}/CRM/Offlinemembershipwizard/creditcardWarning.tpl"
       ));

      // setting default values, if editing a payment processor
      if ($form->getAction() == CRM_Core_Action::UPDATE) {
        $warningMessage = '';
        $processorId = $form->getVar('_id');
        // get existing warning_message
        $customDetailsTable = CRM_Offlinemembershipwizard_Utils::PAYMENTPROCESSOR_CUSTOMDETAILS_TABLE;
        $sql_params = array(
          1 => array($processorId, 'String')
        );
        $sql = "SELECT * FROM {$customDetailsTable} WHERE payment_processor_id = %1";
        $dao = CRM_Core_DAO::executeQuery($sql, $sql_params);

        while ($dao->fetch()) {
          $warningMessage = $dao->warning_message;
        }
        // add into form's default values
        $defaults = array('warning_message' => $warningMessage);
        $form->setDefaults($defaults);
      }
    }

  }
  //End of payment processor admin form

}

 /**
 * Implements hook_civicrm_postProcess().
 *
 * @param string $formName
 * @param CRM_Core_Form $form
 */
 // GK 21092017 - Process warning message field value in payment processor wizard
function offlinemembershipwizard_civicrm_postprocess($formName, &$form) {

  // payment processor admin form
  if ($formName == 'CRM_Admin_Form_PaymentProcessor') {

    $customDetailsTable = CRM_Offlinemembershipwizard_Utils::PAYMENTPROCESSOR_CUSTOMDETAILS_TABLE;

    // Process 'Add payment processor' action
    if ( $form->getAction() == CRM_Core_Action::ADD ) {
      // FIX ME :  _id or _testId not recieved for new action, hence using name to find the payment processor ids
      // return, if name is empty or warning_message not set in the submit values for any reasons
      if (empty($form->_submitValues['name']) || !isset($form->_submitValues['warning_message'])) {
        return;
      }

      $warningMessage = $form->_submitValues['warning_message'] ? $form->_submitValues['warning_message'] : '';

      $result = CRM_Offlinemembershipwizard_Utils::CiviCRMAPIWrapper('PaymentProcessor', 'get', array(
        'sequential' => 1,
        'name' => $form->_submitValues['name'],
      ));

      if (isset($result) && !empty($result['values'])) {
        // add both, live & test processor details
        foreach ($result['values'] as $key => $value) {
          $messageUpdated = CRM_Offlinemembershipwizard_Utils::setProcessorWarningMessage($value['id'], $warningMessage);

          if (!$messageUpdated) {
            CRM_Core_Session::setStatus(ts('The Warning message has not been saved.'), ts('Failed'), 'error');
          }
        }
      }
    }

    // Process 'Update payment processor' action
    if ( $form->getAction() == CRM_Core_Action::UPDATE ) {
      if (isset($form->_submitValues['warning_message'])) {
        $warningMessage = $form->_submitValues['warning_message'] ? $form->_submitValues['warning_message'] : '';

        // update both, live & test processor details
        $processorIds = array();
        if ($form->getVar('_id')) {
          $processorIds['live'] = $form->getVar('_id');
        }
        if ($form->getVar('_testID')) {
          $processorIds['test'] = $form->getVar('_testID');
        }

        foreach ($processorIds as $key => $processorId) {
          $messageUpdated = CRM_Offlinemembershipwizard_Utils::setProcessorWarningMessage($processorId = '', $warningMessage);

          if (!$messageUpdated) {
            CRM_Core_Session::setStatus(ts('The Warning message has not been saved.'), ts('Failed'), 'error');
          }
        }
      }
    }

    // Process 'Delete payment processor' action
    if ( $form->getAction() == CRM_Core_Action::DELETE ) {

      // delete both, live & test processor details
      $processorIds = array();
      if ($form->getVar('_id')) {
        $processorIds['live'] = $form->getVar('_id');
      }
      if ($form->getVar('_testID')) {
        $processorIds['test'] = $form->getVar('_testID');
      }

      foreach ($processorIds as $key => $processorId) {
        $sql_params = array(
          1 => array($processorId, 'String')
        );

        $sql = "DELETE FROM {$customDetailsTable} WHERE
        payment_processor_id = %1";

        $dao = CRM_Core_DAO::executeQuery($sql, $sql_params);
      }
    }

  }
  //End of payment processor admin form

}


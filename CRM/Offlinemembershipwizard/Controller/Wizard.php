<?php

require_once 'CRM/Core/Controller.php';
require_once 'CRM/Core/Action.php';
require_once 'CRM/Offlinemembershipwizard/Form/Wizard/Membership.php';
require_once 'CRM/Offlinemembershipwizard/Form/Wizard/Contribution.php';
require_once 'CRM/Offlinemembershipwizard/Form/Wizard/Confirm.php';

class CRM_Offlinemembershipwizard_Controller_Wizard extends CRM_Core_Controller {

  private $membership;

  private $contribution;

  private $contribution_recur;
    
  /**
   * class constructor
   */
  function __construct($title = NULL, $action = CRM_Core_Action::NONE, $modal = TRUE) {
    parent::__construct($title, $modal);

    $this->_stateMachine = new CRM_Offlinemembershipwizard_StateMachine_Wizard($this, $action);

    /*$p = array(
      'CRM_Offlinemembershipwizard_Form_Wizard_Membership' => NULL,
      'CRM_Offlinemembershipwizard_Form_Wizard_Contribution' => NULL,
      'CRM_Offlinemembershipwizard_Form_Wizard_Confirm' => NULL,
    );
    
    $this->_stateMachine->addSequentialPages($p, $action);*/

    // create and instantiate the pages
    $this->addPages($this->_stateMachine, $action);

    $this->addActions();
  }
}

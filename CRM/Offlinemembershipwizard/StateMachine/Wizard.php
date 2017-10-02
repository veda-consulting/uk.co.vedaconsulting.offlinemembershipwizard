<?php

class CRM_Offlinemembershipwizard_StateMachine_Wizard extends CRM_Core_StateMachine {
 function __construct($controller, $action = CRM_Core_Action::NONE) {
    parent::__construct($controller, $action);
    $this->_pages = array(
      'CRM_Offlinemembershipwizard_Form_Wizard_Membership' => NULL,
      'CRM_Offlinemembershipwizard_Form_Wizard_Contribution' => NULL,
      'CRM_Offlinemembershipwizard_Form_Wizard_Confirm' => NULL,
    );
    $this->addSequentialPages($this->_pages, $action);
  }
}

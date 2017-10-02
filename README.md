# Offline Membership Wizard #

### Overview ###

Extension to facilitate creation of membership, contribution, recurring and subscription records in a multi-step Wizard.

### Installation ###

* Install the extension manually in CiviCRM. More details [here](http://wiki.civicrm.org/confluence/display/CRMDOC/Extensions#Extensions-Installinganewextension) about installing extensions in CiviCRM.
* Create a priceset to be used in the offline membership wizard

### Usage ###

* Click 'Membership Wizard' button in the contact summary page to access the wizard forms
* Step 1: Membership
This form is similar to add membership form, where you select the membership/donation in priceset. Payment instrument methods are displayed as an additional field, upon selection will display the supported payment processors. You can select the payment processor if you want to process payment during signup.
* Step 2: Contribution
This form allows you to setup the subscription (split into weeklt/monthly/yearly payments) and also displays billing block.
* Step 3: Confirmation
This step is a confirmation screen which displays the selected entities (membeships, donation) as line items, along with billing information

Upon submission, the extension will create membership, contribution, recurring and subscription records, where applicable.

### Support ###

support (at) vedaconsulting.co.uk

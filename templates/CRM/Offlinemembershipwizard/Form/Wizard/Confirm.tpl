{* WizardHeader.tpl provides visual display of steps thru the wizard as well as title for current step *}
{include file="CRM/common/WizardHeader.tpl"}
{* HEADER *}

{* FIELD EXAMPLE: OPTION 1 (AUTOMATIC LAYOUT) *}

<div class="crm-block crm-form-block crm-membership-form-block">

	<div class="crm-group membership-details-group">

		<div class="header-dark">
		    {ts}Membership Details{/ts}
		</div>

		<table>
		    <tr class="columnheader">
			    <th>{ts}Item{/ts}</th>
			    <th>{ts}Financial Type{/ts}</th>
			    <th>{ts}Amount{/ts}</th>
		    </tr>
		    {foreach from=$lineItems item=line}
		    	<tr>
		    		<td>{$line.pricefield_label} - {$line.pricefieldvalue_label}</td>
		    		<td>{$line.financial_type}</td>
		    		<td class="right">{$line.amount|crmMoney}</td>
		    	</tr>
		    {/foreach}
		    <tr>
				<td colspan="2"><b>Total<b></td>
				<td class="right"><b>{$totalAmount|crmMoney}</b></td>
			</tr>
		</table>

	</div>

	<div class="crm-group payment-details-group">


		<div class="header-dark">
		    {ts}Payment Details{/ts}
		</div>

		<table class="crm-info-panel">
		<tbody>
			<tr>
		      <td class="label">Frequency</td>
		      <td>{$contributionDetails.frequency}</td>
		    </tr>
		    <tr>
		      <td class="label">Amount</td>
		      <td>{$contributionDetails.amount}</td>
		    </tr>
		    <tr>
		      <td class="label">Number of Payments</td>
		      <td>{$contributionDetails.number_of_payments}</td>
		    </tr>
		</tbody>
		</table>

	</div>

	{if ($billingName or $address)}
	<div class="crm-group payment-details-group">
		<div class="header-dark">
            {ts}Billing Name and Address{/ts}
        </div>
      	<div class="crm-section no-label billing_name-section">
        	<div class="content">{$billingName}</div>
        	<div class="clear"></div>
      	</div>
      	<div class="crm-section no-label billing_address-section">
        	<div class="content">{$address|nl2br}</div>
        	<div class="clear"></div>
      	</div>
	</div>
	{/if}

	{if ($credit_card_number or $bank_account_number)}
        <div class="crm-group credit_card-group">
            <div class="header-dark">
            {if $paymentProcessor.payment_type & 2}
                 {ts}Direct Debit Information{/ts}
            {else}
                {ts}Credit Card Information{/ts}
            {/if}
            </div>
            {if $paymentProcessor.payment_type & 2}
                <div class="display-block">
                    {ts}Account Holder{/ts}: {$account_holder}<br />
                    <!-- MV #4443, For UK account we dont get these fields based on SEPA setting. -->
                    {if !$ukbankacsc}
                    {ts}Bank Account Number{/ts}: {$bank_account_number}<br />
                    {ts}Bank Identification Number{/ts}: {$bank_identification_number}<br />
                    {ts}Bank Name{/ts}: {$bank_name}<br />
                    {else}
                    {ts}Bank Account Number{/ts}: {$ukbank_account_number}<br />
                    {ts}Bank Sort Code{/ts}: {$ukbank_sort_code}<br />
                    {/if}
                </div>
                {if $contributeMode eq 'direct'}
                  <div class="crm-group debit_agreement-group">
                      <div class="header-dark">
                          {ts}Agreement{/ts}
                      </div>
                      <div class="display-block">
                          {ts}Your account data will be used to charge your bank account via direct debit. While submitting this form you agree to the charging of your bank account via direct debit.{/ts}
                      </div>
                  </div>
                {/if}
            {else}
                <div class="crm-section no-label credit_card_details-section">
                  <div class="content">{$credit_card_type}</div>
                  <div class="content">{$credit_card_number}</div>
                  <div class="content">{ts}Expires{/ts}: {$credit_card_exp_date|truncate:7:''|crmDate}</div>
                  <div class="clear"></div>
                </div>
            {/if}
        </div>
      {/if}

</div>

<div class="crm-submit-buttons">
{include file="CRM/common/formButtons.tpl" location="bottom"}
</div>

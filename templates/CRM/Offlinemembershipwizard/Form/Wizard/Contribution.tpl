{* WizardHeader.tpl provides visual display of steps thru the wizard as well as title for current step *}
{include file="CRM/common/WizardHeader.tpl"}
{* HEADER *}

{* FIELD EXAMPLE: OPTION 1 (AUTOMATIC LAYOUT) *}

<div class="crm-block crm-form-block crm-membership-form-block">

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
<br /><br />

{if $distributionTypefields}
<fieldset class="billing_mode-group distribution-details-group">
<legend>
    {ts}Distribution Details{/ts}
</legend>
<table class="form-layout-compressed">

    {foreach from=$distributionTypefields item=distributionTypefields}

    <tr>
        <td class="label">{$form.$distributionTypefields.label}</td>
        <td>{$form.$distributionTypefields.html}</td>
    </tr>

    {/foreach}

</table>
</fieldset>
<br />
{/if}

<fieldset class="billing_mode-group recurring-contribution-group">
<legend>
    {ts}Contribution Details{/ts}
</legend>
<table class="form-layout-compressed">
    <tr>
        <td class="label">{$form.record_contribution.label}</td>
        <td>{$form.record_contribution.html}<br />
        <span class="description">Tick if you want to create the first contribution. Example: Cash/Cheque payments.</span>
        </td>
    </tr>
</table>
</fieldset>


<fieldset class="billing_mode-group recurring-contribution-group">
<legend>
    {ts}Payment Details{/ts}
</legend>
<table class="form-layout-compressed">
	<tr>
		<td class="label">{$form.frequency_unit.label}</td>
		<td>{$form.frequency_unit.html}</td>
	</tr>
	<tr>
		<td class="label">{$form.amount.label}</td>
		<td>{$form.currency.html|crmAddClass:eight}&nbsp;{$form.amount.html|crmAddClass:eight}</td>
	</tr>
	<tr>
		<td class="label">{$form.number_of_payments.label}</td>
		<td>{$form.number_of_payments.html}</td>
	</tr>
</table>
</fieldset>

{if $paymentMethod eq 'cheque'}
<table class="form-layout-compressed">
    <tr>
        <td class="label">{$form.check_number.label}</td>
        <td>{$form.check_number.html}</td>
    </tr>
</table>    
{/if}

<div id="billing-payment-block">
  {include file="CRM/Core/BillingBlock.tpl"}
</div>

{* FOOTER *}
<div class="crm-submit-buttons">
{include file="CRM/common/formButtons.tpl" location="bottom"}
</div>

</div>


{literal}
<script type="text/javascript">
cj( document ).ready(function() {

    {/literal}
    {if $defaultCountry neq ''}
        {literal}
            var defaultCountry = {/literal}{$defaultCountry}{literal}
            cj('#billing_country_id-5').select2("val", defaultCountry);
        {/literal}
    {/if}
    {if $defaultStateProvince neq ''}
        {literal}
            var defaultStateProvince = {/literal}{$defaultStateProvince}{literal}
            cj('#billing_state_province_id-5').select2("val", defaultStateProvince);
        {/literal}
    {/if}
    {literal}

    //cj('#billing_country_id-5').select2("val", defaultCountry);
    //cj('#billing_state_province_id-5').select2("val", defaultStateProvince);

	cj("#number_of_payments").prop("readonly", true);
	cj("#amount").prop("readonly", true);

    //calculate amount
    cj('#frequency_unit').change(function(){
        updateAmountField();
    });

    function updateAmountField(){
      var unit = cj('#frequency_unit').val();
      var totalAmount = cj("input[name=totalAmount]").val();
      if (unit && totalAmount) {

      	var amountVal = 1;
        var day   = 365;
        var week  = 52;
        var month = 12;
        var year  = 1;
        calcUnit  = month;
        if (unit == 'year') {
            calcUnit = year;
        }
        if (unit == 'week') {
            calcUnit = week;
        }
        if (unit == 'day') {
            calcUnit = day;
        }
        
        amountVal = (totalAmount / calcUnit).toFixed(2);

        cj('#number_of_payments').val(calcUnit);
        cj('#amount').val(amountVal);
      }
    }
});
</script>
{/literal}

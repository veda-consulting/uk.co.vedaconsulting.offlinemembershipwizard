{* GK 21092017 - template block that contains the creadircard warning message field for Payment Processors*}
  <fieldset id="creditcard_warning_message">
    <legend>Warning Message</legend>
    <table class="form-layout-compressed">
      <tbody>
        <tr class="crm-paymentProcessor-form-block-creditcard_warning">
          <td class="label">{$form.warning_message.label}</td>
          <td>{$form.warning_message.html}</td>
        </tr>
      </tbody>
    </table>
  </fieldset>
{* reposition the above block*}
{literal}
<script type="text/javascript">
  // display right on top of processor details
  cj('#creditcard_warning_message').insertAfter(cj('.crm-paymentProcessor-form-block-payment_processor_type').parent().parent());
</script>
{/literal}

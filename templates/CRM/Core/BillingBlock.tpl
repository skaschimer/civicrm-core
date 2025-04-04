{*
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC. All rights reserved.                        |
 |                                                                    |
 | This work is published under the GNU AGPLv3 license with some      |
 | permitted exceptions and without any warranty. For full license    |
 | and copyright information, see https://civicrm.org/licensing       |
 +--------------------------------------------------------------------+
*}
{crmRegion name="billing-block"}
<div id="payment_information">
  {if $paymentFields}
    <fieldset class="billing_mode-group {$paymentTypeName}_info-group">
      <legend>
        {$paymentTypeLabel}
      </legend>
      {crmRegion name="billing-block-pre"}
      {/crmRegion}
      <div class="crm-section billing_mode-section {$paymentTypeName}_info-section">
        {foreach from=$paymentFields item=paymentField}
          {assign var='name' value=$form.$paymentField.name}
          <div class="crm-section {$form.$paymentField.name}-section">
            <div class="label">{$form.$paymentField.label}
              {if $requiredPaymentFields.$name}<span class="crm-marker" title="{ts escape='htmlattribute'}This field is required.{/ts}">*</span>{/if}
            </div>
            <div class="content">
                {$form.$paymentField.html}
              {if $paymentFieldsMetadata.$name.description}
                <div class="description">{$paymentFieldsMetadata.$name.description}</div>
              {elseif $paymentField == 'cvv2'}{* @todo move to form assignment*}
                <span class="cvv2-icon" title="{ts escape='htmlattribute'}Usually the last 3-4 digits in the signature area on the back of the card.{/ts}"> </span>
              {/if}
              {if $paymentField == 'credit_card_type'}
                <div class="crm-credit_card_type-icons"></div>
              {/if}
            </div>
            <div class="clear"></div>
          </div>
        {/foreach}
      </div>
    </fieldset>
    {if !$isBackOffice && $paymentAgreementTitle}
      <div id="payment_notice">
        <fieldset class="crm-public-form-item crm-group payment_notice-group">
          <legend>{$paymentAgreementTitle}</legend>
          {$paymentAgreementText}
        </fieldset>
      </div>
    {/if}
  {/if}
  {if $billingDetailsFields && $paymentProcessor.payment_processor_type neq 'PayPal_Express'}
    {if $profileAddressFields && !$ccid}
      <input type="checkbox" id="billingcheckbox" value="0">
      <label for="billingcheckbox">{ts}My billing address is the same as above{/ts}</label>
    {/if}
    <fieldset class="billing_name_address-group">
      <legend>{ts}Billing Name and Address{/ts}</legend>
      <div class="crm-section billing_name_address-section">
        {foreach from=$billingDetailsFields item=billingField}
          {assign var='name' value=$form.$billingField.name}
          <div class="crm-section {$form.$billingField.name}-section">
            <div class="label">{$form.$billingField.label}
              {if $requiredPaymentFields.$name}<span class="crm-marker" title="{ts escape='htmlattribute'}This field is required.{/ts}">*</span>{/if}
            </div>
            {if $form.$billingField.type == 'text'}
              <div class="content">{$form.$billingField.html}</div>
            {else}
              <div class="content">{$form.$billingField.html|crmAddClass:big}</div>
            {/if}
            <div class="clear"></div>
          </div>
        {/foreach}
      </div>
    </fieldset>
  {/if}
</div>
{if $profileAddressFields}
  <script type="text/javascript">
    {literal}

    CRM.$(function ($) {
      // build list of ids to track changes on
      var address_fields = {/literal}{$profileAddressFields|@json_encode}{literal};
      var input_ids = {};
      var select_ids = {};
      var orig_id, field, field_name;

      // build input ids
      $('.billing_name_address-section input').each(function (i) {
        orig_id = $(this).attr('id');
        field = orig_id.split('-');
        field_name = field[0].replace('billing_', '');
        if (field[1]) {
          if (address_fields[field_name]) {
            input_ids['#' + field_name + '-' + address_fields[field_name]] = '#' + orig_id;
          }
        }
      });
      if ($('#first_name').length)
        input_ids['#first_name'] = '#billing_first_name';
      if ($('#middle_name').length)
        input_ids['#middle_name'] = '#billing_middle_name';
      if ($('#last_name').length)
        input_ids['#last_name'] = '#billing_last_name';

      // build select ids
      $('.billing_name_address-section select').each(function (i) {
        orig_id = $(this).attr('id');
        field = orig_id.split('-');
        field_name = field[0].replace('billing_', '').replace('_id', '');
        if (field[1]) {
          if (address_fields[field_name]) {
            select_ids['#' + field_name + '-' + address_fields[field_name]] = '#' + orig_id;
          }
        }
      });

      // detect if billing checkbox should default to checked
      var checked = true;
      for (var id in input_ids) {
        orig_id = input_ids[id];
        if ($(id).val() != $(orig_id).val()) {
          checked = false;
          break;
        }
      }
      for (var id in select_ids) {
        orig_id = select_ids[id];
        if ($(id).val() != $(orig_id).val()) {
          checked = false;
          break;
        }
      }
      if (checked) {
        $('#billingcheckbox').prop('checked', true).data('crm-initial-value', true);
        if (!CRM.billing || CRM.billing.billingProfileIsHideable) {
          $('.billing_name_address-group').hide();
        }
      }

      // onchange handlers for non-billing fields
      for (var id in input_ids) {
        orig_id = input_ids[id];
        $(id).change(function () {
          var id = '#' + $(this).attr('id');
          var orig_id = input_ids[id];

          // if billing checkbox is active, copy other field into billing field
          if ($('#billingcheckbox').prop('checked')) {
            $(orig_id).val($(id).val());
          }
        });
      }
      for (var id in select_ids) {
        orig_id = select_ids[id];
        $(id).change(function () {
          var id = '#' + $(this).attr('id');
          var orig_id = select_ids[id];

          // if billing checkbox is active, copy other field into billing field
          if ($('#billingcheckbox').prop('checked')) {
            $(orig_id + ' option').prop('selected', false);
            $(orig_id + ' option[value="' + $(id).val() + '"]').prop('selected', true);
            $(orig_id).change();
          }
        });
      }

      // toggle show/hide
      var billingCheckboxElement = $('#billingcheckbox');
      billingCheckboxElement.click(function() {
        billingCheckboxChanged(billingCheckboxElement);
      });

      billingCheckboxElement.change(function() {
        billingCheckboxChanged(billingCheckboxElement);
      });

      function billingCheckboxChanged(billingCheckbox) {
        if (billingCheckbox.prop('checked')) {
          if (!CRM.billing || CRM.billing.billingProfileIsHideable) {
            $('.billing_name_address-group').hide(200);
          }

          // copy all values
          for (var id in input_ids) {
            orig_id = input_ids[id];
            $(orig_id).val($(id).val());
          }
          for (var id in select_ids) {
            orig_id = select_ids[id];
            $(orig_id + ' option').prop('selected', false);
            $(orig_id + ' option[value="' + $(id).val() + '"]').prop('selected', true);
            $(orig_id).change();
          }
        } else {
          $('.billing_name_address-group').show(200);
        }
      }

      // remove spaces, dashes from credit card number
      $('#credit_card_number').change(function () {
        var cc = $('#credit_card_number').val()
                .replace(/ /g, '')
                .replace(/-/g, '');
        $('#credit_card_number').val(cc);
      });
    });

  </script>
  {/literal}
{/if}
{if $suppressSubmitButton}
{literal}
  <script type="text/javascript">
    CRM.$(function($) {
      $('.crm-submit-buttons', $('#billing-payment-block').closest('form')).hide();
    });
  </script>
{/literal}
{/if}
{/crmRegion}
{crmRegion name="billing-block-post"}
  {* Payment processors sometimes need to append something to the end of the billing block. We create a region for
     clarity  - the plan is to move to assigning this through the payment processor to this region *}
{/crmRegion}

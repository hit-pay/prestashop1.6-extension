{*
* 2007-2021 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2021 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}
<style>
    .hitpay-subtable {
        border-bottom: 1px solid #bbcdd2 !important;
        margin-bottom: 10px;
    }
    .hitpay-subtable th {
        font-weight: normal !important;
        width: 200px !important;
    }
    .hitpay-subtable td, .hitpay-subtable th{
        border-top: none !important;
    }
</style>
<div id="formAddPaymentPanel" class="panel card">
    <div class="panel-heading card-header">
      <i class="icon-money"></i>
       {l s="HitPay Refund" mod='hitpay'}
    </div>

    <div class="table-responsive card-body">
        {if $refund_error}
            <div class="module_confirmation conf confirm alert alert-danger">
                <button type="button" class="close" data-dismiss="alert">x</button>
                {$refund_error}
            </div>
        {/if}

        {if $refund_success}
            <div class="module_confirmation conf confirm alert alert-success">
                <button type="button" class="close" data-dismiss="alert">x</button>
                {$refund_success}
            </div>
        {/if}
    
        <table class="table hitpay-subtable">
            {if isset($refundData)}
                <tr>
                    <th>{l s="Refund Id" mod='hitpay'}</th>
                    <td class="value">{$refundData.refund_id}</td>
                </tr>
                <tr>
                    <th>{l s="Payment Id" mod='hitpay'}</th>
                    <td class="value">{$refundData.payment_id}</td>
                </tr>
                <tr>
                    <th>{l s="Refund Status" mod='hitpay'}</th>
                    <td class="value">{$refundData.status}</td>
                </tr>
                <tr>
                    <th>{l s="Amount Refunded" mod='hitpay'}</th>
                    <td class="value">{$refundData.amount_refunded}</td>
                </tr>
                <tr>
                    <th>{l s="Total Amount" mod='hitpay'}</th>
                    <td class="value">{$refundData.total_amount}</td>
                </tr>
                 <tr>
                    <th>{l s="Payment Method" mod='hitpay'}</th>
                    <td class="value">{$refundData.payment_method}</td>
                </tr>
                <tr>
                    <th>{l s="Refunded At" mod='hitpay'}</th>
                    <td class="value">{$refundData.created_at}</td>
                </tr>
            {else}
                <tr>
                    <th>{l s="Payment Id" mod='hitpay'}</th>
                    <td class="value">{$payment_id}</td>
                </tr>
                <tr>
                    <th>{l s="Payment Status" mod='hitpay'}</th>
                    <td class="value">{$savedPayment->status}</td>
                </tr>
                <tr>
                    <th>{l s="Amount Paid" mod='hitpay'}</th>
                    <td class="value">{$savedPayment->amount}</td>
                </tr>
                <tr>
                    <th></th>
                    <td class="value">
                        <strong>
                            <span id="refund_span_{$payment_id}">
                            <a class="btn btn-primary" href="javascript:void(0);" onclick="$('#refund_span_{$payment_id}').hide();$('#refund_form_{$payment_id}').show();">{l s="Refund" mod='hitpay'}</a>
                            </span>
                            <form id="refund_form_{$payment_id}" method="post"
                                  action="{$smarty.server.REQUEST_URI}" 
                                  class="form-horizontal" style="display:none">
                                <div class="form-group row ">
                                        <label for="refund_amount_{$payment_id}" class="form-control-label label-on-top col-12">
                                            {l s="Enter the amount" mod='hitpay'}<span class="text-danger">*</span>
                                        </label>
                                     <div class="col-sm">
                                        <div class="input-group">
                                            <input id="refund_amount_{$payment_id}" type="text" name="hitpay_amount" required="required" class="form-control" />
                                        </div>
                                    </div>
                                </div>
                                <div class="text-left">
                                    <button type="button" class="btn btn-secondary" onclick="$('#refund_span_{$payment_id}').show();$('#refund_form_{$payment_id}').hide();">{l s="Cancel" mod='hitpay'}</button>
                                </div>
                                <div class="text-right">
                                    <input type="hidden" name="payment_id" value="{$payment_id}" />
                                    <button type="submit" name="hitpay_refund" class="btn btn-primary">{l s="Refund Payment" mod='hitpay'}</button>
                                </div>
                            </form>
                        </strong>
                    </td>
                </tr>
            {/if}
        </table>
    </div>
</div>

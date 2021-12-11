<table id="hitpay-payment-details" style="display: none">
    <tr id="hitpay_payment_method">
        <td class="text-right"><strong>{l s='HitPay Payment Type' mod='hitpay'}</strong></td>
        <td class="amount text-right nowrap">
            <strong>{$payment_method|escape:'htmlall':'UTF-8'}</strong>
        </td>
        <td class="partial_refund_fields current-edit" style="display:none;"></td>
    </tr>
    <tr id="hitpay_fees">
        <td class="text-right"><strong>{l s='HitPay Fee' mod='hitpay'}</strong></td>
        <td class="amount text-right nowrap">
            <strong>{$hitpay_fee|escape:'htmlall':'UTF-8'}</strong>
        </td>
        <td class="partial_refund_fields current-edit" style="display:none;"></td>
    </tr>
</table>
<script type="text/javascript">
$(document).ready(function() {
    $("#hitpay_fees").insertAfter($('#total_order'));
    $("#hitpay_payment_method").insertAfter($('#total_order'));
});
</script>
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
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2021 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}

    <script>
    let hitpay_payment_id='{$hitpay_payment_id|escape:'htmlall':'UTF-8'}';
    let hitpay_cart_id='{$hitpay_cart_id|escape:'htmlall':'UTF-8'}';
    let status_ajax_url='{$link->getModuleLink('hitpay', 'status')}';
    </script>
    <div>
        <p>&nbsp;</p>
        <div class="payment_pending" style="width: 100%; text-align: center; display: ">
            <img src="{$hitpay_img_path|escape:'htmlall':'UTF-8'}loader.gif" />
            <h3>{l s='We are retrieving your payment status, please wait...' mod='hitpay'}</h3>
        </div>
        <div class="payment_error" style="width: 100%; text-align: center; display: none">
            <img src="{$hitpay_img_path|escape:'htmlall':'UTF-8'}disabled.gif" />
            <span>{l s='Something went wrong, please contact the merchant' mod='hitpay'}</span>
        </div>
        <div class="payment_status_complete" style="width: 100%; text-align: center; display: none">
            <img src="{$hitpay_img_path|escape:'htmlall':'UTF-8'}check.png" style="width:200px" />
            <h3>{l s='Your payment has been confirmed!' mod='hitpay'}</h3>
            <p>&nbsp;</p>
            <p>{l s='We are redirecting you to order confirmation page...' mod='hitpay'}</p>
        </div>
        <div class="payment_status_failed" style="width: 100%; text-align: center; display: none">
            <img src="{$hitpay_img_path|escape:'htmlall':'UTF-8'}disabled.gif" style="width:200px" />
            <span>{l s='Your payment has been failed. ' mod='hitpay'}</span>
            <p>&nbsp;</p>
            <p>{l s='We are redirecting you to order confirmation page...' mod='hitpay'}</p>
        </div>
        <div class="payment_status_pending" style="width: 100%; text-align: center; display: none">
            <img src="{$hitpay_img_path|escape:'htmlall':'UTF-8'}warning.gif" style="width:200px" />
            <span>{l s='Your payment status is in pending.' mod='hitpay'}</span>
            <p>&nbsp;</p>
            <p>{l s='We are redirecting you to order confirmation page...' mod='hitpay'}</p>
        </div>
        <p>&nbsp;</p>
    </div>

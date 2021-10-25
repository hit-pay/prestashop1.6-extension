/**
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
*
* Don't forget to prefix your containers with your own identifier
* to avoid any conflicts with others containers.
*/

let is_status_received = false;
let is_payment_option_found = false;
$(document).ready(function(){
   check_hitpay_payment_status();

   function check_hitpay_payment_status() {

        function status_loop() {
            if (is_status_received) {
                return;
            }

            if (typeof(status_ajax_url) !== "undefined") {
                $.getJSON(status_ajax_url, {'payment_id' : hitpay_payment_id, 'cart_id' : hitpay_cart_id}, function (data) {
                    $.ajaxSetup({ cache: false });
                    if (data.status == 'wait') {
                        setTimeout(status_loop, 2000);
                    } else if (data.status == 'error') {
                        $('.payment_pending').hide();
                        $('.payment_error').show();
                        is_status_received = true;
                    } else if (data.status == 'pending') {
                        $('.payment_pending').hide();
                        $('.payment_status_pending').show();
                        is_status_received = true;
                        setTimeout(function(){window.location.href = data.redirect;}, 5000);
                    } else if (data.status == 'failed') {
                        $('.payment_pending').hide();
                        $('.payment_status_failed').show();
                        is_status_received = true;
                        setTimeout(function(){window.location.href = data.redirect;}, 5000);
                    } else if (data.status == 'completed') {
                        $('.payment_pending').hide();
                        $('.payment_status_complete').show();
                        is_status_received = true;
                        setTimeout(function(){window.location.href = data.redirect;}, 5000);
                    }
                });
            }
        }
        status_loop();
    }
});

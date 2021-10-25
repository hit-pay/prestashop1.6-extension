<?php
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
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2021 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

require_once _PS_MODULE_DIR_ . 'hitpay/classes/HitPayPayment.php';

class HitpayStatusModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        $status = 'wait';
        $redirect = '';
        $message = '';
        
        try {
            $payment_id = Tools::getValue('payment_id');
            $payment_id = trim($payment_id);
            $payment_id = strip_tags($payment_id);
            
            if (Tools::isEmpty($payment_id)) {
                throw new Exception($this->module->l('No payment id found'));
            }

            $cart_id = (int)Tools::getValue('cart_id');
            $cart = new Cart($cart_id);

            $saved_payment = HitPayPayment::getById($payment_id);
            if (Validate::isLoadedObject($saved_payment)
                && number_format($saved_payment->amount, 2) == number_format($cart->getOrderTotal(), 2)
                && $saved_payment->order_id
            ) {
                $status = $saved_payment->status;

                $customer = new Customer((int) $cart->id_customer);

                $params = [
                    'id_cart' => $saved_payment->cart_id,
                    'id_module' => $this->module->id,
                    'id_order' => $saved_payment->order_id,
                    'key' => $customer->secure_key
                ];

                $redirect = $this->context->link->getPageLink(
                    'order-confirmation',
                    true,
                    null,
                    $params
                );
            }
        } catch (\Exception $e) {
            $status = 'error';
            $message = $e->getMessage();
        }
        
        $data = [
            'status' => $status,
            'redirect' => $redirect,
            'message' => $message
        ];
        
        echo Tools::jsonEncode($data);
        die();
    }
}

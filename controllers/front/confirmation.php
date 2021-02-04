<?php
/**
* 2007-2020 PrestaShop
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
*  @copyright 2007-2020 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

require_once _PS_MODULE_DIR_ . 'hitpay/classes/HitPayPayment.php';

use HitPay\Client;

/**
 * Class HitpayConfirmationModuleFrontController
 */
class HitpayConfirmationModuleFrontController extends ModuleFrontController
{
    /**
     * @return bool
     */
    public function postProcess()
    {
        if ((Tools::isSubmit('cart_id') == false)
            || (Tools::isSubmit('secure_key') == false)
            || (Tools::isSubmit('reference') == false)) {
            return false;
        }

        $cart_id = Tools::getValue('cart_id');
        $secure_key = Tools::getValue('secure_key');
        $status = Tools::getValue('status');

        if ($status == 'canceled') {
            Tools::redirect('index.php?controller=order');
        }

        $cart = new Cart((int) $cart_id);
        $customer = new Customer((int) $cart->id_customer);

        if ($secure_key != $customer->secure_key) {
            $this->errors[] = $this->module->l(
                'An error occured. Please contact the merchant to have more informations'
            );

            return $this->setTemplate('error.tpl');
        }

        try {
            $payment_id = Tools::getValue('reference');
            /**
             * @var HitPayPayment $hitpay_payment
             */
            $saved_payment = HitPayPayment::getById($payment_id);
            if ($saved_payment->status == 'completed'
                && $saved_payment->amount == $cart->getOrderTotal()
                /*&& $saved_payment->is_paid*/
                && $saved_payment->order_id) {

                Tools::redirect(
                    'index.php?controller=order-confirmation&id_cart='
                    . $saved_payment->cart_id
                    . '&id_module='
                    . $this->module->id
                    . '&id_order='
                    . $saved_payment->order_id
                    . '&key='
                    . $secure_key
                );
            } else {
                throw new \Exception(
                    sprintf(
                        'HitPay: payment id: %s, amount is %s, status is %s, is paid: %s',
                        $saved_payment->payment_id,
                        $saved_payment->amount,
                        $saved_payment->status,
                        $saved_payment->is_paid ? 'yes' : 'no'
                    )
                );
            }
        } catch (\Exception $e) {
            PrestaShopLogger::addLog(
                'HitPay: ' . $e->getMessage(),
                3,
                null,
                'HitPay'
            );

            $this->errors[] = $this->module->l('Something went wrong, please contact the merchant');

            return $this->setTemplate('error.tpl');
        }

        exit;
    }
}

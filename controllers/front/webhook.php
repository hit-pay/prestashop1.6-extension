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
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2021 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

require_once _PS_MODULE_DIR_ . 'hitpay/classes/HitPayPayment.php';

use HitPay\Client;

/**
 * Class HitpayWebhookModuleFrontController
 */
class HitpayWebhookModuleFrontController extends ModuleFrontController
{
    /**
     * @return bool|void
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function postProcess()
    {
        PrestaShopLogger::addLog(
                date("Y-m-d H:i:s").': '.'HitPay: Webhook triggered',
                1,
                null,
                'HitPay'
            );

        if ((Tools::isSubmit('cart_id') == false)
            || (Tools::isSubmit('secure_key') == false)
            || (Tools::isSubmit('hmac') == false)) {
            exit;
        }

        ini_set('max_execution_time', 300);
        ini_set('max_input_time', -1);

        $cart_id = (int)Tools::getValue('cart_id');
        $secure_key = Tools::getValue('secure_key');
        
        if ($cart_id > 0) {
            if ($this->module->isWebhookTriggered($cart_id)) {
                exit;
            } else {
                //$this->module->addOrderWebhookTrigger($cart_id);
            }
        }
        
        $cart = new Cart((int) $cart_id);
        $customer = new Customer((int) $cart->id_customer);

        if ($secure_key != $customer->secure_key) {
            PrestaShopLogger::addLog(
                date("Y-m-d H:i:s").': '.'HitPay: Webhook Security Key Not matched',
                3,
                null,
                'HitPay'
            );
        }

        $payment_status = Configuration::get('HITPAY_WAITING_PAYMENT_STATUS');
        $message = null; // You can add a comment directly into the order so the merchant will see it in the BO.
        $transaction_id = null;
        $module_name = $this->module->displayName;
        $currency_id = (int) Context::getContext()->currency->id;

        try {
            $data = $_POST;
            /*PrestaShopLogger::addLog(
                date("Y-m-d H:i:s").': '.'HitPay: Webhook POST Data: '.print_r($data, true),
                1,
                null,
                'HitPay'
            );*/
            
            unset($data['hmac']);

            $salt = Configuration::get('HITPAY_ACCOUNT_SALT');
            
            $hmac_generated = Client::generateSignatureArray($salt, $data);
            
            if (Client::generateSignatureArray($salt, $data) == trim(Tools::getValue('hmac'))) {
                
                PrestaShopLogger::addLog(
                    date("Y-m-d H:i:s").': '.'HitPay: Webhook $hmac valid ',
                    1,
                    null,
                    'HitPay'
                );
                $payment_request_id = Tools::getValue('payment_request_id');
                /**
                 * @var HitPayPayment $hitpay_payment
                 */
                $saved_payment = HitPayPayment::getById($payment_request_id);
                if (Validate::isLoadedObject($saved_payment) && !$saved_payment->is_paid) {
                    $saved_payment->status = Tools::getValue('status');
                    
                    if ($saved_payment->status == 'completed'
                        && number_format($saved_payment->amount, 2) == Tools::getValue('amount')
                        && $saved_payment->cart_id == Tools::getValue('reference_number')
                        && $saved_payment->currency_id == Currency::getIdByIsoCode(Tools::getValue('currency'))) {
                        $payment_status = Configuration::get('PS_OS_PAYMENT');
                    } elseif ($saved_payment->status == 'failed') {
                        $payment_status = Configuration::get('PS_OS_ERROR');
                    } elseif ($saved_payment->status == 'pending') {
                        $payment_status = Configuration::get('HITPAY_WAITING_PAYMENT_STATUS');
                    } else {
                        throw new \Exception(
                            sprintf(
                                'HitPay: payment request id: %s, amount is %s, status is %s, is paid: %s',
                                $saved_payment->payment_id,
                                $saved_payment->amount,
                                $saved_payment->status,
                                $saved_payment->is_paid ? 'yes' : 'no'
                            )
                        );
                    }
                    
                    $order_id = $this->module->getIdByCartId((int) $cart->id);
                    if (!$order_id) {
                         $this->module->validateOrder(
                            $cart_id,
                            $payment_status,
                            $cart->getOrderTotal(),
                            $module_name,
                            $message,
                            array(),
                            $currency_id,
                            false,
                            $secure_key
                        );
                        $order_id = $this->module->getIdByCartId((int) $cart->id);
                        $saved_payment->order_id = $order_id;
                        $saved_payment->save();
                        
                        $this->module->addOrderWebhookTrigger($cart_id);
                    } else {
                        $order = new Order($order_id);
                        if ($order->current_state != Configuration::get('PS_OS_PAYMENT')) {
                            $new_history = new OrderHistory();
                            $new_history->id_order = (int) $order_id;
                            $new_history->changeIdOrderState((int) $payment_status, $order_id, true);
                            $new_history->add();
                            
                            $this->module->addOrderWebhookTrigger($cart_id);
                        }
                    }

                    if ($order_id) {
                        $hitpay_client = new Client(
                            Configuration::get('HITPAY_ACCOUNT_API_KEY'),
                            Configuration::get('HITPAY_LIVE_MODE')
                        );

                        $result = $hitpay_client->getPaymentStatus($payment_request_id);
                        if ($payments = $result->getPayments()) {
                            $payment = array_shift($payments);
                            if ($payment->status == 'succeeded') {
                                $transaction_id = $payment->id;
                            }

                            $order = new Order((int)$order_id);
                            $order_payments = OrderPayment::getByOrderReference($order->reference);
                            if (isset($order_payments[0])) {
                                $order_payments[0]->transaction_id = $transaction_id;
                                $order_payments[0]->save();

                                $saved_payment->is_paid = true;
                                $saved_payment->save();
                            }
                        }
                        
                        $order = new Order((int)$order_id);
                        $orderTotal = $order->total_paid;
                        $orderTotalPayment = 0;
                        $order_payments = OrderPayment::getByOrderReference($order->reference);
                        if ($order_payments) {
                            foreach ($order_payments as $order_payment) {
                                $orderTotalPayment += $order_payment->amount;
                                if ($orderTotalPayment > $orderTotal) {
                                    Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . 'order_payment`
                                        WHERE `id_order_payment` = \'' . pSQL($order_payment->id_order_payment) . '\''
                                    );
                                }
                            }
                        }
                    }
                } else {
                    PrestaShopLogger::addLog(
                        date("Y-m-d H:i:s").': '.'HitPay: Webhook Not valid saved payment or already Paid',
                        1,
                        null,
                        'HitPay'
                    );
                }
            } else {
                throw new \Exception(sprintf('HitPay: hmac is not the same like generated'));
            }
        } catch (\Exception $e) {
            PrestaShopLogger::addLog(
                date("Y-m-d H:i:s").': '.'HitPay: ' . $e->getMessage(),
                3,
                null,
                'HitPay'
            );
        }

        exit;
    }
}

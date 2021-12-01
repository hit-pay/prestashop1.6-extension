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
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2020 PrestaShop SA
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

require_once _PS_MODULE_DIR_ . 'hitpay/classes/HitPayPayment.php';

use HitPay\Client;
use HitPay\Request\CreatePayment;

class HitpayRedirectModuleFrontController extends ModuleFrontController
{
    /**
     * Do whatever you have to before redirecting the customer on the website of your payment processor.
     */
    public function postProcess()
    {

        try {
            /**
             * @var CartCore $cart
             */
            $cart = Context::getContext()->cart;

            /**
             * @var CurrencyCore $currency
             */
            $currency = Context::getContext()->currency;

            $hitpay_client = new Client(
                Configuration::get('HITPAY_ACCOUNT_API_KEY'),
                Configuration::get('HITPAY_LIVE_MODE')
            );
            $redirect_url = Context::getContext()->link->getModuleLink(
                'hitpay',
                'confirmation',
                [
                    'cart_id' => $cart->id,
                    'secure_key' => Context::getContext()->customer->secure_key,
                ],
                true
            );

            $webhook = Context::getContext()->link->getModuleLink(
                'hitpay',
                'webhook',
                [
                    'cart_id' => $cart->id,
                    'secure_key' => Context::getContext()->customer->secure_key,
                ],
                true
            );

            $create_payment_request = new CreatePayment();
            $create_payment_request->setAmount($cart->getOrderTotal())
                ->setCurrency($currency->iso_code)
                ->setReferenceNumber($cart->id)
                ->setWebhook($webhook)
                ->setRedirectUrl($redirect_url)
                ->setChannel('api_prestashop');

            /**
             * @var Customer $customer
             */
            if ($customer = Context::getContext()->customer) {
                $create_payment_request->setName($customer->firstname . ' ' . $customer->lastname);
                $create_payment_request->setEmail($customer->email);
            }
            $create_payment_request->setPurpose($this->module->getSiteName());

            $result = $hitpay_client->createPayment($create_payment_request);

            $payment = new HitPayPayment();
            $payment->payment_id = $result->getId();
            $payment->amount = $cart->getOrderTotal();
            $payment->currency_id = $currency->id;
            $payment->status = $result->getStatus();
            $payment->customer_id = isset($customer->id) ? $customer->id : null;
            $payment->cart_id = $cart->id;
            $payment->save();

            if ($result->getStatus() == 'pending') {
                Tools::redirect($result->getUrl());
            } else {
                $message = sprintf('HitPay: sent status is %s', $result->getStatus());
                throw new \Exception($message);
            }
        } catch (\Exception $e) {
            PrestaShopLogger::addLog(
                'HitPay: ' . $e->getMessage(),
                3,
                null,
                'HitPay'
            );

            return $this->displayError($this->module->l('Something went wrong, please contact the merchant'));
        }
    }

    /**
     * @param $message
     * @param bool $description
     * @return mixed
     */
    protected function displayError($message, $description = false)
    {
        /*
         * Create the breadcrumb for your ModuleFrontController.
         */
        $this->context->smarty->assign('path', '
			<a href="' . $this->context->link->getPageLink('order', null, null, 'step=3') . '">' . $this->module->l('Payment') . '</a>
			<span class="navigation-pipe">&gt;</span>' . $this->module->l('Error'));

        /*
         * Set error message and description for the template.
         */
        array_push($this->errors, $this->module->l($message), $description);

        return $this->setTemplate('error.tpl');
    }
}

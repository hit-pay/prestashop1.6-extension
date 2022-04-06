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

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'hitpay/vendor/autoload.php';
require_once _PS_MODULE_DIR_ . 'hitpay/classes/HitPayPayment.php';

use HitPay\Client;

/**
 * Class Hitpay
 */
class Hitpay extends PaymentModule
{
    protected $html = '';
    protected $postErrors = array();
    
    protected $config_form = false;
    public $refundTableName = 'hitpay_refund_order';
    public $webhookTableName = 'hitpay_webhook_order'; 
    
    protected $form_lang_fields = array(
        'HITPAY_TITLE',
    );

    /**
     * Hitpay constructor.
     */
    public function __construct()
    {
        $this->name = 'hitpay';
        $this->tab = 'payments_gateways';
        $this->version = '2.0.0';
        $this->author = 'hitpay';
        $this->need_instance = 0;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('HitPay Payment Gateway');
        $this->description = $this->l('HitPay allows merchants to accept secure PayNow QR, Credit Card, WeChatPay and AliPay payments.');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => '1.6.1.24');
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        if (extension_loaded('curl') == false)
        {
            $this->_errors[] = $this->l('You have to enable the cURL extension on your server to install this module');
            return false;
        }

        Configuration::updateValue('HITPAY_LIVE_MODE', false);

        $order_status = new OrderState();
        foreach (Language::getLanguages() as $lang) {
            $order_status->name[$lang['id_lang']] = $this->l('Waiting for payment confirmation');
        }
        $order_status->module_name = $this->name;
        $order_status->color = '#FF8C00';
        $order_status->send_email = false;
        if ($order_status->save()) {
            Configuration::updateValue('HITPAY_WAITING_PAYMENT_STATUS', $order_status->id);
        }

        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('backOfficeHeader') &&
            $this->registerHook('payment') &&
            $this->registerHook('paymentReturn') &&
            $this->registerHook('paymentOptions') &&
            $order_status->id &&
            HitPayPayment::install() && 
            $this->upgrade_1_1_5() &&
            $this->upgrade_1_1_6() &&
            $this->upgrade_2_0_0();
    }

    public function uninstall()
    {
        Configuration::deleteByName('HITPAY_LIVE_MODE');

        $order_status = new OrderState(Configuration::get('HITPAY_WAITING_PAYMENT_STATUS'));

        return parent::uninstall() &&
            $order_status->delete() &&
            HitPayPayment::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */
        if (((bool)Tools::isSubmit('submitHitpayModule')) == true) {
            $this->postValidation();
            if (!count($this->postErrors)) {
                $this->postProcess();
            } else {
                foreach ($this->postErrors as $err) {
                    $this->html .= $this->displayError($err);
                }
            }
        }

        $this->html .=  $this->renderForm();
        return $this->html;
    }
    
    public function getPaymentLogos()
    {
        $list = array(
            array(
                'value' => 'visa',
                'label' => $this->l('Visa')
            ),
            array(
                'value' => 'master',
                'label' => $this->l('Mastercard')
            ),
            array(
                'value' => 'american_express',
                'label' => $this->l('American Express')
            ),
            array(
                'value' => 'apple_pay',
                'label' => $this->l('Apple Pay')
            ),
            array(
                'value' => 'google_pay',
                'label' => $this->l('Google Pay')
            ),
            array(
                'value' => 'paynow',
                'label' => $this->l('PayNow QR')
            ),
            array(
                'value' => 'grabpay',
                'label' => $this->l('GrabPay')
            ),
            array(
                'value' => 'wechatpay',
                'label' => $this->l('WeChatPay')
            ),
            array(
                'value' => 'alipay',
                'label' => $this->l('AliPay')
            ),
            array(
                'value' => 'shopeepay',
                'label' => $this->l('Shopee Pay')
            ),
            array(
                'value' => 'fpx',
                'label' => $this->l('FPX')
            ),
            array(
                'value' => 'zip',
                'label' => $this->l('Zip')
            ),
        );
        return $list;
    }
    
    public function getPaymentLogoOptions()
    {
        $options = [];
        $list = $this->getPaymentLogos();
        foreach ($list as $item) {
            $options[$item['value']] = $item['label'];
        }
        
        return $options;
    }
    
    public function getOrderStatuses()
    {
        $list[] = ['id' => 0, 'name' => '---'];
        $skipStates = [
            Configuration::get('PS_OS_CANCELED'),
            Configuration::get('PS_OS_REFUND'),
            Configuration::get('PS_OS_ERROR'),
            Configuration::get('PS_OS_OUTOFSTOCK'),
            Configuration::get('PS_OS_OUTOFSTOCK_PAID'),
            Configuration::get('PS_OS_OUTOFSTOCK_UNPAID')
        ];
        
        $states = OrderState::getOrderStates($this->context->language->id);
        foreach ($states as $state) {
            if (!in_array($state['id_order_state'], $skipStates)) {
                $list[] = ['id' => $state['id_order_state'], 'name' => $state['name']];
            }
        }
        return $list;
    }
    
    public function getOrderStatusesOptions()
    {
        $options = [];
        $list = $this->getOrderStatuses();
        foreach ($list as $item) {
            $options[$item['id']] = $item['name'];
        }
        
        return $options;
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitHitpayModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                'title' => $this->l('Settings'),
                'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Live mode'),
                        'name' => 'HITPAY_LIVE_MODE',
                        'is_bool' => true,
                        'desc' => $this->l('Use this module in live mode'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Title'),
                        'desc' => $this->l('This will display on the checkout'),
                        'name' => 'HITPAY_TITLE',
                        'lang' => true,
                    ),
                    array(
                        'type' => 'text',
                        'name' => 'HITPAY_ACCOUNT_API_KEY',
                        'label' => $this->l('Api Key'),
                    ),
                    array(
                        'type' => 'text',
                        'name' => 'HITPAY_ACCOUNT_SALT',
                        'label' => $this->l('Salt'),
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->l('Payment Logos'),
                        'name' => 'HITPAY_PAYMENT_LOGOS',
                        'multiple' => true,
                        'options' => array(
                            'query' => $this->getPaymentLogos(),
                            'id' => 'value',
                            'name' => 'label'
                        ),
                        'size' => 10,
                        'desc' => $this->l('Select the logos which you would like to display on checkout. CTRL + click to select multiple')
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->l('Order Status'),
                        'name' => 'HITPAY_ORDER_STATUS',
                        'options' => array(
                            'query' => $this->getOrderStatuses(),
                            'id' => 'id',
                            'name' => 'name'
                        ),
                        'desc' => $this->l('Set your desired order status upon successful payment.')
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        $HITPAY_PAYMENT_LOGOS = Configuration::get('HITPAY_PAYMENT_LOGOS');
        if (isset($_REQUEST['HITPAY_PAYMENT_LOGOS'])) {
            $HITPAY_PAYMENT_LOGOS = Tools::getValue('HITPAY_PAYMENT_LOGOS');
            $HITPAY_PAYMENT_LOGOS =  implode(',', $HITPAY_PAYMENT_LOGOS);
        }
        
        $LIVE_MODE = Configuration::get('HITPAY_LIVE_MODE');
        $API_KEY = Configuration::get('HITPAY_ACCOUNT_API_KEY');
        $SALT = Configuration::get('HITPAY_ACCOUNT_SALT');
        $ORDER_STATUS = Configuration::get('HITPAY_ORDER_STATUS');
        
        $params1 = array(
            'HITPAY_LIVE_MODE' => Tools::getValue('HITPAY_LIVE_MODE', $LIVE_MODE),
            'HITPAY_ACCOUNT_API_KEY' => Tools::getValue('HITPAY_ACCOUNT_API_KEY', $API_KEY),
            'HITPAY_ACCOUNT_SALT' => Tools::getValue('HITPAY_ACCOUNT_SALT', $SALT),
            'HITPAY_PAYMENT_LOGOS[]' => explode(',',$HITPAY_PAYMENT_LOGOS),
            'HITPAY_ORDER_STATUS' => Tools::getValue('HITPAY_ORDER_STATUS', $ORDER_STATUS),
        );
        
        $languages = Language::getLanguages(false);
        foreach ($languages as $language) {
            $id_lang = $language['id_lang'];
            foreach ($this->form_lang_fields as $key => $val) {
                $field_key = $val.'_'.$id_lang;
                $field_val = Configuration::get($field_key);
                $params2[$val][$id_lang] = Tools::getValue($field_key, $field_val);
            }
        }
        $params = array_merge($params1, $params2);
        return $params;
    }

    protected function postValidation()
    {
        $HITPAY_ACCOUNT_API_KEY = Tools::getValue('HITPAY_ACCOUNT_API_KEY');
        $HITPAY_ACCOUNT_API_KEY = trim($HITPAY_ACCOUNT_API_KEY);
        $HITPAY_ACCOUNT_API_KEY = strip_tags($HITPAY_ACCOUNT_API_KEY);
        
        $HITPAY_ACCOUNT_SALT = Tools::getValue('HITPAY_ACCOUNT_SALT');
        $HITPAY_ACCOUNT_SALT = trim($HITPAY_ACCOUNT_SALT);
        $HITPAY_ACCOUNT_SALT = strip_tags($HITPAY_ACCOUNT_SALT);
        
        if (empty($HITPAY_ACCOUNT_API_KEY)) {
            $this->postErrors[] = $this->l('Please provide API Key');
        }
        if (empty($HITPAY_ACCOUNT_SALT)) {
            $this->postErrors[] = $this->l('Please provide API Salt');
        }
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            if ($key == 'HITPAY_PAYMENT_LOGOS[]') {
                continue;
            }
            Configuration::updateValue($key, Tools::getValue($key));
        }
        
        if (isset($_REQUEST['HITPAY_PAYMENT_LOGOS'])) {
            $HITPAY_PAYMENT_LOGOS = Tools::getValue('HITPAY_PAYMENT_LOGOS');
            $HITPAY_PAYMENT_LOGOS =  implode(',', $HITPAY_PAYMENT_LOGOS);
        } else {
            $HITPAY_PAYMENT_LOGOS = '';
        } 
        Configuration::updateValue('HITPAY_PAYMENT_LOGOS', $HITPAY_PAYMENT_LOGOS);
        
        $languages = Language::getLanguages(false);
        foreach ($languages as $language) {
            $id_lang = $language['id_lang'];
            foreach ($this->form_lang_fields as $key => $val) {
                $field_key = $val.'_'.$id_lang;
                $field_val = Tools::getValue($field_key);
                Configuration::updateValue($field_key,  $field_val);
            }
        }

        $this->html .= $this->displayConfirmation($this->l('Settings updated'));
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        $this->context->controller->addJS($this->_path.'/views/js/front.js');
        $this->context->controller->addCSS($this->_path.'/views/css/front.css');
    }

    /**
     * This method is used to render the payment button,
     * Take care if the button should be displayed or not.
     */
    public function hookPayment($params)
    {
        $currency_id = $params['cart']->id_currency;

        $this->smarty->assign('module_dir', $this->_path);

        $logos = Configuration::get('HITPAY_PAYMENT_LOGOS');
        if (!empty($logos)) {
            $this->smarty->assign('hitpay_logos', explode(',',$logos));
        }
        $this->smarty->assign('hitpay_logo_path', _MODULE_DIR_.$this->name.'/views/img/');
        $this->smarty->assign('hitpay_title', $this->getTitle());
    
        return $this->display(__FILE__, 'views/templates/hook/payment.tpl');
    }
    
    /**
     * This hook is used to display the order confirmation page.
     */
    public function hookPaymentReturn($params)
    {
        if ($this->active == false)
            return;

        $order = $params['objOrder'];

        if ($order->getCurrentOrderState()->id != Configuration::get('PS_OS_ERROR'))
            $this->smarty->assign('status', 'ok');

        $this->smarty->assign(array(
            'id_order' => $order->id,
            'reference' => $order->reference,
            'params' => $params,
            'total' => Tools::displayPrice($params['total_to_pay'], $params['currencyObj'], false),
        ));

        return $this->display(__FILE__, 'views/templates/hook/confirmation.tpl');
    }

    public function getSiteName()
    {
        $name = Configuration::get("PS_SHOP_NAME");
        if (empty($name)) {
            $name = $this->context->shop->name;
        }
        
        return $name;
    }
    
    public function getPaymentOrderStatus()
    {
        $status = (int)Configuration::get("HITPAY_ORDER_STATUS");
        if ($status == 0) {
            $status = Configuration::get('PS_OS_PAYMENT');
        }
        
        return $status;
    }
    
    public function getTitle()
    {
        $id_lang = $this->context->language->id;

        $title = Configuration::get(
            'HITPAY_TITLE_'.$id_lang,
            null, 
            null, 
            null, 
            'HitPay Payment Gateway'
        );
        
        if (empty($title)) {
            $title = $this->l('HitPay Payment Gateway');
        }
        
        return $title;
    }

    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);
        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;
    }
    
    public function upgrade_1_1_5()
    {
        $sql = 'CREATE TABLE IF NOT EXISTS '._DB_PREFIX_.$this->refundTableName. '(
            id int not null auto_increment,
            order_id int(11),
            refund_id varchar(255),
            payment_id varchar(255),
            status  varchar(50),
            amount_refunded float(10,2),
            total_amount float(10,2),
            currency varchar(4),
            payment_method varchar(50),
            created_at varchar(50),
            primary key(id)
        )';
        Db::getInstance()->execute($sql);
        return $this->registerHook('displayAdminOrder');
    }
    
    public function upgrade_1_1_6()
    {
        $sql = 'CREATE TABLE IF NOT EXISTS '._DB_PREFIX_.$this->webhookTableName. '(
            id int not null auto_increment,
            order_id int(11),
            primary key(id)
        )';
        Db::getInstance()->execute($sql);
        return true;
    }
    
    public function upgrade_2_0_0()
    {
        $sql = 'ALTER TABLE '._DB_PREFIX_. 'hitpay_payments ADD payment_type varchar(50)';
        Db::getInstance()->execute($sql);
        
        $sql = 'ALTER TABLE '._DB_PREFIX_. 'hitpay_payments_shop ADD payment_type varchar(50)';
        Db::getInstance()->execute($sql);
        
        $sql = 'ALTER TABLE '._DB_PREFIX_. 'hitpay_payments ADD fees decimal(20,6)';
        Db::getInstance()->execute($sql);
        
        $sql = 'ALTER TABLE '._DB_PREFIX_. 'hitpay_payments_shop ADD fees decimal(20,6)';
        Db::getInstance()->execute($sql);
        return true;
    }
    
    public function getIdByCartId($id_cart)
    {
        $sql = 'SELECT `id_order` 
            FROM `' . _DB_PREFIX_ . 'orders`
            WHERE `id_cart` = ' . (int) $id_cart .
            Shop::addSqlRestriction();

        $result = Db::getInstance()->getValue($sql);

        return !empty($result) ? (int) $result : false;
    }
    
    public function isWebhookTriggered($order_id)
    {
        return (int)Db::getInstance()->getValue('select id FROM ' . _DB_PREFIX_.$this->webhookTableName . ' WHERE order_id='.(int)($order_id));
    }
    
    public function addOrderWebhookTrigger($order_id)
    {
        Db::getInstance()->insert($this->webhookTableName, array('order_id' => (int) $order_id));
    }
    
    public function isRefunded($order_id)
    {
        return (int)Db::getInstance()->getValue('select id FROM ' . _DB_PREFIX_.$this->refundTableName . ' WHERE order_id='.(int)($order_id));
    }
    
    public function getRefund($order_id)
    {
        return Db::getInstance()->getRow('select * FROM ' . _DB_PREFIX_.$this->refundTableName . ' WHERE order_id='.(int)($order_id));
    }
    
    public function addOrderRefund($order_id, $result)
    {
        Db::getInstance()->insert($this->refundTableName, array(
            'order_id' => (int) $order_id,
            'refund_id' =>  $result->getId(),
            'payment_id' => $result->getPaymentId(),
            'status' => $result->getStatus(),
            'amount_refunded' => $result->getAmountRefunded(),
            'total_amount' => $result->getTotalAmount(),
            'currency' => $result->getCurrency(),
            'payment_method' => $result->getPaymentMethod(),
            'created_at' => $result->getCreatedAt()
        ));
    }
    
    private function getOrderFromParams($params)
    {
        if (isset($params['id_order'])) {
            return new Order((int)$params['id_order']);
        } elseif (isset($params['order']->id)) {
            return  $params['order'];
        } elseif (isset($params['objOrder']->id)) {
            return  $params['objOrder'];
        }
        return false;
    }

    public function hookDisplayAdminOrder($params)
    {
        $html = '';
        $refund_error = '';
        $refund_success = '';
        $id_order = Tools::getValue('id_order');
        if ($id_order > 0) {
            $order = new Order($id_order);
            if ($order && $order->id > 0 && ($order->module == $this->name)) {
                
                $id_cart = $order->id_cart;

                $order_payments = OrderPayment::getByOrderReference($order->reference);
                if (isset($order_payments[0])) {
                    $transaction_id = $order_payments[0]->transaction_id;
                    if (!empty($transaction_id)) {
                        $savedPayment = HitPayPayment::getByOrderId($id_order);
                        if (Validate::isLoadedObject($savedPayment) && $savedPayment->is_paid) {
                            if (Tools::isSubmit('hitpay_refund')) {
                                try {
                                    
                                    $amount = (float)strip_tags(trim(Tools::getValue('hitpay_amount')));
                                    $amount = Tools::ps_round($amount, 2);
                                    
                                    $order_total_paid = $order->getTotalPaid();
                                    if ($amount <= $order_total_paid) {

                                        $hitpayClient = new Client(
                                            Configuration::get('HITPAY_ACCOUNT_API_KEY'),
                                            Configuration::get('HITPAY_LIVE_MODE')
                                        );

                                        $result = $hitpayClient->refund($transaction_id, $amount);

                                        $this->addOrderRefund($id_order, $result);

                                        $message = $this->l('Refund successful. Refund Reference Id: '.$result->getId().', '
                                                . 'Payment Id: '.$transaction_id.', Amount Refunded: '.$result->getAmountRefunded().', '
                                                . 'Payment Method: '.$result->getPaymentMethod().', Created At: '.$result->getCreatedAt());
                                        $refund_success = $message;
                                        
                                        $total_refunded = $result->getAmountRefunded();
                                        if ($total_refunded >= $order_total_paid) {
                                            $order->setCurrentState(Configuration::get('PS_OS_REFUND'));
                                            $refund_success .= $this->l(' Order status changed, please reload the page');
                                        }
                    
                                    } else {
                                        throw new Exception($this->l('Refund amount shoule be less than or equal to order paid total ('.$order_total_paid.')'));
                                    }
                                } catch (\Exception $e) {
                                    $refund_error = $this->l('Refund Payment Failed: ').$e->getMessage();
                                }
                            }
                            $savedPayment->amount = $this->displayPrice($savedPayment->amount);
                            
                            $refund = $this->getRefund($id_order);
                            if ($refund) {
                                $refund['amount_refunded'] = $this->displayPrice($refund['amount_refunded']);
                                $refund['total_amount'] = $this->displayPrice($refund['total_amount']);
                                $this->context->smarty->assign('refundData', $refund);
                            } else {
                                $this->context->smarty->assign('payment_id', $transaction_id);
                                $this->context->smarty->assign('savedPayment', $savedPayment);
                            }
                            $this->context->smarty->assign('refund_error', $refund_error);
                            $this->context->smarty->assign('refund_success', $refund_success);

                            $html .= $this->display(__FILE__, 'admin_order.tpl');
                        }
                    }
                }
            }
        }
        
        $order = $this->getOrderFromParams($params);
        if ($order && $order->id > 0 && ($order->module == $this->name)) {
            $savedPayment = HitPayPayment::getByOrderId($order->id);
            if (Validate::isLoadedObject($savedPayment) && $savedPayment->is_paid) {
                
                $payment_method = '';
                $payment_request_id = $savedPayment->payment_id;
                
                if (!empty($payment_request_id)) {
                    $payment_method = $savedPayment->payment_type;
                    $fees = $savedPayment->fees;
                    if (empty($payment_method) || empty($fees)) {
                        try {
                            $hitpayClient = new Client(
                                Configuration::get('HITPAY_ACCOUNT_API_KEY'),
                                Configuration::get('HITPAY_LIVE_MODE')
                            );

                            $paymentStatus = $hitpayClient->getPaymentStatus($payment_request_id);
                            if ($paymentStatus) {
                                $payments = $paymentStatus->payments;
                                if (isset($payments[0])) {
                                    $payment = $payments[0];
                                    $payment_method = $payment->payment_type;
                                    $savedPayment->payment_type = $payment_method;
                                    $fees = $payment->fees;
                                    $savedPayment->fees = $fees;
                                    $savedPayment->save();
                                }
                            }
                        } catch (\Exception $e) {
                            $payment_method = $e->getMessage();
                        }
                    }
                }
                
                if (!empty($payment_method)) {
                    $this->context->smarty->assign(
                        array(
                            'payment_method' => ucwords(str_replace("_", " ", $payment_method)),
                            'hitpay_fee' => $this->displayPriceWithCurrency($savedPayment->fees, (int)$savedPayment->currency_id)
                        )
                    );
                    $html .= $this->display(__FILE__, 'payment_details.tpl');
                }
            }
        }
        
        return $html;
    }
    
    public function displayPrice($price)
    {
        return Tools::displayPrice($price);
    }
    
    public function displayPriceWithCurrency($price, $currency)
    {
        return Tools::displayPrice($price, $currency);
    }
}

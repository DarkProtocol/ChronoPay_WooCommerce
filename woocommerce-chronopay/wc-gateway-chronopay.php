<?php
/**
 * Plugin Name: WooCommerce ChronoPay Gateway
 * Description: Extends WooCommerce with ChronoPay Gateway.
 * Version: 1.0.0
 * Author: Stepan Kushtuev
 */
if (!defined('ABSPATH')) exit;


// if ngnix, not Apache 
if (!function_exists('getallheaders'))  {

    function getallheaders()
    {
        if (!is_array($_SERVER)) {
            return array();
        }

        $headers = array();
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }

}

// require CountryCodes
require_once __DIR__ . '/CountryCodes.php';

// register plugin
add_action('plugins_loaded', 'ChronoPay', 0);

function ChronoPay() {  

    // if not exists main class
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    } 
    

    function wc_add_chronopay_payment($methods) {   
        $methods[] = 'WC_ChronoPay'; 
        return $methods;
    }

    // register chronopay payment
    add_filter('woocommerce_payment_gateways', 'wc_add_chronopay_payment');

    class WC_ChronoPay extends WC_Payment_Gateway 
    {
        

        /**
         * Default paymentUrl constant  
         */
        const DEFAULT_PAYMENT_URL = 'https://payments.chronopay.com/';


        /**
         * Purchase transaction status
         */
        const PURCHASE_TRANSACTION_TYPE = 'Purchase';


        /**
         * Refund transaction status
         */
        const REFUND_TRANSACTION_TYPE = 'Refund';


        /** 
         * Contruct method (now we init plugin options)
         *
         * @return void
         */
        public function __construct() 
        {

            // init payment method options
            $this->id                 = 'chronopay';
            $this->has_fields         = true;
            $this->icon               = plugin_dir_url(__FILE__) . 'chronopay-icon.jpg';
            $this->method_title       = __('ChronoPay', 'woocommerce');
            $this->method_description = 'ChronoPay Payment method';
            $this->supports           = array('products', 'pre-orders');
            $this->enabled            =  $this->get_option('enabled');

            // init settings data
            $this->init_form_fields();
            $this->init_settings();

            // Define user set variables
            $this->title              = $this->get_option('title');
            $this->description        = $this->get_option('description');
            $this->productId          = $this->get_option('productId');
            $this->sharedSec          = $this->get_option('sharedSec');
            $this->paymentsUrl        = $this->get_option('paymentsUrl');
            $this->cbUrl              = $this->get_option('cbUrl');
            $this->cbType             = $this->get_option('cbType');
            $this->successUrl         = $this->get_option('successUrl');
            $this->declineUrl         = $this->get_option('declineUrl');
            $this->paymentTypeGroupId = $this->get_option('paymentTypeGroupId');
            $this->language           = $this->get_option('language');
            $this->orderTimelimit     = $this->get_option('orderTimelimit');
            $this->orderExpiretime    = $this->get_option('orderExpiretime');

            add_action('wc_add_chronopay_payment', array($this, 'payment_page'));
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this,'process_admin_options'));
            add_action( 'woocommerce_api_' . strtolower(get_class($this)), array($this, 'callbackHandler'));

        }


        /**
         * Get enabled
         *
         * @return bool
         */
        public function getEnabled()
        {
            return $this->enabled === 'yes';
        }


        /**
         * Get sharedSec
         *
         * @return string
         */
        public function getSharedSec()
        {
            return $this->sharedSec;
        }


        /**
         * Get productId
         *
         * @return string
         */
        public function getProductId()
        {
            return $this->productId;
        }


        /**
         * Get paymentsUrl
         *
         * @return string
         */
        public function getPaymentsUrl()
        {
            return $this->paymentsUrl;
        }


        /**
         * Get request param or null
         *
         * @param string $paramName
         *
         * @return string | null 
         */
        public function getRequestParam($paramName)
        {
            if (!isset($_REQUEST[$paramName])) {
                return null;
            }

            return $_REQUEST[$paramName];
        }


        /**
         * Get default callback url
         *
         * @return string | null 
         */
        public function getDefaultCallbackUrl()
        {
            return home_url('/wc-api/'.strtolower(get_class($this)));
        }


        /**
         * Get param cbUrl
         *
         * @return string | null 
         */
        public function getCbUrl()
        {
            return $this->cbUrl;
        }


        /**
         * Get param cbType
         *
         * @return string | null 
         */
        public function getCbType()
        {
            return $this->cbType;
        }


        /**
         * Get param successUrl
         *
         * @return string | null 
         */
        public function getSuccessUrl()
        {
            return $this->successUrl;
        }


        /**
         * Get param declineUrl
         *
         * @return string | null 
         */
        public function getDeclineUrl()
        {
            return $this->declineUrl;
        }


        /**
         * Get param paymentTypeGroupId
         *
         * @return string | null 
         */
        public function getPaymentTypeGroupId()
        {
            return $this->paymentTypeGroupId;
        }


        /**
         * Get param language
         *
         * @return string | null 
         */
        public function getLanguage()
        {
            return $this->language;
        }


        /**
         * Get param orderTimelimit
         *
         * @return int | null 
         */
        public function getOrderTimelimit()
        {
            return $this->orderTimelimit === null ? $this->orderTimelimit : (int) $this->orderTimelimit;
        }


        /**
         * Get param orderExpiretime
         *
         * @return int | null 
         */
        public function getOrderExpiretime()
        {
            return $this->orderExpiretime === null ? $this->orderExpiretime : (int) $this->orderExpiretime;
        }


        /** 
         * Add new form fields to admin
         *
         * @return void
         */
        public function init_form_fields() 
        {
            $this->form_fields = array(
                'enabled' => array(
                    'title'   => __('Enable/Disable', 'woocommerce'),
                    'type'    => 'checkbox',
                    'label'   => __('Включить ChronoPay', 'woocommerce'),
                    'default' => 'no',
                ),
                'title' => array(
                    'title'       => __('Title', 'woocommerce'),
                    'type'        => 'text',
                    'description' => __('Заголовок на странице оплаты', 'woocommerce' ),
                    'default'     => __('ChronoPay', 'woocommerce'),
                    'desc_tip'    => false,
                ),
                'description' => array(
                    'title'       => __('Description', 'woocommerce' ),
                    'type'        => 'textarea',
                    'description' => __('Описание платежного метода на странице оплаты.', 'woocommerce' ),
                    'default'     => __('ChronoPay Payment Method', 'woocommerce'),
                    'desc_tip'    => false,
                ),
                'paymentsUrl' => array(
                    'title'       => __('Payments Url', 'woocommerce'),
                    'type'        => 'text',
                    'description' => 'URL перенаправления на страницу оплаты (если не знаете, что это, оставьте поле пустым)',
                    'default'     => self::DEFAULT_PAYMENT_URL,
                    'desc_tip'    => false,
                ),
                'sharedSec' => array(
                    'title'       => __('SharedSec', 'woocommerce'),
                    'type'        => 'text',
                    'description' => 'SharedSec из личного кабинета ChronoPay',
                    'default'     => '',
                    'desc_tip'    => false,
                ),
                'productId' => array(
                    'title'       => __('Product ID', 'woocommerce'),
                    'type'        => 'text',
                    'description' => 'Product ID из личного кабинета ChronoPay',
                    'default'     => '',
                    'desc_tip'    => false,
                ),
                'cbUrl' => array(
                    'title'       => __('Cb Url', 'woocommerce'),
                    'type'        => 'text',
                    'description' => 'URL для отправки уведомления о платеже. (Оставьте по умолчанию)',
                    'default'     => $this->getDefaultCallbackUrl(),
                    'desc_tip'    => false,
                ),
                'cbType' => array(
                    'title'       => __('Cb Type', 'woocommerce'),
                    'type'        => 'text',
                    'description' => 'Метод отправки уведомления о платеже',
                    'default'     => '',
                    'desc_tip'    => false,
                ),
                'successUrl' => array(
                    'title'       => __('Success Url', 'woocommerce'),
                    'type'        => 'text',
                    'description' => 'URL страницы в системе Продавца для перенаправления Покупателя в случае успешной оплаты. (По умолчанию будет стандартная страница успешной оплаты WooCommerce)',
                    'default'     => '',
                    'desc_tip'    => false,
                ),
                'declineUrl' => array(
                    'title'       => __('Decline Url', 'woocommerce'),
                    'type'        => 'text',
                    'description' => 'URL страницы в системе Продавца для перенаправления Покупателя в случае неуспешной попытки оплаты.',
                    'default'     => '',
                    'desc_tip'    => false,
                ),
                'paymentTypeGroupId' => array(
                    'title'       => __('Payment Type Group Id', 'woocommerce'),
                    'type'        => 'text',
                    'description' => 'Идентификатор Платежного инструмента, который используется для его автовыбора.',
                    'default'     => '',
                    'desc_tip'    => false,
                ),
                'language' => array(
                    'title'       => __('Language', 'woocommerce'),
                    'type'        => 'text',
                    'description' => 'Язык отображаемых Покупателю страниц в процессе оформления им платежа на стороне платежной платформы',
                    'default'     => 'ru',
                    'desc_tip'    => false,
                ),
                'orderTimelimit' => array(
                    'title'       => __('Order Time Limit', 'woocommerce'),
                    'type'        => 'text',
                    'description' => 'Максимальное время нахождения Покупателя на платежной странице в минутах. Укажите целые числа. (Укажите либо orderTimelimit, либо orderExpiretime)',
                    'default'     => '',
                    'desc_tip'    => false,
                ),
                'orderExpiretime' => array(
                    'title'       => __('Order Expire Time', 'woocommerce'),
                    'type'        => 'text',
                    'description' => 'Дата и время истечения резерва заказа. Укажите количество минут в целых числах. (Укажите либо orderTimelimit, либо orderExpiretime)',
                    'default'     => '',
                    'desc_tip'    => false,
                ),
            );
        }


        /**
         * @inheritdoc
         */
        public function admin_options() 
        {
            $cbUrl = $this->getDefaultCallbackUrl();

            echo "
            <h2>Настройки ChronoPay:</h2>
                <p>
                    <strong>Callback Url - урл для подтверждения транзакции (cbUrl): </strong>
                    $cbUrl 
                </p>
            ";
        
            echo '<table class="form-table">';
            $this->generate_settings_html();
            echo '</table>';
        }


        /**
         * @inheritdoc
         */
        public function process_payment($order_id) 
        {
            
            global $woocommerce;
            
            $order = new WC_Order( $order_id );

            // Mark as on-hold
            $order->update_status('pending');
            $order->add_order_note(__( 'Ожидание оплаты (ChronoPay)<br\>', 'woocommerce' ));

            // Reduce stock levels
            $order->reduce_order_stock();

            // Remove cart
            $woocommerce->cart->empty_cart();

            // generate url
            return array(
                'result' => 'success',
                'redirect' => $this->generatePaymentUrl($order)
            );

        }


        /**
         * Generate payment url
         *
         * @param WC_Order $order
         *
         * @return sting
         */
        private function generatePaymentUrl(WC_Order $order)
        {   

            // get success url
            $successUrl = $this->getSuccessUrl();
            if (!$successUrl) {
                $successUrl = $this->get_return_url($order);
            }

            $orderId = $order->get_id();
            $orderPrice = (float)$order->get_total() - (float)$order->get_total_shipping();

            $url = $this->getPaymentsUrl();
            $url .= '?product_id=' . urlencode($this->getProductId());
            $url .= '&product_price=' . urlencode($orderPrice);
            $url .= '&order_id=' . urlencode($orderId);
            $url .= '&success_url=' . urlencode($successUrl);

            // add decline url
            if (strlen($this->getDeclineUrl()) > 0) {
                $url .= '&decline_url=' . $this->getDeclineUrl();
            }

            // add cb_url
            if (strlen($this->getCbUrl()) > 0) {
                $url .= '&cb_url=' . $this->getCbUrl();
            } 

            // add cb_type
            if (strlen($this->getCbType()) > 0) {
                $url .= '&cb_type=' . $this->getCbType();
            } 

            // add payment_type_group_id
            if (strlen($this->getPaymentTypeGroupId()) > 0) {
                $url .= '&payment_type_group_id=' . $this->getPaymentTypeGroupId();
            } 

            // add language
            if (strlen($this->getLanguage()) > 0) {
                $url .= '&language=' . $this->getLanguage();
            } 

            // add orderTimelimit
            if ($this->getOrderTimelimit() != null) {

                $url .= '&orderTimelimit=' . $this->getOrderTimelimit();
                $url .= '&sign=' . $this->generatePaymentSign($orderPrice, $orderId, $this->getOrderTimelimit());

            } elseif ($this->getOrderExpiretime() != null) {

                $orderExpiretime = date('Y-m-d\TH:i:sO', time() + $this->getOrderExpiretime() * 60);
                $url .= '&orderExpiretime=' . urlencode($orderExpiretime);
                $url .= '&sign=' . $this->generatePaymentSign($orderPrice, $orderId, $orderExpiretime);

            } else {
                $url .= '&sign=' . $this->generatePaymentSign($orderPrice, $orderId);
            }

            /* CLIENT ADRESS DATA */
            // add country
            if ($order->get_billing_country() != null) {
                $url .= '&country=' . CountryCodes::$countries[strtoupper($order->get_billing_country())]['alpha3'];
            } 


            // add city
            if ($order->get_billing_city() != null) {
                $url .= '&city=' . urlencode($order->get_billing_city());
            } 

            // add street
            if ($order->get_billing_address_1() != null) {

                $url .= '&street=' . urlencode($order->get_billing_address_1());

                if ($order->get_billing_address_2() != null) {
                    $url .= urlencode($order->get_billing_address_2);
                }
            } 

            // add zip
            if ($order->get_billing_postcode() != null) {
                $url .= '&zip=' . urlencode($order->get_billing_postcode());
            } 


            /* CLIENT NAME DATA */

            // add f_name
            if ($order->get_billing_first_name() != null) {
                $url .= '&f_name=' . urlencode($order->get_billing_first_name());
            } 

            // add s_name
            if ($order->get_billing_last_name() != null) {
                $url .= '&s_name=' . urlencode($order->get_billing_last_name());
            } 

            // add phone
            if ($order->get_billing_phone() != null) {
                $url .= '&phone=' . urlencode($order->get_billing_phone());
            } 

            // add email
            if ($order->get_billing_email() != null) {
                $url .= '&email=' . urlencode($order->get_billing_email());
            }

            return $url;

        }


        /**
         * Generate payment sign
         *
         * @param float $orderPrice
         * @param string $orderId
         *
         * @return string
         */
        private function generatePaymentSign($orderPrice, $orderId, $additionalParam = null)
        {   

            $additionalParamString = '';

            if ($additionalParam != null) {
                $additionalParamString = $additionalParam . '-' ;
            }

            return md5(
                $this->getProductId() . '-' . 
                $orderPrice . '-' . 
                $orderId . '-' . $additionalParamString . $this->getSharedSec()
            );
        }


        /**
         * Check callback sign
         *
         * @param string $sign
         * @param string $customerId
         * @param string $transactionId
         * @param string $transactionType
         * @param string $total
         *
         * @return bool
         */
        public function checkCallbackSign($sign, $customerId, $transactionId, $transactionType, $total)
        {
            $generatedSign = md5($this->getSharedSec() . $customerId . $transactionId . $transactionType . $total);
            return $sign === $generatedSign;
        } 

        
        /**
         * Callback handler
         *
         * @return void
         */
        public function callbackHandler() {

            $response = array (
                'status' => 'fail'
            );

            // check all params
            $customerId = $this->getRequestParam('customer_id'); 
            $transactionId = $this->getRequestParam('transaction_id');
            $transactionType = $this->getRequestParam('transaction_type');
            $total = $this->getRequestParam('total'); 
            $sign = $this->getRequestParam('sign'); 
            $orderId = $this->getRequestParam('order_id'); 

            if (!$customerId || !$transactionId || !$transactionType || !$total || !$sign || !$orderId) {
                $response['error'] = 'Missing required params';
                echo json_encode($response);
                exit;
            }

            // сheck module 
            if (!$this->getEnabled() || empty($this->getSharedSec())) {
                $response['error'] = 'Plugin not enabled';
                echo json_encode($response);
                exit;
            }

            // check sign
            if (!$this->checkCallbackSign($sign, $customerId, $transactionId, $transactionType, $total)) {
                $response['error'] = 'Invalid sign';
                echo json_encode($response);
                exit;
            }

            
            // check order
            try {
                // get order 
                $order = new WC_Order($orderId);
            } catch (\Exception $e) {
                $response['error'] = 'Order not exists';
                echo json_encode($response);
                exit;
            }


            // if purchase
            if ($transactionType == self::PURCHASE_TRANSACTION_TYPE) {
                $order->update_status('on-hold', __( 'Оплата была произведена успешно (ChronoPay)<br/>', 'woocommerce' ));
            }   

            // if refund
            if ($transactionType == self::REFUND_TRANSACTION_TYPE) {
                $order->update_status('refunded', __( 'Средства были возвращены (ChronoPay)<br/>', 'woocommerce' ));
            }

            $response['status'] = 'ok';
            echo json_encode($response);
            exit;
        }

    }
        
}

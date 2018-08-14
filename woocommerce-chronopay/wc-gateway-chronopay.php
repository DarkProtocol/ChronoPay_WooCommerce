<?php
/**
 * Plugin Name: WooCommerce ChronoPay Gateway
 * Description: Extends WooCommerce with ChronoPay Gateway.
 * Version: 1.0.0
 * Author: Stepan Kushtuev
 */
if (!defined('ABSPATH')) exit;

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
            $this->title        = $this->get_option('title');
            $this->description  = $this->get_option('description');
            $this->productId    = $this->get_option('productId');
            $this->sharedSec    = $this->get_option('sharedSec');
            $this->paymentsUrl  = $this->get_option('paymentsUrl');
            
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
         * Add new form fields to admin
         *
         * @return void
         */
        public function init_form_fields() 
        {
            $this->form_fields = include __DIR__ . '/settings-chronopay.php';
        }


        /**
         * @inheritdoc
         */
        public function admin_options() 
        {
            $cbUrl = home_url('/wc-api/'.strtolower(get_class($this)));

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
            $successUrl = $this->get_return_url($order);
            $orderId = $order->get_id();
            $orderPrice = (float)$order->get_total() - (float)$order->get_total_shipping();

            $url = $this->getPaymentsUrl();
            $url .= '?product_id=' . urlencode($this->getProductId());
            $url .= '&product_price=' . urlencode($orderPrice);
            $url .= '&order_id=' . urlencode($orderId);
            $url .= '&sign=' . urlencode($this->generatePaymentSign($orderPrice, $orderId));
            $url .= '&success_url=' . urlencode($successUrl);
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
        private function generatePaymentSign($orderPrice, $orderId)
        {	
            return md5(
                $this->getProductId() . '-' . 
                $orderPrice . '-' . 
                $orderId . '-' . $this->getSharedSec()
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

    	    // check all params
            $customerId = $this->getRequestParam('customer_id'); 
            $transactionId = $this->getRequestParam('transaction_id');
            $transactionType = $this->getRequestParam('transaction_type');
            $total = $this->getRequestParam('total'); 
            $sign = $this->getRequestParam('sign'); 
            $orderId = $this->getRequestParam('order_id'); 

            if (!$customerId || !$transactionId || !$transactionType || !$total || !$sign || !$orderId) {
                exit;
            }

            // сheck module 
            if (!$this->getEnabled() || empty($this->getSharedSec())) {
                exit;
            }

            // check sign
            if (!$this->checkCallbackSign($sign, $customerId, $transactionId, $transactionType, $total)) {
                exit;
            }

            
            // check order
            try {
                // get order 
                $order = new WC_Order($orderId);
            } catch (\Exception $e) {
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

    	    exit;
        }

	}
        
}
<?php
/**
 * Settings for ChronoPay Gateway.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'enabled' => array(
		'title'   => __('Enable/Disable', 'woocommerce'),
		'type' 	  => 'checkbox',
		'label'   => __('Включить ChronoPay', 'woocommerce'),
		'default' => 'no',
	),
	'title' => array(
		'title'       => __('Title', 'woocommerce'),
		'type'        => 'text',
		'description' => __('Заголовок на странице оплаты', 'woocommerce' ),
		'default' 	  => __('ChronoPay', 'woocommerce'),
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
		'title' 	  => __('Payments Url', 'woocommerce'),
		'type' 		  => 'text',
		'description' => 'URL перенаправления на страницу оплаты (если не знаете, что это, оставьте поле пустым)',
		'default' 	  => self::DEFAULT_PAYMENT_URL,
		'desc_tip' 	  => false,
	),
	'sharedSec' => array(
		'title' 	  => __('SharedSec', 'woocommerce'),
		'type' 		  => 'text',
		'description' => 'SharedSec из личного кабинета ChronoPay',
		'default' 	  => '',
		'desc_tip' 	  => false,
	),
	'productId' => array(
		'title' 	  => __('Product ID', 'woocommerce'),
		'type' 		  => 'text',
		'description' => 'Product ID из личного кабинета ChronoPay',
		'default' 	  => '',
		'desc_tip' 	  => false,
	)
);
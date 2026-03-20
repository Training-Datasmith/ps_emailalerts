<?php

declare(strict_types=1);

/**
 * Example: Working with the ps_emailalerts PrestaShop module.
 *
 * ps_emailalerts sends email notifications to merchants (new order, new
 * customer registration) and customers (order status changes, back-in-stock
 * alerts). All emails use PrestaShop's Mail::Send() with configurable
 * per-language templates.
 *
 * This file documents common integration patterns.
 */

// --- Hook: actionOrderStatusPostUpdate ---
// Fired when an order status changes. The module sends the customer
// a status-change email automatically.
//
// To add custom notification logic alongside ps_emailalerts:
//
// Hook::register('actionOrderStatusPostUpdate', 'MyModule', 'onStatusChange');
//
// public function onStatusChange(array $params): void
// {
//     $order     = $params['order'];
//     $newStatus = new OrderState($params['newOrderStatus']->id);
//
//     if ($newStatus->id === _PS_OS_SHIPPING_) {
//         SmsService::send($order->id_customer, "Your order #{$order->id} has shipped!");
//     }
// }

// --- Hook: actionValidateOrder ---
// Fired immediately after a new order is created. The module emails the merchant.
//
// Merchant email list is configured in Back Office > Modules > Email Alerts:
//   - Merchant email address(es) for new order notifications

// --- Hook: actionUpdateQuantity ---
// Fired when product stock changes. The module checks if any customers
// have subscribed to back-in-stock alerts for this product and sends them
// a notification email if stock is now available.

// --- Sending a custom email using the same Mail::Send() pattern ---
// Mail::Send(
//     id_lang: (int) Context::getContext()->language->id,
//     template: 'order_conf',                         // template filename without extension
//     subject: Mail::l('Order confirmation'),
//     template_vars: [
//         '{firstname}' => $customer->firstname,
//         '{order_name}' => $order->reference,
//     ],
//     to: $customer->email,
//     to_name: $customer->firstname . ' ' . $customer->lastname,
// );

// --- Back Office configuration ---
// Modules > Email Alerts:
//   - Merchant email(s) for new order notifications
//   - Enable/disable customer order status emails
//   - Enable/disable back-in-stock alerts
//   - Enable/disable new product alerts

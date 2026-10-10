<?php

class PsEmailAlertsOrderHookTest extends TestsCase
{
    public function testGetAllMessagesJoinsOrderedMessages()
    {
        $m = new Ps_EmailAlerts();
        TestState::$pdo->exec("INSERT INTO ps_message VALUES (2,1,'second')");
        TestState::$pdo->exec("INSERT INTO ps_message VALUES (1,1,'first')");
        $this->assertSame('first<br/>second', $m->getAllMessages(1));
    }

    public function testValidateOrderSendsNewOrderTemplate()
    {
        $m = $this->moduleWithInit([
            'MA_MERCHANT_ORDER' => '1',
            'MA_MERCHANT_ORDER_EMAILS' => 'merchant@shop.test,other@shop.test',
        ]);
        $order = new Order(1);
        $customer = new Customer(7);
        $currency = (object) ['iso_code' => 'EUR', 'sign' => '€'];
        $cart = (object) ['id' => 1];
        $m->hookActionValidateOrder([
            'order' => $order,
            'customer' => $customer,
            'currency' => $currency,
            'cart' => $cart,
            'orderStatus' => new OrderState(),
        ]);
        $this->assertCount(2, TestState::$mailSends);
        $this->assertSame('new_order', TestState::$mailSends[0]['template']);
        $this->assertContains('SKU-1', TestState::$mailSends[0]['template_vars']['{items}']);
    }

    public function testOrderEditedSendsWhenEnabled()
    {
        Configuration::updateGlobalValue('MA_ORDER_EDIT', '1');
        $m = $this->moduleWithInit();
        $order = new Order(5);
        $m->hookActionOrderEdited(['order' => $order]);
        $this->assertCount(1, TestState::$mailSends);
        $this->assertSame('order_changed', TestState::$mailSends[0]['template']);
        $this->assertSame(5, TestState::$mailSends[0]['template_vars']['{id_order}']);
    }
}

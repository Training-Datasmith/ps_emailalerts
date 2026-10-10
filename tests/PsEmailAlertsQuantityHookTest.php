<?php

class PsEmailAlertsQuantityHookTest extends TestsCase
{
    public function testUpdateQuantitySkipsZeroDelta()
    {
        $m = $this->moduleWithInit(['MA_CUSTOMER_QTY' => '1', 'MA_MERCHANT_OOS' => '1', 'MA_MERCHANT_OOS_EMAILS' => 'm@shop.test']);
        TestState::$pdo->exec('INSERT INTO ps_product VALUES (10,5,1,1,0)');
        TestState::$pdo->exec('INSERT INTO ps_product_shop VALUES (10,1,1)');
        TestState::$pdo->exec("INSERT INTO ps_product_lang VALUES (10,1,1,'Wool','wool')");
        $m->hookActionUpdateQuantity(['id_product' => 10, 'id_product_attribute' => 0, 'quantity' => 5, 'delta_quantity' => 0]);
        $this->assertSame([], TestState::$mailSends);
    }

    public function testUpdateQuantitySendsMerchantOosMail()
    {
        $m = $this->moduleWithInit([
            'MA_MERCHANT_OOS' => '1',
            'MA_MERCHANT_OOS_EMAILS' => 'merchant@shop.test,other@shop.test',
            'MA_LAST_QTIES' => '2',
            'MA_CUSTOMER_QTY' => '0',
        ]);
        TestState::$pdo->exec('INSERT INTO ps_product VALUES (10,0,1,1,0)');
        TestState::$pdo->exec('INSERT INTO ps_product_shop VALUES (10,1,1)');
        TestState::$pdo->exec("INSERT INTO ps_product_lang VALUES (10,1,1,'Wool sweater','wool')");
        $m->hookActionUpdateQuantity(['id_product' => 10, 'id_product_attribute' => 0, 'quantity' => 0]);
        $this->assertCount(2, TestState::$mailSends);
        $this->assertSame('productoutofstock', TestState::$mailSends[0]['template']);
        $this->assertSame(0, TestState::$mailSends[0]['template_vars']['{qty}']);
    }

    public function testProductDeleteRemovesAlerts()
    {
        TestState::$pdo->exec("INSERT INTO ps_mailalert_customer_oos VALUES (0,'guest@shop.test',10,0,1,1)");
        TestState::$pdo->exec("INSERT INTO ps_mailalert_customer_oos VALUES (0,'guest@shop.test',11,0,1,1)");
        $m = $this->moduleWithInit();
        $p = new Product(10);
        $m->hookActionProductDelete(['product' => $p]);
        $ids = TestState::$pdo->query('SELECT id_product FROM ps_mailalert_customer_oos')->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame(['11'], $ids);
    }
}

<?php

class PsEmailAlertsGdprTest extends TestsCase
{
    public function testDeleteGdprCustomerRemovesRows()
    {
        TestState::$pdo->exec("INSERT INTO ps_mailalert_customer_oos VALUES (0,'customer@shop.test',10,0,1,1)");
        $m = $this->moduleWithInit();
        $result = $m->hookActionDeleteGDPRCustomer(['email' => 'customer@shop.test']);
        $this->assertSame('true', $result);
        $this->assertSame(0, (int) TestState::$pdo->query('SELECT COUNT(*) FROM ps_mailalert_customer_oos')->fetchColumn());
    }

    public function testExportGdprCustomerReturnsJsonRows()
    {
        TestState::$pdo->exec("INSERT INTO ps_mailalert_customer_oos VALUES (0,'customer@shop.test',10,0,1,1)");
        $m = $this->moduleWithInit();
        $result = $m->hookActionExportGDPRData(['email' => 'customer@shop.test']);
        $decoded = json_decode($result, true);
        $this->assertSame('customer@shop.test', $decoded[0]['customer_email']);
    }
}

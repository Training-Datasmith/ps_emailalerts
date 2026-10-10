<?php

class PsEmailAlertsCoverageHookTest extends TestsCase
{
    public function testCoverageHookSendsToOosEmailList()
    {
        Configuration::updateValue('PS_ADVANCED_STOCK_MANAGEMENT', '1');
        Configuration::updateGlobalValue('MA_MERCHANT_COVERAGE', '1');
        Configuration::updateGlobalValue('MA_PRODUCT_COVERAGE', '10');
        TestState::$pdo->exec('INSERT INTO ps_product VALUES (10,5,1,1,1)');
        TestState::$pdo->exec('INSERT INTO ps_product_shop VALUES (10,1,1)');
        TestState::$pdo->exec("INSERT INTO ps_product_lang VALUES (10,1,1,'Wool sweater','wool')");
        TestState::$stockCoverage = 3;

        $m = $this->moduleWithInit([
            'MA_MERCHANT_OOS_EMAILS' => 'merchant@shop.test,other@shop.test',
        ]);

        $warehouse = new Address(4);
        $m->hookActionProductCoverage([
            'id_product' => 10,
            'id_product_attribute' => 0,
            'warehouse' => $warehouse,
        ]);

        $this->assertCount(2, TestState::$mailSends);
        $this->assertSame('productcoverage', TestState::$mailSends[0]['template']);
        $emails = $this->sortEmails(TestState::$mailSends);
        $this->assertSame(['merchant@shop.test', 'other@shop.test'], $emails);
        $this->assertSame(3, TestState::$mailSends[0]['template_vars']['{current_coverage}']);
        $this->assertSame(10, TestState::$mailSends[0]['template_vars']['{warning_coverage}']);
    }

    public function testCoverageHookSkipsWhenAdvancedStockOff()
    {
        Configuration::updateValue('PS_ADVANCED_STOCK_MANAGEMENT', '0');
        $m = $this->moduleWithInit(['MA_MERCHANT_OOS_EMAILS' => 'merchant@shop.test']);
        $m->hookActionProductCoverage(['id_product' => 10, 'id_product_attribute' => 0, 'warehouse' => new Address(0)]);
        $this->assertSame([], TestState::$mailSends);
    }
}

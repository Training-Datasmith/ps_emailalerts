<?php

class PsEmailAlertsInstallTest extends TestsCase
{
    private $requiredHooks = [
        'actionValidateOrder', 'actionUpdateQuantity', 'displayCustomerAccount', 'displayMyAccountBlock',
        'actionProductDelete', 'actionProductAttributeDelete', 'actionProductAttributeUpdate', 'actionProductCoverage',
        'actionOrderReturn', 'actionOrderEdited', 'registerGDPRConsent', 'actionDeleteGDPRCustomer',
        'actionExportGDPRData', 'displayProductAdditionalInfo', 'actionFrontControllerSetMedia', 'actionAdminControllerSetMedia',
    ];

    public function testInstallRegistersRequiredHooksAndCreatesTable()
    {
        $m = new Ps_EmailAlerts();
        $this->assertTrue($m->install());
        foreach ($this->requiredHooks as $hook) {
            $this->assertContains($hook, TestState::$hooks);
        }
        $cols = TestState::$pdo->query("SHOW COLUMNS FROM ps_mailalert_customer_oos")->fetchAll(PDO::FETCH_COLUMN);
        $this->assertContains('id_customer', $cols);
        $this->assertContains('customer_email', $cols);
    }

    public function testUninstallDeletesSplitEmailKeys()
    {
        Configuration::updateValue('MA_MERCHANT_ORDER_EMAILS', 'a@shop.test');
        Configuration::updateValue('MA_MERCHANT_OOS_EMAILS', 'b@shop.test');
        Configuration::updateValue('MA_RETURN_SLIP_EMAILS', 'c@shop.test');
        Configuration::updateValue('MA_CUSTOMER_QTY', '1');
        Configuration::updateValue('PS_SHOP_EMAIL', 'unrelated@shop.test');
        $m = new Ps_EmailAlerts();
        $this->assertTrue($m->install(true));
        $this->assertTrue($m->uninstall(true));
        $names = TestState::$pdo->query("SELECT name FROM ps_configuration WHERE name LIKE 'MA_%' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame([], $names);
        $this->assertSame('unrelated@shop.test', Configuration::get('PS_SHOP_EMAIL'));
    }

    public function testUninstallFalsePreservesData()
    {
        TestState::$pdo->exec("INSERT INTO ps_mailalert_customer_oos VALUES (0,'guest@shop.test',10,0,1,1)");
        Configuration::updateValue('MA_MERCHANT_ORDER_EMAILS', 'a@shop.test');
        $m = new Ps_EmailAlerts();
        $m->install(false);
        $this->assertTrue($m->uninstall(false));
        $this->assertSame(1, (int) TestState::$pdo->query('SELECT COUNT(*) FROM ps_mailalert_customer_oos')->fetchColumn());
        $this->assertSame('a@shop.test', Configuration::get('MA_MERCHANT_ORDER_EMAILS'));
    }

    public function testInstallHookFailureReturnsFalse()
    {
        TestState::$registerHookResults['actionProductCoverage'] = false;
        $m = new Ps_EmailAlerts();
        $this->assertFalse($m->install());
        $this->assertNotContains('actionProductCoverage', TestState::$hooks);
    }
}

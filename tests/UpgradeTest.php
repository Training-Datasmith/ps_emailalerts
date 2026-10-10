<?php

class UpgradeTest extends TestsCase
{
    public function testUpgrade240CopiesMerchantMailsToActiveLists()
    {
        Configuration::updateValue('MA_MERCHANT_MAILS', "a@shop.test\nb@shop.test");
        Configuration::updateValue('MA_MERCHANT_ORDER', '1');
        Configuration::updateValue('MA_MERCHANT_OOS', '1');
        Configuration::updateValue('MA_RETURN_SLIP', '1');
        $m = new Ps_EmailAlerts();
        $this->assertTrue(upgrade_module_2_4_0($m));
        $this->assertSame('a@shop.test,b@shop.test', Configuration::get('MA_MERCHANT_ORDER_EMAILS'));
        $this->assertSame('a@shop.test,b@shop.test', Configuration::get('MA_MERCHANT_OOS_EMAILS'));
        $this->assertContains('actionAdminControllerSetMedia', TestState::$hooks);
    }

    public function testUpgrade241RegistersGdprConsent()
    {
        $m = new Ps_EmailAlerts();
        $this->assertTrue(upgrade_module_2_4_1($m));
        $this->assertContains('registerGDPRConsent', TestState::$hooks);
    }
}

<?php

class PsEmailAlertsConfigTest extends TestsCase
{
    public function testPostProcessStoresSanitizedMerchantEmails()
    {
        $m = $this->moduleWithInit();
        TestState::$request = [
            'submitMAMerchant' => '1',
            'MA_MERCHANT_ORDER' => '1',
            'MA_MERCHANT_ORDER_EMAILS' => 'a@shop.test, bad, b@shop.test ',
            'MA_MERCHANT_OOS' => '1',
            'MA_MERCHANT_OOS_EMAILS' => 'oos@shop.test',
            'MA_RETURN_SLIP' => '1',
            'MA_RETURN_SLIP_EMAILS' => 'ret@shop.test',
            'MA_LAST_QTIES' => '2',
            'MA_MERCHANT_COVERAGE' => '0',
            'MA_PRODUCT_COVERAGE' => '0',
        ];
        $ref = new ReflectionClass($m);
        $pp = $ref->getMethod('postProcess');
        $pp->setAccessible(true);
        $pp->invoke($m);
        $htmlProp = $ref->getProperty('html');
        $htmlProp->setAccessible(true);
        $html = $htmlProp->getValue($m);
        $this->assertContains('Invalid email:', (string) $html);
        $this->assertSame('a@shop.test,b@shop.test', Configuration::get('MA_MERCHANT_ORDER_EMAILS'));
    }

    public function testRenderFormIncludesExpectedFieldNames()
    {
        Configuration::updateValue('PS_ADVANCED_STOCK_MANAGEMENT', '0');
        $m = $this->moduleWithInit();
        $html = $m->renderForm();
        $this->assertContains('MA_MERCHANT_ORDER_EMAILS', $html);
        $this->assertNotContains('MA_MERCHANT_COVERAGE', $html);
    }
}

<?php

class PsEmailAlertsContractTest extends TestsCase
{
    public function testUsesNewTranslationSystem()
    {
        $m = new Ps_EmailAlerts();
        $this->assertTrue($m->isUsingNewTranslationSystem());
    }

    public function testRegisterGdprConsentHookExists()
    {
        $m = new Ps_EmailAlerts();
        $this->assertTrue(method_exists($m, 'hookRegisterGDPRConsent'));
        $this->assertTrue((new ReflectionMethod($m, 'hookRegisterGDPRConsent'))->isPublic());
    }
}

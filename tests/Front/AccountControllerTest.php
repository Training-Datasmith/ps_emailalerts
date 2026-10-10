<?php

class AccountControllerTest extends TestsCase
{
    public function testInitContentAssignsAlertsFromDatabase()
    {
        TestState::$pdo->exec('INSERT INTO ps_product VALUES (10,5,1,1,0)');
        TestState::$pdo->exec('INSERT INTO ps_product_shop VALUES (10,1,1)');
        TestState::$pdo->exec("INSERT INTO ps_product_lang VALUES (10,1,1,'Wool','wool')");
        TestState::$pdo->exec("INSERT INTO ps_mailalert_customer_oos VALUES (7,'customer@shop.test',10,0,1,1)");
        $ctx = Context::getContext();
        $ctx->customer = new Customer(7);
        $ctx->customer->logged = 1;
        $controller = new Ps_EmailAlertsAccountModuleFrontController();
        $controller->module = new Ps_EmailAlerts();
        $controller->initContent();
        $this->assertArrayHasKey('mailAlerts', $ctx->smarty->vars);
        $this->assertCount(1, $ctx->smarty->vars['mailAlerts']);
        $this->assertSame(10, (int) $ctx->smarty->vars['mailAlerts'][0]['id_product']);
    }
}

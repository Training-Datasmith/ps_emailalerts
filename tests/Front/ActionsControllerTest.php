<?php

class ActionsControllerTest extends TestsCase
{
    public function testProcessCheckReturnsOneWhenAlertExists()
    {
        TestState::$pdo->exec("INSERT INTO ps_mailalert_customer_oos VALUES (7,'customer@shop.test',10,0,1,1)");
        list($out, $err) = $this->runSubprocess(
            ['id_product' => 10, 'id_product_attribute' => 0],
            'processCheck',
            ['customer_id' => 7, 'logged' => 1]
        );
        $this->assertSame('1', $out);
        $this->assertNotContains('DID_NOT_EXIT', $err);
    }

    public function testProcessRemoveSuccessReturnsZero()
    {
        TestState::$pdo->exec('INSERT INTO ps_product VALUES (10,1,1,1,0)');
        TestState::$pdo->exec('INSERT INTO ps_product_shop VALUES (10,1,1)');
        TestState::$pdo->exec("INSERT INTO ps_mailalert_customer_oos VALUES (7,'customer@shop.test',10,0,1,1)");
        list($out, $err) = $this->runSubprocess(
            ['process' => 'remove', 'id_product' => 10, 'id_product_attribute' => 0],
            'processRemove',
            ['customer_id' => 7, 'logged' => 1]
        );
        $this->assertSame('0', $out);
        $this->assertNotContains('DID_NOT_EXIT', $err);
    }

    public function testProcessAddCreatesRow()
    {
        TestState::$pdo->exec('INSERT INTO ps_product VALUES (10,1,1,1,0)');
        TestState::$pdo->exec('INSERT INTO ps_product_shop VALUES (10,1,1)');
        TestState::$pdo->exec("INSERT INTO ps_product_lang VALUES (10,1,1,'Wool','wool')");
        list($out, $err) = $this->runSubprocess(
            ['process' => 'add', 'id_product' => 10, 'id_product_attribute' => 0, 'customer_email' => 'guest@shop.test'],
            'processAdd',
            ['logged' => 0]
        );
        $this->assertContains('"error":false', $out);
        $this->assertNotContains('DID_NOT_EXIT', $err);
        $this->assertSame(1, (int) TestState::$pdo->query('SELECT COUNT(*) FROM ps_mailalert_customer_oos')->fetchColumn());
    }
}

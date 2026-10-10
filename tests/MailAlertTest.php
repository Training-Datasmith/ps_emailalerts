<?php

class MailAlertTest extends TestsCase
{
    public function testCustomerHasNotificationCountsMatchingRows()
    {
        TestState::$pdo->exec("INSERT INTO ps_mailalert_customer_oos VALUES (0,'guest@shop.test',10,0,1,1)");
        $this->assertSame(1, MailAlert::customerHasNotification(0, 10, 0, 1, null, 'guest@shop.test'));
        $this->assertSame(0, MailAlert::customerHasNotification(0, 10, 0, 1, null, 'other@shop.test'));
    }

    public function testDeleteAlertRemovesByCustomerOrEmail()
    {
        TestState::$pdo->exec("INSERT INTO ps_mailalert_customer_oos VALUES (7,'customer@shop.test',10,0,1,1)");
        $this->assertTrue(MailAlert::deleteAlert(7, 'other@shop.test', 10, 0, 1));
        $rows = TestState::$pdo->query('SELECT customer_email FROM ps_mailalert_customer_oos')->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame([], $rows);
    }

    public function testGetProductsFiltersActiveAndCustomer()
    {
        TestState::$pdo->exec("INSERT INTO ps_product VALUES (10,5,1,1,0)");
        TestState::$pdo->exec('INSERT INTO ps_product_shop VALUES (10,1,1)');
        TestState::$pdo->exec("INSERT INTO ps_product_lang VALUES (10,1,1,'Wool','wool')");
        TestState::$pdo->exec("INSERT INTO ps_mailalert_customer_oos VALUES (7,'customer@shop.test',10,0,1,1)");
        TestState::$pdo->exec("INSERT INTO ps_customer VALUES (8,'other@shop.test')");
        TestState::$pdo->exec("INSERT INTO ps_product VALUES (11,5,1,1,0)");
        TestState::$pdo->exec('INSERT INTO ps_product_shop VALUES (11,1,0)');
        TestState::$pdo->exec("INSERT INTO ps_product_lang VALUES (11,1,1,'Inactive','inactive')");
        TestState::$pdo->exec("INSERT INTO ps_mailalert_customer_oos VALUES (7,'customer@shop.test',11,0,1,1)");
        TestState::$pdo->exec("INSERT INTO ps_mailalert_customer_oos VALUES (8,'other@shop.test',10,0,1,1)");
        $customer = new Customer(7);
        $products = MailAlert::getProducts($customer, 1);
        $ids = array_map(function ($row) {
            return (int) $row['id_product'];
        }, $products);
        sort($ids);
        $this->assertSame([10], $ids);
    }

    public function testGetProductAttributeCombinationReturnsNames()
    {
        TestState::$pdo->exec('INSERT INTO ps_attribute_group VALUES (1)');
        TestState::$pdo->exec('INSERT INTO ps_attribute VALUES (1,1)');
        TestState::$pdo->exec("INSERT INTO ps_attribute_lang VALUES (1,1,'Red')");
        TestState::$pdo->exec('INSERT INTO ps_product_attribute VALUES (5,10,1)');
        TestState::$pdo->exec('INSERT INTO ps_product_attribute_shop VALUES (5,1)');
        TestState::$pdo->exec('INSERT INTO ps_product_attribute_combination VALUES (5,1)');
        $rows = MailAlert::getProductAttributeCombination(5, 1);
        $names = array_column($rows, 'attribute_name');
        sort($names);
        $this->assertSame(['Red'], $names);
    }

    public function testGetCustomersSameShopOnly()
    {
        TestState::$pdo->exec("INSERT INTO ps_mailalert_customer_oos VALUES (0,'a@shop.test',10,3,1,1)");
        TestState::$pdo->exec("INSERT INTO ps_mailalert_customer_oos VALUES (0,'b@shop.test',10,3,1,1)");
        TestState::$pdo->exec("INSERT INTO ps_mailalert_customer_oos VALUES (0,'c@shop.test',11,3,1,1)");
        $rows = MailAlert::getCustomers(10, 3, 1);
        $emails = array_column($rows, 'customer_email');
        sort($emails);
        $this->assertSame(['a@shop.test', 'b@shop.test'], $emails);
    }

    public function testGetFormatedAddressDelegates()
    {
        $addr = new Address(9);
        MailAlert::getFormatedAddress($addr, "\n");
        $this->assertCount(1, TestState::$addressFormatCalls);
        $this->assertSame(9, (int) TestState::$addressFormatCalls[0]['addressId']);
        $this->assertSame("\n", TestState::$addressFormatCalls[0]['lineSep']);
        $this->assertSame(['avoid' => []], TestState::$addressFormatCalls[0]['params']);
    }

    public function testSendCustomerAlertSendsAndDeletes()
    {
        TestState::$pdo->exec("INSERT INTO ps_product VALUES (10,5,1,1,0)");
        TestState::$pdo->exec('INSERT INTO ps_product_shop VALUES (10,1,1)');
        TestState::$pdo->exec("INSERT INTO ps_product_lang VALUES (10,1,1,'Wool sweater','wool')");
        TestState::$pdo->exec("INSERT INTO ps_mailalert_customer_oos VALUES (0,'guest@shop.test',10,3,1,1)");
        MailAlert::sendCustomerAlert(10, 3);
        $this->assertCount(1, TestState::$mailSends);
        $this->assertSame('customer_qty', TestState::$mailSends[0]['template']);
        $this->assertSame('guest@shop.test', TestState::$mailSends[0]['to']);
        $this->assertSame(0, (int) TestState::$pdo->query('SELECT COUNT(*) FROM ps_mailalert_customer_oos')->fetchColumn());
    }

    public function testGetMailAlertsInvalidIdsExitViaDisplayError()
    {
        list($code, $err) = $this->runGetMailAlertsSubprocess(0, 1);
        $this->assertSame(0, $code);
        $this->assertNotContains('DID_NOT_EXIT', $err);
    }

    private function runGetMailAlertsSubprocess($customerId, $langId)
    {
        $cmd = 'php -d error_reporting=-1 ' . escapeshellarg(__DIR__ . '/bin/get-mailalerts.php')
            . ' ' . (int) $customerId . ' ' . (int) $langId;
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc = proc_open($cmd, $descriptors, $pipes);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($proc);
        $this->assertNoPhpDiagnosticsInOutput($stdout, $stderr);

        return [$code, $stderr];
    }
}

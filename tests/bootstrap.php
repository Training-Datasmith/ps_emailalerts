<?php

require_once __DIR__ . '/bootstrap-module.php';

TestState::$pdo = tests_connect_pdo();
tests_create_schema(TestState::$pdo);
tests_seed_basics(TestState::$pdo);

class TestsCase extends PHPUnit\Framework\TestCase
{
    protected function setUp()
    {
        TestState::reset();
        Context::resetInstance();
        Context::getContext();
        ObjectModel::$nextAddResult = true;
        Mail::$throwForRecipient = null;
        Module::$installed = [];
        Module::$instances = [];
        tests_create_schema(TestState::$pdo);
        tests_seed_basics(TestState::$pdo);
    }

    protected function moduleWithInit(array $config = [])
    {
        foreach ($config as $key => $value) {
            if (in_array($key, ['MA_MERCHANT_COVERAGE', 'MA_PRODUCT_COVERAGE', 'MA_ORDER_EDIT', 'MA_RETURN_SLIP'], true)) {
                Configuration::updateGlobalValue($key, $value);
            } else {
                Configuration::updateValue($key, $value);
            }
        }
        $m = new Ps_EmailAlerts();
        $m->id = 1;
        $ref = new ReflectionClass($m);
        $init = $ref->getMethod('init');
        $init->setAccessible(true);
        $init->invoke($m);

        return $m;
    }

    protected function sortEmails(array $sends)
    {
        $emails = array_map(function ($s) {
            return $s['to'];
        }, $sends);
        sort($emails);

        return $emails;
    }

    protected function assertNoPhpDiagnosticsInOutput($stdout, $stderr)
    {
        $markers = ['Notice:', 'Warning:', 'Deprecated:', 'Strict Standards:', 'Fatal error:'];
        foreach (['stdout' => (string) $stdout, 'stderr' => (string) $stderr] as $label => $text) {
            foreach ($markers as $marker) {
                if (strpos($text, $marker) !== false) {
                    $this->fail('PHP diagnostic in subprocess ' . $label . ': ' . $marker);
                }
            }
        }
    }

    protected function runSubprocess(array $request, $method, array $contextOverrides = [], $defineMass = false)
    {
        $payload = [
            'request' => $request,
            'method' => $method,
            'context' => $contextOverrides,
            'define_mass' => $defineMass,
        ];
        $cmd = 'php -d error_reporting=-1 ' . escapeshellarg(__DIR__ . '/bin/invoke.php') . ' ' . escapeshellarg(json_encode($payload));
        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc = proc_open($cmd, $descriptors, $pipes);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);
        $this->assertNoPhpDiagnosticsInOutput($stdout, $stderr);

        return [$stdout, $stderr];
    }
}

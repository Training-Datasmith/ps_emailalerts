<?php

class GuardExitTest extends TestsCase
{
    public function testMainModuleExitsWithoutPsVersion()
    {
        $this->assertGuardTriggered(dirname(__DIR__) . '/ps_emailalerts.php');
    }

    public function testUpgradeGuardsExitWithoutPsVersion()
    {
        $files = [
            'upgrade/install-2.1.0.php',
            'upgrade/install-2.3.4.php',
            'upgrade/upgrade-2.3.1.php',
            'upgrade/upgrade-2.3.3.php',
            'upgrade/upgrade-2.4.0.php',
            'upgrade/upgrade-2.4.1.php',
        ];
        foreach ($files as $file) {
            $this->assertGuardTriggered(dirname(__DIR__) . '/' . $file);
        }
    }

    private function assertGuardTriggered($file)
    {
        $cmd = 'php -d error_reporting=-1 ' . escapeshellarg(__DIR__ . '/bin/check-guard.php') . ' ' . escapeshellarg($file);
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc = proc_open($cmd, $descriptors, $pipes, __DIR__);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($proc);
        $this->assertNoPhpDiagnosticsInOutput($stdout, $stderr);
        $this->assertSame(0, $code);
        $this->assertNotContains('GUARD_NOT_TRIGGERED', $stderr);
    }
}

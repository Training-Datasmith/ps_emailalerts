<?php

putenv('DB_HOST=' . (getenv('DB_HOST') ?: '127.0.0.1'));
putenv('DB_PORT=' . (getenv('DB_PORT') ?: '3306'));
putenv('DB_NAME=' . (getenv('DB_NAME') ?: 'ps_emailalerts'));
putenv('DB_USER=' . (getenv('DB_USER') ?: 'root'));
putenv('DB_PASSWORD=' . (getenv('DB_PASSWORD') ?: 'root'));

require dirname(__DIR__) . '/bootstrap-module.php';
TestState::$pdo = tests_connect_pdo();
tests_create_schema(TestState::$pdo);
tests_seed_basics(TestState::$pdo);

MailAlert::getMailAlerts((int) $argv[1], (int) $argv[2]);
fwrite(STDERR, 'DID_NOT_EXIT');
exit(1);

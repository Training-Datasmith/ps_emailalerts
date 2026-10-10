<?php

$payload = json_decode($argv[1], true);
if (!is_array($payload)) {
    fwrite(STDERR, 'BAD_PAYLOAD');
    exit(3);
}

putenv('DB_HOST=' . (getenv('DB_HOST') ?: '127.0.0.1'));
putenv('DB_PORT=' . (getenv('DB_PORT') ?: '3306'));
putenv('DB_NAME=' . (getenv('DB_NAME') ?: 'ps_emailalerts'));
putenv('DB_USER=' . (getenv('DB_USER') ?: 'root'));
putenv('DB_PASSWORD=' . (getenv('DB_PASSWORD') ?: 'root'));

if (!empty($payload['define_mass'])) {
    define('PS_MASS_PRODUCT_CREATION', true);
}

require dirname(__DIR__) . '/bootstrap-module.php';
TestState::$pdo = tests_connect_pdo();

$ctx = Context::getContext();
if (!empty($payload['context']['customer_id'])) {
    $ctx->customer = new Customer((int) $payload['context']['customer_id']);
    $ctx->customer->logged = 1;
}
if (isset($payload['context']['guest_email'])) {
    $ctx->customer->email = $payload['context']['guest_email'];
}
if (isset($payload['context']['logged'])) {
    $ctx->customer->logged = (int) $payload['context']['logged'];
}

TestState::$request = $payload['request'];

$module = new Ps_EmailAlerts();
$module->id = 1;
$controller = new Ps_EmailAlertsActionsModuleFrontController();
$controller->module = $module;
$controller->init();

$method = $payload['method'];
if (!method_exists($controller, $method)) {
    fwrite(STDERR, 'NO_METHOD');
    exit(4);
}

$controller->$method();
fwrite(STDERR, 'DID_NOT_EXIT');

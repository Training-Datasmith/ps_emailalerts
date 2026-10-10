<?php

if (!defined('_PS_VERSION_')) {
    define('_PS_VERSION_', '1.7.7.0');
}
define('_DB_PREFIX_', 'ps_');
define('_MYSQL_ENGINE_', 'InnoDB');
define('_PS_USE_SQL_SLAVE_', false);
define('PS_TAX_EXC', 1);

$testsDir = __DIR__;
$moduleDir = dirname($testsDir);

if (!is_dir('/tmp/ps-mail-dir')) {
    mkdir('/tmp/ps-mail-dir', 0777, true);
}
define('_PS_MAIL_DIR_', '/tmp/ps-mail-dir/');

require_once $testsDir . '/stubs/PrestaShopStubs.php';
require_once $testsDir . '/stubs/OrderShipmentService.php';

function tests_connect_pdo()
{
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3306';
    $name = getenv('DB_NAME') ?: 'ps_emailalerts';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASSWORD') ?: 'root';
    $dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $name . ';charset=utf8';

    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
}

function tests_create_schema(PDO $pdo)
{
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    $tables = [
        'mailalert_customer_oos', 'product', 'product_shop', 'product_lang', 'product_attribute',
        'product_attribute_shop', 'product_attribute_combination', 'product_attribute_image',
        'attribute', 'attribute_lang', 'attribute_group', 'attribute_group_lang', 'stock_available',
        'shop', 'shop_group', 'configuration', 'message', 'employee', 'customer', 'image', 'module_hook', 'language',
    ];
    foreach ($tables as $t) {
        $pdo->exec('DROP TABLE IF EXISTS ps_' . $t);
    }

    $pdo->exec('CREATE TABLE ps_mailalert_customer_oos (
        id_customer INT UNSIGNED NOT NULL,
        customer_email VARCHAR(128) NOT NULL,
        id_product INT UNSIGNED NOT NULL,
        id_product_attribute INT UNSIGNED NOT NULL,
        id_shop INT UNSIGNED NOT NULL,
        id_lang INT UNSIGNED NOT NULL,
        PRIMARY KEY (id_customer, customer_email, id_product, id_product_attribute, id_shop)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8');

    $pdo->exec('CREATE TABLE ps_product (id_product INT PRIMARY KEY, quantity INT, active TINYINT, minimal_quantity INT, advanced_stock_management TINYINT)');
    $pdo->exec('CREATE TABLE ps_product_shop (id_product INT, id_shop INT, active TINYINT, PRIMARY KEY(id_product,id_shop))');
    $pdo->exec('CREATE TABLE ps_product_lang (id_product INT, id_shop INT, id_lang INT, name VARCHAR(255), link_rewrite VARCHAR(255))');
    $pdo->exec('CREATE TABLE ps_product_attribute (id_product_attribute INT PRIMARY KEY, id_product INT, minimal_quantity INT)');
    $pdo->exec('CREATE TABLE ps_product_attribute_shop (id_product_attribute INT, id_shop INT, PRIMARY KEY(id_product_attribute,id_shop))');
    $pdo->exec('CREATE TABLE ps_product_attribute_combination (id_product_attribute INT, id_attribute INT)');
    $pdo->exec('CREATE TABLE ps_product_attribute_image (id_product_attribute INT, id_image INT)');
    $pdo->exec('CREATE TABLE ps_attribute (id_attribute INT PRIMARY KEY, id_attribute_group INT)');
    $pdo->exec('CREATE TABLE ps_attribute_lang (id_attribute INT, id_lang INT, name VARCHAR(255))');
    $pdo->exec('CREATE TABLE ps_attribute_group (id_attribute_group INT PRIMARY KEY)');
    $pdo->exec('CREATE TABLE ps_attribute_group_lang (id_attribute_group INT, id_lang INT, name VARCHAR(255))');
    $pdo->exec('CREATE TABLE ps_stock_available (id_product INT, id_product_attribute INT, quantity INT)');
    $pdo->exec('CREATE TABLE ps_shop (id_shop INT PRIMARY KEY, id_shop_group INT)');
    $pdo->exec('CREATE TABLE ps_shop_group (id_shop_group INT PRIMARY KEY, share_stock TINYINT)');
    $pdo->exec('CREATE TABLE ps_configuration (name VARCHAR(255), value TEXT, id_shop INT NULL, KEY(name))');
    $pdo->exec('CREATE TABLE ps_message (id_message INT PRIMARY KEY, id_order INT, message TEXT)');
    $pdo->exec('CREATE TABLE ps_employee (email VARCHAR(255), id_lang INT)');
    $pdo->exec('CREATE TABLE ps_customer (id_customer INT PRIMARY KEY, email VARCHAR(255))');
    $pdo->exec('CREATE TABLE ps_image (id_product INT, id_image INT, cover TINYINT)');
    $pdo->exec('CREATE TABLE ps_module_hook (hook_name VARCHAR(128))');
    $pdo->exec('CREATE TABLE ps_language (id_lang INT PRIMARY KEY, iso VARCHAR(8))');

    $pdo->exec("INSERT INTO ps_language VALUES (1,'en'),(2,'fr')");
    $pdo->exec('INSERT INTO ps_shop_group VALUES (1,0),(2,1)');
    $pdo->exec('INSERT INTO ps_shop VALUES (1,1),(2,2)');
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
}

function tests_seed_basics(PDO $pdo)
{
    $pdo->exec("INSERT INTO ps_customer VALUES (7,'customer@shop.test')");
    Configuration::updateValue('PS_SHOP_EMAIL', 'shop@shop.test');
    Configuration::updateValue('PS_SHOP_NAME', 'Shop');
    Configuration::updateValue('PS_LANG_DEFAULT', '1');
    Configuration::updateValue('PS_LAST_QTIES', '3');
    Configuration::updateValue('PS_STOCK_MANAGEMENT', '1');
}

require_once $moduleDir . '/MailAlert.php';
require_once $moduleDir . '/ps_emailalerts.php';
require_once $moduleDir . '/controllers/front/actions.php';
require_once $moduleDir . '/controllers/front/account.php';

foreach (glob($moduleDir . '/upgrade/*.php') as $upgradeFile) {
    if (basename($upgradeFile) === 'index.php') {
        continue;
    }
    require_once $upgradeFile;
}

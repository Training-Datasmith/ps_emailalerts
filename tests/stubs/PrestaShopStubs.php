<?php

class TestState
{
    public static $hooks = [];
    public static $mailSends = [];
    public static $hookExecs = [];
    public static $logger = [];
    public static $request = [];
    public static $failNextExecute = false;
    public static $nextAddResult = true;
    public static $redirectUrl = null;
    public static $pdo = null;
    public static $context = null;
    public static $moduleParentInstall = true;
    public static $moduleParentUninstall = true;
    public static $registerHookResults = [];
    public static $orderShipmentService = null;
    public static $stockCoverage = 3;
    public static $displayCalls = [];
    public static $addressFormatCalls = [];

    public static function reset()
    {
        self::$hooks = [];
        self::$mailSends = [];
        self::$hookExecs = [];
        self::$logger = [];
        self::$request = [];
        self::$failNextExecute = false;
        self::$nextAddResult = true;
        self::$redirectUrl = null;
        self::$moduleParentInstall = true;
        self::$moduleParentUninstall = true;
        self::$registerHookResults = [];
        self::$orderShipmentService = null;
        self::$stockCoverage = 3;
        self::$displayCalls = [];
        self::$addressFormatCalls = [];
        if (self::$context !== null) {
            self::$context->shop = (object) ['id' => 1];
            self::$context->language = (object) ['id' => 1, 'iso' => 'en'];
            self::$context->customer = new Customer();
            self::$context->customer->id = 0;
            self::$context->customer->email = '';
            self::$context->customer->logged = 0;
        }
    }
}

function pSQL($string)
{
    return str_replace(
        ["\\", "\0", "\n", "\r", "\x1a", "'", '"', "\x1a"],
        ["\\\\", "\\0", "\\n", "\\r", "\\Z", "\\'", '\\"', "\\Z"],
        (string) $string
    );
}

class Db
{
    private static $instance;

    public static function getInstance($slave = false)
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function execute($sql)
    {
        if (TestState::$failNextExecute) {
            return false;
        }

        return TestState::$pdo->exec($sql) !== false;
    }

    public function executeS($sql)
    {
        $stmt = TestState::$pdo->query($sql);
        if ($stmt === false) {
            throw new RuntimeException('SQL error: ' . $sql);
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRow($sql)
    {
        $rows = $this->executeS($sql);

        return isset($rows[0]) ? $rows[0] : false;
    }
}

class Configuration
{
    public static function get($name, $idLang = null, $idShopGroup = null, $idShop = null)
    {
        $shopId = self::resolveShopId($idShop);
        $stmt = TestState::$pdo->prepare(
            'SELECT value FROM ps_configuration WHERE name = ? AND (id_shop = ? OR (id_shop IS NULL AND ? IS NULL)) ORDER BY id_shop DESC LIMIT 1'
        );
        $stmt->execute([$name, $shopId, $shopId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $row['value'] : null;
    }

    public static function getGlobalValue($name)
    {
        $stmt = TestState::$pdo->prepare(
            'SELECT value FROM ps_configuration WHERE name = ? AND id_shop IS NULL LIMIT 1'
        );
        $stmt->execute([$name]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $row['value'] : null;
    }

    public static function updateValue($name, $value, $html = false, $idShopGroup = null, $idShop = null)
    {
        $shopId = self::resolveShopId($idShop);
        TestState::$pdo->prepare('DELETE FROM ps_configuration WHERE name = ? AND id_shop = ?')->execute([$name, $shopId]);
        TestState::$pdo->prepare('INSERT INTO ps_configuration (name, value, id_shop) VALUES (?, ?, ?)')->execute([$name, (string) $value, $shopId]);

        return true;
    }

    public static function updateGlobalValue($name, $value)
    {
        TestState::$pdo->prepare('DELETE FROM ps_configuration WHERE name = ? AND id_shop IS NULL')->execute([$name]);
        TestState::$pdo->prepare('INSERT INTO ps_configuration (name, value, id_shop) VALUES (?, ?, NULL)')->execute([$name, (string) $value]);

        return true;
    }

    public static function deleteByName($name)
    {
        TestState::$pdo->prepare('DELETE FROM ps_configuration WHERE name = ?')->execute([$name]);

        return true;
    }

    public static function getMultiple(array $keys, $idLang = null, $idShopGroup = null, $idShop = null)
    {
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = self::get($key, $idLang, $idShopGroup, $idShop);
        }

        return $out;
    }

    private static function resolveShopId($idShop)
    {
        if ($idShop !== null) {
            return (int) $idShop;
        }

        return (int) Context::getContext()->shop->id;
    }
}

class Shop
{
    public static function addSqlAssociation($table, $alias)
    {
        $shopId = (int) Context::getContext()->shop->id;

        return ' INNER JOIN ps_' . $table . '_shop ' . $table . '_shop ON (' . $table . '_shop.id_' . $table . ' = ' . $alias . '.id_' . $table . ' AND ' . $table . '_shop.id_shop = ' . $shopId . ') ';
    }

    public static function addSqlRestriction($share = false, $alias = '')
    {
        $shopId = (int) Context::getContext()->shop->id;
        $prefix = $alias ? $alias . '.' : '';

        return ' AND ' . $prefix . 'id_shop = ' . $shopId;
    }

    public static function getContextListShopID($share = false)
    {
        return [(int) Context::getContext()->shop->id];
    }
}

class Validate
{
    public static function isEmail($email)
    {
        return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    public static function isUnsignedId($value)
    {
        return is_numeric($value) && (int) $value >= 1;
    }

    public static function isUnsignedInt($value)
    {
        return is_numeric($value) && (int) $value >= 0;
    }

    public static function isLoadedObject($object)
    {
        return is_object($object) && isset($object->id) && (int) $object->id > 0;
    }
}

class Tools
{
    public static function getValue($key, $default = false)
    {
        return array_key_exists($key, TestState::$request) ? TestState::$request[$key] : $default;
    }

    public static function isSubmit($key)
    {
        return !empty(TestState::$request[$key]);
    }

    public static function substr($str, $start, $length = null)
    {
        return $length === null ? substr($str, $start) : substr($str, $start, $length);
    }

    public static function displayDate($date)
    {
        return (string) $date;
    }

    public static function purifyHTML($html)
    {
        return strip_tags($html);
    }

    public static function safeOutput($str)
    {
        return htmlspecialchars((string) $str, ENT_QUOTES, 'UTF-8');
    }

    public static function displayError($msg)
    {
        return 'ERROR:' . $msg;
    }

    public static function redirect($url)
    {
        TestState::$redirectUrl = $url;
        throw new RuntimeException('REDIRECT');
    }

    public static function isEmpty($value)
    {
        return $value === null || $value === '';
    }

    public static function getAdminTokenLite($tab)
    {
        return 'token';
    }
}

class Mail
{
    public static function Send(
        $idLang,
        $template,
        $subject,
        $templateVars,
        $to,
        $toName = null,
        $from = null,
        $fromName = null,
        $fileAttachment = null,
        $modeSMTP = null,
        $templatePath = null,
        $die = false,
        $idShop = null,
        $bcc = null,
        $replyTo = null
    ) {
        if (isset(Mail::$throwForRecipient) && Mail::$throwForRecipient === $to) {
            throw new Exception('mail fail for ' . $to);
        }
        TestState::$mailSends[] = [
            'id_lang' => $idLang,
            'template' => $template,
            'subject' => $subject,
            'template_vars' => $templateVars,
            'to' => $to,
            'from' => $from,
            'template_path' => $templatePath,
            'id_shop' => $idShop,
        ];

        return true;
    }

    public static $throwForRecipient = null;
}

class Hook
{
    public static function exec($hookName, $params = [])
    {
        TestState::$hookExecs[] = ['hook' => $hookName, 'params' => $params];
    }
}

class PrestaShopLogger
{
    public static function addLog($message, $severity = 1)
    {
        TestState::$logger[] = ['message' => $message, 'severity' => $severity];
    }
}

class AddressFormat
{
    public static function generateAddress(Address $address, $params, $lineSep, $fieldSep, $fieldsStyle = [])
    {
        TestState::$addressFormatCalls[] = [
            'addressId' => $address->id,
            'params' => $params,
            'lineSep' => $lineSep,
            'fieldSep' => $fieldSep,
            'fieldsStyle' => $fieldsStyle,
        ];

        return 'formatted-address';
    }
}

class Language
{
    public $id;
    public $iso = 'en';

    public function __construct($id = null)
    {
        if ($id !== null) {
            $this->id = (int) $id;
            $iso = self::getIsoById($this->id);
            if ($iso) {
                $this->iso = $iso;
            }
        }
    }

    public function getLocale()
    {
        return $this->iso === 'fr' ? 'fr-FR' : 'en-US';
    }

    public static function getIsoById($idLang)
    {
        $stmt = TestState::$pdo->prepare('SELECT iso FROM ps_language WHERE id_lang = ?');
        $stmt->execute([(int) $idLang]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $row['iso'] : '';
    }

    public static function getLocaleByIso($iso)
    {
        return $iso === 'fr' ? 'fr-FR' : 'en-US';
    }
}

class Customer
{
    public $id = 0;
    public $email = '';
    public $firstname = 'Test';
    public $lastname = 'User';
    public $logged = 0;

    public function __construct($id = null)
    {
        if ($id !== null) {
            $this->id = (int) $id;
            $stmt = TestState::$pdo->prepare('SELECT email FROM ps_customer WHERE id_customer = ?');
            $stmt->execute([(int) $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $this->email = $row['email'];
            }
        }
    }

    public function isLogged()
    {
        return (int) $this->logged === 1 || (int) $this->id > 0;
    }

    public function getByEmail($email)
    {
        $stmt = TestState::$pdo->prepare('SELECT id_customer, email FROM ps_customer WHERE email = ?');
        $stmt->execute([(string) $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $c = new Customer();
        if ($row) {
            $c->id = (int) $row['id_customer'];
            $c->email = $row['email'];
        }

        return $c;
    }
}

class Product
{
    public $id;
    public $active = 1;
    public $minimal_quantity = 1;
    public $advanced_stock_management = 0;
    public $link_rewrite = 'product';

    public const CUSTOMIZE_TEXTFIELD = 1;
    public const CUSTOMIZE_FILE = 2;

    public function __construct($id = null, $full = false, $idLang = null, $idShop = null, $context = null)
    {
        $this->id = (int) $id;
        if ($id) {
            $stmt = TestState::$pdo->prepare('SELECT active, minimal_quantity, advanced_stock_management FROM ps_product WHERE id_product = ?');
            $stmt->execute([(int) $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $this->active = (int) $row['active'];
                $this->minimal_quantity = (int) $row['minimal_quantity'];
                $this->advanced_stock_management = (int) $row['advanced_stock_management'];
            }
            $stmt = TestState::$pdo->prepare('SELECT link_rewrite FROM ps_product_lang WHERE id_product = ? AND id_lang = ? LIMIT 1');
            $stmt->execute([(int) $id, (int) ($idLang ?: 1)]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $this->link_rewrite = $row['link_rewrite'];
            }
        }
    }

    public static function getProductName($idProduct, $idAttribute, $idLang)
    {
        $stmt = TestState::$pdo->prepare('SELECT name FROM ps_product_lang WHERE id_product = ? AND id_lang = ? LIMIT 1');
        $stmt->execute([(int) $idProduct, (int) $idLang]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $row['name'] : 'Product';
    }

    public static function getTaxCalculationMethod($customerId)
    {
        return PS_TAX_EXC;
    }

    public static function getAllCustomizedDatas($cartId)
    {
        return [];
    }

    public static function addCustomizationPrice(&$products, $customized)
    {
    }

    public function hasAttributes()
    {
        $stmt = TestState::$pdo->prepare('SELECT COUNT(*) FROM ps_product_attribute WHERE id_product = ?');
        $stmt->execute([(int) $this->id]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function getAttributesGroups($idLang)
    {
        return [];
    }

    public static function _getAttributeImageAssociations($idProductAttribute)
    {
        $stmt = TestState::$pdo->prepare('SELECT id_image FROM ps_product_attribute_image WHERE id_product_attribute = ? ORDER BY id_image');
        $stmt->execute([(int) $idProductAttribute]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getImages($idLang)
    {
        $stmt = TestState::$pdo->prepare('SELECT id_image, cover FROM ps_image WHERE id_product = ?');
        $stmt->execute([(int) $this->id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLink()
    {
        return 'http://shop.test/product/' . (int) $this->id;
    }

    public static function isAvailableWhenOutOfStock($outOfStock)
    {
        return (int) $outOfStock === 1;
    }
}

class Address
{
    public $id;
    public $company = '';
    public $firstname = '';
    public $lastname = '';
    public $address1 = '';
    public $address2 = '';
    public $city = '';
    public $postcode = '';
    public $country = '';
    public $id_state = 0;
    public $phone = '';
    public $phone_mobile = '';
    public $other = '';

    public function __construct($id = null)
    {
        $this->id = (int) $id;
    }
}

class State
{
    public $name = '';

    public function __construct($id = null)
    {
    }
}

class Carrier
{
    public $name;

    public function __construct($id = null)
    {
        $this->name = 'DHL';
    }
}

class Link
{
    public function getProductLink($product, $rewrite = null, $category = null, $ean = null, $idLang = null, $idShop = null, $idProductAttribute = null)
    {
        $id = is_object($product) ? $product->id : $product;

        return 'http://shop.test/p/' . (int) $id . '/' . (int) $idProductAttribute;
    }

    public function getImageLink($rewrite, $cover, $type)
    {
        return 'http://shop.test/img/' . $cover . '-' . $type;
    }

    public function getModuleLink($module, $controller)
    {
        return 'http://shop.test/module/' . $module . '/' . $controller;
    }

    public function getAdminLink($controller, $withToken = true)
    {
        return 'http://shop.test/admin/' . $controller;
    }
}

class Context
{
    public $shop;
    public $language;
    public $customer;
    public $link;
    public $smarty;
    public $controller;
    public $container;

    private static $instance;

    public function __construct()
    {
        $this->shop = (object) ['id' => 1];
        $this->language = new class {
            public $id = 1;

            public function getLocale()
            {
                return 'en-US';
            }
        };
        $this->customer = new Customer();
        $this->link = new Link();
        $this->smarty = new class {
            public $vars = [];

            public function assign($key, $value = null)
            {
                if (is_array($key)) {
                    foreach ($key as $k => $v) {
                        $this->vars[$k] = $v;
                    }
                } else {
                    $this->vars[$key] = $value;
                }
            }
        };
        $this->controller = new class {
            public $jquery = [];

            public function addJqueryUi($name)
            {
                $this->jquery[] = $name;
            }

            public function addJqueryPlugin($name)
            {
                $this->jquery[] = $name;
            }

            public function registerJavascript($id, $path)
            {
                $this->jquery[] = $id . ':' . $path;
            }

            public function addJS($path)
            {
                $this->jquery[] = $path;
            }

            public function getLanguages()
            {
                return [];
            }
        };
    }

    public static function getContext()
    {
        if (self::$instance === null) {
            self::$instance = new self();
            TestState::$context = self::$instance;
        }

        return self::$instance;
    }

    public static function resetInstance()
    {
        self::$instance = null;
        TestState::$context = null;
    }

    public function getCurrentLocale()
    {
        return new class {
            public function formatPrice($amount, $currency)
            {
                return number_format((float) $amount, 2) . ' ' . $currency;
            }
        };
    }

    public function getTranslatorFromLocale($locale)
    {
        return new class {
            public function trans($id, $params = [], $domain = null, $locale = null)
            {
                return $id;
            }
        };
    }
}

class Module
{
    public $name;
    public $id = 0;
    public $tab = 'administration';
    public $version = '3.0.2';
    public $author = 'PrestaShop';
    public $need_instance = 0;
    public $controllers = [];
    public $bootstrap = true;
    public $displayName;
    public $description;
    public $ps_versions_compliancy = [];
    public $context;
    public $table = 'module';
    public $identifier = 'id_module';
    public $_path = '/modules/ps_emailalerts/';

    public function __construct()
    {
        $this->context = Context::getContext();
    }

    public function registerHook($name)
    {
        if (isset(TestState::$registerHookResults[$name]) && TestState::$registerHookResults[$name] === false) {
            return false;
        }
        TestState::$hooks[] = $name;

        return true;
    }

    public function unregisterHook($name)
    {
        TestState::$hooks = array_values(array_filter(TestState::$hooks, function ($h) use ($name) {
            return $h !== $name;
        }));

        return true;
    }

    public function install()
    {
        return TestState::$moduleParentInstall;
    }

    public function uninstall()
    {
        return TestState::$moduleParentUninstall;
    }

    public function trans($id, $params = [], $domain = null, $locale = null)
    {
        return $id;
    }

    public function display($file, $template)
    {
        TestState::$displayCalls[] = $template;

        return 'TPL:' . $template;
    }

    public function displayError($msg)
    {
        return 'ERROR:' . $msg;
    }

    public function displayConfirmation($msg)
    {
        return 'OK:' . $msg;
    }

    public function getLocalPath()
    {
        return dirname(__DIR__, 2) . '/';
    }

    public function get($service)
    {
        if ($service === 'PrestaShop\\PrestaShop\\Adapter\\Shipment\\OrderShipmentService') {
            if (TestState::$orderShipmentService === 'throw') {
                throw new Error('service missing');
            }
            if (TestState::$orderShipmentService !== null) {
                return TestState::$orderShipmentService;
            }
        }
        throw new Error('unknown service');
    }

    public static function isInstalled($name)
    {
        return isset(Module::$installed[$name]) && Module::$installed[$name];
    }

    public static function getInstanceByName($name)
    {
        return isset(Module::$instances[$name]) ? Module::$instances[$name] : null;
    }

    public static $installed = [];
    public static $instances = [];
}

class ModuleFrontController
{
    public $module;
    public $context;
    public $template;

    public function __construct()
    {
        $this->context = Context::getContext();
    }

    public function init()
    {
    }

    public function initContent()
    {
    }

    public function trans($id, $params = [], $domain = null)
    {
        return $id;
    }

    protected function addMyAccountToBreadcrumb()
    {
        return ['title' => 'My account', 'url' => 'http://shop.test/account'];
    }

    public function getBreadcrumbLinks()
    {
        return ['links' => []];
    }

    public function setTemplate($tpl)
    {
        $this->template = $tpl;
    }
}

class ObjectModel
{
    public const TYPE_INT = 1;
    public const TYPE_BOOL = 2;
    public const TYPE_STRING = 3;
    public const TYPE_FLOAT = 4;
    public const TYPE_DATE = 5;
    public const TYPE_HTML = 6;
    public const TYPE_NOTHING = 7;
    public const TYPE_SQL = 8;

    public static $nextAddResult = true;

    public function add($autoDate = true, $nullValues = false)
    {
        if (!TestState::$nextAddResult) {
            return false;
        }
        $def = MailAlert::$definition;
        $table = 'ps_' . $def['table'];
        TestState::$pdo->prepare(
            'INSERT INTO ' . $table . ' (id_customer, customer_email, id_product, id_product_attribute, id_shop, id_lang) VALUES (?,?,?,?,?,?)'
        )->execute([
            (int) $this->id_customer,
            (string) $this->customer_email,
            (int) $this->id_product,
            (int) $this->id_product_attribute,
            (int) $this->id_shop,
            (int) $this->id_lang,
        ]);

        return true;
    }
}

class HelperForm
{
    public $module;
    public $tpl_vars = [];
    public $fields = [];

    public function generateForm($forms)
    {
        $names = [];
        foreach ($forms as $form) {
            foreach ($form['form']['input'] as $input) {
                $names[] = $input['name'];
            }
            if (isset($form['form']['submit']['name'])) {
                $names[] = $form['form']['submit']['name'];
            }
        }
        $this->fields = $names;

        return 'FORM:' . implode(',', $names);
    }
}

class StockManagerFactory
{
    public static function getManager()
    {
        return new class {
            public function getProductCoverage($idProduct, $idProductAttribute, $warning, $idWarehouse)
            {
                return TestState::$stockCoverage;
            }
        };
    }
}

class OrderReturn
{
    public static function getOrdersReturnProducts($idReturn, $order)
    {
        return [
            [
                'product_id' => 10,
                'product_reference' => 'SKU-R',
                'product_name' => 'Returned item',
                'product_quantity' => 2,
            ],
        ];
    }
}

class Order
{
    public $id;
    public $id_lang = 1;
    public $id_shop = 1;
    public $reference = 'REF1';
    public $id_address_delivery = 1;
    public $id_address_invoice = 1;
    public $date_add = '2020-01-15 10:00:00';
    public $id_carrier = 1;
    public $payment = 'Bank wire';
    public $total_paid = 100;
    public $total_discounts = 0;
    public $total_shipping = 5;
    public $total_shipping_tax_excl = 4;
    public $total_shipping_tax_incl = 5;
    public $total_paid_tax_incl = 100;
    public $total_paid_tax_excl = 90;
    public $total_wrapping = 0;
    public $gift = 0;
    public $gift_message = '';

    public function __construct($id = null)
    {
        $this->id = (int) $id;
    }

    public function getProducts()
    {
        return [
            [
                'product_id' => 10,
                'product_reference' => 'SKU-1',
                'product_name' => 'Wool sweater',
                'product_price' => 10,
                'product_price_wt' => 12,
                'product_quantity' => 3,
                'id_product_attribute' => 0,
                'attributes_small' => '',
                'id_customization' => 0,
            ],
        ];
    }

    public function getCartRules()
    {
        return [];
    }

    public function getTotalProductsWithoutTaxes()
    {
        return 30;
    }

    public function getTotalProductsWithTaxes()
    {
        return 36;
    }

    public function getCustomer()
    {
        $c = new Customer(7);
        $c->firstname = 'Jane';
        $c->lastname = 'Doe';

        return $c;
    }

    public function getUniqReference()
    {
        return 'UNIQ-REF';
    }
}

class OrderState
{
    public $name = 'Payment accepted';
}

class Controller
{
    public const SERVICE_LOCALE_REPOSITORY = 'locale.repository';
}

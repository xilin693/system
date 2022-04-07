<?php

define('EXT', '.php');
define('ENV', 'prod');
defined('ENV_PREFIX') or define('ENV_PREFIX', 'PHP_');

$public_directory = 'public';
$app_directory = 'application';
$system_directory = '../system';
$test_directory = 'tests';
$pos = strrpos(FCPATH, $public_directory . DS);

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', substr_replace(FCPATH, '', $pos, strlen($public_directory . DS)));
}

if (!defined('APP_PATH')) {
    define('APP_PATH', realpath(ROOT_PATH . $app_directory) . DS);
}

if (!defined('TEST_PATH')) {
    define('TEST_PATH', realpath(ROOT_PATH . $test_directory) . DS);
}

if (!defined('SYS_PATH')) {
    define('SYS_PATH', realpath(ROOT_PATH . $system_directory) . DS);
}

if (!defined('VENDOR_PATH')) {
    define('VENDOR_PATH', realpath(APP_PATH . 'vendor') . DS);
}

$env_file = '.env';
if (isset($_SERVER['ENV_FILE']) && $_SERVER['ENV_FILE']) {
    $env_file = $_SERVER['ENV_FILE'];
}

require SYS_PATH . 'lib/Env.php';
king\lib\Env::loadFile(APP_PATH . $env_file);

require SYS_PATH . 'core/Loader.php';
if (C('use_composer') && is_file(APP_PATH . 'vendor/autoload.php')) {
    require APP_PATH . 'vendor/autoload.php';
}

spl_autoload_register('king\core\Loader::autoload', true, true);
if (!empty(C('timezone'))) {
    date_default_timezone_set(C('timezone'));
}

king\core\Error::register();
if (C('sentry') != false) {
    \Sentry\init(['dsn' => C('sentry.dsn')]);
    \Sentry\captureLastError();
}

if(function_exists('newrelic_name_transaction') && C('newrelic.app_name') && PHP_SAPI !== 'cli') {
    newrelic_set_appname(C('newrelic.app_name'));
    $request_url_method = $_SERVER['REQUEST_METHOD'] . ' '. $_SERVER['REQUEST_URI'];
    newrelic_name_transaction($request_url_method);
}

king\core\Loader::run();

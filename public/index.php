<?php
error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', 1);
date_default_timezone_set('Europe/Tallinn');

if (PHP_SAPI == 'cli-server') {
    // To help the built-in PHP dev server, check if the request was actually for
    // something which should probably be served as a static file
    $url  = parse_url($_SERVER['REQUEST_URI']);
    $file = __DIR__ . $url['path'];
    if (is_file($file)) {
        return false;
    }
}

require __DIR__ . '/../vendor/autoload.php';



session_start();

// Instantiate the app
if (php_sapi_name() == 'cli' || isset($_SERVER['SHELL'])) {
    $argv = $GLOBALS['argv'];
    if($argv) {
        array_shift($argv);
    }

    if(php_sapi_name() == 'cli') {
        $pathInfo = implode('/', $argv);
    } else {
        $pathInfo = array_keys($_GET)[1];
    }

    $env = \Slim\Http\Environment::mock(['REQUEST_URI' => '/' . $pathInfo]);

    $settings = require __DIR__ . '/../src/settings.php';

    //I try adding here path_info but this is wrong, I'm sure
    $settings['environment'] = $env;

} else {
    $settings = require __DIR__ . '/../src/settings.php';
}

$app = new \Slim\App($settings);

// Set up dependencies
require __DIR__ . '/../src/dependencies.php';

// Register middleware
require __DIR__ . '/../src/middleware.php';

// Register routes
require __DIR__ . '/../src/routes.php';

// Run app
$app->run();

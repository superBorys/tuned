<?php
// DIC configuration

use App\Models\Items;
use GeoIp2\Database\Reader;


$container = $app->getContainer();

class DumbExceptionHandler implements \Illuminate\Contracts\Debug\ExceptionHandler
{
    public function report(Exception $e)
    {
        //
    }

    public function render($request, Exception $e)
    {
        throw $e;
    }

    public function renderForConsole($output, Exception $e)
    {
        throw $e;
    }
}

//Eloquent
$capsule = new \Illuminate\Database\Capsule\Manager;
$capsule->addConnection($container['settings']['db']);
$capsule->setAsGlobal();
$capsule->bootEloquent();
$capsule->getContainer()->singleton(
    \Illuminate\Contracts\Debug\ExceptionHandler::class,
    \DumbExceptionHandler::class
);

// Register component on container
$container['view'] = function ($c) {
    $settings = $c->get('settings')['renderer'];

    $view = new \Slim\Views\Twig($settings['template_path'], [
//        'cache' => 'path/to/cache'
    ]);

    // Instantiate and add Slim specific extension
    $basePath = rtrim(str_ireplace('index.php', '', $c['request']->getUri()->getBasePath()), '/');
    $view->addExtension(new Slim\Views\TwigExtension($c['router'], $basePath));


    $reader = new Reader($c->get('settings')['GeoLiteDBFile']);

    try {
        $record = $reader->country($_SERVER['REMOTE_ADDR']);
        $country = $record->country->isoCode;
    } catch (Exception $e) {
        $country = null;
    }

    $view->getEnvironment()->addGlobal('country', $country);
    $view->getEnvironment()->addGlobal('base_url', $c->get('settings')['baseUrl']);


    $aMobileUA = array(
        '/iphone/i' => 'iPhone',
        '/ipod/i' => 'iPod',
        '/ipad/i' => 'iPad',
        '/android/i' => 'Android',
        '/blackberry/i' => 'BlackBerry',
        '/webos/i' => 'Mobile'
    );

    $isMobile = false;
    //Return true if Mobile User Agent is detected
    foreach($aMobileUA as $sMobileKey => $sMobileOS){
        if(preg_match($sMobileKey, $_SERVER['HTTP_USER_AGENT'])){
            $isMobile = true;
        }
    }
    if($isMobile && $c->request->getUri()->getPath() != '/mobile'){
        header('Location: '.$c->get('settings')['baseUrl'].'/mobile');
        exit();
    }
    return $view;
};

//memcached
//$container['memcached'] = function ($c) {
//    $memcached = new \Memcached();
//    $memcached->addServer('localhost', 11211);
//
//    return $memcached;
//};

//// view renderer
//$container['renderer'] = function ($c) {
//    $settings = $c->get('settings')['renderer'];
//    return new Slim\Views\PhpRenderer($settings['template_path']);
//};

//not found handler
//$container['notFoundHandler'] = function ($c) {
//    return function ($request, $response) use ($c) {
//        return $c['view']->render($response, '404.html')->withStatus(404);
//    };
//};

// monolog
//$container['logger'] = function ($c) {
//    $settings = $c->get('settings')['logger'];
//    $logger = new Monolog\Logger($settings['name']);
//    $logger->pushProcessor(new Monolog\Processor\UidProcessor());
//    $logger->pushHandler(new Monolog\Handler\StreamHandler($settings['path'], $settings['level']));
//    return $logger;
//};

// db
$container['db'] = function ($c) use ($capsule) {
    return $capsule;
};

// API
$container['APIController'] = function ($c) {
    return new App\Controllers\APIController($c);
};
// Importer
$container['ImporterController'] = function ($c) {
    return new App\Controllers\ImporterController($c);
};
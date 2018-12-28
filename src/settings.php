<?php
// $baseUrl = 'http://tuned.rocks';
$baseUrl = 'http://localhost';
return [
    'settings' => [
        'baseUrl' => $baseUrl,
        'displayErrorDetails' => true, // set to false in production
        'addContentLengthHeader' => false, // Allow the web server to send the content-length header

        // Renderer settings
        'renderer' => [
            'template_path' => __DIR__ . '/../templates/',
            'cache_path' => __DIR__ . '/../cache/twig/',
        ],

        // Monolog settings
//        'logger' => [
//            'name' => 'real-estate-app',
//            'path' => __DIR__ . '/../logs/app.log',
//            'level' => \Monolog\Logger::DEBUG,
//        ],
        'db' => [
            'driver' => 'mysql',
//            'host' => '172.17.0.3',
            'host' => 'localhost',
            'database' => 'tuned_rocks',
            // 'username' => 'tuned_rocks',
           'username' => 'root',
            'password' => 'CNf6rAibWctm',
           'password' => ' ',

            'charset'  => 'utf8mb4',
            'collation' => 'utf8mb4_general_ci',
            'prefix' => '',
        ],
        'GeoLiteDBFile' => __DIR__ . '/data/GeoLite2-Country.mmdb',
        'googleApp' => [
            'appName' => 'Online streaming-like videos',
            'credentialsPath' => __DIR__ . '/data/token.json',
            'clientSecretPath' => __DIR__ . '/data/client_secret.json',
            'channels' => [
                'music' => 'UCbX8qmLJwbFlR5VqUMnFd2A',
                'sports' => 'UCN2CfhRQ8arS_ZKZmqCXsKQ',
                'tv' => 'UCkn6mgPFlWrjx_0jUXbydRA',
                'kids' => 'UCmMdNOYFgOVk30iLvJLDfZw',
                'vlogs' => 'UCmFIQVa5Alil498VPEY6GDg',
                'news' => 'UCnlViNUp8RE66N_AaufK6dQ'
            ]
        ],
        'spotifyApp' => [
            'clientId' => '2906deb3b9634eda9833a65e21bbf0f9',
            'clientSecret' => '6dbaf72cf683498e95c65d45e2f88297',
            'credentialsPath' => __DIR__ . '/data/spotify_token.json',
        ]
    ],
];

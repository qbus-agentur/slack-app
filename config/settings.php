<?php
return [
    'displayErrorDetails' => PHP_SAPI == 'cli-server', // set to false in production
    'addContentLengthHeader' => false, // Allow the web server to send the content-length header

    /* As far as i can see output buffering is pure convenience in slim:
     * https://github.com/slimphp/Slim/commit/2f02b4ba
     * let's disable it */
    'outputBuffering' => false,

    'routerCacheFile' => __DIR__ . '/../var/cache/router-cache',

    'db' => [
        'host' => getenv('DB_HOST'),
        'name' => getenv('DB_NAME'),
        'user' => getenv('DB_USER'),
        'pass' => getenv('DB_PASS'),
    ],

    // Monolog settings
    'log' => [
        'name' => 'qbus/slack-app',
        'path' => __DIR__ . '/../var/log/app.log',
        'level' => \Monolog\Logger::DEBUG,
    ],

    'auth' => [
        'salt' => getenv('ACTIVECOLLAB_SALT')
    ],
];

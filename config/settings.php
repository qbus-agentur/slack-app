<?php
return [
    'displayErrorDetails' => PHP_SAPI == 'cli-server', // set to false in production
    'displayErrorDetails' => true,
    'addContentLengthHeader' => false, // Allow the web server to send the content-length header

    // Required for slimphp-api/slim-acl
    'determineRouteBeforeAppMiddleware' => true,

    /* As far as i can see output buffering is pure convenience in slim:
     * https://github.com/slimphp/Slim/commit/2f02b4ba
     * let's disable it */
    'outputBuffering' => false,

    'routerCacheFile' => __DIR__ . '/../var/cache/router-cache',

    // Renderer settings
    'view' => [
        'prettyprint' => true,
        'extension' => '.pug',
        'basedir' => __DIR__ . '/../templates/',
        'cache' => __DIR__ . '/../var/cache/view/'
    ],
    'db' => [
        'host' => getenv('DB_HOST'),
        'name' => getenv('DB_NAME'),
        'user' => getenv('DB_USER'),
        'pass' => getenv('DB_PASS'),
    ],

    // Monolog settings
    'log' => [
        'name' => 'qac',
        'path' => __DIR__ . '/../var/log/app.log',
        'level' => \Monolog\Logger::DEBUG,
    ],

    'acl' => [
        'default_role' => 'guest',
        'roles' => [
            'guest' => [],
            'user'  => ['guest'],
            'admin' => ['user']
        ],
        'guards' => [
            'routes' => [
                ['/', ['admin'],  ['get']],
            ],
            'callables' => [
                [Qbus\QAC\Controller\Ticket::class . ':new', ['admin']],
                [Qbus\QAC\Controller\Ticket::class . ':save', ['admin']],
                [Qbus\QAC\Controller\TimeRecord::class . ':index', ['admin']],
                [Qbus\QAC\Controller\TimeRecord::class . ':save', ['admin']],
            ],
        ],
    ],

    'auth' => [
        'salt' => getenv('ACTIVECOLLAB_SALT')
    ],
];

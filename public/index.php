<?php

(function() {
    $container = require __DIR__ . '/../bootstrap.php';

    session_start();

    $serverRequest = \Zend\Diactoros\ServerRequestFactory::fromGlobals();
    $response = $container->get(\Slim\App::class)->handle($serverRequest);

    $emitter = new \Slim\ResponseEmitter;
    $emitter->emit($response);
})();

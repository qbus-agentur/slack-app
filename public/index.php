<?php

(function() {
    $container = require __DIR__ . '/../bootstrap.php';

    session_start();

    $container->get(\Slim\App::class)->run();
})();

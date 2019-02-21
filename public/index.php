<?php

(function() {
    $container = require __DIR__ . '/../bootstrap.php';

    session_start();

    $container->get('app')->run();
})();

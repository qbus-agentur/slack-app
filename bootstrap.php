<?php

require __DIR__ . '/vendor/autoload.php';

// Load (database) configuration from .env
$dotenv = \Dotenv\Dotenv::create(__DIR__);
$dotenv->load();
$dotenv->required(['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'ACTIVECOLLAB_SALT']);

return new \Bnf\Di\Container([
    new \Bnf\SlimInterop\ServiceProvider,
    new \Qbus\SlackApp\Bootstrap,
]);

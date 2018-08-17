<?php

require __DIR__ . '/../vendor/autoload.php';

session_start();

// Load (database) configuration from ../.env
$dotenv = new \Dotenv\Dotenv(__DIR__ . '/../');
$dotenv->load();
$dotenv->required(['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'ACTIVECOLLAB_SALT']);

(new \Bnf\Di\Container([new \Qbus\SlackApp\Bootstrap]))->get('app')->run();

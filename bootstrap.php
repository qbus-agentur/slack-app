<?php

require __DIR__ . '/vendor/autoload.php';

return new \Bnf\Di\Container([
    new \Bnf\SlimInterop\ServiceProvider,
    new \Bnf\ZendDiactoros\ServiceProvider,
    new \Qbus\SlackApp\Bootstrap,
]);

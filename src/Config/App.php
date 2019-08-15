<?php
declare(strict_types = 1);
namespace Qbus\SlackApp\Config;

interface App
{
    public function rootUrl(): string;
}

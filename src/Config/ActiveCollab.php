<?php
declare(strict_types = 1);
namespace Qbus\SlackApp\Config;

interface ActiveCollab
{
    public function url(): string;
    public function salt(): string;
    public function database(): Database;
}

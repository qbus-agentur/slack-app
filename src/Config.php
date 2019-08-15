<?php
declare(strict_types = 1);
namespace Qbus\SlackApp;

use Qbus\SlackApp\Config\App;
use Qbus\SlackApp\Config\ActiveCollab;
use Qbus\SlackApp\Config\Database;
use Qbus\SlackApp\Config\Slack;

interface Config
{
    public function app(): App;
    public function activeCollab(): ActiveCollab;
    public function database(): Database;
    public function slack(): Slack;
}

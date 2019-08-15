<?php
declare(strict_types = 1);
namespace Qbus\SlackApp\Config;

interface Slack
{
    public function appId(): string;
    public function clientId(): string;
    public function clientSecret(): string;
    public function signingSecret(): string;
    public function rootUrl(): string;
}

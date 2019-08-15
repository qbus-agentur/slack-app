<?php
declare(strict_types = 1);
namespace Qbus\SlackApp\Config;

interface Database
{
    public function host(): string;
    public function port(): ?string;
    public function name(): string;
    public function user(): string;
    public function pass(): string;
}

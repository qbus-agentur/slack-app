<?php
declare(strict_types = 1);
namespace Qbus\SlackApp\Config;

use Qbus\SlackApp\Config as ConfigInterface;

final class Config implements ConfigInterface
{
    /** @var Database */
    private $database;
    /** @var Slack */
    private $slack;
    /** @var ActiveCollab */
    private $activeCollab;
    /** @var App */
    private $app;

    public function __construct(array $configuration)
    {
        $this->app = $this->createAppConfiguration($configuration['App']);
        $this->database = $this->createDatabaseConfiguration($configuration['Database']);
        $this->slack = $this->createSlackConfiguration($configuration['Slack']);
        $this->activeCollab = $this->createActiveCollabConfiguration($configuration['ActiveCollab']);
    }

    public function database(): Database
    {
        return $this->database;
    }

    public function slack(): Slack
    {
        return $this->slack;
    }

    public function activeCollab(): ActiveCollab
    {
        return $this->activeCollab;
    }

    public function app(): App
    {
        return $this->app;
    }

    private function createDatabaseConfiguration(array $configuration): Database
    {
        return new class ($configuration) implements Database {
            /** @var string */
            private $host;
            /** @var string */
            private $port;
            /** @var string */
            private $name;
            /** @var string */
            private $user;
            /** @var string */
            private $pass;

            public function __construct(array $data)
            {
                $this->host = $data['Host'] ?? '';
                $this->port = $data['Port'] ?? '3306';
                $this->name = $data['Name'] ?? '';
                $this->user = $data['User'] ?? '';
                $this->pass = $data['Pass'] ?? '';
            }

            public function host(): string
            {
                return $this->host;
            }

            public function port(): string
            {
                return $this->port;
            }

            public function name(): string
            {
                return $this->name;
            }

            public function user(): string
            {
                return $this->user;
            }

            public function pass(): string
            {
                return $this->pass;
            }
        };
    }

    private function createSlackConfiguration(array $configuration): Slack
    {
        return new class ($configuration) implements Slack {
            /** @var string */
            private $appId;
            /** @var string */
            private $clientId;
            /** @var string */
            private $clientSecret;
            /** @var string */
            private $signingSecret;
            /** @var string */
            private $rootUrl;

            public function __construct(array $data)
            {
                $this->appId = $data['AppId'] ?? '';
                $this->clientId = $data['ClientId'] ?? '';
                $this->clientSecret = $data['ClientSecret'] ?? '';
                $this->signingSecret = $data['SigningSecret'] ?? '';
                $this->rootUrl = $data['RootUrl'] ?? 'https://slack.com';
            }

            public function appId(): string
            {
                return $this->appId;
            }

            public function clientId(): string
            {
                return $this->clientId;
            }

            public function clientSecret(): string
            {
                return $this->clientSecret;
            }

            public function signingSecret(): string
            {
                return $this->signingSecret;
            }

            public function rootUrl(): string
            {
                return $this->rootUrl;
            }
        };
    }

    private function createAppConfiguration(array $configuration): App
    {
        return new class ($configuration) implements App {
            /** @var string */
            private $rootUrl;

            public function __construct(array $data)
            {
                $this->rootUrl = $data['RootUrl'] ?? '';
            }

            public function rootUrl(): string
            {
                return $this->rootUrl;
            }
        };
    }

    private function createActiveCollabConfiguration(array $configuration): ActiveCollab
    {
        $database = $this->createDatabaseConfiguration([
            'Host' => $configuration['DBHost'] ?? '',
            'Port' => $configuration['DBPort'] ?? null,
            'Name' => $configuration['DBName'] ?? '',
            'User' => $configuration['DBUser'] ?? '',
            'Pass' => $configuration['DBPass'] ?? '',
        ]);

        return new class ($configuration, $database) implements ActiveCollab {
            /** @var string */
            private $salt;
            /** @var string */
            private $url;
            /** @var Database */
            private $database;

            public function __construct(array $data, Database $database)
            {
                $this->url = $data['URL'] ?? '';
                $this->salt = $data['Salt'] ?? '';
                $this->database = $database;
            }

            public function url(): string
            {
                return $this->url;
            }
            public function salt(): string
            {
                return $this->salt;
            }
            public function database(): Database
            {
                return $this->database;
            }
        };
    }
}

<?php
declare(strict_types = 1);
namespace Qbus\SlackApp;

use Interop\Container\ServiceProviderInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Container\ContainerInterface as CI;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Log\LoggerInterface;
use Slim\App;
use Slim\Interfaces\DispatcherInterface;
use Slim\Interfaces\RouteCollectorInterface;
use Slim\Handlers\ErrorHandler;
use Slim\Middleware\ErrorMiddleware;
use Slim\PDO\Database;
use Sunrise\Http\Client\Curl\Client;

/**
 * Bootstrap
 *
 * This class configures a dependency injection container that conforms to
 * PSR-11, by implementing the ServiceProviderInterface defined by
 * https://github.com/container-interop/service-provider
 *
 * @author Benjamin Franzke <bfr@qbus.de>
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class Bootstrap implements ServiceProviderInterface
{
    public function getFactories(): array
    {
        return [
            Config::class => function (): Config {
                $config = parse_ini_file(__DIR__ . '/../app.conf', true, INI_SCANNER_RAW);
                if ($config === false) {
                    throw new \RuntimeException('Invalid configuration file');
                }
                return new Config\Config($config);
            },
            'slim.route_cache_file' => function (): ?string {
                return PHP_SAPI === 'cli-server' ? null : __DIR__ . '/../var/cache/router-cache';
            },
            'slim.display_error_details' => function (): bool {
                return PHP_SAPI === 'cli-server'; // set to false in production
            },
            'slim.log_errors' => function (): bool {
                return true;
            },
            'slim.log_error_detais' => function (): bool {
                return true;
            },
            RouteDispatcher::class => function (CI $c): RouteDispatcher {
                return new RouteDispatcher(
                    $c->get(RouteCollectorInterface::class)
                );
            },
            DispatcherInterface::class => function (CI $c): DispatcherInterface {
                return $c->get(RouteDispatcher::class);
            },
            'db' => function (CI $c): Database {
                $config = $c->get(Config::class)->database();
                $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8', $config->host(), $config->name());

                return new Database($dsn, $config->user(), $config->pass());
            },
            'acdb' => function (CI $c): Database {
                $config = $c->get(Config::class)->activeCollab()->database();
                $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8', $config->host(), $config->name());

                return new Database($dsn, $config->user(), $config->pass());
            },
            LoggerInterface::class => function (): LoggerInterface {
                $path = __DIR__ . '/../var/log/app.log';
                $level = \Monolog\Logger::DEBUG;
                $logger = new \Monolog\Logger('qbus/slack-app');
                $logger->pushProcessor(new \Monolog\Processor\UidProcessor());
                $logger->pushHandler(new \Monolog\Handler\StreamHandler($path, $level));
                return $logger;
            },
            ClientInterface::class => function (CI $c): ClientInterface {
                $curlOptions = [];
                return new Client(
                    $c->get(ResponseFactoryInterface::class),
                    $curlOptions
                );
            },
            'guard' => function (CI $c): MiddlewareInterface {
                /** @var Config */
                $config = $c->get(Config::class);
                return new Middleware\SlackGuard(
                    $c->get(LoggerInterface::class),
                    $config->slack()->signingSecret()
                );
            },

            Handler\Event::class => function (CI $c): RequestHandler {
                return new Handler\Event($c, $c->get(LoggerInterface::class));
            },
            Handler\Command::class => function (): RequestHandler {
                return new Handler\Command;
            },
            Handler\Interaction::class => function (): RequestHandler {
                return new Handler\Interaction;
            },
            Handler\Oauth\Start::class => function (CI $c): RequestHandler {
                /** @var Config */
                $config = $c->get(Config::class);
                return new Handler\Oauth\Start(
                    $config->slack()
                );
            },
            Handler\Oauth\Callback::class => function (CI $c): RequestHandler {
                /** @var Config */
                $config = $c->get(Config::class);
                return new Handler\Oauth\Callback(
                    $c->get(RequestFactoryInterface::class),
                    $c->get(ClientInterface::class),
                    $c->get('db'),
                    $config->slack()
                );
            },
            'handler.start' => function (): RequestHandler {
                return new Handler\Generic\FileContents(__DIR__ . '/../templates/index.html');
            },

            Service\Client\Slack::class => function (CI $c): Service\Client\Slack {
                return new Service\Client\Slack(
                    $c->get('db'),
                    $c->get(RequestFactoryInterface::class),
                    $c->get(ClientInterface::class),
                    $c->get(LoggerInterface::class),
                    $c->get(Config::class)->slack()->rootUrl()
                );
            },

            'slack.event:message' => function (CI $c): Event\EventHandlerInterface {
                return new Event\Message(
                    $c->get(Service\Client\Slack::class),
                    $c->get(LoggerInterface::class)
                );
            },
            'slack.event:link_shared' => function (CI $c): Event\EventHandlerInterface {
                /** @var Config */
                $config = $c->get(Config::class);
                return new Event\LinkShared(
                    $c->get(Service\Client\Slack::class),
                    $c->get('acdb'),
                    $c->get(LoggerInterface::class),
                    $config->activeCollab()->url(),
                    $config->app()->rootUrl()
                );
            },
        ];
    }

    public function getExtensions(): array
    {
        return [
            /* This is just to demonstrate the 'extensions' function,
             * we could do the same in the app factory. */
            App::class => function (CI $c, App $app): App {
                $this->addMiddleware($app);
                $this->addRoutes($app);

                $c->get(LoggerInterface::class)->info('constructed app');

                return $app;
            },
            ErrorHandler::class => function (CI $c, ErrorHandler $errorHandler): ErrorHandler {
                $errorHandler->setDefaultErrorRenderer('text/html', Handler\Error\HtmlErrorHandler::class);
                $errorHandler->registerErrorRenderer('text/html', Handler\Error\HtmlErrorHandler::class);
                return $errorHandler;
            },
        ];
    }

    protected function addMiddleware(App $app): void
    {
        $app->add(ErrorMiddleware::class);
    }

    protected function addRoutes(App $app): void
    {
        $app->get('/install', Handler\Oauth\Start::class)->setName('oauth-start');
        $app->get('/oauth/callback', Handler\Oauth\Callback::class)->setName('oauth-callback');

        $app->post('/command', Handler\Command::class)->add('guard')->setName('slack-command');
        $app->post('/event', Handler\Event::class)->add('guard')->setName('slack-event');
        $app->post('/interaction', Handler\Interaction::class)->add('guard')->setName('slack-interaction');

        $app->get('/', 'handler.start');
    }
}

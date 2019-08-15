<?php
declare(strict_types = 1);
namespace Qbus\SlackApp;

use Interop\Container\ServiceProviderInterface;
use Psr\Container\ContainerInterface as CI;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Log\LoggerInterface;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use Slim\App;
use Slim\Collection;
use Slim\Interfaces\DispatcherInterface;
use Slim\Interfaces\RouteCollectorInterface;
use Slim\Handlers\ErrorHandler;
use Slim\Middleware\ErrorMiddleware;
use Slim\PDO\Database;

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
            'settings' => function (CI $c): array {
                return require __DIR__ . '/../config/settings.php';
            },
            'slim.route_cache_file' => function (CI $c): ?string {
                return $c->get('settings')['routerCacheFile'];
            },
            'slim.display_error_details' => function (CI $c): bool {
                return $c->get('settings')['displayErrorDetails'];
            },
            'slim.log_errors' => function (CI $c): bool {
                return true;
            },
            'slim.log_error_detais' => function (CI $c): bool {
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
                $settings = $c->get('settings')['db'];
                $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8', $settings['host'], $settings['name']);

                return new Database($dsn, $settings['user'], $settings['pass']);
            },
            'acdb' => function (CI $c): Database {
                $settings = $c->get('settings')['acdb'];
                $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8', $settings['host'], $settings['name']);

                return new Database($dsn, $settings['user'], $settings['pass']);
            },
            LoggerInterface::class => function (CI $c): LoggerInterface {
                $settings = $c->get('settings')['log'];
                $logger = new \Monolog\Logger($settings['name']);
                $logger->pushProcessor(new \Monolog\Processor\UidProcessor());
                $logger->pushHandler(new \Monolog\Handler\StreamHandler($settings['path'], $settings['level']));
                return $logger;
            },
            GuzzleClient::class => function (CI $c): GuzzleClient {
                return new GuzzleClient;
            },
            GuzzleClientInterface::class => function (CI $c): GuzzleClientInterface {
                return $c->get(GuzzleClient::class);
            },
            'guard' => function (CI $c): MiddlewareInterface {
                return new Middleware\SlackGuard($c->get(LoggerInterface::class));
            },

            Handler\Event::class => function (CI $c): RequestHandler {
                return new Handler\Event($c, $c->get(LoggerInterface::class));
            },
            Handler\Command::class => function (CI $c): RequestHandler {
                return new Handler\Command;
            },
            Handler\Interaction::class => function (CI $c): RequestHandler {
                return new Handler\Interaction;
            },
            Handler\Oauth\Start::class => function (CI $c): RequestHandler {
                return new Handler\Oauth\Start;
            },
            Handler\Oauth\Callback::class => function (CI $c): RequestHandler {
                return new Handler\Oauth\Callback($c->get('db'));
            },
            'handler.start' => function (CI $c): RequestHandler {
                return new Handler\Generic\FileContents(__DIR__ . '/../templates/index.html');
            },

            Service\Client\Slack::class => function (CI $c): Service\Client\Slack {
                return new Service\Client\Slack(
                    $c->get('db'),
                    $c->get(RequestFactoryInterface::class),
                    $c->get(GuzzleClientInterface::class),
                    $c->get(LoggerInterface::class)
                );
            },

            'slack.event:message' => function (CI $c): Event\EventHandlerInterface {
                return new Event\Message(
                    $c->get(Service\Client\Slack::class),
                    $c->get(LoggerInterface::class)
                );
            },
            'slack.event:link_shared' => function (CI $c): Event\EventHandlerInterface {
                return new Event\LinkShared(
                    $c->get(Service\Client\Slack::class),
                    $c->get('acdb'),
                    $c->get(LoggerInterface::class)
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

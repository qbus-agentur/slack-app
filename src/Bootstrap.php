<?php
declare(strict_types = 1);
namespace Qbus\SlackApp;

use Bnf\Slim3Psr15\CallableResolver as Psr15CallableResolver;
use Interop\Container\ServiceProviderInterface;
use Psr\Container\ContainerInterface as CI;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Log\LoggerInterface;
use Slim\App;
use Slim\Collection;
use Slim\Interfaces\CallableResolverInterface;
use Slim\Interfaces\RouterInterface;
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
            'callableResolver' => function (CI $c): CallableResolverInterface {
                return new Psr15CallableResolver($c);
            },
            'router' => function (CI $c): RouterInterface {
                $routerCacheFile = $c->get('settings')['routerCacheFile'] ?? false;

                $router = (new Router)->setCacheFile($routerCacheFile);
                $router->setContainer($c);

                return $router;
            },
            'db' => function (CI $c): Database {
                $settings = $c->get('settings')['db'];
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
            'guard' => function (CI $c): MiddlewareInterface {
                return new Middleware\SlackGuard($c->get(LoggerInterface::class));
            },
            'notFoundHandler' => function (CI $c): callable {
                return new Handler\Error\NotFound;
            },
            'erorHandler' => function (CI $c): callable {
                return new Handler\Error\Error(
                    $c->get('settings')['displayErrorDetails'],
                    $c->get(LoggerInterface::class)
                );
            },
            'phpErrorHandler' => function (CI $c): callable {
                return new Handler\Error\PhpError(
                    $c->get('settings')['displayErrorDetails'],
                    $c->get(LoggerInterface::class)
                );
            },
            Handler\Event::class => function (CI $c): RequestHandler {
                return new Handler\Event($c->get(LoggerInterface::class));
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
        ];
    }

    protected function addMiddleware(App $app): void
    {
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

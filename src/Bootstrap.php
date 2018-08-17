<?php
namespace Qbus\SlackApp;

use Interop\Container\ServiceProviderInterface;
use Psr\Container\ContainerInterface as CI;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\App;

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
    public function getFactories()
    {
        $services = [
            'settings' => function (CI $c) {
                $slimDefaultSettings = [
                    'httpVersion' => '1.1',
                    'responseChunkSize' => 4096,
                    'outputBuffering' => 'append',
                    'determineRouteBeforeAppMiddleware' => false,
                    'displayErrorDetails' => false,
                    'addContentLengthHeader' => true,
                    'routerCacheFile' => false,
                ];
                $settings = require __DIR__ . '/../config/settings.php';
                return new \Slim\Collection(array_merge($slimDefaultSettings, $settings));
            },
            'app' => function (CI $c) {
                return new App($c);
            },
            'callableResolver' => function (CI $c) {
                return new \Bnf\Slim3Psr15\CallableResolver($c);
            },
            'db' => function (CI $c) {
                $settings = $c->get('settings')['db'];
                $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8', $settings['host'], $settings['name']);

                return new \Slim\PDO\Database($dsn, $settings['user'], $settings['pass']);
            },
            'log' => function (CI $c) {
                $settings = $c->get('settings')['log'];
                $logger = new \Monolog\Logger($settings['name']);
                $logger->pushProcessor(new \Monolog\Processor\UidProcessor());
                $logger->pushHandler(new \Monolog\Handler\StreamHandler($settings['path'], $settings['level']));
                return $logger;
            },
            'guard' => function (CI $c) {
                return new \Qbus\SlackApp\Middleware\SlackGuard;
            },
            'notFoundHandler' => function (CI $c) {
                return new Handlers\NotFound;
            },
            Controller\Oauth::class => function (CI $c) {
                return new Controller\Oauth($c->get('db'));
            },
        ];

        $defaultServices = new \ArrayObject;
        (new \Slim\DefaultServicesProvider)->register($defaultServices);

        foreach ($defaultServices as $service => $callable) {
            if (!isset($services[$service])) {
                $services[$service] = $callable;
            }
        }

        return $services;
    }

    public function getExtensions()
    {
        return [
            /* This is just to demonstrate the 'extensions' function,
             * we could do the same in the app factory. */
            'app' => function (CI $c, App $app) {
                $this->addMiddleware($app);
                $this->addRoutes($app);

                return $app;
            },
        ];
    }

    protected function addMiddleware(App $app)
    {
    }

    protected function addRoutes(App $app)
    {
        $app->post(
            '/command',
            function (Request $request, Response $response, array $args) {

                file_put_contents('../logs/command-' . date('Y-m-d_his'), (string) $request->getBody());

                $body = $request->getParsedBody();
                //
                //$dump = print_r($body, true);
                $text = $body['text'] ?? 'no-text';

                $res = new \stdClass;
                $res->response_type = 'in_channel';
                $res->text = 'Some Answer';
                $res->attachments = [
                    0 => (new \stdClass),
                ];
                $res->attachments[0]->text = 'attachment, text was: ' . $text;

                $response = $response->withHeader('Content-type', 'application/json');
                $response->getBody()->write(json_encode($res));

                return $response;
            }
        )->add('guard')->setName('slack-command');
        $app->post(
            '/event',
            function (Request $request, Response $response, array $args) {
                $body = (string) $request->getBody();
                $data = json_decode($body);

                file_put_contents('../logs/event-' . date('Y-m-d_his'), $body);

                if (($data->type ?? '') === 'url_verification') {
                    $res = new \stdClass;
                    $res->challenge = $data->challenge ?? '';

                    $token = $data->token ?? '';
                    file_put_contents('../token', $token);

                    $response = $response->withHeader('Content-type', 'application/json');
                    $response->getBody()->write(json_encode($res));
                }

                return $response;
            }
        )->add('guard')->setName('slack-event');
        $app->post(
            '/interaction',
            function (Request $request, Response $response, array $args) {
                $body = (string) $request->getBody();
                $data = json_decode($body);

                file_put_contents('../logs/interaction-' . date('Y-m-d_his'), $body);

                return $response;
            }
        )->add('guard')->setName('slack-event');


        $app->get('/install', Controller\Oauth::class . ':start')->setName('oauth-start');
        $app->get('/oauth/callback', Controller\Oauth::class . ':callback')->setName('oauth-callback');

        $app->get(
            '/',
            function (Request $request, Response $response) {
                $response->getBody()->write(file_get_contents(__DIR__ . '/../templates/index.html'));
                return $response;
            }
        );
    }
}

<?php
declare(strict_types = 1);
namespace Qbus\SlackApp\Handler;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Qbus\SlackApp\Event\EventHandlerInterface;
use Qbus\SlackApp\Http\JsonResponse;
use Zend\Diactoros\Response;

/**
 * Event
 *
 * @author Benjamin Franzke <bfr@qbus.de>
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class Event implements RequestHandlerInterface
{
    /** @var ContainerInterface */
    private $container;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(ContainerInterface $container, LoggerInterface $logger)
    {
        $this->container = $container;
        $this->logger = $logger;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $body = (string) $request->getBody();
        file_put_contents(__DIR__ . '/../../logs/event-' . date('Y-m-d_His'), $body);

        $data = json_decode($body);

        $type = $data->type ?? '';
        $this->logger->info('Recevied event: ' . $type, ['data' => $data]);

        if ($type === 'url_verification') {
            $res = new \stdClass;
            $res->challenge = $data->challenge ?? '';

            $token = $data->token ?? '';
            $this->logger->notice('Recieved new url_verification', ['data' => $data]);
            // @todo: this token is deprecated and it seems we never need this
            // (was intended to be used for verification of the origin, but our SlackGuard actually does that)
            // see https://api.slack.com/events-api#request_url_configuration__amp__verification
            file_put_contents(__DIR__ . '/../../token', $token);
            return new JsonResponse($res);
        }

        if ($type === 'event_callback') {
            return $this->eventCallback($data);
        }

        return new Response('php://memory', 400);
    }

    private function eventCallback(\stdClass $data): ResponseInterface
    {
        $event = $data->event ?? null;
        $event_type = $event->type ?? '';

        $this->logger->debug('Received event:' . $event_type);

        $service = 'slack.event:' . $event_type;
        try {
            $handler = $this->container->get($service);
        } catch (NotFoundExceptionInterface $e) {
            // @todo log invalid request
            return new Response('php://memory', 400);
        }

        if ($handler instanceof EventHandlerInterface) {
            $handler->handle($data);
        } else {
            // @todo log internal error
        }

        return new Response;
    }
}

<?php
declare(strict_types = 1);
namespace Qbus\SlackApp\Handler;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Qbus\SlackApp\Http\JsonResponse;
use Slim\Http\Response;

/**
 * Event
 *
 * @author Benjamin Franzke <bfr@qbus.de>
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class Event implements RequestHandlerInterface
{
    /** @var LoggerInterface */
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
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
            file_put_contents(__DIR__ . '/../../token', $token);
            return new JsonResponse($res);
        }

        return new Response;
    }
}

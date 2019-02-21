<?php
declare(strict_types = 1);
namespace Qbus\SlackApp\Handler;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Qbus\SlackApp\Http\JsonResponse;
use Slim\Http\Response;
use Slim\Http\Headers;

/**
 * Event
 *
 * @author Benjamin Franzke <bfr@qbus.de>
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class Event implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $body = (string) $request->getBody();
        $data = json_decode($body);

        file_put_contents('../logs/event-' . date('Y-m-d_his'), $body);

        $res = new \stdClass;

        if (($data->type ?? '') === 'url_verification') {
            $res->challenge = $data->challenge ?? '';

            $token = $data->token ?? '';
            file_put_contents('../token', $token);
        }

        return new JsonResponse($res);
    }
}

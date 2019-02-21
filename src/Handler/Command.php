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
 * Command
 *
 * @author Benjamin Franzke <bfr@qbus.de>
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class Command implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        file_put_contents(__DIR__ . '/../../logs/command-' . date('Y-m-d_His'), (string) $request->getBody());

        $body = $request->getParsedBody();

        $command = $body['command'] ?? null;
        if ($command !== '/qbus') {
            return new JsonResponse([], 400);
        }

        //$dump = print_r($body, true);
        $text = $body['text'] ?? 'no-text';

        $res = new \stdClass;
        $res->response_type = 'in_channel';
        $res->text = 'Some Answer';
        $res->attachments = [
            0 => (new \stdClass),
        ];
        $res->attachments[0]->text = 'attachment, text was: ' . $text;

        return new JsonResponse($res);
    }
}

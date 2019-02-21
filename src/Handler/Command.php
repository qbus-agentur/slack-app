<?php
declare(strict_types = 1);
namespace Qbus\SlackApp\Handler;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
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
    public function handle(ServerRequestInterface $request): ResponseInterface {
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

        $response = new Response(200, new Headers(['Content-type' => 'application/json']);
        $response->getBody()->write(json_encode($res));

        return $response;
    }
}

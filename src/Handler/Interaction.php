<?php
declare(strict_types = 1);
namespace Qbus\SlackApp\Handler;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\Diactoros\Response;

/**
 * Interaction
 *
 * @author Benjamin Franzke <bfr@qbus.de>
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class Interaction implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $body = (string) $request->getBody();

        file_put_contents(__DIR__ . '/../../logs/interaction-' . date('Y-m-d_His'), $body);
        //$data = json_decode($body);

        return new Response;
    }
}

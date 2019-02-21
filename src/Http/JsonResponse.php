<?php
declare(strict_types = 1);
namespace Qbus\SlackApp\Http;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Http\Body;
use Slim\Http\Headers;
use Slim\Http\Response;

/**
 * JsonResponse
 *
 * @author Benjamin Franzke <bfr@qbus.de>
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class JsonResponse extends Response
{
    /**
     * @var int
     */
    const DEFAULT_JSON_FLAGS = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;

    /**
     * @param array|object $data
     * @param int $status
     * @param array $headers
     * @param int $flags
     */
    public function __construct($data, int $status = 200, array $headers = [], int $flags = self::DEFAULT_JSON_FLAGS)
    {
        $json = json_encode($data, $flags);
        if ($json === false) {
            throw new \InvalidArgumentException('Unable to encode data to JSON: ' . json_last_error_msg());
        }

        $resource = fopen('php://temp', 'wb+');
        if ($resource === false) {
            throw new \RuntimeException('Failed to create temporary resource');
        }
        $body = new Body($resource);
        $body->write($json);

        $headers = new Headers($headers + ['Content-Type' => 'application/json; charset=utf-8']);

        parent::__construct($status, $headers, $body);
    }
}

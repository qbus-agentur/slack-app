<?php
declare(strict_types = 1);
namespace Qbus\SlackApp\Handler\Generic;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Http\Response;
use Slim\Http\Body;

/**
 * Serve contents of a file
 *
 * @author Benjamin Franzke <bfr@qbus.de>
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class FileContents implements RequestHandlerInterface
{
    /** @var string */
    protected $filename;

    public function __construct(string $filename)
    {
        $this->filename = $filename;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $file = fopen($this->filename, 'r');
        if ($file === false) {
            throw new \Exception('Failed to open ' . $this->filename . '.');
        }

        return new Response(200, null, new Body($file));
    }
}

<?php
declare(strict_types = 1);
namespace Qbus\SlackApp\Handler\Generic;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\Diactoros\Response;
use Zend\Diactoros\StreamFactory;

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
        $body = (new StreamFactory)->createStreamFromFile($this->filename);
        return new Response($body);
    }
}

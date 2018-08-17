<?php
namespace Qbus\QAC\Handlers;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Not found handler.
 *
 * It outputs a simple message in either JSON, XML or HTML based on the
 * Accept header.
 *
 * @author Benjamin Franzke <bfr@qbus.de>
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class NotFound extends \Slim\Handlers\NotFound
{

    /**
     * Return a response for text/html content not found
     *
     * @param  ServerRequestInterface $request  The most recent Request object
     *
     * @return ResponseInterface
     */
    protected function renderHtmlNotFoundOutput(ServerRequestInterface $request)
    {
        $homeUrl = (string)($request->getUri()->withPath('')->withQuery('')->withFragment(''));
        return <<<END
<!DOCTYPE html>
<html>
<title>Page Not Found</title>
<style>body{margin:0;padding:0 1.6em;font:1em/1.5 Helvetica,Arial,Verdana,sans-serif;}</style>
<h1>Page Not Found</h1>
<p>
The page you are looking for could not be found. Check the address bar
to ensure your URL is spelled correctly. If all else fails, you can
visit our home page at the link below.
</p>
<a href='$homeUrl'>Home Page</a>
END;
    }
}

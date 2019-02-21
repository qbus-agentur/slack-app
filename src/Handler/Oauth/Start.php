<?php
declare(strict_types = 1);
namespace Qbus\SlackApp\Handler\Oauth;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Http\Response;
use Slim\Http\Headers;

/**
 * Start oauth procedure
 *
 * @author Benjamin Franzke <bfr@qbus.de>
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class Start implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $random = openssl_random_pseudo_bytes(1024);
        if ($random === false) {
            throw new \Exception('openssl can not generate random bytes');
        }
        // Create a state token to prevent request forgery.
        // Store it in the session for later validation.
        $state = sha1($random);
        $_SESSION['state_token'] = $state;

        $url = sprintf(
            '%s/oauth/authorize?client_id=%s&scope=%s&state=%s',
            getenv('SLACK_ROOT_URL') ?: 'https://slack.com',
            getenv('SLACK_CLIENT_ID'),
            'links:read,links:write,commands,chat:write,team:read,channels:history,groups:history,im:history',
            $state
        );

        return new Response(302, new Headers(['Location' => $url]));
    }
}

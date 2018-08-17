<?php
namespace Qbus\SlackApp\Middleware;

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

/**
 * @author Benjamin Franzke <bfr@qbus.de>
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class SlackGuard implements MiddlewareInterface
{
    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $signingSecret = getenv('SLACK_SIGNING_SECRET');

        $timestamp = intval($request->getHeaderLine('X-Slack-Request-Timestamp'));
        $slack_signature = $request->getHeaderLine('X-Slack-Signature');

        if (abs(time() - $timestamp) > (60 * 5)) {
            // The request timestamp is more than five minutes from local time.
            // It could be a replay attack, so let's ignore it.
            throw new \Exception('Invalid request, timing attack');
        }

        $body = (string) $request->getBody();
        $sig_basestring = implode(':', ['v0', $timestamp, $body]);
        $computed_signature = 'v0=' . hash_hmac('sha256', $sig_basestring, $signingSecret, false);
        file_put_contents('../logs/sign-' . date('Y-m-d_his'), $timestamp . "\n" . $slack_signature . "\n" . $computed_signature);
        if (!hash_equals($slack_signature, $computed_signature)) {
            throw new \Exception('Invalid request, slack signature is wrong');
        }
        return $handler->handle($request);
    }
}

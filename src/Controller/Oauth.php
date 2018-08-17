<?php
namespace Qbus\SlackApp\Controller;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

/**
 * Oauth
 *
 * @author Benjamin Franzke <bfr@qbus.de>
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class Oauth extends AbstractController
{
    public function start(Request $request, Response $response) {
        $body = (string) $request->getBody();
        $data = json_decode($body);

        // Create a state token to prevent request forgery.
        // Store it in the session for later validation.
        $state = sha1(openssl_random_pseudo_bytes(1024));
        $_SESSION['state_token'] = $state;

        $url = sprintf(
            '%s/oauth/authorize?client_id=%s&scope=%s&state=%s',
            getenv('SLACK_ROOT_URL') ?: 'https://slack.com',
            getenv('SLACK_CLIENT_ID'),
            'links:read,links:write,commands,chat:write,team:read,channels:history,groups:history,im:history',
            $state
        );

        return $response->withStatus(302)->withHeader('Location', $url);
    }

    public function callback(Request $request, Response $response) {
        $params = $request->getQueryParams();
        $code = $params['code'] ?? '';
        file_put_contents('../logs/oauth-callback-' . date('Y-m-d_his'), json_encode($params));

        $state = $params['state'] ?? '';
        if ($_SESSION['state_token'] !== $state) {
            $response->getBody()->write('Invalid state parameter');
            return $response->withStatus(401);
        }

        $api_url = sprintf(
            '%s/api/oauth.access',
            getenv('SLACK_ROOT_URL') ?: 'https://slack.com'
        );
        $client = new \GuzzleHttp\Client();

        $res = $client->request(
            'POST',
            $api_url,
            [
                'auth' => [getenv('SLACK_CLIENT_ID'), getenv('SLACK_CLIENT_SECRET')],
                'form_params' => [
                    'code' => $code,
                ],
            ]
        );

        if ($res->getStatusCode() === 200) {
            $data = json_decode((string) $res->getBody(), true);
            if (($data['ok'] ?? false) === false) {
                throw new \Exception('oauth error: ' . $data['error'] ?? '');
            }
            $pdo = $this->get('db');

            $workspace = [
                'team_id' => $data['team_id'],
                'team_name' => $data['team_name'],
                'access_token' => $data['access_token'],
                'authorizing_user_id' => $data['authorizing_user']['user_id'],
                'app_home' => $data['authorizing_user']['app_home'],
            ];

            $team_id = $workspace['team_id'];
            // Update existing workspace association or create a new one.
            $current = $pdo->select(['id'])->from('workspaces')->where('team_id', '=', $team_id)->execute()->fetch();
            if ($current) {
                $this->get('db')->update($workspace)->table('workspaces')->where('team_id', '=', $team_id)->execute();
            } else {
                $this->get('db')->insert(array_keys($workspace))->into('workspaces')->values(array_values($workspace))->execute();
            }
        } else {
            $response->getBody()->write('Failed.');
            return $response;
        }

        $url = sprintf(
            '%s/app_redirect?app=%s&team=%s',
            getenv('SLACK_ROOT_URL') ?: 'https://slack.com',
            getenv('SLACK_APP_ID'),
            $team_id
        );

        return $response->withStatus(302)->withHeader('Location', $url);
    }
}

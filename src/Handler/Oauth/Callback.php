<?php
declare(strict_types = 1);
namespace Qbus\SlackApp\Handler\Oauth;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Http\Response;
use Slim\Http\Headers;
use Slim\PDO\Database;

/**
 * Handle oauth callback from slack
 *
 * @author Benjamin Franzke <bfr@qbus.de>
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class Callback implements RequestHandlerInterface
{
    /** @var Database */
    protected $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams();
        $code = $params['code'] ?? '';
        file_put_contents('../logs/oauth-callback-' . date('Y-m-d_his'), json_encode($params));

        $state = $params['state'] ?? '';
        if ($_SESSION['state_token'] !== $state) {
            $response = new Response(401);
            $response->getBody()->write('Invalid state parameter');
            return $response;
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
                switch ($data['error'] ?? null) {
                    case 'code_already_used':
                    case 'invalid_copde':
                        //return new Response(404);
                        break;
                }
                throw new \Exception('oauth error: ' . $data['error'] ?? '');
            }

            $workspace = [
                'team_id' => $data['team_id'],
                'team_name' => $data['team_name'],
                'access_token' => $data['access_token'],
                'authorizing_user_id' => $data['authorizing_user']['user_id'],
                'app_home' => $data['authorizing_user']['app_home'],
            ];

            $team_id = $workspace['team_id'];
            // Update existing workspace association or create a new one.
            $current = $this->db
                ->select(['id'])
                ->from('workspaces')
                ->where('team_id', '=', $team_id)
                ->execute()
                ->fetch();
            if ($current !== false) {
                $this->db->update($workspace)->table('workspaces')->where('team_id', '=', $team_id)->execute();
            } else {
                $this->db
                    ->insert(array_keys($workspace))
                    ->into('workspaces')
                    ->values(array_values($workspace))
                    ->execute();
            }
        } else {
            $response = new Response(400);
            $response->getBody()->write('Failed.');
            return $response;
        }

        $url = sprintf(
            '%s/app_redirect?app=%s&team=%s',
            getenv('SLACK_ROOT_URL') ?: 'https://slack.com',
            getenv('SLACK_APP_ID'),
            $team_id
        );

        return new Response(302, new Headers(['Location' => $url]));
    }
}

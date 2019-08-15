<?php
declare(strict_types = 1);
namespace Qbus\SlackApp\Handler\Oauth;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Qbus\SlackApp\Config\Slack;
use Slim\PDO\Database;
use Zend\Diactoros\Response;

/**
 * Handle oauth callback from slack
 *
 * @author Benjamin Franzke <bfr@qbus.de>
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class Callback implements RequestHandlerInterface
{
    /** @var RequestFactoryInterface */
    private $requestFactory;

    /** @var ClientInterface */
    private $client;

    /** @var Database */
    private $db;

    /** @var Slack */
    private $slack;

    public function __construct(
        RequestFactoryInterface $requestFactory,
        ClientInterface $client,
        Database $db,
        Slack $slack
    ) {
        $this->requestFactory = $requestFactory;
        $this->client = $client;
        $this->db = $db;
        $this->slack = $slack;
    }

    private function createAccessRequest(string $code): RequestInterface
    {
        $api_url = sprintf(
            '%s/api/oauth.access',
            $this->slack->rootUrl()
        );
        $clientId = $this->slack->clientId();
        $clientSecret = $this->slack->clientSecret();

        $postData = ['code' => $code];
        $request = $this->requestFactory->createRequest('POST', $api_url)
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withHeader('Authorization', 'Basic ' . base64_encode($clientId . ':' . $clientSecret));
        $request->getBody()->write(http_build_query($postData, '', '&', PHP_QUERY_RFC1738));

        return $request;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams();
        $code = $params['code'] ?? '';
        file_put_contents(__DIR__ . '/../../../logs/oauth-callback-' . date('Y-m-d_His'), json_encode($params));

        $state = $params['state'] ?? '';
        if ($_SESSION['state_token'] !== $state) {
            $response = new Response('php://memory', 401);
            $response->getBody()->write('Invalid state parameter');
            return $response;
        }

        $res = $this->client->sendRequest($this->createAccessRequest($code));

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
            $response = new Response('php://memory', 400);
            $response->getBody()->write('Failed.');
            return $response;
        }

        $url = sprintf(
            '%s/app_redirect?app=%s&team=%s',
            $this->slack->rootUrl(),
            $this->slack->appId(),
            $team_id
        );

        return new Response('php://memory', 302, ['Location' => $url]);
    }
}

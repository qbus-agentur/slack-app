<?php
declare(strict_types = 1);
namespace Qbus\SlackApp\Service\Client;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Log\LoggerInterface;
use Slim\PDO\Database;

/**
 * LinkShared
 *
 * @author Benjamin Franzke <bfr@qbus.de>
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class Slack
{
    /** @var Database */
    private $db;

    /** @var RequestFactoryInterface */
    private $requestFactory;

    /** @var ClientInterface */
    private $client;

    /** @var LoggerInterface */
    private $logger;

    /** @var string */
    private $slackRootUrl;

    public function __construct(
        Database $db,
        RequestFactoryInterface $requestFactory,
        ClientInterface $client,
        LoggerInterface $logger,
        string $slackRootUrl = 'https://slack.com'
    ) {
        $this->db = $db;
        $this->requestFactory = $requestFactory;
        $this->client = $client;
        $this->logger = $logger;
        $this->slackRootUrl = $slackRootUrl;
    }

    private function createRequest(string $api_url, string $token, object $payload): RequestInterface
    {
        $request = $this->requestFactory->createRequest('POST', $api_url);
        $request = $request
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $token);

        $body = json_encode($payload);
        if ($body === false) {
            throw new \InvalidArgumentException('Payload can not be converted to JSON.');
        }
        $request->getBody()->write($body);

        return $request;
    }

    public function req(string $team, string $method, object $payload): array
    {
        $token = $this->getAccessToken($team);
        if ($token === null) {
            // log non available workspace
            return [];
        }

        $api_url = sprintf(
            '%s/api/%s',
            $this->slackRootUrl,
            $method
        );

        $this->logger->debug('client: posting to slack', [
            'POST',
            $api_url,
            $token,
            $payload,
        ]);

        $request = $this->createRequest($api_url, $token, $payload);
        $res = $this->client->sendRequest($request);

        if ($res->getStatusCode() === 200) {
            $data = json_decode((string) $res->getBody(), true);

            if (($data['ok'] ?? false) === false) {
                // @todo optimize
                error_log('unfurl error: ' . (string) $res->getBody());
            }
            return $data;
        }

        //$this->logger->alert('Slack request failed');
        throw new \RuntimeException('Slack request failed');
    }

    private function getAccessToken(string $team): ?string
    {
        $workspace = $this->db
            ->select(['access_token'])
            ->from('workspaces')
            ->where('team_id', '=', $team)
            ->execute()
            ->fetch();
        $this->logger->debug('Workspace for team:' . $team, ['workspace' => $workspace]);

        if ($workspace === false) {
            return null;
        }

        return $workspace['access_token'] ?? null;
    }
}

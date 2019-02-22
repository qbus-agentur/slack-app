<?php
declare(strict_types = 1);
namespace Qbus\SlackApp\Service\Client;

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

    /** @var LoggerInterface */
    private $logger;

    public function __construct(Database $db, LoggerInterface $logger)
    {
        $this->db = $db;
        $this->logger = $logger;
    }

    public function req(string $team, string $method, array $payload): array
    {
        $token = $this->getAccessToken($team);
        if ($token === null) {
            // log non available workspace
            return [];
        }

        $api_url = sprintf(
            '%s/api/%s',
            getenv('SLACK_ROOT_URL') ?: 'https://slack.com',
            $method
        );

        $payload['headers']['Authorization'] = 'Bearer ' . $token;

        $this->logger->debug('client: posting to slack', [
            'POST',
            $api_url,
            $payload
        ]);

        $client = new \GuzzleHttp\Client();
        $res = $client->request('POST', $api_url, $payload);
        if ($res->getStatusCode() === 200) {
            $data = json_decode((string) $res->getBody(), true);
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

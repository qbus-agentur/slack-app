<?php
declare(strict_types = 1);
namespace Qbus\SlackApp\Event;

use DateTime;
use League\HTMLToMarkdown\HtmlConverter;
use Psr\Log\LoggerInterface;
use Slim\PDO\Database;
use stdClass;
use Qbus\SlackApp\Service\Client\Slack;

/**
 * LinkShared
 *
 * @author Benjamin Franzke <bfr@qbus.de>
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class LinkShared implements EventHandlerInterface
{
    /** @var Slack */
    private $slack;

    /** @var Database */
    private $acdb;

    /** @var LoggerInterface */
    private $logger;

    /** @var string */
    private $activeCollabUrl;

    /** @var string */
    private $rootUrl;

    /** @var string|null */
    private $activeCollabHost;

    public function __construct(
        Slack $slack,
        Database $acdb,
        LoggerInterface $logger,
        string $activeCollabUrl,
        string $rootUrl
    ) {
        $this->slack = $slack;
        $this->acdb = $acdb;
        $this->logger = $logger;

        $this->rootUrl = $rootUrl;
        $this->activeCollabUrl = $activeCollabUrl;
        $this->activeCollabHost = parse_url($activeCollabUrl, PHP_URL_HOST);
    }

    public function handle(\stdClass $payload): void
    {
        $team = $payload->team_id ?? '';

        if (!isset($payload->event)) {
            return;
        }
        $event = $payload->event;

        $channel = $event->channel ?? '';
        $timestamp = $event->message_ts ?? '';

        $message = new stdClass;
        $message->channel = $channel;
        $message->ts = $timestamp;
        $message->unfurls = [];

        $this->logger->debug('Received link_shared', ['payload' => $payload, 'team' => $team]);

        foreach ($event->links ?? [] as $link) {
            if (!isset($link->url)) {
                continue;
            }
            $url = $link->url;

            $preview = $this->previewLink($url);
            if (is_array($preview)) {
                $message->unfurls[$url] = $preview;
            }
        }

        $count = count($message->unfurls);
        if ($count > 0) {
            if ($count > 1) {
                foreach ($message->unfurls as $url => $preview) {
                    unset($message->unfurls[$url]['text']);
                    unset($message->unfurls[$url]['fields']);
                }
            }

            $this->slack->req($team, 'chat.unfurl', $message);
        }
    }

    private function previewLink(string $url): ?array
    {
        $parsed = $this->parseLink($url);
        if ($parsed === null) {
            return null;
        }
        $slug = $parsed['project'];
        $task = $parsed['task'];

        $res = $this->findProject($slug, $task);

        return $this->createUnfurlMessage(
            $url,
            $res['name'] ?? '',
            $res['body'] ?? '',
            $res['assignee'] ?? '',
            $res['creator'] ?? '',
            $slug,
            $res['project'] ?? '',
            ($res['timelimit'] ?? '') === '' ? null : new DateTime($res['timelimit'])
        );
    }

    private function parseLink(string $url): ?array
    {
        $parsed = parse_url($url);
        if ($parsed === false) {
            return null;
        }

        if (($parsed['host'] ?? '') !== $this->activeCollabHost) {
            return null;
        }

        $query = $parsed['query'] ?? '';

        $arguments = [];
        parse_str($query, $arguments);

        $path_info = $arguments['path_info'] ?? '';
        if ($path_info === '') {
            return null;
        }

        $paths = explode('/', $path_info);

        if ($paths[0] !== 'projects' || !isset($paths[1]) || !isset($paths[2]) || $paths[2] !== 'tasks') {
            return null;
        }

        $project = $paths[1];
        $task = (int) $paths[3];

        if ($task === 0) {
            return null;
        }

        return [
            'project' => $project,
            'task' => $task
        ];
    }

    private function findProject(string $slug, int $task): ?array
    {
        $st = $this->acdb
            ->select([
                'project_objects.name AS name',
                'project_objects.body AS body',
                'project_objects.created_by_name as creator',
                'project_objects.due_on AS timelimit',
                'users.first_name AS assignee',
                'projects.name AS project',
            ])
            ->from('project_objects')
            ->join('projects', 'project_objects.project_id', '=', 'projects.id')
            ->leftJoin('users', 'project_objects.assignee_id', '=', 'users.id')
            ->where('projects.slug', '=', $slug)
            ->where('project_objects.integer_field_1', '=', $task)
            ->where('project_objects.type', '=', 'Task');
        $stmt = $st->execute();
        $res = $stmt->fetch();

        if (!is_array($res) || $res['name'] === '' || $res['project'] === '') {
            return null;
        }

        return $res;
    }

    private function createUnfurlMessage(
        string $url,
        string $name,
        string $body,
        string $assignee,
        string $creator,
        string $slug,
        string $project,
        ?DateTime $timelimit
    ): array {
        $converter = new HtmlConverter();
        $converter->getConfig()->setOption('strip_tags', true);
        $converter->getConfig()->setOption('italic_style', '_');
        $converter->getConfig()->setOption('bold_style', '*');

        $markdown = $converter->convert($body);

        $fields = [];
        if ($assignee !== '') {
            $fields[] = [
                'title' => 'Verantwortlich',
                'value' => $assignee,
                'short' => true
            ];
        }

        if ($timelimit !== null) {
            $fields[] = [
                'title' => 'Zeitlimit',
                'value' => sprintf(
                    '<!date^%s^{date_short_pretty}|%s>',
                    $timelimit->format('U'),
                    $timelimit->format('d.m.Y')
                ),
                'short' => true
            ];
        }

        return [
            'title' => $this->escape($name),
            'title_link' => $this->escape($url),
            'author_name' => $this->escape($creator),
            'footer' => sprintf(
                '<%s|%s>',
                $this->escape($this->activeCollabUrl . 'projects/' . $slug),
                $this->escape($project)
            ),
            'footer_icon' => $this->rootUrl . 'active-collab_light.png',
            'mrkdwn' => true,
            'text' => $markdown,
            'fields' => $fields,
        ];
    }

    private function escape(string $str): string
    {
        return str_replace(
            ['&', '<', '>'],
            ['&amp;', '&lt;', '&gt;'],
            $str
        );
    }
}

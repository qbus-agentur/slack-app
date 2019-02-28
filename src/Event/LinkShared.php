<?php
declare(strict_types = 1);
namespace Qbus\SlackApp\Event;

use League\HTMLToMarkdown\HtmlConverter;
use Psr\Log\LoggerInterface;
use Slim\PDO\Database;
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

    public function __construct(Slack $slack, Database $acdb, LoggerInterface $logger)
    {
        $this->slack = $slack;
        $this->acdb = $acdb;
        $this->logger = $logger;
    }

    public function handle(\stdClass $payload): void
    {
        // @todo compare token
        //$token = $payload->token ?? '';
        $team = $payload->team_id ?? '';

        $event = $payload->event ?? null;
        if ($event === null) {
            return;
        }

        $channel = $event->channel ?? '';
        $timestamp = $event->message_ts ?? '';

        $message = new \stdClass;
        $message->channel = $channel;
        $message->ts = $timestamp;
        $message->unfurls = [];

        $this->logger->debug('Received link_shared', ['payload' => $payload, 'team' => $team]);

        foreach ($event->links ?? [] as $link) {
            $url = $link->url ?? null;
            if ($url === null) {
                continue;
            }

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

            $this->slack->req($team, 'chat.unfurl', ['json' => (array) $message]);
        }
    }

    private function previewLink(string $url): ?array
    {
        $parsed = parse_url($url);
        if ($parsed === false) {
            return null;
        }

        $own_host = parse_url(getenv('ACTIVECOLLAB_URL') ?: '', PHP_URL_HOST);
        if ($own_host === null) {
            return null;
        }

        if (($parsed['host'] ?? '') !== $own_host) {
            return null;
        }

        $query = $parsed['query'] ?? '';

        $arguments = '';
        parse_str($query, $arguments);

        $path_info = $arguments['path_info'] ?? '';
        if ($path_info === '') {
            return null;
        }

        $paths = explode('/', $path_info);

        if ($paths[0] !== 'projects') {
            return null;
        }

        $project = $paths[1] ?? '';
        $task = null;
        if (($paths[2] ?? '') === 'tasks' && isset($paths[3])) {
            $task = (int) $paths[3];
        }

        if ($task === null || $task === 0) {
            return null;
        }

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
            ->where('projects.slug', '=', $project)
            ->where('project_objects.integer_field_1', '=', $task)
            ->where('project_objects.type', '=', 'Task');
        $stmt = $st->execute();
        $res = $stmt->fetch();

        if (!is_array($res) || $res['name'] === '' || $res['project'] === '') {
            return null;
        }

        $converter = new HtmlConverter();
        $converter->getConfig()->setOption('strip_tags', true);
        $converter->getConfig()->setOption('italic_style', '_');
        $converter->getConfig()->setOption('bold_style', '*');

        $markdown = $converter->convert($res['body'] ?? '');

        $fields = [];
        $assignee = $res['assignee'] ?? '';
        if ($assignee !== '') {
            $fields[] = [
                'title' => 'Verantwortlich',
                'value' => $assignee,
                'short' => true
            ];
        }

        $timelimit = $res['timelimit'] ?? '';
        if ($timelimit !== '') {
            $fields[] = [
                'title' => 'Zeitlimit',
                'value' => sprintf(
                    '<!date^%s^{date_short_pretty}|%s>',
                    (new \DateTime($timelimit))->format('U'),
                    $timelimit
                ),
                'short' => true
            ];
        }

        return [
            'title' => $this->escape($res['name']),
            'title_link' => $this->escape($url),
            'author_name' => $this->escape($res['creator']),
            'footer' => sprintf(
                '<%s|%s>',
                $this->escape(getenv('ACTIVECOLLAB_URL') . 'projects/' . $project),
                $this->escape($res['project'])
            ),
            'footer_icon' => getenv('ROOT_URL') . 'active-collab_light.png',
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

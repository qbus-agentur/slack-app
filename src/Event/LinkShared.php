<?php
declare(strict_types = 1);
namespace Qbus\SlackApp\Event;

use Psr\Log\LoggerInterface;
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

    /** @var LoggerInterface */
    private $logger;

    public function __construct(Slack $slack, LoggerInterface $logger)
    {
        $this->slack = $slack;
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

        if (count($message->unfurls) > 0) {
            $this->slack->req($team, 'chat.unfurl', ['json' => (array) $message]);
        }
    }

    private function previewLink(string $url): ?array
    {
        $parsed = parse_url($url);
        if ($parsed === false) {
            return null;
        }

        $path = $parsed['path'] ?? '';

        return [
            'text' => 'First unfurl test' . $path,
        ];
    }
}

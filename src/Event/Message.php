<?php
declare(strict_types = 1);
namespace Qbus\SlackApp\Event;

use Psr\Log\LoggerInterface;
use Qbus\SlackApp\Service\Client\Slack;

/**
 * Message
 *
 * @author Benjamin Franzke <bfr@qbus.de>
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class Message implements EventHandlerInterface
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
        //
        $team = $payload->team_id ?? '';

        $event = $payload->event ?? null;
        if ($event === null) {
            return;
        }

        $channel = $event->channel ?? '';
        $timestamp = $event->message_ts ?? '';

        $request = new \stdClass;

        $this->logger->debug('Received message', ['payload' => $payload, 'team' => $team]);
    }
}

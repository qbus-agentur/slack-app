<?php
declare(strict_types = 1);
namespace Qbus\SlackApp\Event;

/**
 * EventHandlerInterface
 *
 * @author Benjamin Franzke <bfr@qbus.de>
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
interface EventHandlerInterface
{
    public function handle(\stdClass $payload): void;
}

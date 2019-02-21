<?php
declare(strict_types = 1);
namespace Qbus\SlackApp;

use Slim\Router as SlimRouter;

/**
 * Not found handler.
 *
 * It outputs a simple message in either JSON, XML or HTML based on the
 * Accept header.
 *
 * @author Benjamin Franzke <bfr@qbus.de>
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class Router extends SlimRouter
{
    public function createCaches(): void
    {
        $this->createDispatcher();
    }
}

<?php
declare(strict_types = 1);
namespace Qbus\SlackApp;

use Slim\Router as SlimRouter;

/**
 * Router overwrite to add a createCaches method
 *
 * @author Benjamin Franzke <bfr@qbus.de>
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class Router extends SlimRouter
{
    public function warmupCaches(): void
    {
        if (is_string($this->cacheFile)) {
            if (file_exists($this->cacheFile)) {
                unlink($this->cacheFile);
            }
            $this->createDispatcher();
        }
    }
}

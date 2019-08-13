<?php
declare(strict_types = 1);
namespace Qbus\SlackApp;

use Slim\Interfaces\RouteCollectorInterface;
use Slim\Routing\Dispatcher;

/**
 * Route Dispatcher overwrite to add a createCaches method
 *
 * @author Benjamin Franzke <bfr@qbus.de>
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class RouteDispatcher extends Dispatcher
{
    /**
     * @var RouteCollectorInterface
     */
    private $routeCollector;

    /**
     * @param RouteCollectorInterface $routeCollector
     */
    public function __construct(RouteCollectorInterface $routeCollector)
    {
        parent::__construct($routeCollector);
        $this->routeCollector = $routeCollector;
    }

    public function warmupCaches(): void
    {
        $cacheFile = $this->routeCollector->getCacheFile();
        if (is_string($cacheFile)) {
            if (file_exists($cacheFile)) {
                unlink($cacheFile);
            }
            $this->createDispatcher();
        }
    }
}

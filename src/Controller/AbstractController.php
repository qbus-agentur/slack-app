<?php
namespace Qbus\QAC\Controller;

use Psr\Container\ContainerInterface;

/**
 * AbstractController
 *
 * @author Benjamin Franzke <bfr@qbus.de>
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
abstract class AbstractController
{
    /**
     * @var \Slim\PDO\Database
     */
    protected $db;

    /**
     * @return void
     */
    public function __construct(
        \Slim\PDO\Database $db = null
    ) {
        $this->db = $db;
    }
}

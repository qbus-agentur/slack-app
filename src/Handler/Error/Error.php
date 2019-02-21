<?php
declare(strict_types = 1);
namespace Qbus\SlackApp\Handler\Error;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Slim\Handlers\Error as SlimErrorHandler;

/**
 * Error handler.
 *
 * It outputs a simple message in either JSON, XML or HTML based on the
 * Accept header.
 *
 * @author Benjamin Franzke <bfr@qbus.de>
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class Error extends SlimErrorHandler
{
    /** @var LoggerInterface */
    private $logger;

    /**
     * Constructor
     *
     * @param bool $displayErrorDetails Set to true to display full details
     * @param LoggerInterface $logger;
     */
    public function __construct(bool $displayErrorDetails, LoggerInterface $logger)
    {
        $this->logger = $logger;
        parent::__construct($displayErrorDetails);
    }

    /**
     * Write to the error log if displayErrorDetails is false
     *
     * @param \Exception|\Throwable $throwable
     *
     * @return void
     */
    protected function writeToErrorLog($throwable)
    {
        $message = 'Application Error:' . PHP_EOL;
        $message .= $this->renderThrowableAsText($throwable);

        $previous = [];
        while ($throwable = $throwable->getPrevious()) {
            $previous[] = PHP_EOL . 'Previous error:' . PHP_EOL . $this->renderThrowableAsText($throwable);
        }

        $this->logger->error($message, $previous);
    }
}

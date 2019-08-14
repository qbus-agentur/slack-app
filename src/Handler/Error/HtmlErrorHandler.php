<?php
declare(strict_types = 1);
namespace Qbus\SlackApp\Handler\Error;

use Slim\Error\Renderers\HtmlErrorRenderer;
use Slim\Exception\HttpException;
use Throwable;

class HtmlErrorHandler extends HtmlErrorRenderer
{

   /**
     * @param Throwable $exception
     * @param bool      $displayErrorDetails
     * @return string
     */
    public function __invoke(Throwable $exception, bool $displayErrorDetails): string
    {
        $title = 'Slim Application Error';

        if ($exception instanceof HttpException) {
            $title = $exception->getMessage();
            $html = '';
            return $this->renderHtmlBody($title, $html);
        }

        return parent::__invoke($exception, $displayErrorDetails);
    }

    /**
     * @param string $title
     * @param string $html
     * @return string
     */
    public function renderHtmlBody(string $title = '', string $html = ''): string
    {
        $format = <<<EOT
<html>
   <head>
       <meta http-equiv='Content-Typeontent='text/html; charset=utf-8'>" .
       <title>%s</title>
       <style>
           body{margin:0;padding:1em;font:1em/1.5 Helvetica,Arial,Verdana,sans-serif}
           h1{margin:0;font-size:3em;font-weight:normal;line-height:1}
           strong{display:inline-block;width:65px}
       </style>
   </head>
   <body>
       <h1>%s</h1>
       <div>%s</div>
   </body>
</html>
EOT;

        return sprintf($format, $title, $title, $html);
    }
}

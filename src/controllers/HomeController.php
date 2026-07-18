<?php

declare(strict_types=1);

namespace orange\framework\controllers;

use orange\framework\controllers\BaseController;

/**
 * Default controller for the "/" route.
 *
 * Ships with a placeholder index() action that renders a simple welcome
 * page. Intended to be replaced by pointing the "/" route at a different
 * controller and method.
 */
class HomeController extends BaseController
{
    public function index(): string
    {
        return '<!DOCTYPE html>
        <html lang="en">
        <head>
          <meta charset="utf-8">
          <title></title>
        </head>
        <body>
         <h3>Welcome!</h3>
         <p>This is the default "HomeController" output.</p>
         <p>You can override this by providing a different controller and method for the / route.</p>
        </body>
        </html>
        ';
    }
}

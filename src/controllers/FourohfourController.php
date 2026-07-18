<?php

declare(strict_types=1);

namespace orange\framework\controllers;

use orange\framework\controllers\BaseController;

/**
 * Default "not found" controller.
 *
 * Used as the fallback controller when routing cannot match a request to a
 * handler. Its index() action simply triggers the framework's 404 response.
 */
class FourohfourController extends BaseController
{
    public function index()
    {
        show404();
    }
}

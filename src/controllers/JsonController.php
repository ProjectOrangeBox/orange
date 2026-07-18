<?php

declare(strict_types=1);

namespace orange\framework\controllers;

use orange\framework\attributes\AttachService;
use orange\framework\controllers\BaseController;
use orange\framework\interfaces\DataInterface;

/**
 * Base controller for JSON / REST style responses.
 *
 * Extends BaseController with a DataInterface service used to hold response
 * data, a status-name to HTTP-response-code map ($restSuccessMap), and
 * restResponse() which sets the response code/content type and JSON-encodes
 * $this->data using $jsonFlags.
 */
abstract class JsonController extends BaseController
{
    #[AttachService('data')]
    protected DataInterface $data;

    // method to responds code
    protected array $restSuccessMap = [
        'ok' => 200,
        'get' => 200,
        'getNew' => 200,
        'getAll' => 200,
        'getById' => 200,
        'read' => 200,
        'create' => 201,
        'post' => 201,
        'update' => 202,
        'put' => 202,
        'patch' => 202,
        'delete' => 202,
        'unknown' => 400,
        'badMethod' => 405,
        'validationFail' => 406,
        'noAuth' => 401,
        'success' => 202,
    ];

    protected int $jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE;

    protected function response(string $status = 'ok'): string
    {
        $this->output->responseCode($this->restSuccessMap[$status] ?? 500)->contentType('json');

        return json_encode($this->data, $this->jsonFlags);
    }
}

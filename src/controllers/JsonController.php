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
 * data and response() which sets the HTTP response code and JSON content
 * type on the output service and returns the response body — either a
 * pre-encoded raw JSON string or $this->data JSON-encoded using $jsonFlags.
 */
abstract class JsonController extends BaseController
{
    #[AttachService('data')]
    protected DataInterface $data;

    protected int $jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE;

    /**
     * Like response() but encodes a plain list so the output is a
     * top-level JSON array ([{...},{...}]) instead of the object an
     * ArrayObject-backed data service always encodes to.
     *
     * @param array $list
     * @param int $status
     * @return string
     */
    protected function listResponse(array $list, int $status = 200): string
    {
        return $this->response($status, json_encode(array_values($list), $this->jsonFlags));
    }

    /**
     * Sets the HTTP response code and JSON content type on the output
     * service and returns the response body: $raw when provided, otherwise
     * $this->data JSON-encoded with $jsonFlags.
     *
     * @param int $status
     * @param null|string $raw pre-encoded JSON response body
     * @return string
     */
    protected function response(int $status = 200, ?string $raw = null): string
    {
        $this->output->responseCode($status)->contentType('json');

        return $raw ?? json_encode($this->data, $this->jsonFlags);
    }
}

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
 *
 * Also provides the common REST reply shapes: errorsResponse() for
 * field-keyed validation failures, notFoundResponse() for missing
 * resources, and noContentResponse() for bodiless 204s.
 */
abstract class JsonController extends BaseController
{
    #[AttachService('data')]
    protected DataInterface $data;

    // JSON_THROW_ON_ERROR: an encode failure raises JsonException at the
    // source instead of json_encode() returning false and the string
    // return type failing somewhere far from the cause
    protected int $jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;

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

    /**
     * Validation-failure payload keyed by input field name:
     *
     *   {"errors": {"in_office": ["in_office must contain a boolean"]}}
     *
     * Defaults to 422 Unprocessable Entity — the REST convention for a
     * well-formed request that fails semantic validation. Accepts a plain
     * array so callers can pass errors from any source (a Dto's errors(),
     * hand-built messages, ...).
     *
     * @param array $errors messages grouped by field name
     * @param int $status
     * @return string
     */
    protected function errorsResponse(array $errors, int $status = 422): string
    {
        $this->data->errors = $errors;

        return $this->response($status);
    }

    /**
     * 404 with a display message the client can show as-is:
     *
     *   {"msg": "Not Found"}
     *
     * @param string $msg
     * @return string
     */
    protected function notFoundResponse(string $msg = 'Not Found'): string
    {
        $this->data->msg = $msg;

        return $this->response(404);
    }

    /**
     * 204 No Content — the one success response that must not carry a body.
     *
     * @return string
     */
    protected function noContentResponse(): string
    {
        return $this->response(204, '');
    }
}

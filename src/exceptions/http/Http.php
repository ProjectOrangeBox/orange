<?php

declare(strict_types=1);

namespace orange\framework\exceptions\http;

use Throwable;
use orange\framework\exceptions\OrangeException;

class Http extends OrangeException
{
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        if ($code == 0) {
            // get the last 3 digits of the class name ie. Http304
            $code = (int)substr(static::class, -3);

            // if the code is still 0 then just use 500
            if ($code == 0) {
                $code = 500;
            }
        }

        if (empty($message)) {
            // if the message is empty then use the HTTP message for the status code
            // config may or may not be setup so we will just go old school and grab ours directly
            $statusCodes = require __DIR__ . '/../../config/statusCodes.php';

            // if we have a match then use that
            if (isset($statusCodes[$code])) {
                $message = $statusCodes[$code];
            } else {
                // if not then use the default
                $message = 'Unknown Status Code ' . $code;
                $code = 500;
            }
        }

        // allow the parent Exception to setup
        parent::__construct($message, $code, $previous);
    }

    public function getHttpCode(): int
    {
        return $this->getCode();
    }
}

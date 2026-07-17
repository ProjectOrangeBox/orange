<?php

declare(strict_types=1);

return [
    'auto detect accepts type' => true,
    'contentType' => 'text/html',
    'charSet' => 'utf-8',
    'language' => 'en',
    'send length' => false,
    'default redirect code' => 301,
    'force http response code' => 301,
    'force https' => false,
    // Hosts this application legitimately answers to (e.g. ['example.com', 'www.example.com']).
    // Required when 'force https' is enabled: the https redirect is built from the client-supplied
    // Host header, so it is only reflected back when it appears in this allowlist. This prevents a
    // Host-header-injection open redirect. When empty the request Host is never trusted.
    'allowed hosts' => [],
    'mimes' => require __DIR__ . '/mimes.php',
    'status codes' => require __DIR__ . '/statusCodes.php',
];

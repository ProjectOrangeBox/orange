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
    'enable cors' => false,
    'allowed cors' => [],
    // Send "Access-Control-Allow-Credentials: true" for allowed origins. Off by
    // default: only enable for cookie / HTTP-auth based cross-origin APIs. Token
    // or bearer APIs do not need it and enabling it needlessly widens exposure.
    'access-control-allow-credentials' => false,
    'access-control-max-age' => 86400,
    'access-control-allow-methods' => 'GET, POST, PUT, DELETE, OPTIONS',
    'mimes' => require __DIR__ . '/mimes.php',
    'status codes' => require __DIR__ . '/statusCodes.php',
];

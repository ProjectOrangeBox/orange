<?php

declare(strict_types=1);

use orange\framework\Input;
use orange\framework\Output;
use orange\framework\exceptions\output\Output as OutputException;

final class OutputTest extends UnitTestHelper
{
    protected $instance;

    protected function setUp(): void
    {
        include_once MOCKDIR . '/testableOutput.php';

        $this->instance = Output::getInstance([
            'contentType' => 'text/html',
            'charSet' => 'utf-8',
        ], Input::getInstance([]));
    }

    // Tests
    public function testFlush(): void
    {
        $this->instance->write('this is the output');

        $this->assertEquals('this is the output', $this->instance->get());

        $this->instance->flush();

        $this->assertEquals('', $this->instance->get());
    }

    public function testSetOutput(): void
    {
        $this->instance->write('this is the output');

        $this->assertEquals('this is the output', $this->instance->get());
    }

    public function testAppendOutput(): void
    {
        $this->instance->write('this is the output');
        $this->instance->write(' this too!');

        $this->assertEquals('this is the output this too!', $this->instance->get());
    }

    public function testGetOutput(): void
    {
        $this->assertEquals('', $this->instance->get());
    }

    public function testHeader(): void
    {
        $this->instance->header('Cache-Control: max-age=604800');

        $this->assertContains('Cache-Control: max-age=604800', $this->instance->getHeaders());
    }

    public function testGetHeaders(): void
    {
        $this->assertEquals(['HTTP/1.0 200 OK', 'Content-Type: text/html; charset=UTF-8'], $this->instance->getHeaders());
    }

    public function testSendHeaders(): void
    {
        $this->instance->header('Cache-Control: max-age=604800');

        $this->assertContains('Cache-Control: max-age=604800', $this->instance->getHeaders());
    }

    public function testFlushHeaders(): void
    {
        $this->instance->header('Cache-Control: max-age=604800');

        $this->instance->flushHeaders();

        $this->assertNotContains('Cache-Control: max-age=604800', $this->instance->getHeaders());
    }

    public function testContentType(): void
    {
        $this->instance->contentType('application/json');

        $this->assertEquals('application/json', $this->instance->getContentType());
        $this->assertEquals(['HTTP/1.0 200 OK', 'Content-Type: application/json; charset=UTF-8'], $this->instance->getHeaders());

        $this->instance->contentType('text/html');
        $this->assertEquals(['HTTP/1.0 200 OK', 'Content-Type: text/html; charset=UTF-8'], $this->instance->getHeaders());
    }

    public function testGetContentTypeShortHand(): void
    {
        $this->instance->contentType('dot', 'utf-8');

        $this->assertEquals('text/vnd.graphviz', $this->instance->getContentType());
        $this->assertEquals(['HTTP/1.0 200 OK', 'Content-Type: text/vnd.graphviz; charset=UTF-8'], $this->instance->getHeaders());
    }

    public function testCharSet(): void
    {
        $this->instance->charSet('ASCII');

        $this->assertEquals('ASCII', $this->instance->getCharSet());
        $this->assertEquals(['HTTP/1.0 200 OK', 'Content-Type: text/html; charset=ASCII'], $this->instance->getHeaders());

        $this->instance->charSet('UTF-8');
        $this->assertEquals(['HTTP/1.0 200 OK', 'Content-Type: text/html; charset=UTF-8'], $this->instance->getHeaders());
    }

    public function testResponseCodeInt(): void
    {
        $this->instance->responseCode(500);

        $this->assertEquals(500, $this->instance->getResponseCode());
        $this->assertEquals(['HTTP/1.0 500 Internal Server Error', 'Content-Type: text/html; charset=UTF-8'], $this->instance->getHeaders());
    }

    public function testResponseCodeString(): void
    {
        $this->instance->responseCode('Bad Gateway');

        $this->assertEquals(502, $this->instance->getResponseCode());
    }

    public function testResponseCodeInvalid(): void
    {
        $this->instance->responseCode('foobar');

        $this->assertEquals(500, $this->instance->getResponseCode());
    }

    public function testResponseCodeInvalidInt(): void
    {
        $this->instance->responseCode(666);

        $this->assertEquals(500, $this->instance->getResponseCode());
    }

    public function testGetResponseCode(): void
    {
        $this->assertEquals(200, $this->instance->getResponseCode());
    }

    public function testSend(): void
    {
        $html = '<h1>Hello World!</h1>';

        $this->instance->write($html);

        ob_start();
        $this->instance->send();
        $output = ob_get_clean();

        $this->assertEquals($html, $output);
        $this->assertEquals($html, $this->instance->get());
        $this->assertEquals(200, $this->instance->getResponseCode());
        $this->assertEquals(['HTTP/1.0 200 OK', 'Content-Type: text/html; charset=UTF-8'], $this->instance->getHeaders());
    }

    public function testRedirect(): void
    {
        ob_start();
        $this->instance->redirect('http://www.example.com', 308, false);
        $output = ob_get_clean();

        $this->assertEquals(308, $this->instance->getResponseCode());
        $this->assertContains('Location: http://www.example.com', $this->instance->getHeaders());
        $this->assertEquals('', $output);
    }

    public function testResolveTrustedHostHonorsAllowedHost(): void
    {
        $instance = Output::newInstance([
            'contentType' => 'text/html',
            'charSet' => 'utf-8',
            'allowed hosts' => ['example.com', 'www.example.com'],
        ], Input::getInstance([]));

        $this->assertEquals('www.example.com', $this->callMethod('resolveTrustedHost', ['www.example.com'], $instance));
    }

    public function testResolveTrustedHostRejectsSpoofedHost(): void
    {
        $instance = Output::newInstance([
            'contentType' => 'text/html',
            'charSet' => 'utf-8',
            'allowed hosts' => ['example.com', 'www.example.com'],
        ], Input::getInstance([]));

        // a spoofed Host header must not be reflected; fall back to the canonical (first) allowed host
        $this->assertEquals('example.com', $this->callMethod('resolveTrustedHost', ['evil.com'], $instance));
    }

    public function testResolveTrustedHostFailsClosedWithoutAllowlist(): void
    {
        // default config has an empty 'allowed hosts', so forcing https can't be done safely
        $this->expectException(OutputException::class);

        $this->callMethod('resolveTrustedHost', ['anything.com']);
    }

    public function testFlushAll(): void
    {
        $this->instance->header('Content-Type: text/html; charset=utf-8');
        $this->instance->write('hello world');

        $this->instance->flushAll();

        $this->assertEmpty($this->getPrivatePublic('headers'));
        $this->assertEquals('', $this->getPrivatePublic('output'));
    }

    public function testToStringReturnsOutput(): void
    {
        $this->instance->write('the body');

        $this->assertEquals('the body', (string)$this->instance);
    }

    public function testGetContentType(): void
    {
        $this->instance->contentType('application/json');

        $this->assertEquals('application/json', $this->instance->getContentType());
    }

    public function testGetCharSet(): void
    {
        $this->instance->charSet('iso-8859-1');

        $this->assertEquals('iso-8859-1', $this->instance->getCharSet());
    }

    public function testConstructorForceHttpsRedirectsAndExits(): void
    {
        // drives __construct() -> forceHttps() -> redirect() -> send() end to end
        // through a non-cli, non-https request; TestableOutput records the
        // would-be header()/exit() calls instead of performing them for real
        $instance = TestableOutput::newInstance([
            'contentType' => 'text/html',
            'charSet' => 'utf-8',
            'force https' => true,
            'allowed hosts' => ['example.com'],
        ], Input::newInstance([
            'php_sapi' => 'apache2handler',
            'stdin' => false,
            'server' => [
                'HTTP_HOST' => 'example.com',
                'REQUEST_URI' => '/foo',
            ],
        ]));

        $this->assertEquals([0], $instance->phpExitCalls);

        $locationHeaders = array_filter($instance->phpHeaderCalls, function ($call) {
            return str_starts_with($call[0], 'Location:');
        });

        $this->assertNotEmpty($locationHeaders);
        $this->assertStringContainsString('https://example.com/foo', array_values($locationHeaders)[0][0]);
        $this->assertEquals(301, $instance->getResponseCode());
    }

    public function testContentTypeFallsBackToMimeLookupOfFallbackKey(): void
    {
        $this->instance->contentType('bogus-type-xyz', 'dot');

        $this->assertEquals('text/vnd.graphviz', $this->instance->getContentType());
    }

    public function testContentTypeAcceptsRawFallbackMimeValue(): void
    {
        $this->instance->contentType('bogus-type-xyz2', 'text/vnd.graphviz');

        $this->assertEquals('text/vnd.graphviz', $this->instance->getContentType());
    }

    public function testContentTypeThrowsWhenTypeAndFallbackAreUnknown(): void
    {
        $this->expectException(OutputException::class);

        $this->instance->contentType('totally-bogus-type', 'totally-bogus-fallback');
    }

    public function testDetectAcceptsTypeSwitchesToJsonForJsonAccept(): void
    {
        $instance = Output::newInstance([
            'contentType' => 'text/html',
            'charSet' => 'utf-8',
        ], Input::newInstance([
            'server' => ['HTTP_ACCEPT' => 'application/json, text/plain'],
        ]));

        $this->assertEquals('application/json', $instance->getContentType());
    }

    public function testDetectAcceptsTypeSwitchesToHtmlForHtmlAccept(): void
    {
        $instance = Output::newInstance([
            'contentType' => 'application/json',
            'charSet' => 'utf-8',
        ], Input::newInstance([
            'server' => ['HTTP_ACCEPT' => 'text/html, */*'],
        ]));

        $this->assertEquals('text/html', $instance->getContentType());
    }

    /* handleCors() */

    /**
     * Build a TestableOutput wired to the given CORS config and request headers.
     * TestableOutput records phpHeader()/phpExit() instead of performing them, so
     * handleCors() - including its send(true) short-circuits - runs end to end.
     */
    private function makeCorsOutput(array $config, array $server): TestableOutput
    {
        return TestableOutput::newInstance(array_merge([
            'contentType' => 'text/html',
            'charSet' => 'utf-8',
        ], $config), Input::newInstance([
            'php_sapi' => 'apache2handler',
            'stdin' => false,
            'server' => $server,
        ]));
    }

    public function testHandleCorsAllowedOriginSetsVaryAndAllowOrigin(): void
    {
        $instance = $this->makeCorsOutput(
            ['allowed cors' => ['http://good.example']],
            ['HTTP_ORIGIN' => 'http://good.example', 'REQUEST_METHOD' => 'GET']
        );

        $instance->handleCors();

        $headers = $instance->getHeaders();

        // the validated origin is reflected and marked as cache-varying
        $this->assertContains('Vary: Origin', $headers);
        $this->assertContains('Access-Control-Allow-Origin: http://good.example', $headers);
        $this->assertContains('Access-Control-Max-Age: 86400', $headers);
        // an allowed, non-preflight request must not short-circuit/exit
        $this->assertEmpty($instance->phpExitCalls);
    }

    public function testHandleCorsOmitsCredentialsByDefault(): void
    {
        $instance = $this->makeCorsOutput(
            ['allowed cors' => ['http://good.example']],
            ['HTTP_ORIGIN' => 'http://good.example', 'REQUEST_METHOD' => 'GET']
        );

        $instance->handleCors();

        // credentials are off unless the app explicitly opts in
        $this->assertNotContains('Access-Control-Allow-Credentials: true', $instance->getHeaders());
    }

    public function testHandleCorsSendsCredentialsWhenEnabled(): void
    {
        $instance = $this->makeCorsOutput(
            [
                'allowed cors' => ['http://good.example'],
                'access-control-allow-credentials' => true,
            ],
            ['HTTP_ORIGIN' => 'http://good.example', 'REQUEST_METHOD' => 'GET']
        );

        $instance->handleCors();

        $this->assertContains('Access-Control-Allow-Credentials: true', $instance->getHeaders());
    }

    public function testHandleCorsDisallowedOriginOmitsAllowOriginAndExits(): void
    {
        $instance = $this->makeCorsOutput(
            ['allowed cors' => ['http://good.example']],
            ['HTTP_ORIGIN' => 'http://evil.example', 'REQUEST_METHOD' => 'GET']
        );

        ob_start();
        $instance->handleCors();
        ob_get_clean();

        // a disallowed origin never gets an Access-Control-Allow-Origin header...
        $allowOrigin = array_filter($instance->getHeaders(), function ($header) {
            return str_starts_with($header, 'Access-Control-Allow-Origin');
        });
        $this->assertEmpty($allowOrigin);
        // ...and the request is sent and terminated
        $this->assertEquals([0], $instance->phpExitCalls);
    }

    public function testHandleCorsWithoutOriginLeavesHeadersUntouched(): void
    {
        $instance = $this->makeCorsOutput(
            ['allowed cors' => ['http://good.example']],
            ['REQUEST_METHOD' => 'GET']
        );

        $before = $instance->getHeaders();
        $instance->handleCors();

        $this->assertEquals($before, $instance->getHeaders());
        $this->assertEmpty($instance->phpExitCalls);
    }

    public function testHandleCorsPreflightSetsMethodsAndReflectsRequestedHeaders(): void
    {
        $instance = $this->makeCorsOutput(
            ['allowed cors' => ['http://good.example']],
            [
                'HTTP_ORIGIN' => 'http://good.example',
                'REQUEST_METHOD' => 'OPTIONS',
                'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
                'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'Authorization, Content-Type',
            ]
        );

        ob_start();
        $instance->handleCors();
        ob_get_clean();

        $headers = $instance->getHeaders();

        $this->assertContains('Access-Control-Allow-Origin: http://good.example', $headers);
        $this->assertContains('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS', $headers);
        // the requested headers are reflected back for the (already validated) origin
        $this->assertContains('Access-Control-Allow-Headers: Authorization, Content-Type', $headers);
        // a preflight request always ends by sending and terminating
        $this->assertEquals([0], $instance->phpExitCalls);
    }
}

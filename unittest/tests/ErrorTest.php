<?php

declare(strict_types=1);

use orange\framework\Data;
use orange\framework\Error;
use orange\framework\ViewFinder;
use orange\framework\interfaces\OutputInterface;
use orange\framework\interfaces\ViewInterface;
use orange\framework\interfaces\ContainerInterface;
use orange\framework\interfaces\DirectorySearchInterface;
use orange\framework\exceptions\container\ServiceNotFound;

/**
 * Error::__construct() resolves services and then calls sendOutput() which
 * exits, so it cannot be exercised in-process. These tests build the instance
 * with newInstanceWithoutConstructor() and drive the individual helper methods,
 * injecting a recording mock for Output and a real Data store.
 */
final class ErrorTest extends unitTestHelper
{
    protected $instance;

    protected function setUp(): void
    {
        $this->instance = new ReflectionClass(Error::class)->newInstanceWithoutConstructor();
    }

    private function withData(array $data): Data
    {
        $store = Data::getInstance();
        $store->merge($data);

        $this->setPrivatePublic('data', $store);

        return $store;
    }

    /* sendResponseCode() */

    public function testSendResponseCodePrefersHttpCode(): void
    {
        $this->instance->httpCode = 404;
        $this->instance->code = 500;
        $this->instance->requestType = 'html';

        $output = $this->createMock(OutputInterface::class);
        $output->expects($this->once())->method('responseCode')->with(404);
        $this->setPrivatePublic('output', $output);

        $this->instance->sendResponseCode();
    }

    public function testSendResponseCodeFallsBackToCode(): void
    {
        $this->instance->httpCode = 0;
        $this->instance->code = 403;
        $this->instance->requestType = 'html';

        $output = $this->createMock(OutputInterface::class);
        $output->expects($this->once())->method('responseCode')->with(403);
        $this->setPrivatePublic('output', $output);

        $this->instance->sendResponseCode();
    }

    public function testSendResponseCodeDefaultsTo500(): void
    {
        $this->instance->httpCode = 0;
        $this->instance->code = 0;
        $this->instance->requestType = 'html';

        $output = $this->createMock(OutputInterface::class);
        $output->expects($this->once())->method('responseCode')->with(500);
        $this->setPrivatePublic('output', $output);

        $this->instance->sendResponseCode();
    }

    public function testSendResponseCodeSkippedForCli(): void
    {
        $this->instance->httpCode = 404;
        $this->instance->code = 500;
        $this->instance->requestType = 'cli';

        $output = $this->createMock(OutputInterface::class);
        $output->expects($this->never())->method('responseCode');
        $this->setPrivatePublic('output', $output);

        $this->instance->sendResponseCode();
    }

    /* sendMimeType() */

    public function testSendMimeTypeAjaxIsJson(): void
    {
        $this->instance->requestType = 'ajax';

        $output = $this->createMock(OutputInterface::class);
        $output->expects($this->once())->method('contentType')->with('json');
        $this->setPrivatePublic('output', $output);

        $this->instance->sendMimeType();
    }

    public function testSendMimeTypeHtmlIsHtml(): void
    {
        $this->instance->requestType = 'html';

        $output = $this->createMock(OutputInterface::class);
        $output->expects($this->once())->method('contentType')->with('html');
        $this->setPrivatePublic('output', $output);

        $this->instance->sendMimeType();
    }

    /* viewRaw() */

    public function testViewRawJson(): void
    {
        $this->instance->requestType = 'json';
        $this->withData(['code' => 404, 'message' => 'Not Found']);

        $raw = $this->callMethod('viewRaw');

        $decoded = json_decode($raw, true);

        $this->assertIsArray($decoded);
        $this->assertEquals(404, $decoded['code']);
        $this->assertEquals('Not Found', $decoded['message']);
    }

    public function testViewRawAjaxIsJson(): void
    {
        // Input::requestType() only ever returns 'html', 'ajax', or 'cli' - it never
        // literally returns 'json' - and sendMimeType() sends a "json" content type for
        // 'ajax'. viewRaw() must match that, or an AJAX error response ends up with a
        // "Content-Type: application/json" header over a print_r()-formatted body.
        $this->instance->requestType = 'ajax';
        $this->withData(['code' => 404, 'message' => 'Not Found']);

        $raw = $this->callMethod('viewRaw');

        $decoded = json_decode($raw, true);

        $this->assertIsArray($decoded);
        $this->assertEquals(404, $decoded['code']);
        $this->assertEquals('Not Found', $decoded['message']);
    }

    public function testViewRawHtml(): void
    {
        $this->instance->requestType = 'html';
        $this->withData(['code' => 500, 'message' => 'Boom']);

        $raw = $this->callMethod('viewRaw');

        $this->assertStringContainsString('<pre>', $raw);
        $this->assertStringContainsString('500', $raw);
        $this->assertStringContainsString('Boom', $raw);
        $this->assertStringContainsString('</pre>', $raw);
    }

    public function testViewRawCli(): void
    {
        $this->instance->requestType = 'cli';
        $this->withData(['code' => 500, 'message' => 'Boom']);

        $raw = $this->callMethod('viewRaw');

        // print_r() style output
        $this->assertStringContainsString('Boom', $raw);
        $this->assertStringContainsString('[message]', $raw);
    }

    public function testViewRawUnknownRequestTypeFallsBackToCliFormat(): void
    {
        $this->instance->requestType = 'something-else';
        $this->withData(['code' => 500]);

        $raw = $this->callMethod('viewRaw');

        $this->assertStringContainsString('[code]', $raw);
    }

    /* viewRawBuildHtml() */

    public function testViewRawBuildHtmlIncludesAllKnownFields(): void
    {
        $data = [
            'code' => 422,
            'message' => 'Unprocessable',
            'file' => '/path/to/file.php',
            'line' => 42,
            'options' => ['extra' => 'detail'],
        ];

        $raw = $this->callMethod('viewRawBuildHtml', [$data]);

        $this->assertStringContainsString('422', $raw);
        $this->assertStringContainsString('Unprocessable', $raw);
        $this->assertStringContainsString('File: /path/to/file.php', $raw);
        $this->assertStringContainsString('Line: 42', $raw);
        $this->assertStringContainsString('detail', $raw);
    }

    public function testViewRawBuildHtmlOmitsMissingFields(): void
    {
        $raw = $this->callMethod('viewRawBuildHtml', [['message' => 'only a message']]);

        $this->assertStringContainsString('only a message', $raw);
        $this->assertStringNotContainsString('File:', $raw);
        $this->assertStringNotContainsString('Line:', $raw);
    }

    public function testViewRawBuildHtmlEscapesUntrustedFields(): void
    {
        // exception message/file can carry attacker-controlled input (e.g. a bad route
        // or header echoed back in a validation message) - it must never reach the
        // response unescaped
        $data = [
            'message' => '<script>alert(1)</script>',
            'file' => '"><img src=x onerror=alert(1)>',
            'options' => ['bad' => '<b>bold</b>'],
        ];

        $raw = $this->callMethod('viewRawBuildHtml', [$data]);

        $this->assertStringNotContainsString('<script>', $raw);
        $this->assertStringContainsString('&lt;script&gt;', $raw);
        $this->assertStringNotContainsString('<img', $raw);
        $this->assertStringNotContainsString('<b>bold</b>', $raw);
    }

    /* findView() */

    /**
     * The finder is asked first, so an application can override any error view
     * simply by owning its name.
     */
    public function testFindViewPrefersTheViewFinder(): void
    {
        $this->setPrivatePublic('viewFinder', ViewFinder::newInstance([
            'view fallbacks' => ['errors/html/404' => '/app/custom/404.php'],
        ]));

        $this->instance->errorViewDirectory = 'errors';
        $this->instance->envDirectory = 'testing';
        $this->instance->requestTypeDirectory = 'html';

        $this->assertSame('/app/custom/404.php', $this->callMethod('findView', ['404']));
    }

    /**
     * With nothing in the finder - which is every application that has not
     * generated a view map yet - the copies shipped beside Error.php are still
     * found. An error handler that cannot render its own error page is worse
     * than useless, so these are addressed directly rather than looked up.
     */
    public function testFindViewFallsBackToTheBundledViews(): void
    {
        $this->setPrivatePublic('viewFinder', ViewFinder::newInstance([]));

        $this->instance->errorViewDirectory = 'errors';
        $this->instance->envDirectory = 'testing';
        $this->instance->requestTypeDirectory = 'html';

        $found = $this->callMethod('findView', ['404']);

        $this->assertSame(ORANGEDIR . '/views/errors/html/404.php', $found);
        $this->assertFileExists($found);
    }

    public function testFindViewReturnsEmptyWhenNeitherHasIt(): void
    {
        $this->setPrivatePublic('viewFinder', ViewFinder::newInstance([]));

        $this->instance->errorViewDirectory = 'errors';
        $this->instance->envDirectory = 'testing';
        $this->instance->requestTypeDirectory = 'html';

        $this->assertSame('', $this->callMethod('findView', ['no-such-code']));
    }

    /* getService() */

    public function testGetServiceFallsBackToOrangeDefaultWhenServiceNotFound(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())->method('get')->willThrowException(new ServiceNotFound('data'));
        $this->setPrivatePublic('container', $container);

        $result = $this->callMethod('getService', ['data', []]);

        $this->assertInstanceOf(Data::class, $result);
    }

    public function testGetServicePropagatesRealConstructionErrors(): void
    {
        // a container that DOES have "data" registered but throws while building it (e.g.
        // a bad autowired dependency) must not be treated the same as "not registered" -
        // catching every Throwable here would silently mask a real bug behind an unrelated
        // fallback instance instead of letting the actual error surface
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())->method('get')->willThrowException(new RuntimeException('boom'));
        $this->setPrivatePublic('container', $container);

        $this->expectException(RuntimeException::class);

        $this->callMethod('getService', ['data', []]);
    }
}

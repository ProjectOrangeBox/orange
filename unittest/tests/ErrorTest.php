<?php

declare(strict_types=1);

use orange\framework\Data;
use orange\framework\Error;
use orange\framework\interfaces\OutputInterface;

/**
 * Error::__construct() resolves services and then calls sendOutput() which
 * exits, so it cannot be exercised in-process. These tests build the instance
 * with newInstanceWithoutConstructor() and drive the individual helper methods,
 * injecting a recording mock for Output and a real Data store.
 */
final class ErrorTest extends UnitTestHelper
{
    protected $instance;

    protected function setUp(): void
    {
        $this->instance = (new ReflectionClass(Error::class))->newInstanceWithoutConstructor();
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

        $raw = $this->callMethod('viewRawBuildHtml', ['', $data]);

        $this->assertStringContainsString('422', $raw);
        $this->assertStringContainsString('Unprocessable', $raw);
        $this->assertStringContainsString('File: /path/to/file.php', $raw);
        $this->assertStringContainsString('Line: 42', $raw);
        $this->assertStringContainsString('detail', $raw);
    }

    public function testViewRawBuildHtmlOmitsMissingFields(): void
    {
        $raw = $this->callMethod('viewRawBuildHtml', ['', ['message' => 'only a message']]);

        $this->assertStringContainsString('only a message', $raw);
        $this->assertStringNotContainsString('File:', $raw);
        $this->assertStringNotContainsString('Line:', $raw);
    }
}

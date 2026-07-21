<?php

declare(strict_types=1);

use orange\framework\Input;
use orange\framework\interfaces\InputInterface;
use orange\framework\exceptions\input\UnknownOffset;
use orange\framework\exceptions\input\ImmutableAccess;

final class InputTest extends UnitTestHelper
{
    protected $instance;

    protected $default = [
        'query' => [
            'name' => 'Jenny Appleseed',
            'age' => 25,
        ],
        'files' => [],
        'server' => [
            'request_uri' => '/product/123abc',
            'request_method' => 'get',
            'http_x_requested_with' => 'xmlhttprequest',
            'https' => 'on',
            'HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.9,zh-CN;q=0.8,zh;q=0.7,fr;q=0.6,el;q=0.5',
        ],
        'request' => [
            'name' => 'Jon Appleseed',
            'age' => 26,
        ],
        'cookies' => [
            'name' => 'James Appleseed',
            'age' => 28,
        ],

        // looks like a apache server request
        'php_sapi' => 'APACHE',
        'stdin' => false,
    ];

    protected function setUp(): void
    {
        $this->instance = Input::getInstance($this->default);
    }

    // Tests
    public function testGetUrl(): void
    {
        $this->assertEquals('/product/123abc', $this->instance->GetUrl());
        $this->assertEquals('/product/123abc', $this->instance->GetUrl(Input::SCHEME));
        $this->assertEquals(null, $this->instance->GetUrl(Input::HOST));
        $this->assertEquals(null, $this->instance->GetUrl(Input::PORT));
        $this->assertEquals(null, $this->instance->GetUrl(Input::USER));
        $this->assertEquals(null, $this->instance->GetUrl(Input::PASS));
        $this->assertEquals('/product/123abc', $this->instance->GetUrl(Input::PATH));
        $this->assertEquals(null, $this->instance->GetUrl(Input::QUERY));
        $this->assertEquals(null, $this->instance->GetUrl(Input::FRAGMENT));
    }

    public function testRequestUri(): void
    {
        $this->assertEquals('/product/123abc', $this->instance->requestUri());
    }

    public function testUriSegment(): void
    {
        $this->assertEquals('', $this->instance->uriSegment(0));
        $this->assertEquals('product', $this->instance->uriSegment(1));
        $this->assertEquals('123abc', $this->instance->uriSegment(2));
        $this->assertEquals('', $this->instance->uriSegment(3));
    }

    public function testContentType(): void
    {
        $this->assertEquals('', $this->instance->contentType());
    }

    public function testRequestMethod(): void
    {
        $this->assertEquals('get', $this->instance->requestMethod());
    }

    public function testRequestType(): void
    {
        $this->assertEquals('ajax', $this->instance->requestType());
    }

    public function testIsAjaxRequest(): void
    {
        $this->assertEquals(true, $this->instance->isAjaxRequest());
    }

    public function testIsCliRequest(): void
    {
        $this->assertEquals(false, $this->instance->isCliRequest());
    }

    public function testIsHttpsRequest(): void
    {
        $this->assertEquals(true, $this->instance->isHttpsRequest());
        $this->assertEquals('https', $this->instance->isHttpsRequest(true));
    }

    public function testRequest(): void
    {
        $this->assertEquals([
            'name' => 'Jon Appleseed',
            'age' => 26,
        ], $this->instance->request());
    }

    public function testQuery(): void
    {
        $this->assertEquals([
            'name' => 'Jenny Appleseed',
            'age' => 25,
        ], $this->instance->query());
    }

    public function testCookie(): void
    {
        $this->assertEquals([
            'name' => 'James Appleseed',
            'age' => 28,
        ], $this->instance->cookie());
    }

    public function testServer(): void
    {
        $this->assertEquals('get', $this->instance->server('request_method'));
        $this->assertEquals('en-US,en;q=0.9,zh-CN;q=0.8,zh;q=0.7,fr;q=0.6,el;q=0.5', $this->instance->server('HTTP_ACCEPT_LANGUAGE'));
    }

    public function testHeader(): void
    {
        $this->assertEquals('en-US,en;q=0.9,zh-CN;q=0.8,zh;q=0.7,fr;q=0.6,el;q=0.5', $this->instance->header('HTTP_ACCEPT_LANGUAGE'));
    }

    public function testFile(): void
    {
        $this->assertEquals([], $this->instance->file(0));
    }

    public function testJsonRequest(): void
    {
        $instance = Input::newInstance([
            'input' => '{"name": "Joe","age": 24}',
            'server' => ['content_type' => 'application/json', 'request_method' => 'POST'],
        ]);

        $this->assertEquals([
            'name' => 'Joe',
            'age' => 24,
        ], $instance->request());
    }

    public function testInputStreamReturnsRawInput(): void
    {
        $instance = Input::newInstance([
            'input' => 'raw body payload',
            'server' => ['request_method' => 'post'],
        ]);

        $this->assertEquals('raw body payload', $instance->inputStream());
    }

    public function testRequestReturnsDefaultForMissingKey(): void
    {
        $this->assertEquals('fallback', $this->instance->request('doesNotExist', 'fallback'));
    }

    public function testQueryReturnsWholeArrayWhenNoKey(): void
    {
        $this->assertEquals(['name' => 'Jenny Appleseed', 'age' => 25], $this->instance->query());
    }

    public function testIsHttpsRequestAsString(): void
    {
        // setUp server has https => 'on'
        $this->assertEquals('https', $this->instance->isHttpsRequest(true));
    }

    public function testUriSegmentOutOfRangeReturnsEmpty(): void
    {
        $this->assertEquals('', $this->instance->uriSegment(99));
    }

    public function testRequestUriReturnsEmptyStringWhenUrlHasNoPath(): void
    {
        // regression guard: parse_url() returns null (not a string) when the URI
        // has no path component - under strict_types that used to throw a
        // TypeError out of requestUri() instead of falling back to ''
        $instance = Input::newInstance(['server' => ['request_uri' => 'http://example.com']]);

        $this->assertEquals('', $instance->requestUri());
    }

    public function testRequestUriReturnsEmptyStringForMalformedUrl(): void
    {
        // regression guard: parse_url() returns false (not a string) for a
        // malformed URI - same TypeError risk as the no-path case above
        $instance = Input::newInstance(['server' => ['request_uri' => 'http://[invalid']]);

        $this->assertEquals('', $instance->requestUri());
    }

    public function testArrayAccessGetMatchesMethodAccessors(): void
    {
        $this->assertEquals($this->instance->query(), $this->instance['query']);
        $this->assertEquals($this->instance->request(), $this->instance['request']);
        $this->assertEquals($this->instance->cookie(), $this->instance['cookie']);
        $this->assertEquals($this->instance->file(), $this->instance['file']);
        $this->assertEquals($this->instance->server(), $this->instance['server']);
        $this->assertEquals($this->instance->header(), $this->instance['header']);
    }

    public function testArrayAccessIsCaseInsensitive(): void
    {
        $this->assertEquals($this->instance->query(), $this->instance['QUERY']);
    }

    public function testArrayAccessNestedServerKeyUsesUnderscores(): void
    {
        // setUp server has 'HTTP_ACCEPT_LANGUAGE' - normalizeServerKey() now keeps
        // underscores (rather than converting them to spaces) so this raw array
        // access matches the exact same key server('accept_language') would use
        $this->assertEquals(
            'en-US,en;q=0.9,zh-CN;q=0.8,zh;q=0.7,fr;q=0.6,el;q=0.5',
            $this->instance['server']['accept_language']
        );
    }

    public function testArrayAccessExists(): void
    {
        $this->assertTrue(isset($this->instance['server']));
        $this->assertFalse(isset($this->instance['bogus']));
    }

    public function testArrayAccessGetUnknownOffsetThrows(): void
    {
        $this->expectException(UnknownOffset::class);

        $this->instance['bogus'];
    }

    public function testArrayAccessSetThrows(): void
    {
        $this->expectException(ImmutableAccess::class);

        $this->instance['server'] = [];
    }

    public function testArrayAccessUnsetThrows(): void
    {
        $this->expectException(ImmutableAccess::class);

        unset($this->instance['server']);
    }
}

<?php

declare(strict_types=1);

use orange\framework\Error;
use orange\framework\Container;

/**
 * Error::__construct(), show() and sendOutput() all funnel through
 * sendOutput(), which always ends in exit() - impossible to exercise in the
 * running test process. TestableError (unittest/mocks/testableError.php)
 * overrides just that one method to record instead of exiting, letting
 * __construct()/show() - real Error.php logic - run end to end and be
 * covered normally, resolving services through the real getService()
 * container-fallback path (see ErrorAdvancedTest for that path in isolation).
 */
final class ErrorConstructorTest extends UnitTestHelper
{
    protected function setUp(): void
    {
        include_once MOCKDIR . '/testableError.php';
    }

    public function testConstructorWithoutThrownRendersDefaultView(): void
    {
        $instance = TestableError::newInstance([], Container::getInstance([]), null);

        $this->assertCount(1, $instance->sendOutputCalls);
        $this->assertIsString($instance->sendOutputCalls[0][0]);
        $this->assertEquals(500, $instance->code);
    }

    public function testConstructorWithThrownExceptionUsesAllDecorationHooks(): void
    {
        $thrown = new TestableDecoratingException('boom', 404);

        $instance = TestableError::newInstance([], Container::getInstance([]), $thrown);

        // code came from the thrown exception (line 215-216)
        $this->assertEquals(404, $instance->code);
        // getHttpCode() hook (line 221-223)
        $this->assertEquals(404, $instance->httpCode);
        // decorate() hook (line 236-238) set viewFile, which __construct() then
        // renders (line 249-250) instead of falling back to renderViewBasedOnCode()
        $this->assertEquals('errors/html/404', $instance->viewFile);
        $this->assertCount(1, $instance->sendOutputCalls);
        $this->assertNotEmpty($instance->sendOutputCalls[0][0]);
    }

    public function testShowRendersAndRecordsSendOutput(): void
    {
        $instance = TestableError::newInstance([], Container::getInstance([]), null);

        $instance->show(404, 'Not Found', ['extra' => 'detail']);

        // show() merges into data and calls sendOutput() a second time
        $this->assertCount(2, $instance->sendOutputCalls);
        $this->assertIsString($instance->sendOutputCalls[1][0]);
        $this->assertEquals('Not Found', $instance->data['message']);
        $this->assertEquals(404, $instance->data['code']);
    }
}

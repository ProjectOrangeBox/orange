<?php

declare(strict_types=1);

/**
 * Covers the standalone string/HTML helper functions declared in
 * src/helpers/helpers.php (the ones HelpersTest does not already exercise).
 */
final class StringHelpersTest extends UnitTestHelper
{
    protected function setUp(): void
    {
        require_once ORANGEDIR . '/helpers/helpers.php';
    }

    /* is_closure() */

    public function testIsClosureTrueForClosure(): void
    {
        $this->assertTrue(is_closure(fn() => 1));
    }

    public function testIsClosureFalseForString(): void
    {
        $this->assertFalse(is_closure('strlen'));
    }

    /* element() */

    public function testElementBuildsStandardTag(): void
    {
        $this->assertEquals('<span class="big">Hi</span>', element('span', ['class' => 'big'], 'Hi'));
    }

    public function testElementSelfClosingTagHasNoClosingTag(): void
    {
        $this->assertEquals('<br class="x">', element('br', ['class' => 'x']));
    }

    public function testElementEscapesContentByDefault(): void
    {
        $this->assertEquals('<div id="a">&lt;b&gt;&amp;</div>', element('div', ['id' => 'a'], '<b>&'));
    }

    public function testElementDoesNotEscapeWhenDisabled(): void
    {
        $this->assertEquals('<div id="a"><b></div>', element('div', ['id' => 'a'], '<b>', false));
    }

    /* convertLabel() */

    public function testConvertLabelCamel(): void
    {
        $this->assertEquals('helloWorld', convertLabel('Hello World', 'camel'));
        $this->assertEquals('myVarName', convertLabel('my_var name', 'camel'));
    }

    public function testConvertLabelPascal(): void
    {
        $this->assertEquals('HelloWorld', convertLabel('Hello World', 'pascal'));
    }

    public function testConvertLabelSnake(): void
    {
        $this->assertEquals('hello_world', convertLabel('Hello World', 'snake'));
    }

    public function testConvertLabelSlug(): void
    {
        $this->assertEquals('hello-world', convertLabel('Hello World', 'slug'));
        $this->assertEquals('a-b-c', convertLabel('A B! C', 'slug'));
    }

    public function testConvertLabelNormalizeStripsNonAlnum(): void
    {
        $this->assertEquals('helloworld', convertLabel('Hello World', 'normalize'));
    }

    public function testConvertLabelInvalidCaseThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid case: bogus');

        convertLabel('Hello', 'bogus');
    }

    /* esc() */

    public function testEscEscapesDoubleQuotes(): void
    {
        $this->assertEquals('say \\"hi\\"', esc('say "hi"'));
    }

    /* e() */

    public function testEEscapesHtmlInString(): void
    {
        $this->assertEquals('&lt;a href=&quot;x&quot;&gt;', e('<a href="x">'));
    }

    public function testERecursesIntoArrays(): void
    {
        $this->assertEquals(['&lt;b&gt;', 'ok'], e(['<b>', 'ok']));
    }

    public function testELeavesEmptyInputUntouched(): void
    {
        $this->assertEquals('', e(''));
    }

    /* strContains() */

    public function testStrContainsFindsNeedle(): void
    {
        $this->assertTrue(strContains('hello', 'ell'));
    }

    public function testStrContainsMissingNeedle(): void
    {
        $this->assertFalse(strContains('hello', 'zzz'));
    }

    public function testStrContainsEmptyNeedleIsTrue(): void
    {
        $this->assertTrue(strContains('hello', ''));
    }

    /* nthfield() */

    public function testNthfieldReturnsRequestedField(): void
    {
        $this->assertEquals('b', nthfield('a,b,c', ',', 2));
    }

    public function testNthfieldOutOfRangeReturnsNull(): void
    {
        $this->assertNull(nthfield('a,b', ',', 5));
    }

    /* after() / before() / between() */

    public function testAfter(): void
    {
        $this->assertEquals('value', after('=', 'key=value'));
    }

    public function testBefore(): void
    {
        $this->assertEquals('key', before('=', 'key=value'));
    }

    public function testBetween(): void
    {
        $this->assertEquals('mid', between('[', ']', 'x[mid]y'));
    }

    /* left() / right() / mid() */

    public function testLeft(): void
    {
        $this->assertEquals('he', left('hello', 2));
    }

    public function testRight(): void
    {
        $this->assertEquals('lo', right('hello', 2));
    }

    public function testMidIsOneBased(): void
    {
        $this->assertEquals('ell', mid('hello', 2, 3));
    }

    /* isAssociative() */

    public function testIsAssociativeFalseForList(): void
    {
        $this->assertFalse(isAssociative([1, 2, 3]));
    }

    public function testIsAssociativeTrueForMap(): void
    {
        $this->assertTrue(isAssociative(['a' => 1]));
    }

    public function testIsAssociativeFalseForEmptyArray(): void
    {
        $this->assertFalse(isAssociative([]));
    }

    /* sanitizeDownloadFilename() */

    public function testSanitizeDownloadFilenamePassesThroughSafeName(): void
    {
        $this->assertEquals('report.pdf', sanitizeDownloadFilename('report.pdf'));
    }

    public function testSanitizeDownloadFilenameStripsDirectoryComponent(): void
    {
        $this->assertEquals('passwd', sanitizeDownloadFilename('/etc/passwd'));
        $this->assertEquals('report.pdf', sanitizeDownloadFilename('../../report.pdf'));
    }

    public function testSanitizeDownloadFilenameEscapesQuotesAndBackslashes(): void
    {
        // a raw double quote would close the quoted-string early and let an attacker
        // inject additional Content-Disposition parameters
        $this->assertEquals('evil\\"; filename=other.txt', sanitizeDownloadFilename('evil"; filename=other.txt'));
        $this->assertEquals('back\\\\slash.txt', sanitizeDownloadFilename('back\\slash.txt'));
    }

    public function testSanitizeDownloadFilenameStripsControlCharacters(): void
    {
        $this->assertEquals('evil.txt', sanitizeDownloadFilename("evil\r\n.txt"));
        $this->assertEquals('evil.txt', sanitizeDownloadFilename("ev\x00il.txt"));
    }
}

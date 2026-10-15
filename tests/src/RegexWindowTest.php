<?php

declare(strict_types=1);

namespace Tests\Src;

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Parser\Terminal\Regex;
use Vanderlee\Comprehend\Runtime\ParseLimits;
use Vanderlee\Comprehend\Source\Utf8FileSource;
use Vanderlee\Comprehend\Source\Utf8Source;

class RegexWindowTest extends TestCase
{
    /** @dataProvider literalCases */
    public function testBoundedLiteralAgreesWithFullSubjectAtByteOffset(string $literal, string $text, int $offset, bool $caseInsensitive)
    {
        $ordinary = new Regex('/' . preg_quote($literal, '/') . '/A');
        $bounded = Regex::literalWindow($literal);
        $ordinary->setCaseSensitivity(!$caseInsensitive);
        $bounded->setCaseSensitivity(!$caseInsensitive);
        $stream = tmpfile();
        fwrite($stream, $text);
        $file = new Utf8FileSource($stream, 4);
        fclose($stream);
        foreach ([new Utf8Source($text), $file] as $source) {
            $expected = $ordinary->matchPrefix($source, $offset);
            $actual = $bounded->matchPrefix($source, $offset);
            $this->assertSame([$expected->success, $expected->end, $expected->rawText()], [$actual->success, $actual->end, $actual->rawText()]);
        }
    }

    public function literalCases(): array
    {
        return [['abc', 'zzabcx', 2, false], ['abc', 'zzab', 2, false], ['é🙂', 'zé🙂é', 1, false],
            ['k', 'zK🙂', 1, true], ['a.*', 'za.*tail', 1, false], ['a', 'zaé', 1, false], ['a', 'z', 1, false]];
    }

    public function testGeneralRegexRetainsAnchorsAndLookbehindOnFiles()
    {
        $stream = tmpfile();
        fwrite($stream, 'abc');
        $source = new Utf8FileSource($stream);
        fclose($stream);
        $this->assertFalse((new Regex('/^b/'))->matchPrefix($source, 1)->success);
        $this->assertTrue((new Regex('/(?<=a)b/A'))->matchPrefix($source, 1)->success);
        $this->assertTrue(Regex::literalWindow('b')->matchPrefix($source, 1)->success);
    }

    public function testWindowDoesNotChargeOrReadWholeLargeSource()
    {
        $stream = tmpfile();
        fwrite($stream, 'a' . str_repeat('x', 10000));
        $source = new Utf8FileSource($stream);
        fclose($stream);
        $this->assertTrue(Regex::literalWindow('a')->matchPrefix($source, limits: new ParseLimits(maxWork: 5))->success);
    }
}

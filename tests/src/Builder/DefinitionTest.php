<?php

namespace Tests\Src\Builder;

use Tests\ParserTestCase;
use Vanderlee\Comprehend\Builder\Definition;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Range;
use Vanderlee\Comprehend\Parser\Terminal\Set;

/**
 * @group structure
 * @group parser
 */
class DefinitionTest extends ParserTestCase
{
    const CSV_RECORD = [__CLASS__, 'makeCsvRecordParser'];
    const QUOTED_STRING = [__CLASS__, 'makeQuotedStringParser'];

    public static function makeCsvRecordParser($item, $delimiter = ',')
    {
        return new Sequence($item, new Repeat(new Sequence($delimiter, $item)));
    }

    /**
     * @param string $enclosures
     *
     * @throws \Exception
     *
     * @return Choice|Sequence
     */
    public static function makeQuotedStringParser($enclosures = '"')
    {
        if (mb_strlen($enclosures) === 1) {
            return new Sequence(new Set($enclosures), new Repeat(new Set($enclosures, false)), new Set($enclosures));
        } else {
            return new Choice(...array_map(function ($enclosure) {
                return new Sequence(new Set($enclosure), new Repeat(new Set($enclosure, false)), new Set($enclosure));
            }, str_split($enclosures)));
        }
    }

    public function testDefinition()
    {
        $definition = new Definition(self::CSV_RECORD);

        $List = $definition('x');
        $this->assertResult(true, 5, $List('x,x,x'));

        $List = $definition->build('x');
        $this->assertResult(true, 5, $List('x,x,x'));
    }

    public function testDefinitionSetParser()
    {
        $definition = (new Definition())->setGenerator(self::CSV_RECORD);

        $List = $definition->build('x');
        $this->assertResult(true, 5, $List('x,x,x'));
    }

    public function testValidatorInConstructor()
    {
        $d = new Definition(new Repeat(new Range('0', '9'), 1), function ($text) {
            return intval($text) % 2 === 1;
        });

        $number = $d();
        $this->assertResult(true, 2, $number('11'));
        $this->assertResult(false, 2, $number('12'));
    }

    public function testQuotedList()
    {
        $definition = new Definition(self::QUOTED_STRING);
        $qs = $definition();
        $this->assertResult(true, 5, $qs('"foo"'));
        $this->assertResult(false, 5, $qs('"foo`'));

        $qs = $definition('e');
        $this->assertResult(true, 4, $qs('emoe'));
        $this->assertResult(false, 3, $qs('emo'));

        $qs = $definition('"/');
        $this->assertResult(true, 5, $qs('/foo/'));
        $this->assertResult(true, 5, $qs('"foo"'));
        $this->assertResult(false, 5, $qs('"foo/'));
        $this->assertResult(false, 5, $qs('/foo"'));
        $this->assertResult(true, 6, $qs('/foo"/'));
    }

    public function testFirstDigitOdd()
    {
        $d = new Definition(new Sequence((new Range('1', '9'))->setResult('first'),
            new Repeat(new Range('0', '9'))));
        $number = $d();
        $this->assertResult(true, 2, $number('11'));
        $this->assertResult(true, 2, $number('21'));

        $d->addValidator(function (
            /* @noinspection PhpUnusedParameterInspection */
            $text,
            $results
        ) {
            return intval($results['first']) % 2 === 1;
        });
        $number = $d();
        $this->assertResult(true, 2, $number('11'));
        $this->assertResult(false, 2, $number('21'));
    }

    public function testWrap()
    {
        // Must be odd
        $original = new Definition(new Repeat(new Range('0', '9'), 1));
        $original->addValidator(function ($text) {
            return intval($text) % 2 === 1;
        });

        $inherited = new Definition($original);

        // Must be multiple of three
        $inherited->addValidator(function ($text) {
            return intval($text) % 3 === 0;
        });

        // Assert original is untouched (odd)
        $number = $original();
        $this->assertResult(false, 2, $number('10')); // not odd
        $this->assertResult(true, 2, $number('11')); // odd
        $this->assertResult(false, 2, $number('12')); // not odd
        $this->assertResult(true, 2, $number('15')); // odd
        //
        // Test inherited ; both odd and multiple of three
        $number = $inherited();
        $this->assertResult(false, 2, $number('10')); // odd and no multiple of 3
        $this->assertResult(false, 2, $number('11')); // odd, but no multiple of 3
        $this->assertResult(false, 2, $number('12')); // multiple of 3, but not odd
        $this->assertResult(true, 2, $number('15')); // odd and multiple of 3
    }

    public function testClone()
    {
        // Must be odd
        $original = new Definition(new Repeat(new Range('0', '9'), 1));
        $original->addValidator(function ($text) {
            return intval($text) % 2 === 1;
        });

        $clone = clone $original; // works as is!
        //
        // Must be multiple of three
        $clone->clearValidators()->addValidator(function ($text) {
            return intval($text) % 3 === 0;
        });

        // Assert original is untouched (odd)
        $number = $original();
        $this->assertResult(false, 2, $number('10')); // not odd
        $this->assertResult(true, 2, $number('11')); // odd
        $this->assertResult(false, 2, $number('12')); // not odd
        $this->assertResult(true, 2, $number('15')); // odd
        //
        // Test clone; multiple of three, don't care about odd
        $number = $clone();
        $this->assertResult(false, 2, $number('10')); // no multiple of 3
        $this->assertResult(false, 2, $number('11')); // no multiple of 3
        $this->assertResult(true, 2, $number('12')); // multiple of 3
        $this->assertResult(true, 2, $number('15')); // multiple of 3
    }

    public function testProcessor()
    {
        $definition = new Definition(new Sequence((new Range('1', '9'))->setResult('first'),
            new Repeat(new Range('0', '9'))));
        $definition->addProcessor('foo', function ($value) {
            return intval($value) * 3;
        });

        $parser = $definition()->setResult('bar');
        $match = $parser('12');
        $this->assertResult(true, 2, $match);
        $this->assertEquals(3, $match->results['foo']);
        $this->assertEquals(12, $match->results['bar']);
    }
}

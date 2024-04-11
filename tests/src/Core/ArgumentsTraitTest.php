<?php

namespace Tests\Src\Core;

use Tests\ParserTestCase;
use Vanderlee\Comprehend\Core\ArgumentsTrait;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Any;
use Vanderlee\Comprehend\Parser\Terminal\Char;
use Vanderlee\Comprehend\Parser\Terminal\Text;

class ArgumentsTraitStub
{
    use ArgumentsTrait {
        ArgumentsTrait::getArgument as traitgetArgument;
        ArgumentsTrait::getArguments as traitgetArguments;
    }

    public static function getArgument($argument, $arrayToSequence = true)
    {
        return self::traitgetArgument($argument, $arrayToSequence);
    }

    public static function getArguments($arguments, $arrayToSequence = true)
    {
        return self::traitgetArguments($arguments, $arrayToSequence);
    }
}

/**
 * Description of ArgumentsTraitTest.
 *
 * @author Martijn
 */
class ArgumentsTraitParserTest extends ParserTestCase
{
    public function testgetArgumentInvalidType()
    {
        $this->expectExceptionMessage('Invalid argument type `boolean`');
        ArgumentsTraitStub::getArgument(true);
    }

    public function testgetArgumentEmpty()
    {
        $this->expectExceptionMessage('Empty argument');
        ArgumentsTraitStub::getArgument('');
    }

    public function testgetArgumentType()
    {
        $this->assertInstanceOf(Char::class, ArgumentsTraitStub::getArgument('x'));
        $this->assertInstanceOf(Char::class, ArgumentsTraitStub::getArgument(0x50));
        $this->assertInstanceOf(Text::class, ArgumentsTraitStub::getArgument('ab'));
        $this->assertInstanceOf(Any::class, ArgumentsTraitStub::getArgument(new Any()));
        $this->assertInstanceOf(Sequence::class, ArgumentsTraitStub::getArgument(['a', 'b']));
    }

    public function testgetArgumentsInvalidType()
    {
        $this->expectExceptionMessage('Invalid argument type `boolean`');
        ArgumentsTraitStub::getArguments(['a', true]);
    }

    public function testgetArgumentsEmpty()
    {
        $this->expectExceptionMessage('Empty argument');
        ArgumentsTraitStub::getArguments(['a', '']);
    }

    public function testgetArgumentsEmptyArray()
    {
        $this->expectExceptionMessage('Empty array argument');
        ArgumentsTraitStub::getArguments([[]]);
    }

    public function testGetArgumentsType()
    {
        $this->assertInstanceOf(Char::class, ArgumentsTraitStub::getArguments(['x', 'y'])[0]);
        $this->assertInstanceOf(Char::class, ArgumentsTraitStub::getArguments([0x50, 0x51])[0]);
        $this->assertInstanceOf(Text::class, ArgumentsTraitStub::getArguments(['ab', 'cd'])[0]);
        $this->assertInstanceOf(Any::class, ArgumentsTraitStub::getArguments([new Any(), 'a'])[0]);
        $this->assertInstanceOf(Sequence::class, ArgumentsTraitStub::getArguments([['a', 'b'], 'c'])[0]);
        $this->assertInstanceOf(Choice::class, ArgumentsTraitStub::getArguments([['a', 'b'], 'c'], false)[0]);
        $this->assertInstanceOf(Char::class, ArgumentsTraitStub::getArguments([['a'], 'bc'])[0]);
        $this->assertInstanceOf(Text::class, ArgumentsTraitStub::getArguments([['ab'], 'c'])[0]);
    }

    public function testGetArgumentOptimizeArrays()
    {
        // Non-optimizable cases
        $this->assertEquals("( 'a' 'b' )", (string)ArgumentsTraitStub::getArguments([['a', 'b']])[0]);
        $this->assertEquals("( 'a' | 'b' )", (string)ArgumentsTraitStub::getArguments([['a', 'b']], false)[0]);
        $this->assertEquals("( 'a' ( 'b' | 'c' ) )", (string)ArgumentsTraitStub::getArguments([['a', ['b', 'c']]])[0]);
        $this->assertEquals("( 'a' | ( 'b' 'c' ) )",
            (string)ArgumentsTraitStub::getArguments([['a', ['b', 'c']]], false)[0]);

        // Optimizable cases
        $this->assertEquals("'a'", (string)ArgumentsTraitStub::getArguments([['a']])[0]);
        $this->assertEquals("'a'", (string)ArgumentsTraitStub::getArguments([['a']], false)[0]);
        $this->assertEquals("( 'a' 'b' )", (string)ArgumentsTraitStub::getArguments([['a', ['b']]])[0]);
        $this->assertEquals("( 'a' | 'b' )", (string)ArgumentsTraitStub::getArguments([['a', ['b']]], false)[0]);

        // Multi-layer optimizable cases
        $this->assertEquals("'a'", (string)ArgumentsTraitStub::getArguments([[['a']]])[0]);
        $this->assertEquals("'a'", (string)ArgumentsTraitStub::getArguments([[['a']]], false)[0]);
    }
}

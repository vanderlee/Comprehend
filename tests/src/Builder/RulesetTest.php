<?php /** @noinspection PhpDynamicAsStaticMethodCallInspection */
/** @noinspection PhpUndefinedMethodInspection */

/** @noinspection PhpUndefinedFieldInspection */

namespace Tests\Src\Builder;

use Tests\ParserTestCase;
use Vanderlee\Comprehend\Builder\Definition;
use Vanderlee\Comprehend\Builder\Ruleset;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Text;

/**
 * @group structure
 * @group parser
 */
class RulesetTest extends ParserTestCase
{

    const CSV_RECORD = [__CLASS__, 'makeCsvRecordParser'];

    public static function makeCsvRecordParser($item, $delimiter = ',')
    {
        return new Sequence($item, new Repeat(new Sequence($delimiter, $item)));
    }

    public function testConstructorFunction()
    {
        $r    = new Ruleset('line', function ($char) {
            return new Repeat($char);
        });
        $line = $r->line('x');
        $this->assertResult(true, 5, $line('xxxxx'));
    }

    public function testConstructorMultiple()
    {
        $r   = new Ruleset(['Foo' => 'foo', 'Bar' => 'bar']);
        $foo = $r->Foo();
        $bar = $r->Bar();
        $this->assertResult(true, 3, $foo('foo'));
        $this->assertResult(true, 3, $bar('bar'));
    }

    public function testSetFunction()
    {
        $r       = new Ruleset;
        $r->line = function ($char) {
            return new Repeat($char);
        };
        $line    = $r->line('x');
        $this->assertResult(true, 5, $line('xxxxx'));
    }

    public function testSetFunctionException()
    {
        $r       = new Ruleset;
        $r->line = function () {
            return null;
        };
        $this->expectExceptionMessage("Cannot instantiate `line` using definition type `Closure`");
        $line    = $r->line;
        $line->match('x');

    }

    public function testSetClassName()
    {
        $r       = new Ruleset;
        $r->line = Text::class;
        $line    = $r->line('foo');
        $this->assertResult(true, 3, $line('foo'));
    }

    public function testSetUndefined()
    {
        $r       = new Ruleset;
        $r->line = null;
        $line    = $r->line();
        $this->expectExceptionMessage("Parser not defined");
        $line('foo');
    }

    public function testSetArray()
    {
        $r       = new Ruleset;
        $r->line = ['a', 'b'];
        $line    = $r->line();
        $this->assertResult(true, 2, $line('ab'));
    }

    public function testSetDefinition()
    {
        $r       = new Ruleset;
        $r->line = new Definition(function ($char) {
            return new Repeat($char);
        });
        $line    = $r->line('x');
        $this->assertResult(true, 5, $line('xxxxx'));
    }

    public function testSetParser()
    {
        $r       = new Ruleset;
        $r->line = new Repeat('x');
        $line    = $r->line();
        $this->assertResult(true, 5, $line('xxxxx'));
    }

    public function testSetForwardFunction()
    {
        $r       = new Ruleset;
        $line    = $r->line('x');
        $r->line = function ($char) {
            return new Repeat($char);
        };
        $this->assertResult(true, 5, $line('xxxxx'));
    }

    public function testSetForwardDefinition()
    {
        $r       = new Ruleset;
        $line    = $r->line('x');
        $r->line = new Definition(function ($char) {
            return new Repeat($char);
        });
        $this->assertResult(true, 5, $line('xxxxx'));
    }

    public function testSetForwardParser()
    {
        $r       = new Ruleset;
        $line    = $r->line();
        $r->line = new Repeat('x');
        $this->assertResult(true, 5, $line('xxxxx'));
    }

    public function testSetAndGetFunction()
    {
        $r       = new Ruleset;
        $r->line = function ($char = 'x') {
            return new Repeat($char);
        };
        $line    = $r->line;
        $this->assertResult(true, 5, $line('xxxxx'));
    }

    public function testSetAndGetParser()
    {
        $r       = new Ruleset;
        $r->line = new Repeat('x');
        $line    = $r->line;
        $this->assertResult(true, 5, $line('xxxxx'));
    }

    public function testSetToString()
    {
        $r = new Ruleset(Ruleset::ROOT, 'x');
        $this->assertEquals("'x'", (string)$r);
    }

    public function testSetDefaultNull()
    {
        $r = new Ruleset(Ruleset::ROOT, 1.234);
        $this->expectExceptionMessage("Cannot instantiate `ROOT` using definition type `double`");
        $r('foo');
    }

    public function testDefaultToString()
    {
        $r = new Ruleset;
        $this->assertEquals('', (string)$r);
    }

    public function testSetAndGetForwardFunction()
    {
        $r       = new Ruleset;
        $line    = $r->line;
        $r->line = function ($char = 'x') {
            return new Repeat($char);
        };
        $this->assertResult(true, 5, $line('xxxxx'));
    }

    public function testSetAndGetForwardParser()
    {
        $r       = new Ruleset;
        $line    = $r->line;
        $r->line = new Repeat('x');
        $this->assertResult(true, 5, $line('xxxxx'));
    }

    public function testSetAndGetForwardArray()
    {
        $r       = new Ruleset;
        $line    = $r->line;
        $r->line = ['a', 'b'];
        $this->assertResult(true, 2, $line('ab'));
    }

    public function testSetAndGetForwardBad()
    {
        $r = new Ruleset;
        $this->assertInstanceOf(Parser::class, $r->line);
        $this->expectExceptionMessage("Cannot redefine `line` using definition type `NULL`");
        $r->line = null;
    }

    public function testSetDefault()
    {
        $r = new Ruleset(Ruleset::ROOT, 'foo');
        $this->assertResult(true, 3, $r('foo'));
    }

    public function testSetDefaultFailure()
    {
        $r = new Ruleset(Ruleset::ROOT, 'foo');
        $this->assertResult(false, 0, $r('bar'));
    }

    public function testUnsetParser()
    {
        $r       = new Ruleset;
        $r->line = new Repeat('x');
        $line    = $r->line();
        $this->assertResult(true, 5, $line('xxxxx'));
        unset($r->line);
        $this->assertFalse(isset($r->line));
        $this->assertNull($r->line()->parser);
    }

    public function testStaticDefine()
    {
        // Function
        Ruleset::define('line', function ($char) {
            return new Repeat($char);
        });
        $this->assertTrue(Ruleset::defined('line'));
        $line = Ruleset::line('x');
        $this->assertResult(true, 5, $line('xxxxx'));

        Ruleset::undefine('line');
        $this->assertFalse(Ruleset::defined('line'));

        // Definition
        Ruleset::define('line', new Definition(function ($char) {
            return new Repeat($char);
        }));
        $this->assertTrue(Ruleset::defined('line'));
        $line = Ruleset::line('x');
        $this->assertResult(true, 5, $line('xxxxx'));

        Ruleset::undefine('line');
        $this->assertFalse(Ruleset::defined('line'));

        // Parser
        Ruleset::define('line', new Repeat('x'));
        $this->assertTrue(Ruleset::defined('line'));
        $line = Ruleset::line();
        $this->assertResult(true, 5, $line('xxxxx'));

        Ruleset::undefine('line');
        $this->assertFalse(Ruleset::defined('line'));
    }

    public function testInstanceDefine()
    {
        $r = new Ruleset;

        // Function
        $r->define('line', function ($char) {
            return new Repeat($char);
        });
        $this->assertTrue($r->defined('line'));
        $line = $r->line('x');
        $this->assertResult(true, 5, $line('xxxxx'));

        $r->undefine('line');
        $this->assertFalse($r->defined('line'));

        // Definition
        $r->define('line', new Definition(function ($char) {
            return new Repeat($char);
        }));
        $this->assertTrue($r->defined('line'));
        $line = $r->line('x');
        $this->assertResult(true, 5, $line('xxxxx'));

        $r->undefine('line');
        $this->assertFalse($r->defined('line'));

        // Parser
        $r->define('line', new Repeat('x'));
        $this->assertTrue($r->defined('line'));
        $line = $r->line();
        $this->assertResult(true, 5, $line('xxxxx'));

        $r->undefine('line');
        $this->assertFalse($r->defined('line'));
    }

    public function testStaticDefinePrivateMethods()
    {
        // set
        Ruleset::define('set', new Repeat('x'));
        $this->assertTrue(Ruleset::defined('set'));
        $line = Ruleset::set();
        $this->assertResult(true, 5, $line('xxxxx'));

        // call
        Ruleset::define('call', new Repeat('x'));
        $this->assertTrue(Ruleset::defined('call'));
        /** @noinspection Annotator */
        /** @noinspection PhpParamsInspection */
        $line = Ruleset::call();
        $this->assertResult(true, 5, $line('xxxxx'));
    }

    public function testStaticDefineReserved()
    {
        // define
        $this->expectExceptionMessage('Cannot define reserved name `define`');
        Ruleset::define('define', new Repeat('x'));
    }

    public function testInstanceCsv()
    {
        $r = new Ruleset();
        $r->define('Csv', new Definition(self::CSV_RECORD));

        $Csv = $r->Csv('x');
        $this->assertResult(true, 5, $Csv('x,x,x'));
    }

    public function testStaticCsv()
    {
        Ruleset::define('Csv', new Definition(self::CSV_RECORD));

        $Csv = Ruleset::Csv('x');
        $this->assertResult(true, 5, $Csv('x,x,x'));
    }
}

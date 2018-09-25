<?php /** @noinspection PhpUndefinedMethodInspection */

namespace tests\src\core;

use tests\ParserTestCase;
use vanderlee\comprehend\core\Token;
use vanderlee\comprehend\library\Library;
use vanderlee\comprehend\parser\structure\Choice;
use vanderlee\comprehend\parser\structure\Repeat;
use vanderlee\comprehend\parser\structure\Sequence;
use vanderlee\comprehend\parser\terminal\Set;
use vanderlee\comprehend\parser\terminal\Text;

/**
 * Node tests
 *
 * @author Martijn
 */
class TokenTest extends ParserTestCase
{

    public function testTokens()
    {
        $foo    = (new Text('foo'))->token('FooToken');
        $bar    = (new Text('bar'));
        $foobar = (new Sequence($foo, $bar))->token('FoobarToken', 'test');

        $match = $foobar->match('foobarbaz');
        $this->assertResult(true, 6, $match);

        $this->assertEquals([
            'group'    => 'test',
            'name'     => 'FoobarToken',
            'text'     => 'foobar',
            'offset'   => 0,
            'length'   => 6,
            'class'    => Sequence::class,
            'children' => [
                [
                    'group'    => null,
                    'name'     => 'FooToken',
                    'text'     => 'foo',
                    'offset'   => 0,
                    'length'   => 3,
                    'class'    => Text::class,
                    'children' => [],
                ],
                [
                    'group'    => null,
                    'name'     => '',
                    'text'     => 'bar',
                    'offset'   => 3,
                    'length'   => 3,
                    'class'    => Text::class,
                    'children' => [],
                ],
            ],
        ], $match->token->toArray());
    }

    public function testTokenIsTerminal()
    {
        $foo = (new Sequence('f', 'o', 'o'))->token('FooToken', null, true);

        $match = $foo->match('foo');
        $this->assertResult(true, 3, $match);

        $this->assertEquals([
            'group'    => null,
            'name'     => 'FooToken',
            'text'     => 'foo',
            'offset'   => 0,
            'length'   => 3,
            'class'    => Sequence::class,
            'children' => [],
        ], $match->token->toArray());
    }

    public function testRfcGroup()
    {
        $scheme     = Library::uri()->scheme;
        $colon      = (new Text(':'));
        $hier_start = (new Sequence($scheme, $colon))->token('HierStartToken');

        $match = $hier_start->match('foo:');
        $this->assertResult(true, 4, $match);

        $signatures = $this->extractTokenSignatures($match->token);

        $this->assertEquals([
            'HierStartToken' => [
                'Rfc3986::scheme' => [
                    Sequence::class => [
                        'Rfc2234::ALPHA' => [
                            Set::class => [],
                        ],
                        Repeat::class    => [
                            Choice::class => [
                                'Rfc2234::ALPHA' => [
                                    Set::class => [],
                                ],
                            ],
                        ],
                    ],
                ],
                Text::class       => [],
            ],
        ], $signatures);
    }

    public function testUndefinedProperty()
    {
        $foo   = (new Text('foo'))->token('Word');
        $match = $foo->match('foo');
        $this->assertResult(true, 3, $match);
        $this->expectExceptionMessage("Undefined property `i_do_not_exist`");
        /** @noinspection PhpUndefinedFieldInspection */
        $match->token->i_do_not_exist;
    }

    public function testToString()
    {
        $foo = (new Sequence('f', (new Text('oo'))->token('Ooh!')))->token('Word');

        $match = $foo->match('foo');
        $this->assertResult(true, 3, $match);

        $this->assertEquals('Word (`foo`)' . PHP_EOL
            . '  vanderlee\comprehend\parser\terminal\Char (`f`)' . PHP_EOL
            . '  Ooh! (`oo`)', (string)$match->token);
    }

    public function testJsonEncode()
    {
        $foo = (new Sequence('f', (new Text('oo'))->token('Ooh!')))->token('Word');

        $match = $foo->match('foo');
        $this->assertResult(true, 3, $match);

        $this->assertEquals('{"group":null,"name":"Word","text":"foo","offset":0,"length":3'
            . ',"class":"vanderlee\\\\comprehend\\\\parser\\\\structure\\\\Sequence","children":['
            . '{"group":null,"name":null,"text":"f","offset":0,"length":1'
            . ',"class":"vanderlee\\\\comprehend\\\\parser\\\\terminal\\\\Char","children":[]},'
            . '{"group":null,"name":"Ooh!","text":"oo","offset":1,"length":2'
            . ',"class":"vanderlee\\\\comprehend\\\\parser\\\\terminal\\\\Text","children":[]}'
            . ']}', json_encode($match->token));
    }

    public function testToXml()
    {
        $foo = (new Sequence('f', (new Text('oo'))->token('Ooh!', "Snap")))->token('Word');

        $match = $foo->match('foo');
        $this->assertResult(true, 3, $match);

        /** @noinspection HtmlUnknownTag */
        $this->assertEquals('<?xml version="1.0"?>' . "\n"
            . '<Word xmlns:Snap="Snap">'
            . '<vanderlee_comprehend_parser_terminal_Char>f</vanderlee_comprehend_parser_terminal_Char>'
            . '<Snap:Ooh_ xmlns:Snap="Snap">oo</Snap:Ooh_>'
            . '</Word>' . "\n", $match->token->toXml()->saveXML());
    }

    protected function extractTokenSignatures(Token $token)
    {
        $signature = ($token->group ? $token->group . '::' : '')
            . ($token->name ?? $token->class);

        $children = [];
        foreach ($token->children as $child) {
            $children += $this->extractTokenSignatures($child);
        }

        return [$signature => $children];
    }
}

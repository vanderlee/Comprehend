<?php

/** @noinspection PhpUndefinedMethodInspection */

namespace Tests\Src\Core;

use Tests\ParserTestCase;
use Vanderlee\Comprehend\Core\Token;
use Vanderlee\Comprehend\Library\Rfc3986;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Set;
use Vanderlee\Comprehend\Parser\Terminal\Text;

/**
 * Node tests.
 *
 * @author Martijn
 */
class TokenTest extends ParserTestCase
{
    public function testTokens()
    {
        $foo = (new Text('foo'))->token('FooToken');
        $bar = (new Text('bar'));
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
        $scheme = (new Rfc3986())->scheme;
        $colon = (new Text(':'));
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
        $foo = (new Text('foo'))->token('Word');
        $match = $foo->match('foo');
        $this->assertResult(true, 3, $match);
        $this->expectExceptionMessage('Undefined property `i_do_not_exist`');
        /* @noinspection PhpUndefinedFieldInspection */
        $match->token->i_do_not_exist;
    }

    public function testToString()
    {
        $foo = (new Sequence('f', (new Text('oo'))->token('Ooh!'),
            (new Text('bar'))->token('Bar', 'group')
        ))->token('Word');

        $match = $foo->match('foobar');
        $this->assertResult(true, 6, $match);

        $this->assertEquals('Word (`foobar`)'.PHP_EOL
            .'  Vanderlee\Comprehend\Parser\Terminal\Char (`f`)'.PHP_EOL
            .'  Ooh! (`oo`)'.PHP_EOL
            .'  group::Bar (`bar`)', (string) $match->token);
    }

    public function testJsonEncode()
    {
        $foo = (new Sequence('f', (new Text('oo'))->token('Ooh!')))->token('Word');

        $match = $foo->match('foo');
        $this->assertResult(true, 3, $match);

        $this->assertEquals('{"group":null,"name":"Word","text":"foo","offset":0,"length":3'
            .',"class":"Vanderlee\\\\Comprehend\\\\Parser\\\\Structure\\\\Sequence","children":['
            .'{"group":null,"name":null,"text":"f","offset":0,"length":1'
            .',"class":"Vanderlee\\\\Comprehend\\\\Parser\\\\Terminal\\\\Char","children":[]},'
            .'{"group":null,"name":"Ooh!","text":"oo","offset":1,"length":2'
            .',"class":"Vanderlee\\\\Comprehend\\\\Parser\\\\Terminal\\\\Text","children":[]}'
            .']}', json_encode($match->token));
    }

    public function testToXml()
    {
        $foo = (new Sequence('f', (new Text('oo'))->token('Ooh!', 'Snap')))->token('Word');

        $match = $foo->match('foo');
        $this->assertResult(true, 3, $match);

        /* @noinspection HtmlUnknownTag */
        $this->assertEquals('<?xml version="1.0"?>'."\n"
            .'<Word xmlns:Snap="Snap">'
            .'<Vanderlee_Comprehend_Parser_Terminal_Char>f</Vanderlee_Comprehend_Parser_Terminal_Char>'
            .'<Snap:Ooh_ xmlns:Snap="Snap">oo</Snap:Ooh_>'
            .'</Word>'."\n", $match->token->toXml()->saveXML());
    }

    protected function extractTokenSignatures(Token $token)
    {
        $signature = ($token->group
                ? $token->group.'::'
                : '')
            .($token->name
                ? $token->name
                : $token->class);

        $children = [];
        foreach ($token->children as $child) {
            $children += $this->extractTokenSignatures($child);
        }

        return [$signature => $children];
    }
}

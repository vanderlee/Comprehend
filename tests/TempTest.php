<?php

use \vanderlee\comprehend\core\Token;
use \vanderlee\comprehend\library\Library;
use \vanderlee\comprehend\parser\structure\Choice;
use \vanderlee\comprehend\parser\structure\Repeat;
use \vanderlee\comprehend\parser\structure\Sequence;
use \vanderlee\comprehend\parser\terminal\Set;
use \vanderlee\comprehend\parser\terminal\Text;

/**
 * Node tests
 *
 * @author Martijn
 */
class TempTest extends TestCase
{

    /**
     * @covers \vanderlee\comprehend\core\Token
     */
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

    /**
     * @covers \vanderlee\comprehend\core\Token
     */
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

    /**
     * @covers \vanderlee\comprehend\core\Token
     */
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
}

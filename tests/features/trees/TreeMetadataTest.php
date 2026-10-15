<?php

declare(strict_types=1);

namespace Tests\features\trees;

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Tree\ConcreteNode;

class TreeMetadataTest extends TestCase
{
    public function testTagsAreUniqueImmutableAndProjectToNearestNamedNodes(): void
    {
        $base = C::text('a')->named('letter');
        $tagged = $base->tag('word', 'word', 'token');
        $this->assertSame([], $base->parse('a')->ast()->tags);
        $result = C::sequence($tagged, C::text('b')->named('letter'))->tag('group')->parse('ab');
        $ast = $result->ast();
        $this->assertSame(['group', 'word', 'token'], $ast->firstChild()->tags);
        $this->assertSame(['group'], $ast->lastChild()->tags);
        $this->assertSame($ast->firstChild(), $ast->firstTagged('word'));
        $this->assertSame($ast->children, $ast->allTagged('group'));
        $this->assertNull($ast->firstTagged('missing'));
        $this->assertSame([], $ast->allTagged('missing'));
        $this->assertTrue($result->node->hasTag('group'));
        $this->assertSame(['group', 'word', 'token'], json_decode($ast->toJson(), true)['children'][0]['tags']);
        $this->assertSame('group', $ast->toXml()->getElementsByTagName('tag')[0]->textContent);
    }

    public function testDynamicNamesResolveOnceAndNeverReadSemanticValues(): void
    {
        $names = 0;
        $values = 0;
        $base = C::set('ab');
        $parser = $base->named(static function (ConcreteNode $node) use (&$names): string {
            ++$names;

            return 'letter_' . $node->rawText();
        })->mapValue(static function () use (&$values): int {
            ++$values;

            return 1;
        });
        $result = $parser->parse('a');
        $this->assertSame(1, $names);
        $this->assertSame('letter_a', $result->ast()->name);
        $result->ast()->toJson();
        $result->node->toXml();
        $this->assertSame(1, $names);
        $this->assertSame(0, $values);
        $this->assertNull($base->parse('a')->ast()->name);
        $this->assertSame('letter_b', $parser->parse('b')->ast()->name);
        $this->assertSame('letter_a', $result->ast()->name);
        $this->assertNull(C::text('a')->named(static fn() => null)->parse('a')->ast()->name);
    }

    public function testSurroundingSpansKeepTriviaPunctuationAndUtf8Bytes(): void
    {
        $ast = C::sequence(
            '(',
            C::text('é')->named('letter'),
            ', ',
            C::text('b')->named('letter'),
            ')',
        )->parse('(é, b)')->ast();
        $left = $ast->firstChild();
        $right = $ast->lastChild();
        $this->assertSame('(', $left->preambleText());
        $this->assertSame('', $left->postambleText());
        $this->assertSame(', ', $right->preambleText());
        $this->assertSame(')', $right->postambleText());
        $this->assertSame('(é, b)', $left->surroundedText() . $right->surroundedText());
        $this->assertSame('é', $left->rawText());
        $this->assertSame([3, 5], [$right->preamble[0]->start, $right->preamble[0]->end]);
        $this->assertSame(', ', $right->toArray()['preamble'][0]['text']);
        $this->assertSame(
            '(',
            base64_decode(
                $ast->toXml()->getElementsByTagName('preamble')[0]->getElementsByTagName('text')[0]->textContent,
            ),
        );
        $binary = C::sequence("\xff", C::text('a')->named('a'))->parse(new ByteSource("\xffa"))->ast()->firstChild();
        $this->assertSame('base64', $binary->toArray()['preamble'][0]['textEncoding']);
        $this->assertSame("\xffa", $binary->surroundedText());
    }

    public function testNamedParentOwnsItsInnerGapsWithoutDuplicatingOuterSyntax(): void
    {
        $pair = C::sequence('[', C::text('a')->named('a'), ']')->named('pair');
        $ast = C::sequence('(', $pair, ')')->parse('([a])')->ast();
        $this->assertSame('([a])', $ast->firstChild()->surroundedText());
        $this->assertSame('[a]', $ast->firstChild()->firstChild()->surroundedText());
        $this->assertSame('a', $ast->firstChild()->firstChild()->rawText());
    }

    public function testDynamicNameErrorsAndEmptyTagsAreExplicit(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Dynamic names must return string, int or null');
        C::text('a')->named(static fn() => [1])->parse('a');
    }

    public function testEmptyTagsAreRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        C::text('a')->tag('');
    }

    public function testTriviaGapsAndEmptyNamedNodesKeepStableSurroundings(): void
    {
        $trivia = C::text(' ')->many()->trivia();
        $body = C::sequence(C::text('a')->named('a'), C::nothing()->named('empty'), C::text('b')->named('b'))
            ->spacing(C::text(' ')->many());
        $ast = C::sequence($trivia, $body, $trivia)->parse(' a b ')->ast();
        $this->assertSame(['a', 'empty', 'b'], array_map(static fn($node) => $node->name, $ast->children));
        $this->assertSame(' a b ', implode('', array_map(static fn($node) => $node->surroundedText(), $ast->children)));
        $this->assertSame('', $ast->firstNamed('empty')->rawText());
    }
}

<?php

declare(strict_types=1);

namespace Tests\Src\Syntax;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Comprehend;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Syntax\Catalog;

final class CatalogTest extends TestCase
{
    /** @dataProvider repetitionFactories */
    public function testStaticRepetitionFactoriesPreserveBoundsAndNamedArguments(
        string $name,
        string $input,
        bool $accepted,
    ): void {
        $factory = Catalog::builtinStatic($name);
        $this->assertNotNull($factory);

        $expression = $factory(parser: 'a');

        $this->assertInstanceOf(Comprehend::class, $expression);
        $this->assertSame($accepted, $expression->parse($input)->success);
    }

    public static function repetitionFactories(): array
    {
        return [
            ['repeat', '', true],
            ['some', '', false],
            ['some', 'aa', true],
            ['PLUS', 'aa', true],
            ['many', '', true],
            ['star', 'aa', true],
            ['kleene', 'aa', true],
            ['maybe', '', true],
            ['maybe', 'a', true],
            ['maybe', 'aa', false],
            ['optional', 'aa', false],
        ];
    }

    public function testStaticRepetitionDetachesRawParsersAndRetainsExpressionVocabulary(): void
    {
        $parser = new Text('a');
        $repeated = Comprehend::repeat(parser: $parser, min: 2, max: 3);
        $parser->guard(static fn(): bool => false);

        $this->assertTrue($repeated->parse('aa')->success);
        $this->assertFalse($repeated->parse('a')->success);
        $this->assertFalse($repeated->parse('aaaa')->success);

        $syntax = Comprehend::syntax()->define('suffix', static fn(): Text => new Text('!'));
        $expression = Comprehend::repeat(parser: $syntax->text('a'), min: 2, max: 3)->suffix();

        $this->assertTrue($expression->parse('aaa!')->success);
    }

    public function testNamedSequenceArgumentsFlattenThePrefixInInsertionOrder(): void
    {
        $prefix = Comprehend::sequence('a', 'b');
        $sequence = Comprehend::sequence(prefix: $prefix, suffix: 'c');
        $parser = $sequence->parser();

        $this->assertInstanceOf(Sequence::class, $parser);
        $this->assertCount(3, iterator_to_array($parser));
        $this->assertTrue($sequence->parse('abc')->success);
        $this->assertTrue($prefix->parse('ab')->success);
    }

    public function testNamedSequenceArgumentsPreserveAnnotatedPrefixBoundaries(): void
    {
        $prefix = Comprehend::sequence('a', 'b')->named('prefix');
        $sequence = Comprehend::sequence(prefix: $prefix, suffix: 'c');
        $parser = $sequence->parser();

        $this->assertInstanceOf(Sequence::class, $parser);
        $this->assertCount(2, iterator_to_array($parser));
        $result = $sequence->parse('abc');
        $this->assertTrue($result->success);
        $this->assertSame('ab', $result->ast()->firstNamed('prefix')->rawText());
    }

    public function testCustomFactoriesAreCaseInsensitiveAndCatalogsAreImmutable(): void
    {
        $catalog = new Catalog();
        $factory = static fn(): Text => new Text('a');
        $extended = $catalog->define('Letter', $factory);

        $this->assertNull($catalog->static('letter'));
        $this->assertSame($factory, $extended->static('LETTER'));
        $this->assertNull($extended->fluent('letter'));
        $this->assertContains('letter', $extended->names());
        $this->assertSame('many', Catalog::canonicalName('KLEENE'));
    }

    /** @dataProvider reservedNames */
    public function testInvalidOrReservedNamesCannotOverrideBuiltins(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new Catalog())->define($name, static fn(): Text => new Text('a'));
    }

    public static function reservedNames(): array
    {
        return [
            ['text'], ['parse'], ['define'], ['plus'], ['invoke'], ['expression'],
            ['syntaxExpression'], ['not-a-method'], [''],
        ];
    }
}

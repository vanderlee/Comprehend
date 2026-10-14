<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend;

use Vanderlee\Comprehend\Builder\FrozenGrammar;
use Vanderlee\Comprehend\Builder\GraphSnapshot;
use Vanderlee\Comprehend\Builder\AbstractRuleset;
use Vanderlee\Comprehend\Directive\Space;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Regex;
use Vanderlee\Comprehend\Parser\Terminal\Text;

/** Immutable authoring facade over the existing object parser types. */
final readonly class Grammar
{
    private function __construct(private Parser $parser) {}

    public static function of(Parser $parser): self
    {
        return new self((new GraphSnapshot(false))->copy($parser));
    }

    public static function text(string $text): self { return self::of(new Text($text)); }
    public static function regex(string $pattern): self { return self::of(new Regex($pattern)); }
    public static function sequence(self|Parser ...$parsers): self
    {
        return new self(new Sequence(...array_map(self::unwrap(...), $parsers)));
    }

    public static function choice(self|Parser ...$parsers): self
    {
        return new self(new Choice(...array_map(self::unwrap(...), $parsers)));
    }
    public static function ref(AbstractRuleset $rules, string $name, mixed ...$arguments): self
    {
        return new self($rules->ref($name, ...$arguments));
    }

    public function then(self|Parser $next): self
    {
        return new self(new Sequence($this->parser, self::unwrap($next)));
    }

    public function or(self|Parser $next): self
    {
        return new self(new Choice($this->parser, self::unwrap($next)));
    }

    public function repeat(int $min = 0, ?int $max = null): self
    {
        return new self(new Repeat($this->parser, $min, $max));
    }

    public function named(string|int $name, ?string $group = null, bool $leaf = false): self
    {
        return new self($this->parser()->name($name, $group, $leaf));
    }

    public function mapValue(callable $mapper): self
    {
        return new self($this->parser()->mapValue($mapper));
    }

    public function spacing(self|Parser|null $trivia): self
    {
        return new self(new Space($trivia === null ? null : self::unwrap($trivia), $this->parser));
    }

    public function lexeme(): self { return new self($this->parser->lexeme()); }
    public function trivia(): self { return new self($this->parser->trivia()); }

    /** Export a detached object graph for Ruleset factories and object constructors. */
    public function parser(): Parser { return (new GraphSnapshot(false))->copy($this->parser); }
    public function freeze(): FrozenGrammar { return $this->parser->freeze(); }

    private static function unwrap(self|Parser $parser): Parser
    {
        return $parser instanceof self ? $parser->parser : (new GraphSnapshot(false))->copy($parser);
    }
}

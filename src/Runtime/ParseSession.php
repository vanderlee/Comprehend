<?php

declare (strict_types=1);
namespace Vanderlee\Comprehend\Runtime;

use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Result\Diagnostic;
use Vanderlee\Comprehend\Runtime\Scope\CaseSensitiveContextTrait;
use Vanderlee\Comprehend\Runtime\Scope\PreferContextTrait;
use Vanderlee\Comprehend\Runtime\Scope\SpacingContextTrait;
use Vanderlee\Comprehend\Directive\Prefer;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Source\Span;
/**
 * Maintains the current context of the parser chain.
 *
 * @author Martijn
 */
final class ParseSession
{
    use CaseSensitiveContextTrait;
    use PreferContextTrait;
    use SpacingContextTrait;
    private readonly Source $source;
    private ?int $farthestFailure = null;
    private array $expectations = [];
    private array $rulePaths = [];
    private array $path = [];
    private int $failureRevision = 0;
    private int $lexemeDepth = 0;
    private array $activeParsers = [];

    public function enterParser(Parser $parser, int $offset): void
    {
        $key = spl_object_id($parser) . ':' . $offset;
        if (isset($this->activeParsers[$key])) {
            throw new \LogicException('Left recursion at byte ' . $offset . ': ' . implode(' -> ', $this->path)
                . ' -> ' . $parser->graphLabel());
        }
        $this->activeParsers[$key] = true;
    }

    public function leaveParser(Parser $parser, int $offset): void
    {
        unset($this->activeParsers[spl_object_id($parser) . ':' . $offset]);
    }
    public function pushLexeme(): void
    {
        ++$this->lexemeDepth;
    }
    public function popLexeme(): void
    {
        --$this->lexemeDepth;
    }
    public function isLexeme(): bool
    {
        return $this->lexemeDepth > 0;
    }
    public function pushRule(string $rule): void
    {
        $this->path[] = $rule;
    }
    public function popRule(): void
    {
        array_pop($this->path);
    }
    public function failureRevision(): int
    {
        return $this->failureRevision;
    }
    public function diagnosticState(): array
    {
        return [$this->farthestFailure, $this->expectations, $this->rulePaths, $this->failureRevision];
    }
    public function restoreDiagnostics(array $state): void
    {
        [$this->farthestFailure, $this->expectations, $this->rulePaths, $this->failureRevision] = $state;
    }
    public function diagnostic(): ?Diagnostic
    {
        return $this->farthestFailure === null ? null : new Diagnostic($this->source, $this->farthestFailure, $this->expectations, $this->rulePaths);
    }
    public function __construct(Source $source, ?Parser $trivia = null, bool $caseSensitive = true, string $preference = Prefer::FIRST)
    {
        $this->source = $source;
        self::assertPreference($preference);
        $this->pushSpacer($trivia);
        $this->pushCaseSensitivity($caseSensitive);
        $this->pushPreference($preference);
    }
    public function source(): Source
    {
        return $this->source;
    }
    public function recordFailure(int $byteOffset, ?string $expected = null, ?array $paths = null): void
    {
        $this->source->span($byteOffset, $byteOffset);
        ++$this->failureRevision;
        if ($this->farthestFailure === null || $byteOffset > $this->farthestFailure) {
            $this->farthestFailure = $byteOffset;
            $this->expectations = [];
            $this->rulePaths = [];
        }
        if ($byteOffset !== $this->farthestFailure) {
            return;
        }
        if ($expected !== null && !in_array($expected, $this->expectations, true)) {
            $this->expectations[] = $expected;
        }
        foreach ($paths ?? [$this->path] as $path) {
            if (!in_array($path, $this->rulePaths, true)) {
                $this->rulePaths[] = $path;
            }
        }
    }
    public function farthestFailure(): ?int
    {
        return $this->farthestFailure;
    }
    public function readSourceUnit(int $byteOffset): ?Span
    {
        return $this->source->readUnit($byteOffset);
    }
}

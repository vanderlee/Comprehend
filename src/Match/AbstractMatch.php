<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Match;

use InvalidArgumentException;
use Vanderlee\Comprehend\Result\Diagnostic;
use Vanderlee\Comprehend\Source\Span;
use Vanderlee\Comprehend\Tree\ConcreteNode;

/**
 * Carries source metadata and observed progress between recognition operations. ParseResult
 * wraps these internal execution records as the public result API.
 *
 * @internal Execution record, not a public result or serialization model.
 */
abstract class AbstractMatch
{
    public readonly bool $accepted;
    public ?Span $span = null;
    public ?int $farthestFailure = null;
    public ?Diagnostic $diagnostic = null;
    public ?ConcreteNode $node = null;

    /** $length is byte progress; failed attempts still consume zero bytes. */
    public function __construct(public readonly int $length = 0)
    {
        if ($length < 0) {
            throw new InvalidArgumentException('Recognition progress cannot be negative');
        }
        $this->accepted = $this instanceof Success;
    }

    public function setConcreteNode(ConcreteNode $node): void
    {
        if (!$this->accepted || $node->span !== $this->span) {
            throw new InvalidArgumentException('Concrete root must use the successful match span');
        }
        $this->node = $node;
    }

    public function setSourceMetadata(Span $span, ?int $farthestFailure, ?Diagnostic $diagnostic = null): void
    {
        if (!$this->accepted && $span->byteLength() !== 0) {
            throw new InvalidArgumentException('Failed matches must have zero-width spans');
        }
        // A diagnostic has already checked this exact source position at construction.
        if (
            $farthestFailure !== null && (
                $diagnostic === null
                || $diagnostic->source !== $span->source
                || $diagnostic->byteOffset !== $farthestFailure
            )
        ) {
            Span::assertByteOffset($span->source, $farthestFailure);
        }
        $this->span = $span;
        $this->farthestFailure = $farthestFailure;
        $this->diagnostic = $diagnostic;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function getResults(): array
    {
        return [];
    }

    public function getResult(?string $name = null, mixed $default = null): mixed
    {
        return $default;
    }
}

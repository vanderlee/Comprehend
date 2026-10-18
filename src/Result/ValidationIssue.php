<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Result;

use InvalidArgumentException;
use JsonException;
use JsonSerializable;
use Vanderlee\Comprehend\Source\Span;

/** Immutable validation details with source-bound byte coordinates and JSON-shaped context. */
final readonly class ValidationIssue implements JsonSerializable
{
    /** @var array<array-key, mixed> */
    public array $context;

    /** @param array<array-key, mixed> $context */
    public function __construct(
        public string $code,
        public string $message,
        public Span $span,
        array $context = [],
    ) {
        if (trim($code) === '') {
            throw new InvalidArgumentException('Validation issue codes must not be blank');
        }
        $snapshot = self::snapshotContext($context);
        try {
            json_encode($snapshot, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new InvalidArgumentException('Validation context must be JSON-compatible', 0, $error);
        }
        $this->context = $snapshot;
    }

    /**
     * Copy recursively so caller-owned references cannot mutate a retained issue.
     * @param array<array-key, mixed> $context
     * @return array<array-key, mixed>
     */
    private static function snapshotContext(array $context, int $depth = 0): array
    {
        if ($depth >= 512) {
            throw new InvalidArgumentException('Validation context must be finite and within JSON depth limits');
        }
        $snapshot = [];
        foreach ($context as $key => $value) {
            if (is_array($value)) {
                $snapshot[$key] = self::snapshotContext($value, $depth + 1);
            } elseif (
                ($value !== null && !is_scalar($value))
                || (is_float($value) && !is_finite($value))
            ) {
                throw new InvalidArgumentException('Validation context must contain only scalars, null and arrays');
            } else {
                $snapshot[$key] = $value;
            }
        }

        return $snapshot;
    }

    /** @return array{code: string, message: string, span: array{start: int, end: int}, context: array<array-key, mixed>} */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'message' => $this->message,
            'span' => ['start' => $this->span->start, 'end' => $this->span->end],
            'context' => $this->context,
        ];
    }

    /** @return array{code: string, message: string, span: array{start: int, end: int}, context: array<array-key, mixed>} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }
}

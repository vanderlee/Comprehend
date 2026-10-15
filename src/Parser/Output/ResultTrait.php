<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Parser\Output;

use Vanderlee\Comprehend\Source\Span;
use Stringable;
use Throwable;
use UnexpectedValueException;

/**
 * Configures named collection operations on Parser for later semantic reads. Selected Success
 * records apply set, concatenation and push operations with cached transformed values.
 */
trait ResultTrait
{
    /**
     * @var list<callable(Span): callable>
     */
    private array $resultCallbacks = [];

    public function setResult(?string $key = null, mixed $value = null): static
    {
        return $this->collect('set', $key, $value);
    }

    private function collect(string $operation, ?string $key, mixed $value): static
    {
        $this->resultCallbacks[] = static function (Span $span) use ($operation, $key, $value) {
            $evaluated = false;
            $mapped = null;
            $error = null;

            return static function (array &$results) use (
                $span,
                $operation,
                $key,
                $value,
                &$evaluated,
                &$mapped,
                &$error
            ) {
                if (!$evaluated) {
                    $evaluated = true;
                    try {
                        $mapped = is_callable($value) ? $value($span->rawText()) : $value ?? $span->rawText();
                    } catch (Throwable $failure) {
                        $error = $failure;
                    }
                }
                if ($error !== null) {
                    throw $error;
                }

                self::applyCollectionResult($results, $operation, $key ?? '', $mapped);
            };
        };

        return $this;
    }

    /** @param array<array-key, mixed> $results */
    private static function applyCollectionResult(array &$results, string $operation, string $name, mixed $value): void
    {
        if ($operation === 'set') {
            $results[$name] = $value;

            return;
        }
        if (!array_key_exists($name, $results)) {
            $results[$name] = $operation === 'push' ? [$value] : self::resultText($value);

            return;
        }

        if ($operation === 'push') {
            if (is_array($results[$name])) {
                $results[$name][] = $value;
            } else {
                $results[$name] = [$results[$name], $value];
            }
        } elseif (is_array($results[$name])) {
            $results[$name][] = self::resultText(array_pop($results[$name])) . self::resultText($value);
        } else {
            $results[$name] = self::resultText($results[$name]) . self::resultText($value);
        }
    }

    private static function resultText(mixed $value): string
    {
        if (
            is_scalar($value)
            || $value === null
            || is_resource($value)
            || $value instanceof Stringable
        ) {
            return (string) $value;
        }

        throw new UnexpectedValueException('Concatenated results must be scalar, null, resources or Stringable');
    }

    public function concatResult(?string $key = null, mixed $value = null): static
    {
        return $this->collect('concat', $key, $value);
    }

    public function pushResult(?string $key = null, mixed $value = null): static
    {
        return $this->collect('push', $key, $value);
    }
}

<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Parser\Output;

use Vanderlee\Comprehend\Source\Span;

/** Named collection operations; transformed values are cached per recognition. */
trait ResultTrait
{
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
                if ($error !== null) {
                    throw $error;
                }
                if (!$evaluated) {
                    $evaluated = true;
                    try {
                        $mapped = is_callable($value) ? $value($span->rawText()) : $value ?? $span->rawText();
                    } catch (\Throwable $failure) {
                        $error = $failure;
                        throw $failure;
                    }
                }
                $name = $key ?? '';
                if ($operation === 'set') {
                    $results[$name] = $mapped;
                    return;
                }
                if (!array_key_exists($name, $results)) {
                    $results[$name] = $operation === 'push' ? [$mapped] : (string) $mapped;
                    return;
                }

                if ($operation === 'push') {
                    if (is_array($results[$name])) {
                        $results[$name][] = $mapped;
                    } else {
                        $results[$name] = [$results[$name], $mapped];
                    }
                } elseif (is_array($results[$name])) {
                    $results[$name][] = array_pop($results[$name]) . $mapped;
                } else {
                    $results[$name] .= $mapped;
                }
            };
        };
        return $this;
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

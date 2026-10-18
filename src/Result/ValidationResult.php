<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Result;

use InvalidArgumentException;
use JsonSerializable;

/**
 * Carries semantic acceptance and immutable validation issues. Allows ParseResult to report
 * validation separately from syntactic success and recognition diagnostics.
 */
final class ValidationResult implements JsonSerializable
{
    public readonly bool $valid;
    /** @var list<ValidationIssue> */
    public readonly array $issues;

    /** @param list<ValidationIssue> $issues */
    public function __construct(array $issues = [])
    {
        $this->issues = self::snapshotIssues($issues);
        $this->valid = $this->issues === [];
    }

    /** @param array<array-key, mixed> $issues
     * @return list<ValidationIssue>
     */
    private static function snapshotIssues(array $issues): array
    {
        if (!array_is_list($issues)) {
            throw new InvalidArgumentException('Validation issues must be a list');
        }
        $snapshot = [];
        foreach ($issues as $issue) {
            if (!$issue instanceof ValidationIssue) {
                throw new InvalidArgumentException('Validation results must contain ValidationIssue objects');
            }
            $snapshot[] = $issue;
        }

        return $snapshot;
    }

    /** @return array{valid: bool, issues: list<array<string, mixed>>} */
    public function toArray(): array
    {
        return ['valid' => $this->valid, 'issues' => array_map(
            static fn(ValidationIssue $issue): array => $issue->toArray(),
            $this->issues,
        )];
    }

    /** @return array{valid: bool, issues: list<array<string, mixed>>} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }
}

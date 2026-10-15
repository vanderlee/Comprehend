<?php

declare(strict_types=1);

namespace Tests\Src\Runtime;

use PHPUnit\Framework\TestCase;
use ValueError;
use Vanderlee\Comprehend\Runtime\ChoicePolicy;

final class ChoicePolicyTest extends TestCase
{
    /** @dataProvider supportedPolicies */
    public function testSupportedPoliciesKeepTheirPublicStringValues(ChoicePolicy $policy, string $expected): void
    {
        $this->assertSame($expected, $policy->value);
        $this->assertSame($policy, ChoicePolicy::from($expected));
    }

    /** @return iterable<string, array{ChoicePolicy, string}> */
    public static function supportedPolicies(): iterable
    {
        yield 'first' => [ChoicePolicy::FIRST, 'first'];
        yield 'longest' => [ChoicePolicy::LONGEST, 'longest'];
        yield 'shortest' => [ChoicePolicy::SHORTEST, 'shortest'];
    }

    /** @dataProvider unsupportedPolicies */
    public function testUnsupportedPoliciesAreRejected(string $policy): void
    {
        $this->expectException(ValueError::class);

        ChoicePolicy::from($policy);
    }

    /** @return iterable<string, array{string}> */
    public static function unsupportedPolicies(): iterable
    {
        yield 'empty' => [''];
        yield 'uppercase' => ['FIRST'];
        yield 'unknown' => ['unknown'];
        yield 'whitespace' => [' first '];
    }
}

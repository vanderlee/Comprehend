<?php

declare(strict_types=1);

namespace Tests\Src\Runtime;

use DomainException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Runtime\ChoicePolicy;

final class ChoicePolicyTest extends TestCase
{
    /** @dataProvider supportedPolicies */
    public function testSupportedPoliciesKeepTheirPublicStringValues(string $policy, string $expected): void
    {
        ChoicePolicy::assertValid($policy);

        $this->assertSame($expected, $policy);
    }

    /** @return iterable<string, array{string, string}> */
    public static function supportedPolicies(): iterable
    {
        yield 'first' => [ChoicePolicy::FIRST, 'first'];
        yield 'longest' => [ChoicePolicy::LONGEST, 'longest'];
        yield 'shortest' => [ChoicePolicy::SHORTEST, 'shortest'];
    }

    /** @dataProvider unsupportedPolicies */
    public function testUnsupportedPoliciesAreRejected(string $policy): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Invalid preference `' . $policy . '`');

        ChoicePolicy::assertValid($policy);
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

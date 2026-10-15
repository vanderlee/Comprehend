<?php

declare(strict_types=1);

namespace Tests\Src\Builder;

use LogicException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Builder\RuleReference;
use Vanderlee\Comprehend\Builder\Ruleset;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Source\ByteSource;

final class RuleReferenceTest extends TestCase
{
    public function testBindingATargetDetachesTheReferenceFromRedefinitions(): void
    {
        $rules = new Ruleset(['item' => new Text('a')]);
        $reference = new RuleReference($rules, 'item');
        $reference->bindSnapshotTarget($reference->graphTarget());
        $rules->define('item', new Text('b'));

        $this->assertSame('item', (string) $reference);
        $this->assertTrue($reference->parseAll(new ByteSource('a'))->success);
        $this->assertFalse($reference->parseAll(new ByteSource('b'))->success);
    }

    public function testUnresolvedReferenceReportsItsRuleName(): void
    {
        $reference = new RuleReference(new Ruleset(), 'missing');
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Undefined grammar rule: missing');

        $reference->graphTarget();
    }
}

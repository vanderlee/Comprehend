<?php

declare(strict_types=1);

namespace Tests\Src\Builder;

use LogicException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Builder\AbstractRuleset;
use Vanderlee\Comprehend\Parser\Terminal\Text;

final class AbstractRulesetTest extends TestCase
{
    public function testSubclassGroupAndRootAreUsedForRecognition(): void
    {
        $rules = $this->getMockForAbstractClass(AbstractRuleset::class, [['item' => new Text('a')], 'example']);
        $rules->root('item');
        $result = $rules->freeze()->parse('a');

        $this->assertTrue($result->success);
        $this->assertSame('item', $result->ast()->firstChild()->name);
        $this->assertSame('example', $result->ast()->firstChild()->group);
        $this->assertSame($rules->graphIdentity(), $rules->graphIdentity());
    }

    public function testUndefiningARuleInvalidatesItsCachedImplementation(): void
    {
        $rules = $this->getMockForAbstractClass(AbstractRuleset::class, [['item' => new Text('a')]]);
        $rules->resolveRule('item');
        $rules->undefine('item');

        $this->assertFalse($rules->defined('item'));
        $this->expectException(LogicException::class);
        $rules->resolveRule('item');
    }
}

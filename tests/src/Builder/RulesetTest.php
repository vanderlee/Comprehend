<?php

declare(strict_types=1);

namespace Tests\Src\Builder;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Builder\Ruleset;
use Vanderlee\Comprehend\Parser\Terminal\Text;

final class RulesetTest extends TestCase
{
    public function testRedefinitionAffectsFutureSnapshots(): void
    {
        $rules = (new Ruleset(['item' => new Text('a')]))->root('item');
        $frozen = $rules->freeze();
        $rules->define('item', new Text('b'));

        $this->assertTrue($frozen->parse('a')->success);
        $this->assertFalse($frozen->parse('b')->success);
        $this->assertTrue($rules->freeze()->parse('b')->success);
    }

    public function testEmptyRuleNamesAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new Ruleset())->define('', new Text('a'));
    }
}

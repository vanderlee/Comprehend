<?php

declare(strict_types=1);

namespace Tests\Src\Syntax;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Syntax\Catalog;

final class CatalogTest extends TestCase
{
    public function testCustomFactoriesAreCaseInsensitiveAndCatalogsAreImmutable(): void
    {
        $catalog = new Catalog();
        $factory = static fn(): Text => new Text('a');
        $extended = $catalog->define('Letter', $factory);

        $this->assertNull($catalog->start('letter'));
        $this->assertSame($factory, $extended->start('LETTER'));
        $this->assertNull($extended->continuation('letter'));
        $this->assertContains('letter', $extended->names());
        $this->assertSame('many', Catalog::canonicalName('KLEENE'));
    }

    /** @dataProvider reservedNames */
    public function testInvalidOrReservedNamesCannotOverrideBuiltins(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new Catalog())->define($name, static fn(): Text => new Text('a'));
    }

    public static function reservedNames(): array
    {
        return [['text'], ['parse'], ['define'], ['plus'], ['not-a-method'], ['']];
    }
}

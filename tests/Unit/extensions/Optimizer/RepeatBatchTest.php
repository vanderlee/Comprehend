<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Runtime\ParseLimits;
use Vanderlee\Comprehend\Runtime\ParseLimitException;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Builder\GraphSnapshot;
use Vanderlee\Comprehend\Extensions\Optimizer\GraphOptimizer;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Utf8Source;
use Vanderlee\Comprehend\Source\ByteFileSource;
use Vanderlee\Comprehend\Source\Utf8FileSource;

uses(TestCase::class);

test('literal repetition batches preserve trees values and ordered diagnostics', function (
    string $literal,
    int $count,
    string $suffix,
    int $minimum,
    ?int $maximum,
): void {
    $parser = (new Repeat(new Text($literal), $minimum, $maximum))->name('run');
    $input = '>' . str_repeat($literal, $count) . $suffix;
    foreach ([ByteSource::class, Utf8Source::class, ByteFileSource::class, Utf8FileSource::class] as $class) {
        $stream = null;
        if ($class === ByteFileSource::class || $class === Utf8FileSource::class) {
            $stream = tmpfile();
            fwrite($stream, $input);
            $source = new $class($stream, pageSize: 4);
        } else {
            $source = new $class($input);
        }
        try {
            $ordinary = $parser->freeze(0)->matchPrefix($source, 1);
            $batched = $parser->freeze()->matchPrefix($source, 1);
            $this->assertSame($ordinary->success, $batched->success);
            $this->assertSame($ordinary->end, $batched->end);
            $this->assertEquals($ordinary->node, $batched->node);
            $this->assertEquals($ordinary->diagnostic, $batched->diagnostic);
            $this->assertSame($ordinary->farthestFailure, $batched->farthestFailure);
            $this->assertSame($ordinary->value(), $batched->value());
            $this->assertEquals($ordinary->ast(), $batched->ast());
            if ($batched->success) {
                $this->assertCount($maximum === null ? $count : min($count, $maximum), $batched->node->children);
            }
        } finally {
            if ($stream !== null) {
                $source->close();
                fclose($stream);
            }
        }
    }
})->with([
    'multiple chunks and partial operand' => ['abc', 6000, 'ab!', 1, null],
    'EOF probe' => ['ab', 12, '', 0, null],
    'empty accepted' => ['ab', 0, '', 0, null],
    'minimum failure progress' => ['abc', 2, 'ab!', 3, null],
    'bounded' => ['ab', 30, '!', 2, 17],
    'zero maximum' => ['ab', 3, '', 0, 0],
    'regex metacharacters and null' => ["[.\0", 3000, '!', 1, 3000],
    'Unicode fallback' => ["é", 4, '!', 1, null],
    'maximum operand width' => [str_repeat('a', 8192), 2, '!', 1, null],
    'oversized operand fallback' => [str_repeat('a', 8193), 2, '!', 1, null],
]);

test('literal repetition retains operand lifecycle and trivia fallbacks', function (): void {
    $calls = 0;
    $child = (new Text('ab'))->name('pair')->mapValue(static fn(): int => 42)
        ->onCommit(static function () use (&$calls): void {
            ++$calls;
        });
    $result = Repeat::exact($child, 3)->spacing(new Text(' '))->freeze()->parse('ab ab ab');
    $this->assertTrue($result->success);
    $this->assertSame(0, $calls);
    $this->assertCount(5, $result->node->children);
    $result->commit();
    $result->commit();
    $this->assertSame(3, $calls);
    $plain = Repeat::exact($child, 3)->freeze()->parse('ababab');
    $this->assertTrue($plain->success);
    $this->assertSame('pair', $plain->node->children[0]->name);
    $this->assertSame(3, $calls);
    $plain->commit();
    $this->assertSame(6, $calls);
    $folded = Repeat::plus((new Text('ab'))->caseInsensitive());
    $this->assertEquals($folded->freeze(0)->parse('ABab')->node, $folded->freeze()->parse('ABab')->node);
});

test('literal repetition preserves exact exhausted limits', function (): void {
    $parser = Repeat::exact(new Text('ab'), 20);
    foreach ([new ParseLimits(maxWork: 17), new ParseLimits(maxDepth: 1)] as $limits) {
        $errors = [];
        foreach ([0, 1] as $flags) {
            try {
                $parser->freeze($flags)->parse(str_repeat('ab', 20), limits: $limits);
                $this->fail('Expected exhausted limit');
            } catch (ParseLimitException $error) {
                $errors[] = [$error->limit, $error->byteOffset, $error->rulePath, $error->maximum];
            }
        }
        $this->assertSame($errors[0], $errors[1]);
    }
});

test('literal repetition accounts for original work including the terminating partial probe', function (): void {
    foreach ([['ab', 3, 'a!'], ['abc', 4000, 'ab!']] as [$literal, $count, $suffix]) {
        $parser = Repeat::plus(new Text($literal));
        $copy = (new GraphSnapshot(optimize: true))->copy($parser);
        (new GraphOptimizer())->prepare($copy);
        $source = new ByteSource(str_repeat($literal, $count) . $suffix);
        $work = [];
        foreach ([$parser, $copy] as $candidate) {
            $session = new ParseSession($source);
            $candidate->recognizeInSession($source, 0, $session);
            $work[] = $session->workUsed();
        }
        $this->assertSame($work[0], $work[1]);
    }
});

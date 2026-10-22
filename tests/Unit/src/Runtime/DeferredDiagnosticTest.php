<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Result\Diagnostic;
use Vanderlee\Comprehend\Runtime\DeferredDiagnostic;
use Vanderlee\Comprehend\Source\ByteSource;

uses(TestCase::class);

test('deferred unions select farther observations without normalizing their details', function (): void {
    $source = new ByteSource('ab');
    $near = new Diagnostic($source, 0, ['near'], [['near']]);
    $far = new Diagnostic($source, 2, ['far', 'far'], [['0'], ['00']]);
    $this->assertNull(DeferredDiagnostic::resolve(DeferredDiagnostic::merge($source, null, null)));
    $this->assertSame($near, DeferredDiagnostic::merge($source, null, $near));
    $this->assertSame($near, DeferredDiagnostic::merge($source, $near, null));
    $this->assertSame($far, DeferredDiagnostic::merge($source, $near, $far));
    $this->assertSame($far, DeferredDiagnostic::merge($source, $far, $near));
    $this->assertSame($far, DeferredDiagnostic::resolve($far));
});

test('shared union branches flatten in first occurrence order and cache immutable output', function (): void {
    $source = new ByteSource('');
    $a = new Diagnostic($source, 0, ['a'], [['a']]);
    $b = new Diagnostic($source, 0, ['b'], [['b']]);
    $c = new Diagnostic($source, 0, ['c'], [['c']]);
    $shared = DeferredDiagnostic::merge($source, $a, $b);
    $union = DeferredDiagnostic::merge($source, $shared, DeferredDiagnostic::merge($source, $c, $shared));
    $this->assertInstanceOf(DeferredDiagnostic::class, $union);
    $this->assertSame($shared, DeferredDiagnostic::merge($source, $shared, $shared));
    $diagnostic = DeferredDiagnostic::resolve($union);
    $this->assertSame(['a', 'b', 'c'], $diagnostic->expected);
    $this->assertSame([['a'], ['b'], ['c']], $diagnostic->rulePaths);
    $this->assertSame($diagnostic, DeferredDiagnostic::resolve($union));
    $this->assertSame(['a', 'b'], DeferredDiagnostic::resolve($shared)->expected);
    $this->assertSame(['a'], $a->expected);
    $this->assertSame(['b'], $b->expected);
});

test('unions reuse already observed operands when their ordered details are unchanged', function (): void {
    $source = new ByteSource('');
    $a = new Diagnostic($source, 0, ['a'], [['a']]);
    $b = new Diagnostic($source, 0, ['b'], [['b']]);
    $shared = DeferredDiagnostic::merge($source, $a, $b);
    $snapshot = DeferredDiagnostic::resolve($shared);
    $this->assertSame($snapshot, DeferredDiagnostic::resolve(DeferredDiagnostic::merge($source, $shared, $a)));
    $this->assertSame($snapshot, DeferredDiagnostic::resolve(DeferredDiagnostic::merge($source, $a, $shared)));
    $this->assertSame($a, DeferredDiagnostic::resolve(DeferredDiagnostic::merge($source, $a, $a)));
});

test('an immediately required union preserves eager output without a pending node', function (): void {
    $source = new ByteSource('');
    $a = new Diagnostic($source, 0, ['a'], [['a']]);
    $b = new Diagnostic($source, 0, ['b'], [['b']]);
    $result = DeferredDiagnostic::mergeMaterialized($source, $a, $b);
    $this->assertInstanceOf(Diagnostic::class, $result);
    $this->assertSame(['a', 'b'], $result->expected);
    $this->assertSame([['a'], ['b']], $result->rulePaths);
});

test('ordinary unions preserve binary labels strict path boundaries and insertion order', function (): void {
    $source = new ByteSource('');
    $a = new Diagnostic($source, 0, ['', '0', '00', "a\0b"], [[], ['a', 'b'], ["a\0b"]]);
    $b = new Diagnostic($source, 0, ["\xff", '00', '0'], [["\xff"], ['a', 'b'], []]);
    $union = DeferredDiagnostic::merge($source, $a, $b);
    $this->assertInstanceOf(DeferredDiagnostic::class, $union);
    $result = DeferredDiagnostic::resolve($union);
    $this->assertSame(['', '0', '00', "a\0b", "\xff"], $result->expected);
    $this->assertSame([[], ['a', 'b'], ["a\0b"], ["\xff"]], $result->rulePaths);
});

test('observed unions release their operand records without collecting cycles', function (): void {
    $source = new ByteSource('');
    $a = new Diagnostic($source, 0, ['a'], [['a']]);
    $b = new Diagnostic($source, 0, ['b'], [['b']]);
    $aReference = WeakReference::create($a);
    $bReference = WeakReference::create($b);
    $union = DeferredDiagnostic::merge($source, $a, $b);
    unset($a, $b);
    $result = DeferredDiagnostic::resolve($union);
    $this->assertNull($aReference->get());
    $this->assertNull($bReference->get());
    $this->assertSame(['a', 'b'], $result->expected);
    $this->assertSame($result, DeferredDiagnostic::resolve($union));
});

test('identical probes share their existing snapshot instead of retaining a pending history', function (): void {
    $source = new ByteSource('');
    $first = new Diagnostic($source, 0, ['same'], [['same']]);
    $history = $first;
    for ($i = 0; $i < 2048; ++$i) {
        $history = DeferredDiagnostic::merge($source, $history, new Diagnostic($source, 0, ['same'], [['same']]));
    }
    $this->assertSame($first, $history);
});

test('numeric path unions preserve the original pairwise regular comparison', function (): void {
    $source = new ByteSource('');
    $actual = null;
    $expected = null;
    foreach (['2', '10', '1a', '0', '00', '0e0', ' 0', '', 'a', "\xff"] as $label) {
        $right = new Diagnostic($source, 0, [$label], [[$label], ['0'], ['00']]);
        $actual = DeferredDiagnostic::merge($source, $actual, $right);
        $expected = $expected === null ? $right : new Diagnostic(
            $source,
            0,
            array_values(array_unique(array_merge($expected->expected, $right->expected))),
            array_values(array_unique(array_merge($expected->rulePaths, $right->rulePaths), SORT_REGULAR)),
        );
        $this->assertSame($expected->toArray(), DeferredDiagnostic::resolve($actual)->toArray());
    }
});

test('numeric paths after a shared ordinary history retain its original merge boundary', function (): void {
    $source = new ByteSource('');
    $a = new Diagnostic($source, 0, ['a'], [['a']]);
    $b = new Diagnostic($source, 0, ['b'], [['b']]);
    $history = DeferredDiagnostic::merge($source, $a, $b);
    $numeric = new Diagnostic($source, 0, ['number'], [['0'], ['00']]);
    $result = DeferredDiagnostic::resolve(DeferredDiagnostic::merge($source, $history, $numeric));
    $this->assertSame(['a', 'b', 'number'], $result->expected);
    $this->assertSame([['a'], ['b'], ['0']], $result->rulePaths);
    $this->assertSame([['a'], ['b']], DeferredDiagnostic::resolve($history)->rulePaths);
});

test('long duplicate and distinct histories compact without changing retained checkpoints', function (): void {
    $source = new ByteSource('');
    $history = null;
    $checkpoint = null;
    $duplicate = new Diagnostic($source, 0, ['duplicate'], [['duplicate']]);
    for ($i = 0; $i < 2048; ++$i) {
        $observation = new Diagnostic($source, 0, ['e' . $i], [['p' . $i]]);
        $history = DeferredDiagnostic::merge($source, $history, $observation);
        $history = DeferredDiagnostic::merge($source, $history, $duplicate);
        if ($i === 5) {
            $checkpoint = $history;
        }
    }
    $result = DeferredDiagnostic::resolve($history);
    $this->assertCount(2049, $result->expected);
    $this->assertSame(
        ['e0', 'duplicate', 'e1', 'e2', 'e3', 'e4', 'e5'],
        DeferredDiagnostic::resolve($checkpoint)->expected,
    );
    $this->assertSame('e2047', $result->expected[2048]);
    $this->assertCount(2049, $result->rulePaths);
    $this->assertSame($result, DeferredDiagnostic::resolve($history));
});

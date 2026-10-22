<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Builder\FreezeFlags;
use Vanderlee\Comprehend\Extensions\Optimizer\Parser\Choice as OptimizedChoice;
use Vanderlee\Comprehend\Extensions\Optimizer\Parser\Repeat as OptimizedRepeat;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Match\Failure;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\SeparatedList;
use Vanderlee\Comprehend\Parser\Terminal\Any;
use Vanderlee\Comprehend\Parser\Terminal\Char;
use Vanderlee\Comprehend\Parser\Terminal\Range;
use Vanderlee\Comprehend\Parser\Terminal\Regex;
use Vanderlee\Comprehend\Parser\Terminal\Set;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Source\Utf8Source;
use Vanderlee\Comprehend\Tree\ConcreteNode;

uses(TestCase::class);

test('unretained terminal failures keep exact session observations and byte progress', function (string $kind): void {
    $source = new Utf8Source("\u{e9}?");
    $parser = match ($kind) {
        'any' => new Any(),
        'char' => new Char('a'),
        'range' => new Range('a', 'z'),
        'regex' => new Regex('/a/A'),
        'set' => new Set('abc'),
        default => new Text('?a'),
    };
    $parser->diagnosticRule('operand')->label('item');
    $offset = $kind === 'any' ? 3 : 2;
    $session = new ParseSession($source);
    $session->pushRule('owner');
    $failure = $parser->recognizeInSession($source, $offset, $session, retainFailureDiagnostic: false);
    $session->popRule();

    $this->assertInstanceOf(Failure::class, $failure);
    $this->assertNull($failure->diagnostic);
    $this->assertSame($kind === 'text' ? 1 : 0, $failure->length);
    $this->assertSame($kind === 'text' ? $offset + 1 : $offset, $failure->farthestFailure);
    $this->assertSame($offset, $failure->span->start);
    $this->assertSame($offset, $failure->span->end);
    $diagnostic = $session->diagnostic(true);
    $this->assertSame($failure->farthestFailure, $diagnostic->byteOffset);
    $this->assertSame($kind === 'text' ? ['"?a"'] : ['item'], $diagnostic->expected);
    $this->assertSame([['owner', 'operand']], $diagnostic->rulePaths);
})->with(['any', 'char', 'range', 'regex', 'set', 'text']);

test('successful and guard rejected operands retain their observable snapshots', function (): void {
    $source = new ByteSource('a');
    $session = new ParseSession($source);
    $session->recordFailure(0, 'earlier');
    $parser = new Text('a');
    $success = $parser->recognizeInSession($source, 0, $session, retainFailureDiagnostic: false);
    $this->assertInstanceOf(Success::class, $success);
    $this->assertSame(['earlier'], $success->diagnostic->expected);
    $snapshot = $success->diagnostic;
    $parser->guard(static fn(): bool => false);
    $failure = $parser->recognizeInSession($source, 0, $session, retainFailureDiagnostic: false);
    $this->assertSame(1, $failure->diagnostic->byteOffset);
    $this->assertSame(['guard(Text)'], $failure->diagnostic->expected);
    $this->assertSame(['earlier'], $snapshot->expected);
    $this->assertSame(0, $snapshot->byteOffset);
});

test('custom terminal failure hooks retain metadata under built in consumers', function (string $owner): void {
    $operand = new class ('a') extends Text {
        public ?Failure $observed = null;

        protected function failure(Source $source, int $offset, int $progress = 0): Failure
        {
            return $this->observed = parent::failure($source, $offset, $progress);
        }
    };
    $parser = match ($owner) {
        'choice' => new Choice($operand, new Text('b')),
        'repeat' => new Repeat($operand),
        default => new SeparatedList(new Text('b'), $operand),
    };
    $result = $parser->matchPrefix(new ByteSource('b'));
    $this->assertTrue($result->success);
    $this->assertNotNull($operand->observed->diagnostic);
    $this->assertSame(['"a"'], $operand->observed->diagnostic->expected);
})->with(['choice', 'repeat', 'list']);

test('choice subclass hooks observe complete alternative diagnostics', function (bool $optimized): void {
    $parser = $optimized
        ? new class (new Text('a'), new Text('b')) extends OptimizedChoice {
            public array $observations = [];

            protected function attemptAlternative(
                int|string $index,
                Parser $parser,
                Source $source,
                int $offset,
                ParseSession $session,
            ): AbstractMatch {
                $match = parent::attemptAlternative($index, $parser, $source, $offset, $session);
                $this->observations[] = $match->diagnostic->toArray();

                return $match;
            }
        }
    : new class (new Text('a'), new Text('b')) extends Choice {
        public array $observations = [];

        protected function attemptAlternative(
            int|string $index,
            Parser $parser,
            Source $source,
            int $offset,
            ParseSession $session,
        ): AbstractMatch {
            $match = parent::attemptAlternative($index, $parser, $source, $offset, $session);
            $this->observations[] = $match->diagnostic->toArray();

            return $match;
        }
    };
    if ($optimized) {
        $parser->prepareRecognition();
    }
    for ($i = 0; $i < 3; ++$i) {
        $parser->observations = [];
        $result = $parser->parseAll(new ByteSource('b'));
        $this->assertTrue($result->success);
        $this->assertSame(['"a"'], $parser->observations[0]['expected']);
        $this->assertSame($parser->observations[0], $parser->observations[1]);
    }
})->with([false, true]);

test('repeat subclass hooks observe the failed terminating attempt', function (bool $optimized): void {
    $parser = $optimized
        ? new class (new Text('a')) extends OptimizedRepeat {
            public ?AbstractMatch $observed = null;

            protected function attemptChild(Source $source, int $offset, ParseSession $session): AbstractMatch
            {
                return $this->observed = parent::attemptChild($source, $offset, $session);
            }
        }
    : new class (new Text('a')) extends Repeat {
        public ?AbstractMatch $observed = null;

        protected function attemptChild(Source $source, int $offset, ParseSession $session): AbstractMatch
        {
            return $this->observed = parent::attemptChild($source, $offset, $session);
        }
    };
    if ($optimized) {
        $parser->prepareRecognition();
    }
    for ($i = 0; $i < 3; ++$i) {
        $result = $parser->parseAll(new ByteSource('a'));
        $this->assertTrue($result->success);
        $this->assertSame(1, $parser->observed->diagnostic->byteOffset);
        $this->assertSame(['"a"'], $parser->observed->diagnostic->expected);
        $this->assertNull($result->diagnostic);
    }
})->with([false, true]);

test('list subclass separator hooks retain the original failed observation', function (): void {
    $parser = new class (new Text('a'), new Text(',')) extends SeparatedList {
        public ?AbstractMatch $observed = null;

        protected function recognizeSeparator(
            Parser $parser,
            Source $source,
            int $offset,
            ParseSession $session,
            ?Success $spacing,
        ): AbstractMatch|ConcreteNode {
            return $this->observed = parent::recognizeSeparator($parser, $source, $offset, $session, $spacing);
        }
    };
    $result = $parser->parseAll(new ByteSource('a'));
    $this->assertTrue($result->success);
    $this->assertNull($result->diagnostic);
    $this->assertSame(1, $parser->observed->diagnostic->byteOffset);
    $this->assertSame(['","'], $parser->observed->diagnostic->expected);
});

test('cold and replayed alternatives preserve labels and ordered observed failures', function (int $flags): void {
    $parser = new Choice(
        (new Text('a'))->label('alpha'),
        (new Text('c'))->label('gamma'),
        new Text('b'),
    );
    $grammar = $parser->freeze($flags);
    $retained = null;
    for ($i = 0; $i < 3; ++$i) {
        $result = $grammar->parse('b');
        $this->assertTrue($result->success);
        $this->assertSame(['alpha', 'gamma'], $result->diagnostic->expected);
        $this->assertSame([['Choice', 'Text']], $result->diagnostic->rulePaths);
        $retained ??= $result->diagnostic;
        $grammar->parse('?');
        $this->assertSame(['alpha', 'gamma'], $retained->expected);
    }
})->with([FreezeFlags::NONE, FreezeFlags::OPTIMIZE]);

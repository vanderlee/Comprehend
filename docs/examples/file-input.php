<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Runtime\ParseLimits;
use Vanderlee\Comprehend\Runtime\ParseLimitException;
use Vanderlee\Comprehend\Source\Utf8FileSource;
use Vanderlee\Comprehend\Source\EncodedFileSource;
use Vanderlee\Comprehend\Tree\ConcreteNode;

$stream = tmpfile();
fwrite($stream, 'éabc');
fseek($stream, 2);
$source = new Utf8FileSource($stream, 4);
if (ftell($stream) !== 2) {
    throw new RuntimeException('Borrowed stream position changed');
}
fclose($stream);
$grammar = C::literalWindow('éabc')->named('word')->freeze();
$result = $grammar->parseAll($source, limits: new ParseLimits(10, 100));
if (!$result->success || $result->rawText() !== 'éabc' || $result->ast()->span->end !== 5) {
    throw new RuntimeException('UTF-8 file/window example failed');
}
try {
    $grammar->parseAll($source, limits: new ParseLimits(maxWork: 2));
    throw new RuntimeException('Expected work limit');
} catch (ParseLimitException $error) {
    if ($error->limit !== 'work') {
        throw $error;
    }
}
$source->close();
try {
    $result->rawText();
    throw new RuntimeException('Expected closed source error');
} catch (LogicException $error) {
    if ($error->getMessage() !== 'File source is closed') {
        throw $error;
    }
}
echo "Seekable snapshot, UTF-8 window, limits and source lifetime examples passed.\n";

$legacy = tmpfile();
fwrite($legacy, mb_convert_encoding('(café)', 'Windows-1252', 'UTF-8'));
try {
    $encoded = new EncodedFileSource($legacy, 'Windows-1252', 4);
} finally {
    fclose($legacy);
}
try {
    $word = C::range('a', 'z')->or(C::char('é'))->some()->lexeme()
        ->named(static fn(ConcreteNode $node): string => $node->rawText())->tag('word');
    $result = C::sequence('(', $word, ')')->parse($encoded);
    $node = $result->ast()?->firstTagged('word');
    if (!$result->success || $node?->name !== 'café' || $node->surroundedText() !== '(café)'
        || $result->consumedByteLength !== 7 || $encoded->originalByteLength !== 6) {
        throw new RuntimeException('Encoded source and tree metadata example failed');
    }
} finally {
    $encoded->close();
}
echo "Explicit file encoding, dynamic names, tags and surrounding syntax examples passed.\n";

$stream = tmpfile();
fwrite($stream, 'ab42');
$source = new Utf8FileSource($stream, 4);
fclose($stream);
try {
    $grammar = C::regex('/ab/A')->then(C::integer())->freeze();
    foreach ([0, 8] as $cacheBytes) {
        $result = $grammar->parseAll($source, limits: new ParseLimits(maxRegexSubjectBytes: $cacheBytes));
        if (!$result->success || $result->rawText() !== 'ab42') {
            throw new RuntimeException('Regex subject ownership example failed');
        }
    }
} finally {
    $source->close();
}
echo "Shared regex subjects and explicit cache caps passed.\n";

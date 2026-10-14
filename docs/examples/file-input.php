<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Runtime\ParseLimits;
use Vanderlee\Comprehend\Runtime\ParseLimitException;
use Vanderlee\Comprehend\Source\Utf8FileSource;

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

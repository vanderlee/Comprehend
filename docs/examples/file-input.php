<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Vanderlee\Comprehend\Parser\Terminal\Regex;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Runtime\ParseLimits;
use Vanderlee\Comprehend\Runtime\ParseLimitException;
use Vanderlee\Comprehend\Source\Utf8FileSource;

$stream = tmpfile();
fwrite($stream, 'éabc');
fseek($stream, 2);
$source = new Utf8FileSource($stream, 4);
if (ftell($stream) !== 2) { throw new RuntimeException('Borrowed stream position changed'); }
fclose($stream);
$result = Regex::literalWindow('éabc')->name('word')->parseAll($source, limits: new ParseLimits(10, 100));
if (!$result->success || $result->rawText() !== 'éabc' || $result->ast()->span->end !== 5) {
    throw new RuntimeException('UTF-8 file/window example failed');
}
try {
    (new Text('éabc'))->parseAll($source, limits: new ParseLimits(maxWork: 2));
    throw new RuntimeException('Expected work limit');
} catch (ParseLimitException $error) {
    if ($error->limit !== 'work') { throw $error; }
}
$source->close();
try {
    $result->rawText();
    throw new RuntimeException('Expected closed source error');
} catch (LogicException $error) {
    if ($error->getMessage() !== 'File source is closed') { throw $error; }
}
echo "Seekable snapshot, UTF-8 window, limits and source lifetime examples passed.\n";

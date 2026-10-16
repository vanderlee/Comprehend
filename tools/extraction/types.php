<?php

declare(strict_types=1);

namespace ComprehendComparison;

use Parsica\Parsica\StringStream;
use Vanderlee\Comprehend\Extensions\Extraction;

use function Parsica\Parsica\many;
use function Parsica\Parsica\digitChar;
use function Parsica\Parsica\either;
use function Parsica\Parsica\pure;
use function PHPStan\dumpType;

// This is an observation probe, not an assertion of one analyzer's superiority.
function typeObservations(): void
{
    $comprehendNumber = Extraction::syntax()->range('0', '9')->mapText('intval');
    $extractionNumber = digitChar()->map('intval');
    dumpType($comprehendNumber);
    dumpType($extractionNumber);
    dumpType($comprehendNumber->manyValues());
    dumpType(many($extractionNumber));
    dumpType($comprehendNumber->optionalValue(default: false));
    dumpType(either($extractionNumber, pure(false)));
    dumpType(comprehendJob()->freeze()->parse('report []')->value());
    dumpType(extractionJob()->run(new StringStream('report []'))->output());
}

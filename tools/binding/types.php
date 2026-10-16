<?php

declare(strict_types=1);

namespace ComprehendComparison;

use Parsica\Parsica\StringStream;
use Vanderlee\Comprehend\Extensions\Binding;

use function Parsica\Parsica\many;
use function Parsica\Parsica\digitChar;
use function Parsica\Parsica\either;
use function Parsica\Parsica\pure;
use function PHPStan\dumpType;

// This is an observation probe, not an assertion of one analyzer's superiority.
function typeObservations(): void
{
    $comprehendNumber = Binding::syntax()->range('0', '9')->mapText('intval');
    $bindingNumber = digitChar()->map('intval');
    dumpType($comprehendNumber);
    dumpType($bindingNumber);
    dumpType($comprehendNumber->manyValues());
    dumpType(many($bindingNumber));
    dumpType($comprehendNumber->optionalValue(default: false));
    dumpType(either($bindingNumber, pure(false)));
    dumpType(comprehendJob()->freeze()->parse('report []')->value());
    dumpType(bindingJob()->run(new StringStream('report []'))->output());
}

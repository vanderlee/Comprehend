<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Abnf;

use InvalidArgumentException;
use Vanderlee\Comprehend\Result\Diagnostic;
use Vanderlee\Comprehend\Source\Source;

final class SyntaxException extends InvalidArgumentException
{
    public readonly Diagnostic $diagnostic;

    public function __construct(string $reason, Source $source, int $byteOffset)
    {
        $this->diagnostic = new Diagnostic($source, $byteOffset, [$reason], [['ABNF']]);
        $location = $this->diagnostic->location();

        parent::__construct('ABNF: ' . $reason . ' at line ' . $location['line']
            . ', column ' . $location['column'] . ' (byte ' . $byteOffset . ')');
    }
}

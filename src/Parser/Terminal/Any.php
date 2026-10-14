<?php

declare (strict_types=1);
namespace Vanderlee\Comprehend\Parser\Terminal;

use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Parser\Parser;
/**
 * Matches any single symbol.
 *
 * @author Martijn
 */
class Any extends Parser
{
    protected function recognize(Source $input, int $offset, ParseSession $context): AbstractMatch
    {
        $unit = $context->readSourceUnit($offset);
        return $unit !== null ? $this->success($input, $offset, $unit->byteLength()) : $this->failure($input, $offset);
    }
    public function __toString(): string
    {
        return '.';
    }
}

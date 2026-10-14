<?php

declare (strict_types=1);
namespace Vanderlee\Comprehend\Parser\Terminal;

use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Parser\Parser;
/**
 * Matches (with length 0) if the offset is at the very start of the input.
 *
 * @author Martijn
 */
class Start extends Parser
{
    protected function recognize(Source $input, int $offset, ParseSession $context): AbstractMatch
    {
        return $offset === 0 ? $this->success($input, $offset) : $this->failure($input, $offset);
    }
    public function __toString(): string
    {
        return 'start';
    }
}

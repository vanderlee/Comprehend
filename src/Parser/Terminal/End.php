<?php

declare (strict_types=1);

namespace Vanderlee\Comprehend\Parser\Terminal;

use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;

/**
 * Matches (with length 0) if the offset is at the very end of the input.
 *
 * @author Martijn
 */
class End extends Parser
{
    public function __toString(): string
    {
        return 'end';
    }

    protected function recognize(Source $input, int $offset, ParseSession $context): AbstractMatch
    {
        return $offset === $input->byteLength() ? $this->success($input, $offset) : $this->failure($input, $offset);
    }
}

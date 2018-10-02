<?php

namespace Vanderlee\Comprehend\Core;

use Vanderlee\Comprehend\Core\Context\CaseSensitiveContextTrait;
use Vanderlee\Comprehend\Core\Context\PreferContextTrait;
use Vanderlee\Comprehend\Core\Context\SpacingContextTrait;
use Vanderlee\Comprehend\Directive\Prefer;

/**
 * Maintains the current context of the parser chain.
 *
 * @author Martijn
 */
class Context
{
    use CaseSensitiveContextTrait;
    use PreferContextTrait;
    use SpacingContextTrait;

    public function __construct($skipper = null, $caseSensitive = true, $preference = Prefer::FIRST)
    {
        self::assertPreference($preference);

        $this->pushSpacer($skipper);
        $this->pushCaseSensitivity($caseSensitive);
        $this->pushPreference($preference);
    }
}

<?php

namespace vanderlee\comprehend\core;

use vanderlee\comprehend\core\context\CaseSensitiveContextTrait;
use vanderlee\comprehend\core\context\PreferContextTrait;
use vanderlee\comprehend\core\context\SpacingContextTrait;
use vanderlee\comprehend\directive\Prefer;

/**
 * Maintains the current context of the parser chain
 *
 * @author Martijn
 */
class Context
{
    use CaseSensitiveContextTrait;
    use PreferContextTrait;
    use SpacingContextTrait;

    public function __construct($skipper = null, $case_sensitive = true, $preference = Prefer::FIRST)
    {
        self::assertPreference($preference);

        $this->pushSpacer($skipper);
        $this->pushCaseSensitivity($case_sensitive);
        $this->pushPreference($preference);
    }

}

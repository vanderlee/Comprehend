<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Directive;

use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Runtime\ChoicePolicy;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;

/**
 * Overrides first, longest or shortest choice selection within a subtree. Scopes the
 * ParseSession preference consumed by Choice and restores it after recognition.
 */
class Prefer extends UnaryDirective
{
    public function __construct(private readonly ChoicePolicy $preference, Parser $parser)
    {
        parent::__construct($parser);
    }

    public function __toString(): string
    {
        return match ($this->preference) {
            ChoicePolicy::LONGEST => 'longest-of' . $this->parser,
            ChoicePolicy::SHORTEST => 'shortest-of' . $this->parser,
            default => (string) $this->parser,
        };
    }

    protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
    {
        $session->pushPreference($this->preference);
        try {
            return $this->parser->recognizeInSession($source, $byteOffset, $session);
        } finally {
            $session->popPreference();
        }
    }
}

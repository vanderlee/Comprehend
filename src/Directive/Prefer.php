<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Directive;

use DomainException;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;

/** Override choice ranking within a subtree. */
class Prefer extends Parser
{
    public const FIRST = 'first';
    public const LONGEST = 'longest';
    public const SHORTEST = 'shortest';

    public function __construct(private string $preference, private Parser $parser)
    {
        if (!in_array($preference, [self::FIRST, self::LONGEST, self::SHORTEST])) {
            throw new DomainException('Invalid preference `' . $preference . '`');
        }
    }

    public function __toString(): string
    {
        return match ($this->preference) {
            self::LONGEST => 'longest-of' . $this->parser,
            self::SHORTEST => 'shortest-of' . $this->parser,
            default => (string)$this->parser,
        };
    }

    protected function recognize(Source $input, int $offset, ParseSession $context): AbstractMatch
    {
        $context->pushPreference($this->preference);
        try {
            return $this->parser->recognizeInSession($input, $offset, $context);
        } finally {
            $context->popPreference();
        }
    }
}

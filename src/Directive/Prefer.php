<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Directive;

use DomainException;
use Vanderlee\Comprehend\Builder\AnalyzableParser;
use Vanderlee\Comprehend\Builder\ForwardingAnalysisTrait;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;

/**
 * Overrides first, longest or shortest choice selection within a subtree. Scopes the
 * ParseSession preference consumed by Choice and restores it after recognition.
 */
class Prefer extends Parser implements AnalyzableParser
{
    use ForwardingAnalysisTrait;

    public const FIRST = 'first';
    public const LONGEST = 'longest';
    public const SHORTEST = 'shortest';

    public function __construct(private string $preference, private Parser $parser)
    {
        if (!in_array($preference, [self::FIRST, self::LONGEST, self::SHORTEST])) {
            throw new DomainException('Invalid preference `' . $preference . '`');
        }
    }

    /** @internal Structural operand for graph analysis. */
    public function getParser(): Parser
    {
        return $this->parser;
    }

    protected function analysisTarget(): Parser
    {
        return $this->getParser();
    }

    public function __toString(): string
    {
        return match ($this->preference) {
            self::LONGEST => 'longest-of' . $this->parser,
            self::SHORTEST => 'shortest-of' . $this->parser,
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

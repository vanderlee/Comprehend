<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Directive;

use Vanderlee\Comprehend\Builder\ExactClassSnapshotReuseTrait;
use Vanderlee\Comprehend\Builder\ReusableSnapshot;
use Vanderlee\Comprehend\Builder\GraphSnapshot;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;

/**
 * Configures or disables automatic intervening trivia for a parser subtree. Scopes the spacer
 * in ParseSession so enclosing grammars retain their own spacing policy.
 */
class Space extends UnaryDirective implements ReusableSnapshot
{
    use ExactClassSnapshotReuseTrait;

    public function __construct(private ?Parser $spacer, Parser $parser)
    {
        parent::__construct($parser);
    }

    protected function copyDependencies(GraphSnapshot $context): void
    {
        parent::copyDependencies($context);
        if ($this->spacer !== null) {
            $this->spacer = $context->copy($this->spacer);
        }
    }

    /** @return list<Parser> */
    public function children(): array
    {
        if ($this->spacer === null) {
            return [$this->parser];
        }

        return [$this->spacer, $this->parser];
    }

    public function __toString(): string
    {
        return (string) $this->parser;
    }

    protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
    {
        $session->pushSpacer($this->spacer);
        try {
            return $this->parser->recognizeInSession($source, $byteOffset, $session);
        } finally {
            $session->popSpacer();
        }
    }
}

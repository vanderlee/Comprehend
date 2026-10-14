<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Parser\Structure;

use InvalidArgumentException;
use LogicException;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;

/** Repeat consuming children with optional bounds and intervening trivia. */
class Repeat extends Parser
{
    use SpacingTrait;

    public function __construct(private Parser $parser, private int $min = 0, private ?int $max = null)
    {
        if ($min < 0 || ($max !== null && $max < $min)) {
            throw new InvalidArgumentException('Invalid repeat range specified');
        }
    }

    public static function plus(Parser $parser)
    {
        return new self($parser, 1, null);
    }

    public static function star(Parser $parser)
    {
        return new self($parser, 0, null);
    }

    public static function optional(Parser $parser)
    {
        return new self($parser, 0, 1);
    }

    public static function exact(Parser $parser, int $times)
    {
        return new self($parser, $times, $times);
    }

    public function __toString(): string
    {
        // Output ABNF formatting
        $min = $this->min > 0 ? $this->min : '';
        $max = $this->max ?? '';
        return ($min === $max ? $min : $min . '*' . $max) . $this->parser;
    }

    protected function recognize(Source $input, int $offset, ParseSession $context): AbstractMatch
    {
        $childMatches = [];
        $concreteMatches = [];
        $this->pushSpacer($context);
        try {
            $length = 0;
            while ($this->max === null || count($childMatches) < $this->max) {
                $diagnostics = $context->diagnosticState();
                $spacingMatch = null;
                $skip = $length > 0 ? $context->skipSpacing($input, $offset + $length, $spacingMatch) : 0;
                $match = $skip !== false ? $this->parser->recognizeInSession(
                    $input,
                    $offset + $length + $skip,
                    $context
                ) : null;
                if (!$match instanceof Success) {
                    if (count($childMatches) >= $this->min) {
                        $context->restoreDiagnostics($diagnostics);
                    }
                    break;
                }

                if ($match->length === 0) {
                    throw new LogicException('Repeat child matched successfully without consuming input');
                }
                if ($spacingMatch !== null) {
                    $match->addPrecedingMatch($spacingMatch);
                    $concreteMatches[] = $spacingMatch;
                }
                $length += $skip + $match->length;
                $childMatches[] = $match;
                $concreteMatches[] = $match;
            }
        } finally {
            $this->popSpacer($context);
        }
        if (count($childMatches) < $this->min) {
            return $this->failure($input, $offset, $length);
        }
        $success = $this->success($input, $offset, $length, $childMatches);
        $success->setConcreteMatches($concreteMatches);
        return $success;
    }
}

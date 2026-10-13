<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Compatibility;

use LogicException;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Match\Failure;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Source\Utf8Source;

final readonly class LegacyParserAdapter
{
    public function __construct(private Parser $parser, public CoordinatePolicy $policy = CoordinatePolicy::ASCII)
    {
    }

    public function match(Source|string $input, int $offset = 0): AbstractMatch
    {
        return $this->matchPrefix($input, $offset);
    }

    public function matchPrefix(Source|string $input, int $offset = 0): AbstractMatch
    {
        return $this->recognize($input, $offset, false);
    }

    public function parseAll(Source|string $input, int $offset = 0): AbstractMatch
    {
        return $this->recognize($input, $offset, true);
    }

    private function recognize(Source|string $input, int $offset, bool $complete): AbstractMatch
    {
        $source = is_string($input)
            ? ($this->policy === CoordinatePolicy::CODE_POINTS ? new Utf8Source($input) : new ByteSource($input))
            : $input;
        $coordinates = new Coordinates($source, $this->policy);
        $start = $coordinates->toByteOffset($offset);
        $match = $complete ? $this->parser->matchAllSourceUnresolved($source, $start, $this->policy === CoordinatePolicy::ASCII)
            : ($this->policy === CoordinatePolicy::ASCII
                ? $this->parser->matchAsciiUnresolved($source, $start)
                : $this->parser->matchSourceUnresolved($source, $start));
        if (!($match instanceof Success) && !($match instanceof Failure)) {
            throw new LogicException('Legacy projection requires a Success or Failure match');
        }
        if ($match->farthestFailure !== null) {
            $coordinates->position($match->farthestFailure);
        }
        if ($match instanceof Failure) {
            $failure = new Failure($coordinates->length($start, $match->farthestFailure ?? $start));
            $failure->setSourceMetadata($match->span, $match->farthestFailure, $match->diagnostic);
            return $failure;
        }

        // Validate all selected effect positions before executing any callback.
        $match->useCoordinates($coordinates);
        $success = new Success($coordinates->length($start, $match->span->end));
        $success->setSourceMetadata($match->span, $match->farthestFailure, $match->diagnostic);
        $success->setConcreteNode($match->node);
        $success->useCoordinates($coordinates);
        $success->setValueMapper(fn () => $match->getValue());
        $success->addCustomCallback(fn () => $match->resolve());
        $success->addResultCallback(function (&$results) use ($match) { $results = $match->getResults(); });
        return $success->resolve();
    }

    public function __invoke(Source|string $input, int $offset = 0): AbstractMatch
    {
        return $this->match($input, $offset);
    }
}

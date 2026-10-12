<?php

namespace Vanderlee\Comprehend\Match;

use Exception;
use InvalidArgumentException;
use Vanderlee\Comprehend\Core\Token;
use Vanderlee\Comprehend\Source\Span;

/**
 * Description of ParserToken.
 *
 * @author Martijn
 *
 * @property-read bool $match   Success or failure?
 * @property-read int $length  Length of the match
 * @property-read array $results List of output results
 * @property-read string|array|null $result  Default output result
 * @property-read Token|null $token
 * @property-read Span|null $span Canonical byte span, if source-bound
 * @property-read int|null $farthestFailure Canonical byte position, if recorded
 * @property-read \Vanderlee\Comprehend\Result\Diagnostic|null $diagnostic Structured source failure details
 */
abstract class AbstractMatch
{
    protected $length;
    private $sourceSpan;
    private $farthestFailure;
    private $diagnostic;

    /** @internal Attach canonical coordinates without changing legacy length. */
    public function setSourceMetadata(Span $span, ?int $farthestFailure, ?\Vanderlee\Comprehend\Result\Diagnostic $diagnostic = null): void
    {
        if (!$this->match && $span->byteLength() !== 0) {
            throw new InvalidArgumentException('Failed matches must have zero-width spans');
        }
        if ($farthestFailure !== null) {
            $span->source->span($farthestFailure, $farthestFailure);
        }
        $this->sourceSpan = $span;
        $this->farthestFailure = $farthestFailure;
        $this->diagnostic = $diagnostic;
    }

    /**
     * @param string $name
     *
     * @return mixed
     * @throws Exception
     *
     */
    public function __get(string $name)
    {
        switch ($name) {
            case 'diagnostic':
                return $this->diagnostic;
            case 'span':
                return $this->sourceSpan;
            case 'farthestFailure':
                return $this->farthestFailure;
            case 'length':
                return $this->length;
            case 'results':
                return [];
            case 'result':
            case 'token':
                return null;
        }

        throw new InvalidArgumentException('Property name `' . $name . '` not recognized');
    }

    /**
     * Create a new match.
     *
     * @param int $length
     */
    public function __construct(int $length = 0)
    {
        $this->length = $length;
    }

    /**
     * Resolve any match stuff (should only ever be called from AbstractParser).
     * Not for human consumption.
     *
     * @return $this
     */
    public function resolve(): AbstractMatch
    {
        return $this;
    }

    /**
     * Return the result for the name specified or the default value if not set.
     *
     * @param string|null $name
     * @param mixed $default
     *
     * @return mixed
     */
    public function getResult(string $name = null, $default = null)
    {
        return $default;
    }

    /**
     * Return whether there is a result for the name specified.
     *
     * @param string|null $name
     *
     * @return bool
     */
    public function hasResult(string $name = null): bool
    {
        return false;
    }
}

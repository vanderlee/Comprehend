<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Parser\Terminal;

use Exception;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Match\Failure;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;

/**
 * Matches an integer within the specified range.
 *
 * @author Martijn
 */
class Integer extends Parser
{
    use CaseSensitiveTrait;

    /**
     * List of digits to use for the different bases (up to 36).
     *
     * @var string
     */
    private static $set = '0123456789abcdefghijklmnopqrstuvwxyz';
    /**
     * @var int|null
     */
    private ?int $minimum;
    /**
     * @var int|null
     */
    private ?int $maximum;
    /**
     * @var int
     */
    private int $base;

    /**
     * @param int|null $minimum
     * @param int|null $maximum
     * @param int $base
     *
     * @throws Exception
     */
    public function __construct(?int $minimum = 0, ?int $maximum = null, int $base = 10)
    {
        if ($minimum !== null && $maximum !== null && $minimum > $maximum) {
            throw new Exception('Maximum must be greater than minimum');
        }
        $this->minimum = $minimum;
        $this->maximum = $maximum;
        $this->base = $base;
        if ($base < 2 || $base > strlen(self::$set)) {
            throw new Exception('Unsupported base');
        }
    }

    public function __toString(): string
    {
        return ($this->minimum === null ? '<-INF' : '[' . $this->minimum) . ',' . ($this->maximum === null ? 'INF>' : $this->maximum . ']');
    }

    /**
     * @param Source $input
     * @param int $offset
     * @param ParseSession $context
     *
     * @return Failure|Success
     */
    protected function recognize(Source $input, int $offset, ParseSession $context): AbstractMatch
    {
        $this->pushCaseSensitivityToContext($context);
        try {
            $set0 = substr(self::$set, 0, $this->base);
            $set1 = substr(self::$set, 1, $this->base - 1);
            $pattern = '/(?:0|-?[' . $set1 . '][' . $set0 . ']*)/A' . ($context->isCaseSensitive() ? '' : 'i');
        } finally {
            $this->popCaseSensitivityFromContext($context);
        }
        $context->consumeWork(max(1, $input->byteLength()), $offset);
        $subject = $input->sliceBytes(0, $input->byteLength());
        $status = preg_match($pattern, $subject, $match, 0, $offset);
        if ($status === false) {
            throw new \RuntimeException('Integer recognition failed: ' . preg_last_error_msg());
        }
        if ($status !== 1) {
            return $this->failure($input, $offset);
        }

        // Try shorter prefixes when the full integer lies outside the bounds.
        for ($digits = $match[0]; $digits !== ''; $digits = substr($digits, 0, -1)) {
            $context->consumeWork(1, $offset);
            $integer = intval($digits, $this->base);
            if ($this->isInRange($integer) && $digits !== '-') {
                return $this->success($input, $offset, strlen($digits));
            }
        }
        return $this->failure($input, $offset);
    }

    private function isInRange(int $integer): bool
    {
        return ($this->minimum === null || $integer >= $this->minimum) && ($this->maximum === null || $integer <= $this->maximum);
    }
}

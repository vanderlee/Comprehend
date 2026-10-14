<?php

namespace tests;

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Result\ParseResult;
/**
 * Description of TestCase.
 *
 * @author Martijn
 */
class ParserTestCase extends TestCase
{
    /**
     * @param bool $match
     * @param int $length
     * @param AbstractMatch $result
     * @param string $message
     */
    protected function assertResult($match, $length, ParseResult $result, $message = '')
    {
        $this->assertSame($match, $result->success, $message);
        $this->assertSame($match ? $length : 0, $result->consumedByteLength, $message);
        if (!$match) {
            $this->assertSame($result->start, $result->end, $message);
            $this->assertNotNull($result->diagnostic, $message);
        }
    }
}

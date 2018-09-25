<?php

namespace tests;

use vanderlee\comprehend\match\Match;

/**
 * Description of TestCase
 *
 * @author Martijn
 */
class ParserTestCase extends \PHPUnit\Framework\TestCase
{

    /**
     * @param bool $match
     * @param int $length
     * @param Match $result
     * @param string $message
     */
    protected function assertResult($match, $length, Match $result, $message = '')
    {
        $this->assertSame($match, $result->match, $message);
        $this->assertSame($length, $result->length, $message);
    }
}

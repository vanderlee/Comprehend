<?php

namespace tests;

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Match\AbstractMatch;

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
    protected function assertResult($match, $length, AbstractMatch $result, $message = '')
    {
        $this->assertSame($match, $result->match, $message . ' (@'.$result->length.')');
        $this->assertSame($length, $result->length, $message);
    }
}

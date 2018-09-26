<?php

namespace Vanderlee\Comprehend\Parser;

use Vanderlee\Comprehend\Core\ArgumentsTrait;
use Vanderlee\Comprehend\Core\Context;

/**
 * Description of StubParser
 *
 * @property Parser|null $parser
 *
 * @author Martijn
 */
class Stub extends Parser
{

    use ArgumentsTrait;

    /**
     * @var Parser|null
     */
    private $parser = null;

    public function __set($name, $parser)
    {
        if ($name == 'parser') {
            return $this->parser = self::getArgument($parser);
        }

        throw new \InvalidArgumentException("Property `{$name}` does not exist");
    }

    public function __get($name)
    {
        if ($name == 'parser') {
            return $this->parser;
        }

        throw new \InvalidArgumentException("Property `{$name}` does not exist");
    }

    protected function parse(&$input, $offset, Context $context)
    {
        if ($this->parser === null) {
            throw new \UnexpectedValueException('Missing parser');
        }

        $match = $this->parser->parse($input, $offset, $context);
        if ($match->match) {
            return $this->success($input, $offset, $match->length, $match);
        }

        return $this->failure($input, $offset, $match->length);
    }

    public function __toString()
    {
        /** @noinspection HtmlUnknownTag */
        return $this->parser ? (string)$this->parser : '<undefined>';
    }

}

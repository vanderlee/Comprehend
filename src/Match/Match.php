<?php

namespace Vanderlee\Comprehend\Match;

use Vanderlee\Comprehend\Core\Token;

/**
 * Description of ParserToken
 *
 * @author Martijn
 *
 * @property-read bool $match Success or failure?
 * @property-read int $length Length of the match
 * @property-read array $results List of output results
 * @property-read string|array|null $result Default output result
 * @property-read Token|null $token
 */
abstract class Match
{

    private $length;

    /**
     * @param string $name
     * @return mixed
     * @throws \Exception
     */
    public function __get($name)
    {
        switch ($name) {
            case 'length':
                return $this->length;
            case 'results':
                return [];
            case 'result':
                return null;
            case 'token':
                return null;
        }

        throw new \InvalidArgumentException("Property name `{$name}` not recognized");
    }

    /**
     * Create a new match
     *
     * @param int $length
     */
    public function __construct($length = 0)
    {
        $this->length = $length;
    }

    /**
     * Resolve any match stuff (should only ever be called from AbstractParser!
     * Not for human consumption
     *
     * @return $this
     */
    public function resolve()
    {
        return $this;
    }

    /**
     * Return the result for the name specified or the default value if not set.
     *
     * @param string|null $name
     * @param mixed $default
     * @return mixed
     */
    public function getResult($name = null, $default = null)
    {
        return $default;
    }

    /**
     * Return whether there is a result for the name specified.
     *
     * @param string|null $name
     * @return boolean
     */
    public function hasResult($name = null)
    {
        return false;
    }

}

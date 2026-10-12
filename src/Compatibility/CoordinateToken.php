<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Compatibility;

use Vanderlee\Comprehend\Core\Token;

/** Token shape with converted coordinates and text from the original byte token. */
final class CoordinateToken extends Token
{
    public function __construct(private readonly Token $original, Coordinates $coordinates)
    {
        $children = array_map(fn (Token $child) => $coordinates->token($child), $original->children);
        parent::__construct($original->group, $original->name, $original->input,
            $coordinates->position($original->offset),
            $coordinates->length($original->offset, $original->offset + $original->length),
            $children, $original->class);
    }

    public function __get($name)
    {
        if ($name === 'text') {
            return $this->original->text;
        }
        if (!in_array($name, ['group', 'name', 'input', 'offset', 'length', 'children', 'class'], true)) {
            throw new \Exception('Undefined property `' . $name . '`');
        }
        return parent::__get($name);
    }
}

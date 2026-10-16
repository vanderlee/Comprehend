<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Library;

use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Tree\ConcreteNode;

/**
 * JavaScript Object Notation (JSON) Pointer.
 *
 * JSON document paths, including URI fragment representations.
 *
 * @see Rfc8259 JSON values.
 * @link https://www.rfc-editor.org/rfc/rfc6901.txt
 */
class Rfc6901 extends AbstractSpecification
{
    protected const ROOT_RULE = 'json_pointer';

    protected function definitions(): array
    {
        return [
            'reference_token' => C::regex('/(?:[^~\/]|~[01])+/')->maybe(),
            'json_pointer' => C::sequence('/', $this->rule('reference_token'))->many(),
            'array_index' => C::regex('/(?:0|[1-9][0-9]*)/'),
            'uri_fragment' => C::sequence('#', C::regex('/(?:[A-Za-z0-9\-._~!$&\'()*+,;=:@\/?]|%[0-9a-fA-F]{2})+/')
                ->maybe())->guard(static function (ConcreteNode $node): bool {
                    $decoded = rawurldecode(substr($node->rawText(), 1));

                    return mb_check_encoding($decoded, 'UTF-8')
                        && preg_match('/\A(?:\/(?:[^~]|~[01])*)*\z/s', $decoded) === 1;
                }),
        ];
    }
}

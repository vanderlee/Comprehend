<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Network\Grammar;

use Vanderlee\Comprehend\Builder\AbstractSpecification;
use Vanderlee\Comprehend\Comprehend as C;

/**
 * URI Template.
 *
 * URI templates, including Level 4 expressions.
 *
 * Recognizes template syntax; variable expansion is a separate operation.
 *
 * @see Rfc3986 URI syntax.
 * @link https://www.rfc-editor.org/rfc/rfc6570.txt
 */
class Rfc6570 extends AbstractSpecification
{
    protected const ROOT_RULE = 'uri_template';

    protected function definitions(): array
    {
        $iri = new Rfc3987();

        return [
            'pct_encoded' => C::regex('/%[0-9a-f]{2}/i'),
            'varname' => C::regex('/(?:[a-z0-9_]|%[0-9a-f]{2})+(?:\.(?:[a-z0-9_]|%[0-9a-f]{2})+)*/i'),
            'varspec' => C::sequence(
                $this->rule('varname'),
                C::first(C::text('*'), C::regex('/:[1-9][0-9]{0,3}/'))->maybe(),
            ),
            'expression' => C::sequence(
                '{',
                C::set('+#./;?&=,!@|')->maybe(),
                $this->rule('varspec'),
                C::sequence(',', $this->rule('varspec'))->many(),
                '}',
            ),
            'literal' => C::first(
                $this->rule('pct_encoded'),
                C::regex('/[\x21\x23-\x24\x26\x28-\x3b\x3d\x3f-\x5b\x5d\x5f\x61-\x7a\x7e]+/'),
                $iri->rule('ucschar'),
                $iri->rule('iprivate'),
            ),
            'uri_template' => C::first($this->rule('expression'), $this->rule('literal'))->many(),
        ];
    }
}

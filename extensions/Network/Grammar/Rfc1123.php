<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Network\Grammar;

use Vanderlee\Comprehend\Builder\AbstractSpecification;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Tree\ConcreteNode;

/**
 * Requirements for Internet Hosts -- Application and Support.
 *
 * Internet hostnames, including labels beginning with digits.
 *
 * Implements Section 2.1 hostname syntax, not the other application protocols in this RFC.
 * Internationalized names must be supplied as ASCII A-labels.
 *
 * @see Rfc1035 General DNS presentation names.
 * @link https://www.rfc-editor.org/rfc/rfc1123.txt
 */
class Rfc1123 extends AbstractSpecification
{
    protected const ROOT_RULE = 'hostname';

    protected function definitions(): array
    {
        return [
            'label' => C::regex('/[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?/i'),
            'hostname' => C::sequence(
                $this->rule('label'),
                C::sequence('.', $this->rule('label'))->many(),
                C::text('.')->maybe(),
            )->guard(static fn(ConcreteNode $node): bool => strlen(rtrim($node->rawText(), '.')) <= 253),
        ];
    }
}

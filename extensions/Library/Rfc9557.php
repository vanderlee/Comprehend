<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Library;

use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Tree\ConcreteNode;

/**
 * Date and Time on the Internet: Timestamps with Additional Information.
 *
 * Timestamps annotated with time zones and calendar information.
 *
 * Updates RFC 3339. Recognizes annotation syntax; time-zone database resolution and
 * critical-annotation interpretation belong to the application.
 *
 * @see Rfc3339 Updated by this RFC.
 * @link https://www.rfc-editor.org/rfc/rfc9557.txt
 */
class Rfc9557 extends Rfc3339
{
    protected const ROOT_RULE = 'date_time_ext';

    protected function definitions(): array
    {
        return array_replace(parent::definitions(), [
            'annotation_key' => C::regex('/[a-z_][a-z0-9_-]*/'),
            'annotation_value' => C::regex('/[A-Za-z0-9]+(?:-[A-Za-z0-9]+)*/'),
            'time_zone_name' => C::regex('/[A-Za-z._][A-Za-z0-9._+-]*(?:\/[A-Za-z._][A-Za-z0-9._+-]*)*/')
                ->guard(static fn(ConcreteNode $node): bool => !array_intersect(
                    explode('/', $node->rawText()),
                    ['.', '..'],
                )),
            'time_zone_annotation' => C::sequence(
                '[',
                C::text('!')->maybe(),
                C::first($this->rule('time_numoffset'), $this->rule('time_zone_name')),
                ']',
            ),
            'annotation' => C::sequence(
                '[',
                C::text('!')->maybe(),
                $this->rule('annotation_key'),
                '=',
                $this->rule('annotation_value'),
                ']',
            ),
            'date_time_ext' => C::sequence(
                $this->rule('date_time'),
                $this->rule('time_zone_annotation')->maybe(),
                $this->rule('annotation')->many(),
            ),
        ]);
    }
}

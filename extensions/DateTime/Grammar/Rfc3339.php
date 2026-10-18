<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\DateTime\Grammar;

use Vanderlee\Comprehend\Builder\AbstractSpecification;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Tree\ConcreteNode;

/**
 * Date and Time on the Internet: Timestamps.
 *
 * Internet dates and timestamps with UTC offsets.
 *
 * Updated by RFC 9557. Dates are checked against the Gregorian calendar; leap-second
 * spelling is recognized without consulting a historical leap-second table.
 *
 * @see Rfc9557 Extended timestamps.
 * @link https://www.rfc-editor.org/rfc/rfc3339.txt
 */
class Rfc3339 extends AbstractSpecification
{
    protected const ROOT_RULE = 'date_time';

    protected function definitions(): array
    {
        return [
            'date_fullyear' => C::regex('/[0-9]{4}/'),
            'date_month' => C::regex('/(?:0[1-9]|1[0-2])/'),
            'date_mday' => C::regex('/(?:0[1-9]|[12][0-9]|3[01])/'),
            'full_date' => C::sequence(
                $this->rule('date_fullyear'),
                '-',
                $this->rule('date_month'),
                '-',
                $this->rule('date_mday'),
            )->guard(static function (ConcreteNode $node): bool {
                $text = $node->rawText();

                $year = (int) substr($text, 0, 4);

                return checkdate((int) substr($text, 5, 2), (int) substr($text, 8, 2), $year === 0 ? 2000 : $year);
            }),
            'time_hour' => C::regex('/(?:[01][0-9]|2[0-3])/'),
            'time_minute' => C::regex('/[0-5][0-9]/'),
            'time_second' => C::regex('/(?:[0-5][0-9]|60)/'),
            'time_secfrac' => C::regex('/\.[0-9]+/'),
            'time_numoffset' => C::sequence(C::set('+-'), $this->rule('time_hour'), ':', $this->rule('time_minute')),
            'time_offset' => C::first(C::text('Z')->caseSensitive(false), $this->rule('time_numoffset')),
            'partial_time' => C::sequence(
                $this->rule('time_hour'),
                ':',
                $this->rule('time_minute'),
                ':',
                $this->rule('time_second'),
                $this->rule('time_secfrac')->maybe(),
            ),
            'full_time' => C::sequence($this->rule('partial_time'), $this->rule('time_offset')),
            'date_time' => C::sequence(
                $this->rule('full_date'),
                C::text('T')->caseSensitive(false),
                $this->rule('full_time'),
            ),
        ];
    }
}

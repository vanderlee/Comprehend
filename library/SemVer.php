<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Library;

use Vanderlee\Comprehend\Comprehend as C;

/**
 * Semantic Versioning 2.0.0.
 *
 * Software version numbers, prereleases and build metadata.
 *
 * @link https://semver.org/spec/v2.0.0.html
 */
class SemVer extends AbstractSpecification
{
    protected const ROOT_RULE = 'version';

    protected function definitions(): array
    {
        return [
            'numeric_identifier' => C::regex('/(?:0|[1-9][0-9]*)/'),
            'prerelease_identifier' => C::regex('/(?:[0-9A-Za-z-]*[A-Za-z-][0-9A-Za-z-]*|0|[1-9][0-9]*)/'),
            'build_identifier' => C::regex('/[0-9A-Za-z-]+/'),
            'prerelease' => C::sequence(
                $this->rule('prerelease_identifier'),
                C::sequence('.', $this->rule('prerelease_identifier'))->many(),
            ),
            'build' => C::sequence(
                $this->rule('build_identifier'),
                C::sequence('.', $this->rule('build_identifier'))->many(),
            ),
            'version' => C::sequence(
                $this->rule('numeric_identifier'),
                '.',
                $this->rule('numeric_identifier'),
                '.',
                $this->rule('numeric_identifier'),
                C::sequence('-', $this->rule('prerelease'))->maybe(),
                C::sequence('+', $this->rule('build'))->maybe(),
            ),
        ];
    }
}

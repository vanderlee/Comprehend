<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Match;

/**
 * Records an unsuccessful recognition attempt for Parser and ParseSession. Its length tracks
 * observed progress rather than consumed input, preserving failure information for ParseResult.
 *
 * @internal Failed recognition; length records observed progress, not consumption.
 */
final class Failure extends AbstractMatch {}

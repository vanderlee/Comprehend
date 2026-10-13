<?php

namespace Vanderlee\Comprehend\Core\Context;

use Vanderlee\Comprehend\Core\ArgumentsTrait;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Parser;

trait SpacingContextTrait
{
    use ArgumentsTrait;

    /**
     * List of spacers.
     *  -  Parser for a normal spacer.
     *  -  `null` to disable spacing.
     *  -  `true` to use the top-most spacer.
     *
     * @var Parser[]|null[]|bool[]
     */
    private $spacers = [];

    public function pushSpacer($skipper = null)
    {
        $this->spacers[] = ($skipper === null || $skipper === true)
            ? $skipper
            : $this->getArgument($skipper);
    }

    public function popSpacer()
    {
        array_pop($this->spacers);
    }

    /**
     * Return skipped length (or false) without resolving effects. The caller
     * must retain the optional successful match only on its accepted path.
     *
     * @param Success|null $match Unresolved spacing match, if any.
     */
    public function skipSpacing($in, $offset, &$match = null)
    {
        $match = null;
        if ($this->isLexeme()) {
            return 0;
        }
        $spacer = end($this->spacers);

        // If `true`; use top-most Parser instance in stack.
        if ($spacer === true) {
            do {
                $spacer = prev($this->spacers);
            } while ($spacer !== false && !($spacer instanceof Parser));
        }

        if ($spacer instanceof Parser) {
            $attempt = \Vanderlee\Comprehend\Compatibility\LegacySpacing::recognize($spacer, $in, $offset, $this);
            $match = $attempt instanceof Success ? $attempt : null;
            if (!$attempt->match && $attempt->diagnostic !== null) {
                foreach ($attempt->diagnostic->expected as $expected) {
                    $paths = array_map(fn ($path) => array_merge($this->path, $path), $attempt->diagnostic->rulePaths);
                    $this->recordFailure($attempt->farthestFailure, $expected, $paths);
                }
            }

            return $match !== null
                ? $match->length
                : false;
        }

        return 0;
    }
}

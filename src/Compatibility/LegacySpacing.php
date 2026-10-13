<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Compatibility;

use Vanderlee\Comprehend\Core\Context;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Tree\ConcreteNode;

/** Existing inter-item spacing: independent context, required match, deferred effects. */
final class LegacySpacing
{
    public static function recognize(Parser $spacer, string $input, int $offset, Context $context): AbstractMatch
    {
        $source = $context->source();
        $match = $source === null ? $spacer->matchUnresolved($input, $offset)
            : ($context->allowsLegacyAscii() ? $spacer->matchAsciiUnresolved($source, $offset)
                : $spacer->matchSourceUnresolved($source, $offset));
        if ($match->node !== null) {
            $node = $match->node;
            $match->setConcreteNode(new ConcreteNode($node->kind, $node->span, $node->children, true,
                $node->name, $node->group, $node->isTerminal));
        }
        return $match;
    }
}

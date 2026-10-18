<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Abnf;

use InvalidArgumentException;
use Vanderlee\Comprehend\Builder\FrozenGrammar;
use Vanderlee\Comprehend\Builder\Ruleset;
use Vanderlee\Comprehend\Directive\Prefer;
use Vanderlee\Comprehend\Extensions\Abnf\Grammar\Rfc5234;
use Vanderlee\Comprehend\Runtime\ChoicePolicy;
use Vanderlee\Comprehend\Source\SourceMode;

/**
 * Compiles the supported ABNF subset into a reusable FrozenGrammar. Seeds a Ruleset with core
 * rules, delegates syntax reading to Reader, then freezes and analyzes the graph.
 */
final class Compiler
{
    private const CORE_RULES = [
        'ALPHA',
        'BIT',
        'CHAR',
        'CR',
        'CRLF',
        'CTL',
        'DIGIT',
        'DQUOTE',
        'HEXDIG',
        'HTAB',
        'LF',
        'LWSP',
        'OCTET',
        'SP',
        'VCHAR',
        'WSP',
    ];

    public static function compile(
        string $grammar,
        string $root,
        SourceMode $numericEncoding = SourceMode::BYTES,
        ChoicePolicy $choice = ChoicePolicy::FIRST,
    ): FrozenGrammar {
        if (preg_match('/^[A-Za-z][A-Za-z0-9-]*$/D', $root) !== 1) {
            throw new InvalidArgumentException('ABNF root must be a rule name');
        }

        $rules = new Ruleset();
        $core = new Rfc5234();
        foreach (self::CORE_RULES as $name) {
            $rules->define(strtolower($name), $core->ref($name));
        }

        $reader = new Reader($grammar, $rules, $numericEncoding);
        $reader->readRules();
        $root = strtolower($root);
        if (!$rules->defined($root)) {
            throw new InvalidArgumentException('Undefined ABNF root: ' . $root);
        }

        $rules->root(new Prefer($choice, $rules->ref($root)));

        return $rules->freeze();
    }
}

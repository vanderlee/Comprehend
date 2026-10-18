<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Mail\Grammar;

use Vanderlee\Comprehend\Builder\AbstractSpecification;
use Vanderlee\Comprehend\Extensions\Xml\Grammar\Xml;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Tree\ConcreteNode;

/**
 * Domain-Based Message Authentication, Reporting, and Conformance (DMARC) Aggregate Reporting.
 *
 * DMARC aggregate report XML and report filenames.
 *
 * Obsoletes the aggregate-report portions of RFC 7489. Checks XML syntax, the report
 * root and namespace. The RFC's complete XSD constraints and reporting semantics
 * are separate validation operations.
 *
 * @see Rfc9989 DMARC policy records.
 * @see Xml XML document grammar.
 * @link https://www.rfc-editor.org/rfc/rfc9990.txt
 */
class Rfc9990 extends AbstractSpecification
{
    protected const ROOT_RULE = 'aggregate_report';

    protected function definitions(): array
    {
        return [
            'aggregate_report' => (new Xml())->rule('document')->guard(static function (ConcreteNode $node): bool {
                $roots = self::elements($node);
                if (
                    count($roots) !== 1 || preg_match(
                        '/\A<((?:[^\s:>]+:)?feedback)(?=[\s>])/',
                        $roots[0]->rawText(),
                        $name,
                    ) !== 1
                ) {
                    return false;
                }
                $prefix = str_contains($name[1], ':') ? explode(':', $name[1])[0] : '';
                $attribute = $prefix === '' ? 'xmlns' : 'xmlns:' . $prefix;
                $opening = self::named($roots[0], 'start_tag');
                if (
                    $opening === null || preg_match('/\s' . preg_quote($attribute, '/')
                    . '\s*=\s*(["\'])urn:ietf:params:xml:ns:dmarc-2\.0\1/', $opening->rawText()) !== 1
                ) {
                    return false;
                }
                $content = self::named($roots[0], 'content');
                if ($content === null) {
                    return false;
                }
                $names = [];
                foreach (self::elements($content) as $element) {
                    if (preg_match('/\A<([^\s>\/]+)/u', $element->rawText(), $child) !== 1) {
                        return false;
                    }
                    $parts = explode(':', $child[1]);
                    $names[] = end($parts);
                }

                return preg_match(
                    '/\A(?:version )?report_metadata policy_published (?:extension )?record(?: record)*\z/',
                    implode(' ', $names),
                ) === 1;
            }),
            'report_filename' => C::regex('/[^!\/\s]+![^!\/\s]+![0-9]+![0-9]+(?:![^!\/\s]+)?\.xml(?:\.gz)?/'),
        ];
    }

    /** @return list<ConcreteNode> */
    private static function elements(ConcreteNode $node): array
    {
        if ($node->name === 'element') {
            return [$node];
        }
        $elements = [];
        foreach ($node->children as $child) {
            array_push($elements, ...self::elements($child));
        }

        return $elements;
    }

    private static function named(ConcreteNode $node, string $name): ?ConcreteNode
    {
        if ($node->name === $name) {
            return $node;
        }
        foreach ($node->children as $child) {
            $found = self::named($child, $name);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }
}

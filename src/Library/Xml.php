<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Library;

use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Library\Internal\Utf8NonAscii;
use Vanderlee\Comprehend\Tree\ConcreteNode;

/**
 * Extensible Markup Language (XML) 1.0 (Fifth Edition).
 *
 * XML documents, elements, attributes and declarations.
 *
 * Operates on UTF-8 source text. Checks matching element names, unique attributes,
 * legal characters and character references. External resources are never fetched;
 * DTD validation, entity expansion and namespace processing are separate operations.
 *
 * @link https://www.w3.org/TR/2008/REC-xml-20081126/
 */
class Xml extends AbstractSpecification
{
    protected const ROOT_RULE = 'document';

    protected function definitions(): array
    {
        $s = $this->rule('s');
        $optionalS = $s->maybe();
        $nonAscii = (new Utf8NonAscii())->guard(static fn(ConcreteNode $node): bool
            => self::isChar(mb_ord($node->rawText(), 'UTF-8')));

        return [
            's' => C::regex('/[ \t\r\n]+/'),
            'char' => C::first(C::regex('/[\x09\x0a\x0d\x20-\x7f]/'), $nonAscii),
            'name_start_char' => C::first(C::regex('/[A-Za-z_:]/'), (new Utf8NonAscii())
                ->guard(static function (ConcreteNode $node): bool {
                    $cp = mb_ord($node->rawText(), 'UTF-8');

                    return (($cp >= 0xc0 && $cp <= 0xd6) || ($cp >= 0xd8 && $cp <= 0xf6)
                        || ($cp >= 0xf8 && $cp <= 0x2ff) || ($cp >= 0x370 && $cp <= 0x37d)
                        || ($cp >= 0x37f && $cp <= 0x1fff) || ($cp >= 0x200c && $cp <= 0x200d)
                        || ($cp >= 0x2070 && $cp <= 0x218f) || ($cp >= 0x2c00 && $cp <= 0x2fef)
                        || ($cp >= 0x3001 && $cp <= 0xd7ff) || ($cp >= 0xf900 && $cp <= 0xfdcf)
                        || ($cp >= 0xfdf0 && $cp <= 0xfffd) || ($cp >= 0x10000 && $cp <= 0xeffff));
                })),
            'name_char' => C::first(
                $this->rule('name_start_char'),
                C::regex('/[0-9.\-]/'),
                (new Utf8NonAscii())->guard(static function (ConcreteNode $node): bool {
                    $cp = mb_ord($node->rawText(), 'UTF-8');

                    return ($cp === 0xb7 || ($cp >= 0x300 && $cp <= 0x36f)
                        || ($cp >= 0x203f && $cp <= 0x2040));
                }),
            ),
            'name' => C::sequence($this->rule('name_start_char'), $this->rule('name_char')->many()),
            'char_ref' => C::regex('/&#(?:[0-9]+|x[0-9A-Fa-f]+);/')
                ->guard(static function (ConcreteNode $node): bool {
                    $raw = substr($node->rawText(), 2, -1);
                    $value = str_starts_with($raw, 'x') ? hexdec(substr($raw, 1)) : (float) $raw;

                    return $value <= 0x10ffff && self::isChar((int) $value);
                }),
            'entity_ref' => C::sequence('&', $this->rule('name'), ';'),
            'reference' => C::first($this->rule('char_ref'), $this->rule('entity_ref')),
            'attribute_value' => C::first(
                C::sequence('"', C::first($this->rule('reference'), C::sequence(
                    C::not(C::set('"<&')),
                    $this->rule('char'),
                ))->many(), '"'),
                C::sequence("'", C::first($this->rule('reference'), C::sequence(
                    C::not(C::set("'<&")),
                    $this->rule('char'),
                ))->many(), "'"),
            ),
            'attribute' => C::sequence(
                $this->rule('name'),
                $optionalS,
                '=',
                $optionalS,
                $this->rule('attribute_value'),
            ),
            'start_tag' => C::sequence(
                '<',
                $this->rule('name'),
                C::sequence($s, $this->rule('attribute'))->many(),
                $optionalS,
                '>',
            )->guard(self::uniqueAttributes(...)),
            'empty_element_tag' => C::sequence(
                '<',
                $this->rule('name'),
                C::sequence($s, $this->rule('attribute'))->many(),
                $optionalS,
                '/>',
            )->guard(self::uniqueAttributes(...)),
            'end_tag' => C::sequence('</', $this->rule('name'), $optionalS, '>'),
            'char_data' => C::sequence(C::not(C::first(C::text(']]>'), C::set('<&'))), $this->rule('char'))->some(),
            'comment' => C::sequence('<!--', C::sequence(C::not(C::text('--')), $this->rule('char'))->many(), '-->'),
            'cdata' => C::sequence(
                '<![CDATA[',
                C::sequence(C::not(C::text(']]>')), $this->rule('char'))->many(),
                ']]>',
            ),
            'pi' => C::sequence(
                '<?',
                $this->rule('name')->guard(static fn(ConcreteNode $node): bool
                => strcasecmp($node->rawText(), 'xml') !== 0),
                C::sequence($s, C::sequence(C::not(C::text('?>')), $this->rule('char'))->many())->maybe(),
                '?>',
            ),
            'content' => C::first(
                $this->rule('char_data'),
                $this->rule('element'),
                $this->rule('reference'),
                $this->rule('cdata'),
                $this->rule('comment'),
                $this->rule('pi'),
            )->many(),
            'element' => C::first(
                $this->rule('empty_element_tag'),
                C::sequence($this->rule('start_tag'), $this->rule('content'), $this->rule('end_tag'))
                    ->guard(static function (ConcreteNode $node): bool {
                        preg_match('/\A<([^\s\/>]+)/u', $node->rawText(), $start);
                        preg_match('/<\/([^\s>]+)\s*>\z/u', $node->rawText(), $end);

                        return isset($start[1], $end[1]) && $start[1] === $end[1];
                    }),
            ),
            'xml_decl' => C::regex('/<\?xml[ \t\r\n]+version[ \t\r\n]*=[ \t\r\n]*(?:"1\.0"|\'1\.0\')'
                . '(?:[ \t\r\n]+encoding[ \t\r\n]*=[ \t\r\n]*(?:"[A-Za-z][A-Za-z0-9._-]*"|'
                . '\'[A-Za-z][A-Za-z0-9._-]*\'))?(?:[ \t\r\n]+standalone[ \t\r\n]*=[ \t\r\n]*'
                . '(?:"(?:yes|no)"|\'(?:yes|no)\'))?[ \t\r\n]*\?>/'),
            'system_literal' => C::first(C::regex('/"[^"\x00]*"/'), C::regex("/'[^'\x00]*'/")),
            'external_id' => C::first(
                C::sequence('SYSTEM', $s, $this->rule('system_literal')),
                C::sequence('PUBLIC', $s, $this->rule('system_literal'), $s, $this->rule('system_literal')),
            ),
            'entity_decl' => C::sequence(
                '<!ENTITY',
                $s,
                C::sequence('%', $s)->maybe(),
                $this->rule('name'),
                $s,
                C::first($this->rule('system_literal'), C::sequence(
                    $this->rule('external_id'),
                    C::sequence($s, 'NDATA', $s, $this->rule('name'))->maybe(),
                )),
                $optionalS,
                '>',
            ),
            'content_particle' => C::sequence(
                C::first($this->rule('content_group'), $this->rule('name')),
                C::set('?*+')->maybe(),
            ),
            'content_group' => C::sequence(
                '(',
                $optionalS,
                $this->rule('content_particle'),
                C::first(
                    C::sequence($optionalS, '|', $optionalS, $this->rule('content_particle'))->some(),
                    C::sequence($optionalS, ',', $optionalS, $this->rule('content_particle'))->many(),
                ),
                $optionalS,
                ')',
            ),
            'mixed' => C::first(
                C::sequence('(', $optionalS, '#PCDATA', $optionalS, ')', C::text('*')->maybe()),
                C::sequence(
                    '(',
                    $optionalS,
                    '#PCDATA',
                    C::sequence($optionalS, '|', $optionalS, $this->rule('name'))->some(),
                    $optionalS,
                    ')*',
                ),
            ),
            'element_decl' => C::sequence(
                '<!ELEMENT',
                $s,
                $this->rule('name'),
                $s,
                C::first(
                    C::text('EMPTY'),
                    C::text('ANY'),
                    $this->rule('mixed'),
                    C::sequence($this->rule('content_group'), C::set('?*+')->maybe()),
                ),
                $optionalS,
                '>',
            ),
            'attribute_type' => C::first(
                C::regex('/(?:CDATA|IDREFS|IDREF|ID|ENTITIES|ENTITY|NMTOKENS|NMTOKEN)/'),
                C::sequence(
                    C::sequence('NOTATION', $s)->maybe(),
                    '(',
                    $optionalS,
                    $this->rule('name_char')->some(),
                    C::sequence($optionalS, '|', $optionalS, $this->rule('name_char')->some())->many(),
                    $optionalS,
                    ')',
                ),
            ),
            'attribute_default' => C::first(
                C::text('#REQUIRED'),
                C::text('#IMPLIED'),
                C::sequence(C::sequence('#FIXED', $s)->maybe(), $this->rule('attribute_value')),
            ),
            'attlist_decl' => C::sequence(
                '<!ATTLIST',
                $s,
                $this->rule('name'),
                C::sequence(
                    $s,
                    $this->rule('name'),
                    $s,
                    $this->rule('attribute_type'),
                    $s,
                    $this->rule('attribute_default'),
                )->many(),
                $optionalS,
                '>',
            ),
            'notation_decl' => C::sequence(
                '<!NOTATION',
                $s,
                $this->rule('name'),
                $s,
                C::first($this->rule('external_id'), C::sequence('PUBLIC', $s, $this->rule('system_literal'))),
                $optionalS,
                '>',
            ),
            'internal_subset' => C::first(
                $s,
                $this->rule('element_decl'),
                $this->rule('attlist_decl'),
                $this->rule('entity_decl'),
                $this->rule('notation_decl'),
                $this->rule('comment'),
                $this->rule('pi'),
                C::sequence('%', $this->rule('name'), ';'),
            )->many(),
            'doctypedecl' => C::sequence(
                '<!DOCTYPE',
                $s,
                $this->rule('name'),
                C::sequence($s, $this->rule('external_id'))->maybe(),
                $optionalS,
                C::sequence('[', $this->rule('internal_subset'), ']', $optionalS)->maybe(),
                '>',
            ),
            'misc' => C::first($s, $this->rule('comment'), $this->rule('pi')),
            'document' => C::sequence(
                C::text("\xef\xbb\xbf")->maybe(),
                $this->rule('xml_decl')->maybe(),
                $this->rule('misc')->many(),
                C::sequence($this->rule('doctypedecl'), $this->rule('misc')->many())->maybe(),
                $this->rule('element'),
                $this->rule('misc')->many(),
            )->guard(self::wellFormedDocument(...)),
        ];
    }

    private static function isChar(int|false $cp): bool
    {
        return $cp !== false && (in_array($cp, [9, 10, 13], true) || ($cp >= 0x20 && $cp <= 0xd7ff)
            || ($cp >= 0xe000 && $cp <= 0xfffd) || ($cp >= 0x10000 && $cp <= 0x10ffff));
    }

    private static function uniqueAttributes(ConcreteNode $node): bool
    {
        preg_match_all('/\s+([^\s=]+)\s*=\s*(?:"[^"]*"|\'[^\']*\')/u', $node->rawText(), $matches);

        return count($matches[1]) === count(array_unique($matches[1]));
    }

    private static function wellFormedDocument(ConcreteNode $node): bool
    {
        $raw = $node->rawText();
        if (
            !mb_check_encoding($raw, 'UTF-8')
            || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f]|\xef\xbf[\xbe\xbf]/', $raw)
        ) {
            return false;
        }
        $text = preg_replace('/<!--[\s\S]*?-->|<!\[CDATA\[[\s\S]*?\]\]>|<\?[\s\S]*?\?>/', '', $node->rawText());
        if ($text === null) {
            return false;
        }
        $doctypePattern = '/<!DOCTYPE\s+([^\s>\[]+)(?:"[^"]*"|\'[^\']*\'|[^"\'<>\[\]]|'
            . '\[(?:"[^"]*"|\'[^\']*\'|[^"\'\]])*\])*>/';
        preg_match($doctypePattern, $text, $doctype);
        preg_match_all('/<!ENTITY\s+([^\s%]+)\s/', $doctype[0] ?? '', $entities);
        $external = isset($doctype[0]) && preg_match('/\A<!DOCTYPE\s+\S+\s+(?:SYSTEM|PUBLIC)\s/', $doctype[0]) === 1;
        $text = preg_replace($doctypePattern, '', $text);
        if ($text === null) {
            return false;
        }
        $text = str_starts_with($text, "\xef\xbb\xbf") ? substr($text, 3) : $text;
        preg_match('/\A\s*<([^\s>\/]+)/u', $text, $root);
        if (isset($doctype[1]) && ($root[1] ?? null) !== $doctype[1]) {
            return false;
        }
        if (!$external) {
            $declared = array_merge(['amp', 'lt', 'gt', 'apos', 'quot'], $entities[1]);
            preg_match_all('/&([^#;][^;]*);/', $text, $references);
            foreach ($references[1] as $reference) {
                if (!in_array($reference, $declared, true)) {
                    return false;
                }
            }
        }

        return true;
    }
}

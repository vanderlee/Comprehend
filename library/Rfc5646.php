<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Library;

use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Tree\ConcreteNode;

/**
 * Tags for Identifying Languages.
 *
 * BCP 47 language tags.
 *
 * Obsoletes RFC 4646. Recognizes well-formed syntax, including grandfathered tags;
 * IANA registry membership and canonicalization are separate operations.
 *
 * @link https://www.rfc-editor.org/rfc/rfc5646.txt
 */
class Rfc5646 extends AbstractSpecification
{
    protected const ROOT_RULE = 'language_tag';

    protected function definitions(): array
    {
        $grandfathered = 'en-GB-oed|i-ami|i-bnn|i-default|i-enochian|i-hak|i-klingon|i-lux|i-mingo|i-navajo|'
            . 'i-pwn|i-tao|i-tay|i-tsu|sgn-BE-FR|sgn-BE-NL|sgn-CH-DE|art-lojban|cel-gaulish|no-bok|no-nyn|'
            . 'zh-guoyu|zh-hakka|zh-min-nan|zh-min|zh-xiang';

        return [
            'language' => C::regex('/(?:[a-z]{5,8}|[a-z]{4}|[a-z]{2,3}(?:-[a-z]{3}(?![a-z0-9])){0,3})(?![a-z0-9])/i'),
            'script' => C::regex('/[a-z]{4}/i'),
            'region' => C::regex('/(?:[a-z]{2}|[0-9]{3})/i'),
            'variant' => C::regex('/(?:[a-z0-9]{5,8}|[0-9][a-z0-9]{3})/i'),
            'extension' => C::regex('/[0-9a-wy-z](?:-[a-z0-9]{2,8})+/i'),
            'privateuse' => C::regex('/x(?:-[a-z0-9]{1,8})+/i'),
            'grandfathered' => C::regex('/(?:' . $grandfathered . ')/i'),
            'langtag' => C::sequence(
                $this->rule('language'),
                C::sequence('-', $this->rule('script'))->maybe(),
                C::sequence('-', $this->rule('region'))->maybe(),
                C::sequence('-', $this->rule('variant'))->many(),
                C::sequence('-', $this->rule('extension'))->many(),
                C::sequence('-', $this->rule('privateuse'))->maybe(),
            ),
            'language_tag' => C::longest(
                $this->rule('grandfathered'),
                $this->rule('privateuse'),
                $this->rule('langtag'),
            )->guard(static function (ConcreteNode $node): bool {
                $seen = [];
                $extension = false;
                foreach (explode('-', strtolower($node->rawText())) as $index => $part) {
                    if ($part === 'x') {
                        break;
                    }
                    if ($index === 0) {
                        continue;
                    }
                    if (strlen($part) === 1) {
                        if (isset($seen[$part])) {
                            return false;
                        }
                        $seen[$part] = true;
                        $extension = true;
                    } elseif (!$extension && (strlen($part) >= 5 || (strlen($part) === 4 && ctype_digit($part[0])))) {
                        if (isset($seen[$part])) {
                            return false;
                        }
                        $seen[$part] = true;
                    }
                }

                return true;
            }),
        ];
    }
}

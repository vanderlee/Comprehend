<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Mail\Grammar;

use Vanderlee\Comprehend\Builder\AbstractSpecification;
use Vanderlee\Comprehend\Comprehend as C;

/**
 * Multipurpose Internet Mail Extensions (MIME) Part One: Format of Internet Message Bodies.
 *
 * MIME content headers and transfer-encoding text.
 *
 * Recognizes syntax without decoding transfer encodings.
 *
 * @see Rfc2046 Media types and multipart bodies.
 * @link https://www.rfc-editor.org/rfc/rfc2045.txt
 */
class Rfc2045 extends AbstractSpecification
{
    protected const ROOT_RULE = 'content_type';

    protected function definitions(): array
    {
        $mail = new Rfc5322();
        $cfws = $mail->rule('cfws')->maybe();

        return [
            'token' => C::regex('/[!#$%&\'*+\-.0-9A-Z^_`a-z{|}~]+/'),
            'quoted_string' => $mail->rule('quoted_string'),
            'value' => C::first($this->rule('quoted_string'), $this->rule('token')),
            'parameter' => C::sequence($this->rule('token'), $cfws, '=', $cfws, $this->rule('value')),
            'content_type' => C::sequence(
                $cfws,
                $this->rule('token'),
                '/',
                $this->rule('token'),
                C::sequence($cfws, ';', $cfws, $this->rule('parameter'))->many(),
                $cfws,
            ),
            'content_transfer_encoding' => C::sequence($cfws, $this->rule('token'), $cfws),
            'mime_version' => C::sequence($cfws, C::regex('/[0-9]+\.[0-9]+/'), $cfws),
            'quoted_printable' => C::regex('/(?:[\x21-\x3c\x3e-\x7e]|[ \t](?=[^\r\n])|'
                . '=[0-9A-F]{2}|=\r\n|\r\n)+/')->maybe(),
            'base64' => C::regex('/(?:[A-Za-z0-9+\/ \t]|\r\n)*(?:=[ \t\r\n]*){0,2}/')->maybe(),
        ];
    }
}

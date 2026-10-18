<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Mail;

use Vanderlee\Comprehend\Syntax\SpecificationExtension;
use Vanderlee\Comprehend\Extensions\Mail\Grammar\Rfc2045;
use Vanderlee\Comprehend\Extensions\Mail\Grammar\Rfc2046;
use Vanderlee\Comprehend\Extensions\Mail\Grammar\Rfc4408;
use Vanderlee\Comprehend\Extensions\Mail\Grammar\Rfc5321;
use Vanderlee\Comprehend\Extensions\Mail\Grammar\Rfc5322;
use Vanderlee\Comprehend\Extensions\Mail\Grammar\Rfc6376;
use Vanderlee\Comprehend\Extensions\Mail\Grammar\Rfc6531;
use Vanderlee\Comprehend\Extensions\Mail\Grammar\Rfc6532;
use Vanderlee\Comprehend\Extensions\Mail\Grammar\Rfc6854;
use Vanderlee\Comprehend\Extensions\Mail\Grammar\Rfc7208;
use Vanderlee\Comprehend\Extensions\Mail\Grammar\Rfc9989;
use Vanderlee\Comprehend\Extensions\Mail\Grammar\Rfc9990;
use Vanderlee\Comprehend\Extensions\Mail\Grammar\Rfc9991;

/** Discoverable Mail entry points over the shared specification grammars. */
final class Mail extends SpecificationExtension
{
    protected const PARSERS = [
        'emailAddress' => [Rfc5322::class, 'addr_spec'],
        'mailbox' => [Rfc5322::class, 'mailbox'],
        'mailboxList' => [Rfc5322::class, 'mailbox_list'],
        'emailAddressList' => [Rfc5322::class, 'address_list'],
        'mailMessage' => [Rfc5322::class, 'message'],
        'emailLocalPart' => [Rfc5322::class, 'local_part'],
        'emailDomain' => [Rfc5322::class, 'domain'],
        'emailDotAtomText' => [Rfc5322::class, 'dot_atom_text'],
        'emailQuotedString' => [Rfc5322::class, 'quoted_string'],
        'emailFoldingWhitespace' => [Rfc5322::class, 'fws'],
        'emailCommentsAndFoldingWhitespace' => [Rfc5322::class, 'cfws'],
    ];

    protected const VERSIONS = [
        'rfc2045' => Rfc2045::class,
        'rfc2046' => Rfc2046::class,
        'rfc4408' => Rfc4408::class,
        'rfc5321' => Rfc5321::class,
        'rfc5322' => Rfc5322::class,
        'rfc6376' => Rfc6376::class,
        'rfc6531' => Rfc6531::class,
        'rfc6532' => Rfc6532::class,
        'rfc6854' => Rfc6854::class,
        'rfc7208' => Rfc7208::class,
        'rfc9989' => Rfc9989::class,
        'rfc9990' => Rfc9990::class,
        'rfc9991' => Rfc9991::class,
    ];
}

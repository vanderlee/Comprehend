<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend;

use Closure;
use Vanderlee\Comprehend\Builder\AbstractRuleset;
use Vanderlee\Comprehend\Builder\Definition;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Extensions\Numbers\Parser\IntegerNumber;
use Vanderlee\Comprehend\Extensions\Numbers\SignPolicy;
use Vanderlee\Comprehend\Syntax\Catalog;
use Vanderlee\Comprehend\Syntax\SyntaxExtension;

/**
 * Defines an immutable vocabulary of custom grammar factories and fluent operations. Delegates
 * authoring calls through Catalog to Comprehend so extensions use the same parser runtime.
 * BEGIN GENERATED FACTORIES (tools/generate-syntax-phpdoc.php)
 * @method Comprehend<string> text(string $text)
 * @method Comprehend<string> regex(string $pattern)
 * @method Comprehend<string> char(string $character, bool $include = true)
 * @method Comprehend<string> set(string $characters, bool $include = true)
 * @method Comprehend<string> range(?string $first, ?string $last, bool $in = true)
 * @method Comprehend<string> any()
 * @method Comprehend<string> nothing()
 * @method Comprehend<string> start()
 * @method Comprehend<string> end()
 * @method Comprehend<string> literalWindow(string $literal)
 * @method Comprehend<list<mixed>> separatedBy(mixed ...$arguments)
 * @method Comprehend<string> sequence(Comprehend|Parser|string ...$parsers)
 * @method Comprehend<string> choice(Comprehend|Parser|string ...$parsers)
 * @method Comprehend<string> first(Comprehend|Parser|string ...$parsers)
 * @method Comprehend<string> longest(Comprehend|Parser|string ...$parsers)
 * @method Comprehend<string> shortest(Comprehend|Parser|string ...$parsers)
 * @method Comprehend<string> all(Comprehend|Parser|string ...$parsers)
 * @method Comprehend<string> except(Comprehend|Parser|string $match, Comprehend|Parser|string $not)
 * @method Comprehend<string> not(Comprehend|Parser|string $parser)
 * @method Comprehend ref(AbstractRuleset $rules, string $name, mixed ...$arguments)
 * @method Comprehend or(Comprehend|Parser|string ...$parsers)
 * @method Comprehend repeat(mixed ...$arguments)
 * @method Comprehend some(mixed ...$arguments)
 * @method Comprehend many(mixed ...$arguments)
 * @method Comprehend maybe(mixed ...$arguments)
 * @method Comprehend plus(mixed ...$arguments)
 * @method Comprehend star(mixed ...$arguments)
 * @method Comprehend kleene(mixed ...$arguments)
 * @method Comprehend optional(mixed ...$arguments)
 * @method Comprehend<string> integer(?int $minimum = 0, ?int $maximum = null, int $base = 10, bool $allowLeadingZeros = false, string $digits = IntegerNumber::DIGITS, SignPolicy $sign = SignPolicy::MINUS_ONLY, bool $strict = false)
 * @method Comprehend<string> binary(?int $minimum = 0, ?int $maximum = null, bool $allowLeadingZeros = false, SignPolicy $sign = SignPolicy::MINUS_ONLY, bool $strict = false)
 * @method Comprehend<string> octal(?int $minimum = 0, ?int $maximum = null, bool $allowLeadingZeros = false, SignPolicy $sign = SignPolicy::MINUS_ONLY, bool $strict = false)
 * @method Comprehend<string> hexadecimal(?int $minimum = 0, ?int $maximum = null, bool $allowLeadingZeros = false, SignPolicy $sign = SignPolicy::MINUS_ONLY, bool $strict = false)
 * @method Comprehend<string> octet(bool $allowLeadingZeros = false, SignPolicy $sign = SignPolicy::MINUS_ONLY, bool $strict = false)
 * @method Comprehend<string> float(?float $minimum = 0.0, ?float $maximum = null, bool $allowLeadingZeros = false, bool $allowExponent = true, SignPolicy $sign = SignPolicy::MINUS_ONLY, bool $allowWholeNumbers = true, bool $strict = false)
 * @method Comprehend<int> integerValue(?int $minimum = 0, ?int $maximum = null, int $base = 10, bool $allowLeadingZeros = false, string $digits = IntegerNumber::DIGITS, SignPolicy $sign = SignPolicy::MINUS_ONLY, bool $strict = false)
 * @method Comprehend<int> binaryValue(?int $minimum = 0, ?int $maximum = null, bool $allowLeadingZeros = false, SignPolicy $sign = SignPolicy::MINUS_ONLY, bool $strict = false)
 * @method Comprehend<int> octalValue(?int $minimum = 0, ?int $maximum = null, bool $allowLeadingZeros = false, SignPolicy $sign = SignPolicy::MINUS_ONLY, bool $strict = false)
 * @method Comprehend<int> hexadecimalValue(?int $minimum = 0, ?int $maximum = null, bool $allowLeadingZeros = false, SignPolicy $sign = SignPolicy::MINUS_ONLY, bool $strict = false)
 * @method Comprehend<int> octetValue(bool $allowLeadingZeros = false, SignPolicy $sign = SignPolicy::MINUS_ONLY, bool $strict = false)
 * @method Comprehend<float> floatValue(?float $minimum = 0.0, ?float $maximum = null, bool $allowLeadingZeros = false, bool $allowExponent = true, SignPolicy $sign = SignPolicy::MINUS_ONLY, bool $allowWholeNumbers = true, bool $strict = false)
 * @method Comprehend<string> readWhile(callable $predicate, int $min = 0, ?int $max = null)
 * @method Comprehend<string> readUntil(string|callable $delimiter, int $min = 0, ?int $max = null)
 * @method Comprehend<string> readWhileSet(string $characters, int $min = 0, ?int $max = null)
 * @method Comprehend<string> readWhileRange(?string $first, ?string $last, int $min = 0, ?int $max = null)
 * @method Comprehend<string> readRest()
 * @method Comprehend<null> skipWhile(callable $predicate, int $min = 0, ?int $max = null)
 * @method Comprehend<null> skipUntil(string|callable $delimiter, int $min = 0, ?int $max = null)
 * @method Comprehend<null> skipRest()
 * @method Comprehend<string> rfc2234(string $rule = 'ROOT')
 * @method Comprehend<string> rfc4234(string $rule = 'ROOT')
 * @method Comprehend<string> rfc5234(string $rule = 'ROOT')
 * @method Comprehend<string> rfc7405(string $rule = 'ROOT')
 * @method Comprehend<string> dateTime()
 * @method Comprehend<string> annotatedDateTime()
 * @method Comprehend<string> dateTimeDate()
 * @method Comprehend<string> dateTimeTime()
 * @method Comprehend<string> rfc3339(string $rule = 'ROOT')
 * @method Comprehend<string> rfc9557(string $rule = 'ROOT')
 * @method Comprehend<string> base16()
 * @method Comprehend<string> base32()
 * @method Comprehend<string> base32Hex()
 * @method Comprehend<string> base64()
 * @method Comprehend<string> base64Url()
 * @method Comprehend<string> base64Unpadded()
 * @method Comprehend<string> base64UrlUnpadded()
 * @method Comprehend<string> rfc4648(string $rule = 'ROOT')
 * @method Comprehend<string> structuredFieldItem()
 * @method Comprehend<string> structuredFieldList()
 * @method Comprehend<string> structuredFieldDictionary()
 * @method Comprehend<string> rfc9651(string $rule = 'ROOT')
 * @method Comprehend<string> json()
 * @method Comprehend<string> jsonPointer()
 * @method Comprehend<string> jsonPath()
 * @method Comprehend<string> jsonString()
 * @method Comprehend<string> jsonNumber()
 * @method Comprehend<string> rfc6901(string $rule = 'ROOT')
 * @method Comprehend<string> rfc8259(string $rule = 'ROOT')
 * @method Comprehend<string> rfc9535(string $rule = 'ROOT')
 * @method Comprehend<string> languageTag()
 * @method Comprehend<string> rfc5646(string $rule = 'ROOT')
 * @method Comprehend<string> emailAddress()
 * @method Comprehend<string> mailbox()
 * @method Comprehend<string> mailboxList()
 * @method Comprehend<string> emailAddressList()
 * @method Comprehend<string> mailMessage()
 * @method Comprehend<string> emailLocalPart()
 * @method Comprehend<string> emailDomain()
 * @method Comprehend<string> emailDotAtomText()
 * @method Comprehend<string> emailQuotedString()
 * @method Comprehend<string> emailFoldingWhitespace()
 * @method Comprehend<string> emailCommentsAndFoldingWhitespace()
 * @method Comprehend<string> rfc2045(string $rule = 'ROOT')
 * @method Comprehend<string> rfc2046(string $rule = 'ROOT')
 * @method Comprehend<string> rfc4408(string $rule = 'ROOT')
 * @method Comprehend<string> rfc5321(string $rule = 'ROOT')
 * @method Comprehend<string> rfc5322(string $rule = 'ROOT')
 * @method Comprehend<string> rfc6376(string $rule = 'ROOT')
 * @method Comprehend<string> rfc6531(string $rule = 'ROOT')
 * @method Comprehend<string> rfc6532(string $rule = 'ROOT')
 * @method Comprehend<string> rfc6854(string $rule = 'ROOT')
 * @method Comprehend<string> rfc7208(string $rule = 'ROOT')
 * @method Comprehend<string> rfc9989(string $rule = 'ROOT')
 * @method Comprehend<string> rfc9990(string $rule = 'ROOT')
 * @method Comprehend<string> rfc9991(string $rule = 'ROOT')
 * @method Comprehend<string> ipv4()
 * @method Comprehend<string> ipv6()
 * @method Comprehend<string> hostname()
 * @method Comprehend<string> uri()
 * @method Comprehend<string> uriReference()
 * @method Comprehend<string> absoluteUri()
 * @method Comprehend<string> iri()
 * @method Comprehend<string> urn()
 * @method Comprehend<string> uriTemplate()
 * @method Comprehend<string> hostnameLabel()
 * @method Comprehend<string> uriScheme()
 * @method Comprehend<string> uriAuthority()
 * @method Comprehend<string> uriHost()
 * @method Comprehend<string> uriPort()
 * @method Comprehend<string> uriRelativePart()
 * @method Comprehend<string> uriPathAbempty()
 * @method Comprehend<string> uriSegment()
 * @method Comprehend<string> uriQuery()
 * @method Comprehend<string> uriFragment()
 * @method Comprehend<string> uriPercentEncoded()
 * @method Comprehend<string> uriUnreserved()
 * @method Comprehend<string> iriUcschar()
 * @method Comprehend<string> iriPrivateChar()
 * @method Comprehend<string> rfc1034(string $rule = 'ROOT')
 * @method Comprehend<string> rfc1035(string $rule = 'ROOT')
 * @method Comprehend<string> rfc1123(string $rule = 'ROOT')
 * @method Comprehend<string> rfc3513(string $rule = 'ROOT')
 * @method Comprehend<string> rfc3986(string $rule = 'ROOT')
 * @method Comprehend<string> rfc3987(string $rule = 'ROOT')
 * @method Comprehend<string> rfc4291(string $rule = 'ROOT')
 * @method Comprehend<string> rfc6570(string $rule = 'ROOT')
 * @method Comprehend<string> rfc8141(string $rule = 'ROOT')
 * @method Comprehend<string> semver()
 * @method Comprehend<string> uuid()
 * @method Comprehend<string> uuidUrn()
 * @method Comprehend<string> rfc9562(string $rule = 'ROOT')
 * @method Comprehend<string> xmlDocument()
 * @method Comprehend<string> yaml()
 * @method Comprehend<array<array-key, mixed>> record(array<array-key, Comprehend|Parser|string> $fields)
 * @method Comprehend<list<mixed>> collect(Comprehend|Parser|string ...$parsers)
 * @method Comprehend<mixed> valueChoice(Comprehend|Parser|string ...$parsers)
 * @method Comprehend<list<mixed>> repeatValues(Comprehend|Parser|string $parser, int $min = 0, ?int $max = null)
 * @method Comprehend<list<mixed>> manyValues(Comprehend|Parser|string $parser)
 * @method Comprehend<list<mixed>> someValues(Comprehend|Parser|string $parser)
 * @method Comprehend<mixed> optionalValue(Comprehend|Parser|string $parser, mixed $default = null)
 * END GENERATED FACTORIES
 */
final readonly class Syntax
{
    private function __construct(private Catalog $catalog) {}

    public static function create(): self
    {
        return new self(new Catalog());
    }

    /**
     * Attach this vocabulary to an existing expression or detached parser graph.
     *
     * @template U
     * @param Comprehend<U>|Parser|Definition $parser
     * @return ($parser is Comprehend<U> ? Comprehend<U> : Comprehend<mixed>)
     */
    public function of(Comprehend|Parser|Definition $parser): Comprehend
    {
        return Comprehend::of($parser, $this);
    }

    /**
     * Existing expressions retain their original catalog.
     */
    public function defineRule(string $name, callable $factory): self
    {
        return new self($this->catalog->defineRule($name, $factory));
    }

    /**
     * Install rule then fluent factories; the original vocabulary survives installation errors.
     */
    public function extend(SyntaxExtension $extension): self
    {
        $syntax = $this;

        foreach ($extension->rules() as $name => $factory) {
            $syntax = $syntax->defineRule($name, $factory);
        }

        foreach ($extension->fluents() as $name => $factory) {
            $syntax = $syntax->defineFluent($name, $factory);
        }

        return $syntax;
    }

    /**
     * Callbacks receive the current facade before user arguments.
     */
    public function defineFluent(string $name, callable $factory): self
    {
        return new self($this->catalog->defineFluent($name, $factory));
    }

    /**
     * Start an expression through static factory lookup, including when a fluent shares its name.
     *
     * @param array<array-key, mixed> $arguments
     */
    public function __call(string $name, array $arguments): Comprehend
    {
        return Comprehend::callSyntax($name, $arguments, $this);
    }

    /**
     * Explicit-name equivalent of magic factory dispatch; argument keys are retained.
     *
     *
     *
     * @param string $name
     * @param mixed ...$arguments
     * @return Comprehend
     */
    public function call(string $name, mixed ...$arguments): Comprehend
    {
        return Comprehend::callSyntax($name, $arguments, $this);
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return $this->catalog->names();
    }

    /**
     * @internal Authoring dispatch only.
     * @param string $name
     * @return Closure|null
     */
    public function rule(string $name): ?Closure
    {
        return $this->catalog->rule($name);
    }

    /**
     * @internal Authoring dispatch only.
     * @param string $name
     * @return Closure|null
     */
    public function fluent(string $name): ?Closure
    {
        return $this->catalog->fluent($name);
    }
}

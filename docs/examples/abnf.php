<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Vanderlee\Comprehend\Extensions\Abnf\Compiler;
use Vanderlee\Comprehend\Builder\Ruleset;
use Vanderlee\Comprehend\Directive\Prefer;
use Vanderlee\Comprehend\Extensions\Network\Grammar\Rfc3986;
use Vanderlee\Comprehend\Extensions\Abnf\Grammar\Rfc5234;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Range;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Runtime\ChoicePolicy;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Tree\AstNode;

$abnf = <<<'ABNF'
IPv4address = dec-octet "." dec-octet "." dec-octet "." dec-octet
dec-octet = DIGIT / %x31-39 DIGIT / "1" 2DIGIT
 / "2" %x30-34 DIGIT / "25" %x30-35
ABNF;
$compiled = Compiler::compile($abnf, 'IPv4address', choice: ChoicePolicy::LONGEST);

$rules = new Ruleset();
$rules->define('digit', (new Rfc5234())->ref('DIGIT'));
$rules->define('dec-octet', new Choice(
    $rules->ref('digit'),
    new Sequence(new Range('1', '9'), $rules->ref('digit')),
    new Sequence(new Text('1'), Repeat::exact($rules->ref('digit'), 2)),
    new Sequence(new Text('2'), new Range('0', '4'), $rules->ref('digit')),
    new Sequence(new Text('25'), new Range('0', '5')),
));
$address = new Sequence(
    $rules->ref('dec-octet'),
    new Text('.'),
    $rules->ref('dec-octet'),
    new Text('.'),
    $rules->ref('dec-octet'),
    new Text('.'),
    $rules->ref('dec-octet'),
);
$rules->define('ipv4address', $address)->root(new Prefer(ChoicePolicy::LONGEST, $rules->ref('ipv4address')));
$object = (new Prefer(ChoicePolicy::LONGEST, $rules->ref('ipv4address')))->freeze();
$named = $rules->freeze();
$bundled = (new Rfc3986())->ref('IPv4address')->freeze();

function abnfAstShape(AstNode $node): array
{
    return [$node->name, $node->group, $node->span->start, $node->span->end,
        array_map(abnfAstShape(...), $node->children)];
}

foreach (['192.0.2.1' => true, '0.0.0.0' => true, '255.255.255.255' => true,
    '256.0.0.1' => false, '01.2.3.4' => false, '1.2.3' => false, '1.2.3.4x' => false] as $text => $accepted) {
    $shape = null;
    foreach ([$compiled, $object, $named, $bundled] as $index => $grammar) {
        $result = $grammar->parseAll(new ByteSource($text));
        if ($result->success !== $accepted) {
            throw new RuntimeException('IPv4 acceptance disagreement: ' . $text);
        }
        if (!$accepted) {
            continue;
        }
        if ($result->rawText() !== $text || $result->value() !== $text || $result->end !== strlen($text)) {
            throw new RuntimeException('IPv4 output/span disagreement');
        }
        // The bundled RFC uses its own historical rule spellings/groups.
        if ($index < 3) {
            $current = abnfAstShape($result->ast());
            if ($shape !== null && $shape !== $current) {
                throw new RuntimeException('Translated IPv4 AST disagreement');
            }
            $shape = $current;
        }
    }
}

echo "ABNF, object, Ruleset and bundled RFC IPv4 acceptance/output examples passed.\n";

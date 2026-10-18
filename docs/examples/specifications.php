<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Vanderlee\Comprehend\Extensions\Library\Rfc3339;
use Vanderlee\Comprehend\Extensions\Library\Rfc5322;
use Vanderlee\Comprehend\Extensions\Library\Rfc8259;
use Vanderlee\Comprehend\Extensions\Library\Rfc9535;
use Vanderlee\Comprehend\Extensions\Library\Rfc9651;
use Vanderlee\Comprehend\Extensions\Library\SemVer;
use Vanderlee\Comprehend\Extensions\Library\Xml;
use Vanderlee\Comprehend\Extensions\Library\Yaml;

$examples = [
    [new Rfc5322(), 'Alice <alice@example.org>'],
    [new Rfc3339(), '2024-02-29T12:34:56Z'],
    [new Rfc8259(), '{"items":[1,true,null]}'],
    [new Rfc9535(), '$.items[?@.price < 10]'],
    [new Rfc9651(), 'token;flag, ("a" "b");q=0.5'],
    [new SemVer(), '1.2.3-rc.1+build.007'],
    [new Xml(), '<root lang="en"><item>Comprehend</item></root>'],
    [new Yaml(), "name: Comprehend\nfeatures:\n  - source spans\n  - composable grammars\n"],
];
foreach ($examples as [$specification, $input]) {
    $result = $specification->freeze()->parse($input);
    if (!$result->success || $result->rawText() !== $input) {
        throw new RuntimeException('Specification example failed: ' . $specification->groupName());
    }
    echo $specification->groupName() . ': accepted ' . $result->consumedByteLength . ' bytes' . PHP_EOL;
}

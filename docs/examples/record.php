<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Extensions\Extraction;

$syntax = Extraction::syntax();
$record = $syntax->record([
    'name' => $syntax->readWhileRange('a', 'z', min: 1),
    'count' => $syntax->integer()->mapText('intval'),
])->spacing(C::readWhileSet(' '))->freeze();
$result = $record->parse('item 12');
if (!$result->success || $result->value() !== ['name' => 'item', 'count' => 12]) {
    throw new RuntimeException('Record extraction failed');
}
echo "Record extraction verified\n";

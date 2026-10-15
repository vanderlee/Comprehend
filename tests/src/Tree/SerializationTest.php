<?php

declare(strict_types=1);

namespace Tests\Src\Tree;

use JsonSerializable;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Span;
use Vanderlee\Comprehend\Tree\Serialization;

uses(TestCase::class);

test('hooks supply all data without implicit node properties', function (): void {
    $span = (new ByteSource("\xff"))->span(0, 1);
    $node = new class ($span) implements JsonSerializable {
        use Serialization;

        private readonly Span $sourceSpan;

        public function __construct(Span $span)
        {
            $this->sourceSpan = $span;
        }

        /** @return array{name: string, group: null, span: Span, children: list<self>, tags: list<string>} */
        protected function serializationData(): array
        {
            return [
                'name' => 'custom',
                'group' => null,
                'span' => $this->sourceSpan,
                'children' => [],
                'tags' => ['tag'],
            ];
        }

        /** @return array<string, string|bool> */
        protected function serializationFields(): array
        {
            return ['kind' => 'custom', 'selected' => true, 'hidden' => false];
        }

        /** @return array<string, list<Span>> */
        protected function serializationSurroundings(): array
        {
            return ['preamble' => [$this->sourceSpan], 'postamble' => []];
        }
    };

    $data = json_decode($node->toJson(), true, flags: JSON_THROW_ON_ERROR);
    $this->assertSame($node->toArray(), $data);
    $this->assertSame('base64', $data['textEncoding']);
    $this->assertSame("\xff", base64_decode($data['text']));
    $this->assertTrue($data['selected']);
    $this->assertFalse($data['hidden']);
    $this->assertSame(['tag'], $data['tags']);
    $this->assertArrayNotHasKey('postamble', $data);
    $xml = $node->toXml();
    $this->assertSame('custom', $xml->documentElement->getAttribute('kind'));
    $this->assertSame('true', $xml->documentElement->getAttribute('selected'));
    $this->assertSame('false', $xml->documentElement->getAttribute('hidden'));
    $this->assertSame(1, $xml->getElementsByTagName('preamble')->length);
    $this->assertSame(0, $xml->getElementsByTagName('postamble')->length);
});

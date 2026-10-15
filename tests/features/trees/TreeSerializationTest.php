<?php

declare(strict_types=1);

namespace Tests\features\trees;

use Tests\ParserTestCase;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Nothing;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Utf8Source;

class TreeSerializationTest extends ParserTestCase
{
    public function testConcreteAndAstSerializationUseSourceSpansAndNamingAsData(): void
    {
        $letter = (new Text('é'))->name('unsafe: <name>', 'group & value')->setResult('letter', 'changed');
        $parser = (new Sequence(
            new Text('('),
            $letter,
            (new Text(' '))->trivia(),
            (new Nothing())->name('empty'),
            new Text(')'),
        ))->name('root');
        $result = $parser->parseAll(new Utf8Source('(é )'));
        $concrete = json_decode($result->node->toJson(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('(é )', $concrete['text']);
        $this->assertSame([0, 5], [$concrete['start'], $concrete['end']]);
        $this->assertCount(5, $concrete['children']);
        $this->assertTrue($concrete['children'][2]['trivia']);
        $ast = $result->ast()->toArray();
        $this->assertSame(['unsafe: <name>', 'empty'], array_column($ast['children'], 'name'));
        $this->assertSame('é', $ast['children'][0]['text']);
        $this->assertSame([4, 4], [$ast['children'][1]['start'], $ast['children'][1]['end']]);
        $xml = $result->node->toXml();
        $this->assertSame('root', $xml->documentElement->getAttribute('name'));
        $this->assertSame('(é )', base64_decode($xml->getElementsByTagName('text')[0]->textContent));
        $reloaded = new \DOMDocument();
        $this->assertTrue($reloaded->loadXML($xml->saveXML()));
        $this->assertSame('changed', $result->result('letter'));
        $this->assertSame('é', $letter->parseAll(new Utf8Source('é'))->ast()->rawText());
    }

    public function testArbitraryBytesRoundTripThroughJsonAndXml(): void
    {
        $raw = "\xff\x00\x01";
        $node = (new Text($raw))->parseAll(new ByteSource($raw))->node;
        $data = json_decode($node->toJson(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('base64', $data['textEncoding']);
        $this->assertSame($raw, base64_decode($data['text']));
        $xml = $node->toXml();
        $this->assertSame($raw, base64_decode($xml->getElementsByTagName('text')[0]->textContent));
        $this->assertSame('base64', $xml->getElementsByTagName('text')[0]->getAttribute('encoding'));
        $this->assertSame($raw, $node->rawText());
    }
}

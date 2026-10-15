<?php

declare(strict_types=1);

namespace Tests\Src\Tree;

use PHPUnit\Framework\TestCase;
use DOMDocument;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Tree\ConcreteNode;
use Vanderlee\Comprehend\Tree\XmlSerializer;

final class XmlSerializerTest extends TestCase
{
    public function testVisitorUsesSuppliedDocumentAndEscapesMetadata(): void
    {
        $span = (new ByteSource("\x00"))->span(0, 1);
        $node = new ConcreteNode('text', $span, name: '<&');
        $document = new DOMDocument();
        $element = $node->accept(new XmlSerializer($document));
        $document->appendChild($element);
        $this->assertSame($document, $element->ownerDocument);
        $this->assertSame('<&', $element->getAttribute('name'));
        $this->assertSame('AA==', $element->getElementsByTagName('text')[0]->textContent);
        $this->assertStringContainsString('&lt;&amp;', $document->saveXML());
        $this->assertFalse($node->ast()->accept(new XmlSerializer(new DOMDocument()))->hasAttribute('kind'));
    }
}

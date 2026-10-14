<?php

declare (strict_types=1);

namespace Vanderlee\Comprehend\Tree;

use DOMDocument;
use DOMElement;

/** The same explicit source-span schema for concrete and named trees. */
trait Serialization
{
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function toArray(): array
    {
        $raw = $this->rawText();
        $isUtf8 = mb_check_encoding($raw, 'UTF-8');
        $data = [
            'name' => $this->name,
            'group' => $this->group,
            'start' => $this->span->start,
            'end' => $this->span->end,
            'text' => $isUtf8 ? $raw : base64_encode($raw),
            'textEncoding' => $isUtf8 ? 'utf8' : 'base64',
            'children' => array_map(static fn($child) => $child->toArray(), $this->children),
        ];
        if ($this instanceof ConcreteNode) {
            $data['kind'] = $this->kind;
            $data['trivia'] = $this->isTrivia;
            $data['semanticLeaf'] = $this->semanticLeaf;
        }
        return $data;
    }

    public function toJson(): string
    {
        return json_encode($this, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /** Fixed XML element names; grammar names/groups are data, never namespaces. */
    public function toXml(): DOMDocument
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->appendChild($this->xmlNode($document));
        return $document;
    }

    private function xmlNode(DOMDocument $document): DOMElement
    {
        $element = $document->createElement('node');
        if ($this->name !== null) {
            $element->setAttribute('name', (string)$this->name);
        }
        if ($this->group !== null) {
            $element->setAttribute('group', $this->group);
        }
        $element->setAttribute('start', (string)$this->span->start);
        $element->setAttribute('end', (string)$this->span->end);
        if ($this instanceof ConcreteNode) {
            $element->setAttribute('kind', $this->kind);
            $element->setAttribute('trivia', $this->isTrivia ? 'true' : 'false');
            $element->setAttribute('semanticLeaf', $this->semanticLeaf ? 'true' : 'false');
        }
        // Bytes are valid parser data even when they cannot be XML text.
        $raw = $this->rawText();
        $text = $document->createElement('text');
        $text->setAttribute('encoding', 'base64');
        $text->appendChild($document->createTextNode(base64_encode($raw)));
        $element->appendChild($text);
        foreach ($this->children as $child) {
            $element->appendChild($child->xmlNode($document));
        }
        return $element;
    }
}

<?php

declare (strict_types=1);

namespace Vanderlee\Comprehend\Tree;

use DOMDocument;
use DOMElement;
use LogicException;
use Vanderlee\Comprehend\Source\Span;

/** The same explicit source-span schema for concrete and named trees. */
trait Serialization
{
    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'name' => $this->name,
            'group' => $this->group,
            ...self::spanArray($this->span),
            'children' => array_map(static fn($child) => $child->toArray(), $this->children),
        ];
        if ($this instanceof ConcreteNode) {
            $data['kind'] = $this->kind;
            $data['trivia'] = $this->isTrivia;
            $data['semanticLeaf'] = $this->semanticLeaf;
        }
        if ($this->tags !== []) {
            $data['tags'] = $this->tags;
        }
        if ($this instanceof AstNode) {
            foreach (['preamble', 'postamble'] as $side) {
                if ($this->$side !== []) {
                    $data[$side] = array_map(self::spanArray(...), $this->$side);
                }
            }
        }
        return $data;
    }

    /**
     * @return array{start: int, end: int, text: string, textEncoding: 'utf8'|'base64'}
     */
    private static function spanArray(Span $span): array
    {
        $raw = $span->rawText();
        $utf8 = mb_check_encoding($raw, 'UTF-8');
        return [
            'start' => $span->start,
            'end' => $span->end,
            'text' => $utf8 ? $raw : base64_encode($raw),
            'textEncoding' => $utf8 ? 'utf8' : 'base64',
        ];
    }

    public function toJson(): string
    {
        return json_encode($this, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /** Fixed XML element names; grammar names/groups are data, never namespaces. */
    public function toXml(): DOMDocument
    {
        if (!class_exists(DOMDocument::class)) {
            throw new LogicException('XML serialization requires the ext-dom PHP extension');
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $document->appendChild($this->xmlNode($document));
        return $document;
    }

    private function xmlNode(DOMDocument $document): DOMElement
    {
        $element = $document->createElement('node');
        if ($this->name !== null) {
            $element->setAttribute('name', (string) $this->name);
        }
        if ($this->group !== null) {
            $element->setAttribute('group', $this->group);
        }
        $element->setAttribute('start', (string) $this->span->start);
        $element->setAttribute('end', (string) $this->span->end);
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
        $this->appendXmlTags($document, $element);
        $this->appendXmlSurroundings($document, $element);
        foreach ($this->children as $child) {
            $element->appendChild($child->xmlNode($document));
        }
        return $element;
    }

    private function appendXmlTags(DOMDocument $document, DOMElement $element): void
    {
        if ($this->tags !== []) {
            $tags = $document->createElement('tags');
            foreach ($this->tags as $tag) {
                $entry = $document->createElement('tag');
                $entry->appendChild($document->createTextNode($tag));
                $tags->appendChild($entry);
            }
            $element->appendChild($tags);
        }
    }

    private function appendXmlSurroundings(DOMDocument $document, DOMElement $element): void
    {
        if ($this instanceof AstNode) {
            foreach (['preamble', 'postamble'] as $side) {
                if ($this->$side === []) {
                    continue;
                }
                $container = $document->createElement($side);
                foreach ($this->$side as $span) {
                    $fragment = $document->createElement('span');
                    $fragment->setAttribute('start', (string) $span->start);
                    $fragment->setAttribute('end', (string) $span->end);
                    $raw = $document->createElement('text');
                    $raw->setAttribute('encoding', 'base64');
                    $raw->appendChild($document->createTextNode(base64_encode($span->rawText())));
                    $fragment->appendChild($raw);
                    $container->appendChild($fragment);
                }
                $element->appendChild($container);
            }
        }
    }
}

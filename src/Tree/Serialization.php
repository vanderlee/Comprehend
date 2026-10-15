<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Tree;

use DOMDocument;
use DOMElement;
use LogicException;
use Vanderlee\Comprehend\Source\Span;

/**
 * Shares array, JSON and XML serialization between ConcreteNode and AstNode. Encodes source
 * spans explicitly and preserves arbitrary bytes without relying on semantic output.
 */
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
            foreach (self::surroundings($this) as $side => $spans) {
                if ($spans !== []) {
                    $data[$side] = array_map(self::spanArray(...), $spans);
                }
            }
        }

        return $data;
    }

    /** @return array{preamble: list<Span>, postamble: list<Span>} */
    private static function surroundings(AstNode $node): array
    {
        return ['preamble' => $node->preamble, 'postamble' => $node->postamble];
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
            self::appendConcreteAttributes($element, $this);
        }
        // Bytes are valid parser data even when they cannot be XML text.
        $raw = $this->rawText();
        self::appendXmlText($document, $element, $raw);
        $this->appendXmlTags($document, $element);
        $this->appendXmlSurroundings($document, $element);
        foreach ($this->children as $child) {
            $element->appendChild($child->xmlNode($document));
        }

        return $element;
    }

    private static function appendXmlText(DOMDocument $document, DOMElement $element, string $raw): void
    {
        $text = $document->createElement('text');
        $text->setAttribute('encoding', 'base64');
        $text->appendChild($document->createTextNode(base64_encode($raw)));
        $element->appendChild($text);
    }

    private static function appendConcreteAttributes(DOMElement $element, ConcreteNode $node): void
    {
        $element->setAttribute('kind', $node->kind);
        $element->setAttribute('trivia', $node->isTrivia ? 'true' : 'false');
        $element->setAttribute('semanticLeaf', $node->semanticLeaf ? 'true' : 'false');
    }

    private function appendXmlTags(DOMDocument $document, DOMElement $element): void
    {
        if ($this->tags === []) {
            return;
        }

        $tags = $document->createElement('tags');
        foreach ($this->tags as $tag) {
            $entry = $document->createElement('tag');
            $entry->appendChild($document->createTextNode($tag));
            $tags->appendChild($entry);
        }
        $element->appendChild($tags);
    }

    private function appendXmlSurroundings(DOMDocument $document, DOMElement $element): void
    {
        if (!$this instanceof AstNode) {
            return;
        }

        foreach (self::surroundings($this) as $side => $spans) {
            if ($spans === []) {
                continue;
            }

            $container = $document->createElement($side);
            foreach ($spans as $span) {
                $fragment = $document->createElement('span');
                $fragment->setAttribute('start', (string) $span->start);
                $fragment->setAttribute('end', (string) $span->end);
                self::appendXmlText($document, $fragment, $span->rawText());
                $container->appendChild($fragment);
            }
            $element->appendChild($container);
        }
    }
}

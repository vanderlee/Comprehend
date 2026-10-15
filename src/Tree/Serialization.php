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
     * @return array{name: string|int|null, group: ?string, span: Span,
     *     children: list<self>, tags: list<string>}
     */
    abstract protected function serializationData(): array;

    /** @return array<string, string|bool> Node-specific array fields and XML attributes. */
    abstract protected function serializationFields(): array;

    /** @return array<string, list<Span>> */
    abstract protected function serializationSurroundings(): array;

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
        $node = $this->serializationData();
        $data = [
            'name' => $node['name'],
            'group' => $node['group'],
            ...self::spanArray($node['span']),
            'children' => array_map(static fn(self $child): array => $child->toArray(), $node['children']),
            ...$this->serializationFields(),
        ];
        if ($node['tags'] !== []) {
            $data['tags'] = $node['tags'];
        }
        foreach ($this->serializationSurroundings() as $side => $spans) {
            if ($spans !== []) {
                $data[$side] = array_map(self::spanArray(...), $spans);
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
        $node = $this->serializationData();
        $element = $document->createElement('node');
        if ($node['name'] !== null) {
            $element->setAttribute('name', (string) $node['name']);
        }
        if ($node['group'] !== null) {
            $element->setAttribute('group', $node['group']);
        }
        $element->setAttribute('start', (string) $node['span']->start);
        $element->setAttribute('end', (string) $node['span']->end);
        foreach ($this->serializationFields() as $name => $value) {
            $element->setAttribute($name, is_bool($value) ? ($value ? 'true' : 'false') : $value);
        }
        // Bytes are valid parser data even when they cannot be XML text.
        $raw = $node['span']->rawText();
        self::appendXmlText($document, $element, $raw);
        $this->appendXmlTags($document, $element);
        $this->appendXmlSurroundings($document, $element);
        foreach ($node['children'] as $child) {
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

    private function appendXmlTags(DOMDocument $document, DOMElement $element): void
    {
        $tagsData = $this->serializationData()['tags'];
        if ($tagsData === []) {
            return;
        }

        $tags = $document->createElement('tags');
        foreach ($tagsData as $tag) {
            $entry = $document->createElement('tag');
            $entry->appendChild($document->createTextNode($tag));
            $tags->appendChild($entry);
        }
        $element->appendChild($tags);
    }

    private function appendXmlSurroundings(DOMDocument $document, DOMElement $element): void
    {
        foreach ($this->serializationSurroundings() as $side => $spans) {
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

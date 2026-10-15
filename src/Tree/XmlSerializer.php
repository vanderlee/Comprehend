<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Tree;

use Vanderlee\Comprehend\Source\Span;
use DOMDocument;
use DOMElement;

/** @internal
 * @implements NodeVisitor<DOMElement>
 */
final class XmlSerializer implements NodeVisitor
{
    use SerializationData;

    private readonly DOMDocument $document;

    public function __construct(DOMDocument $document)
    {
        $this->document = $document;
    }

    /** @return DOMElement */
    public function visitConcrete(ConcreteNode $node): DOMElement
    {
        return $this->visitData(self::data($node), [
            'kind' => $node->kind,
            'trivia' => $node->isTrivia,
            'semanticLeaf' => $node->semanticLeaf,
        ], []);
    }

    /** @return DOMElement */
    public function visitAst(AstNode $node): DOMElement
    {
        return $this->visitData(self::data($node), [], [
            'preamble' => $node->preamble,
            'postamble' => $node->postamble,
        ]);
    }


    /**
     * @param array{name: string|int|null, group: ?string, span: Span,
     *     children: list<AstNode|ConcreteNode>, tags: list<string>} $node
     * @param array<string, string|bool> $fields
     * @param array<string, list<Span>> $surroundings
     * @return DOMElement
     */
    public function visitData(array $node, array $fields, array $surroundings): DOMElement
    {
        $document = $this->document;
        $element = $document->createElement('node');
        if ($node['name'] !== null) {
            $element->setAttribute('name', (string) $node['name']);
        }
        if ($node['group'] !== null) {
            $element->setAttribute('group', $node['group']);
        }
        $element->setAttribute('start', (string) $node['span']->start);
        $element->setAttribute('end', (string) $node['span']->end);
        foreach ($fields as $name => $value) {
            $element->setAttribute($name, is_bool($value) ? ($value ? 'true' : 'false') : $value);
        }
        // Bytes are valid parser data even when they cannot be XML text.
        $raw = $node['span']->rawText();
        self::appendXmlText($document, $element, $raw);
        $this->appendXmlTags($document, $element, $node['tags']);
        $this->appendXmlSurroundings($document, $element, $surroundings);
        foreach ($node['children'] as $child) {
            $element->appendChild($child->accept($this));
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

    /** @param list<string> $tagsData */
    private function appendXmlTags(DOMDocument $document, DOMElement $element, array $tagsData): void
    {
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

    /** @param array<string, list<Span>> $surroundings */
    private function appendXmlSurroundings(DOMDocument $document, DOMElement $element, array $surroundings): void
    {
        foreach ($surroundings as $side => $spans) {
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

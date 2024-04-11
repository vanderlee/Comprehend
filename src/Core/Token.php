<?php

namespace Vanderlee\Comprehend\Core;

use DOMDocument;
use DOMElement;
use DOMException;
use Exception;
use JsonSerializable;

/**
 * Class Token.
 *
 *
 * @property-read Token[] $children
 * @property-read string $class
 * @property-read string $group
 * @property-read string $input
 * @property-read int $length
 * @property-read int $offset
 * @property-read string $name
 * @property-read string $text
 */
class Token implements JsonSerializable
{
    private $group;
    private $name;
    private $input;
    private $offset;
    private $length;
    private $class;

    /**
     * @var Token[]
     */
    private $children;

    public function __construct($group, $name, $input, int $offset, $length, $children = [], $class = null)
    {
        $this->group = $group;
        $this->name = $name;
        $this->input = $input;
        $this->offset = &$offset;
        $this->length = $length;
        $this->children = $children;
        $this->class = $class;
    }

    /**
     * @throws Exception
     */
    public function __get($name)
    {
        switch ($name) {
            case 'text':
                return substr($this->input, $this->offset, $this->length);

            default:
                if (property_exists($this, $name)) {
                    return $this->$name;
                }
        }

        throw new Exception('Undefined property `' . $name . '`');
    }

    private function toString($depth = 0)
    {
        $signature = ($this->group
                ? $this->group . '::'
                : '')
            . ($this->name
                ?: $this->class);

        $output = str_repeat('  ', $depth) . $signature . ' (`' . $this->text . '`)';

        foreach ($this->children as $child) {
            $output .= PHP_EOL . $child->toString($depth + 1);
        }

        return $output;
    }

    public function __toString()
    {
        return $this->toString();
    }

    /**
     * @return DOMDocument
     * @throws DOMException
     */
    public function toXml()
    {
        $document = new DOMDocument();
        $document->appendChild($this->createXmlNode($document));
        $document->normalizeDocument();

        return $document;
    }

    /**
     * Create an XML node of this token.
     *
     * @param DOMDocument $document
     *
     * @return DOMElement
     *
     * @throws DOMException
     */
    private function createXmlNode(DOMDocument $document)
    {
        $value = $this->children
            ? null
            : $this->text;
        $name = preg_replace('/[^-_a-zA-Z0-9]/', '_', $this->name
            ?: $this->class);
        $group = preg_replace('/[^-_a-zA-Z0-9]/', '_', $this->group);

        $element = $this->group
            ? $document->createElementNS($this->group, $group . ':' . $name, $value)
            : $document->createElement($name, $value);

        foreach ($this->children as $child) {
            $element->appendChild($child->createXmlNode($document));
        }

        return $element;
    }

    /**
     * Convert object to JSON for json_encode
     * Implements /JsonSerializable interface.
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * Output this node as an array.
     *
     * @return array
     */
    public function toArray()
    {
        $array = [
            'group' => $this->group,
            'name' => $this->name,
            'text' => $this->text,
            'offset' => $this->offset,
            'length' => $this->length,
            'class' => $this->class,
            'children' => [],
        ];

        foreach ($this->children as $child) {
            $array['children'][] = $child->toArray();
        }

        return $array;
    }
}

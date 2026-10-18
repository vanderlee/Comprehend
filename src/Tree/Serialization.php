<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Tree;

use DOMDocument;
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
     * @template T
     * @param NodeVisitor<T> $visitor
     * @return T
     */
    public function accept(NodeVisitor $visitor): mixed
    {
        return $visitor->visitData(
            $this->serializationData(),
            $this->serializationFields(),
            $this->serializationSurroundings(),
        );
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->accept(new ArraySerializer());
    }

    public function toJson(): string
    {
        return json_encode($this, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    public function toXml(): DOMDocument
    {
        if (!class_exists(DOMDocument::class)) {
            throw new LogicException('XML serialization requires the ext-dom PHP extension');
        }
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->appendChild($this->accept(new XmlSerializer($document)));

        return $document;
    }
}

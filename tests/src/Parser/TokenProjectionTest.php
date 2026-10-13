<?php

declare(strict_types=1);

namespace Tests\Src\Parser;

use Tests\ParserTestCase;
use Vanderlee\Comprehend\Builder\Definition;
use Vanderlee\Comprehend\Compatibility\CoordinatePolicy;
use Vanderlee\Comprehend\Compatibility\LegacyParserAdapter;
use Vanderlee\Comprehend\Compatibility\TokenProjection;
use Vanderlee\Comprehend\Directive\Space;
use Vanderlee\Comprehend\Library\Rfc3986;
use Vanderlee\Comprehend\Parser\Structure\All;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Nothing;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Source\Utf8Source;

class TokenProjectionTest extends ParserTestCase
{
    /** @dataProvider legacyCases */
    public function testAsciiTokenAndSerializerContractsMatchLegacy($parser, string $input)
    {
        $legacy = $parser->match($input)->token;
        $source = $parser->matchPrefix(new Utf8Source($input));
        $projected = TokenProjection::project($source->node);
        $this->assertSame($legacy->toArray(), $projected->toArray());
        $this->assertSame($legacy->toArray(), $source->token()->toArray());
        $this->assertSame(json_encode($legacy), json_encode($source->token()));
        $this->assertSame($this->xmlShape($legacy->toXml()->documentElement), $this->xmlShape($source->token()->toXml()->documentElement));
        $this->assertSame((string) $legacy, (string) $projected);
    }

    private function xmlShape(\DOMElement $element): array
    {
        $children = [];
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement) { $children[] = $this->xmlShape($child); }
        }
        return [$element->namespaceURI, $element->localName, $element->textContent, $children];
    }

    public function legacyCases(): array
    {
        $processed = new Definition(new Sequence((new Text('a'))->token('child'), 'b'));
        $processed->addProcessor('value', fn () => 42);
        return [
            [(new Sequence(new Text('a'), (new Text('b'))->token('B!', 'urn:test')))->token('root'), 'ab'],
            [(new Sequence((new Text('a'))->token('hidden'), 'b'))->token('leaf', null, true), 'ab'],
            [new Space(' ', (new Sequence('a', 'b'))->token('body')), 'a b'],
            [(new Sequence('a', 'b'))->spacing((new Text(' '))->token('spacer')), 'a b'],
            [Repeat::plus('a')->spacing(' ')->token('items'), 'a a'],
            [new All('a', 'ab'), 'ab'],
            [$processed()->token('processed'), 'ab'],
            [(new Sequence((new Text(' '))->trivia(), (new Text('a'))->lexeme()))->token('document'), ' a'],
            [Choice::longest('a', 'ab'), 'ab'],
            [(new Nothing())->token('empty'), ''],
            [(new Rfc3986())->scheme, 'http'],
        ];
    }

    public function testUtf8NestedEmptyAndNamespaceXml()
    {
        $parser = (new Sequence((new Text('é'))->token('Letter!', 'urn:test'), (new Nothing())->token('empty')))->token('root');
        $result = $parser->parseAll(new Utf8Source('é'));
        $token = $result->token();
        $this->assertSame('é', $token->text);
        $this->assertSame([0, 2], [$token->offset, $token->length]);
        $this->assertSame([2, 0, ''], [$token->children[1]->offset, $token->children[1]->length, $token->children[1]->text]);
        $json = json_decode(json_encode($token, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('é', $json['children'][0]['text']);
        $root = $token->toXml()->documentElement;
        $this->assertSame('root', $root->localName);
        $this->assertSame(2, $root->childNodes->length);
        $this->assertSame('urn:test', $root->childNodes->item(0)->namespaceURI);
        $this->assertSame('Letter_', $root->childNodes->item(0)->localName);
        $this->assertSame('é', $root->childNodes->item(0)->textContent);
        $this->assertSame('', $root->childNodes->item(1)->textContent);
    }

    public function testCoordinateAdapterConvertsOnceAndSerializesRawText()
    {
        $parser = (new Sequence(new Text('é'), new Text('a')))->token('root');
        $token = (new LegacyParserAdapter($parser, CoordinatePolicy::CODE_POINTS))->parseAll(new Utf8Source('zéa'), 1)->token;
        $this->assertSame([1, 2, 'éa'], [$token->offset, $token->length, $token->text]);
        $this->assertSame([1, 1, 'é'], [$token->children[0]->offset, $token->children[0]->length, $token->children[0]->text]);
        $this->assertSame('é', json_decode(json_encode($token), true)['children'][0]['text']);
        $this->assertSame('é', $token->toXml()->documentElement->childNodes->item(0)->textContent);
    }

    public function testProjectionUsesNamingSnapshotWithoutEvaluatingSemanticResults()
    {
        $calls = 0;
        $parser = (new Text('a'))->token('before')->setResult(null, function () use (&$calls) { ++$calls; return 'changed'; });
        $result = $parser->matchPrefix(new Utf8Source('a'));
        $parser->token('after');
        $this->assertSame('before', $result->token()->name);
        $this->assertSame('a', $result->token()->text);
        $this->assertSame(0, $calls);
        $this->assertSame('changed', $result->result());
        $this->assertSame('a', $result->token()->text);
    }
}

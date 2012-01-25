<?php

	header('Content-Type: text/html; charset=utf-8');

	require_once(dirname(__FILE__).'/Parser.php');
	require_once(dirname(__FILE__).'/P.php');
	require_once(dirname(__FILE__).'/UnitTest.php');

	//---------------------------------------------------------------------------------------------

	// Core
		// ParserMatch
			$success = new ParserMatch(TRUE, 123);
			test($success->match, TRUE);
			test($success->length, 123);

			$failure = new ParserMatch(FALSE, 321);
			test($failure->match, FALSE);
			test($failure->length, 321);

		// ParserUtil
			// getCharArg
				test(ParserUtil::getCharArg(''), FALSE);
				test(ParserUtil::getCharArg('a'), 'a');
				test(ParserUtil::getCharArg(ord('a')), 'a');
			// getParserArg
				test(ParserUtil::getParserArg(''), FALSE);
				test(get_class(ParserUtil::getParserArg('a')), 'CharParser');
				test(get_class(ParserUtil::getParserArg('aa')), 'TextParser');
				test(get_class(ParserUtil::getParserArg(P::any())), 'AnyParser');
			// getParserArgs
				test(ParserUtil::getParserArgs(), FALSE);
				test(ParserUtil::getParserArgs(''), FALSE);
				test(ParserUtil::getParserArgs('a', ''), FALSE);
				$args = ParserUtil::getParserArgs('a', 'aa', P::any());
				test(count($args), 3);
				test(get_class($args[0]), 'CharParser');
				test(get_class($args[1]), 'TextParser');
				test(get_class($args[2]), 'AnyParser');

		// ParserContext
			test(get_class(P::context('_')), 'ParserContext');
			test(P::context('_')->skip('___', 0), 1);
			test(P::context(P::plus('_'))->skip('___', 0), 3);
			test(P::context(P::plus('_'))->skip('a__', 1), 2);
			test(P::context(P::plus('_'))->skip('a_b', 0), 0);
			test(P::context(P::plus('_'))->skip('a_b', 1), 1);
			test(P::context(P::plus('_'))->skip('a_b', 2), 0);
			test(P::context(P::plus('_'))->skip('a_b', 3), 0);
			test(P::context(P::whitespace())->skip(" \t\n\r", 0), 4);

	// Chaining
		$p2 = P::char('a')->plus('b');
		//$p2 = P::char('a')->plus('b')->;
		testParser($p2, 'abb', TRUE, 3);


	// Terminals
		// AnyParser
			test(get_class(P::any()), 'AnyParser');
			testParser(P::any(), 'aa', TRUE, 1);
			testParser(P::any(), '', FALSE, 0);

		// CharParser
			test(get_class(P::char('a')), 'CharParser');
			testParser(P::char(''), '', FALSE, Parser::INVALID_ARGUMENTS);
			testParser(P::char(''), 'a', FALSE, Parser::INVALID_ARGUMENTS);
			testParser(P::char('aa'), 'a', FALSE, Parser::INVALID_ARGUMENTS);
			testParser(P::char('a'), 'a', TRUE, 1);
			testParser(P::char('A'), 'a', FALSE, 0);
			testParser(P::char('a'), 'A', FALSE, 0);
			testParser(P::char('a'), 'b', FALSE, 0);
			testParser(P::char('a'), '', FALSE, 0);
			testParser(P::char('a'), 'aa', TRUE, 1);

		// TextParser
			test(get_class(P::text('foo')), 'TextParser');
			testParser(P::text(''), '', FALSE, Parser::INVALID_ARGUMENTS);		// invalid input!
			testParser(P::text(''), 'foo', FALSE, Parser::INVALID_ARGUMENTS);	// invalid input!
			testParser(P::text('foo'), 'foo', TRUE, 3);
			testParser(P::text('FOO'), 'foo', FALSE, 0);
			testParser(P::text('foo'), 'FOO', FALSE, 0);
			testParser(P::text('foo'), 'foobar', TRUE, 3);
			testParser(P::text('foobar'), 'foobar', TRUE, 6);
			testParser(P::text('foobars'), 'foobar', FALSE, 6);
			testParser(P::text('bar'), 'foobar', FALSE, 0);

		// RangeParser
			test(get_class(P::range('a', 'z')), 'RangeParser');
			testParser(P::range('', ''), '', FALSE, Parser::INVALID_ARGUMENTS);		// invalid input!
			testParser(P::range('', ''), 'a', FALSE, Parser::INVALID_ARGUMENTS);	// invalid input!
			testParser(P::range(null, 'z'), 'foo', TRUE, 1);
			testParser(P::range('a', null), 'foo', TRUE, 1);
			testParser(P::range('', 'z'), 'foo', TRUE, 1);
			testParser(P::range('a', ''), 'foo', TRUE, 1);
			testParser(P::range('a', 'z'), 'foo', TRUE, 1);
			testParser(P::range('A', 'Z'), 'foo', FALSE, 0);

		// SetParser
			test(get_class(P::set('az')), 'SetParser');
			testParser(P::set(''), '', FALSE, Parser::INVALID_ARGUMENTS);		// invalid input!
			testParser(P::set(''), 'abc', FALSE, Parser::INVALID_ARGUMENTS);	// invalid input!
			testParser(P::set('a'), 'abc', TRUE, 1);
			testParser(P::set('b'), 'abc', FALSE, 0);
			testParser(P::set('az'), 'abc', TRUE, 1);
			testParser(P::set('az'), 'b', FALSE, 0);
			testParser(P::set('az'), 'z', TRUE, 1);
			testParser(P::set('abc'), 'a', TRUE, 1);
			testParser(P::set('abc'), 'b', TRUE, 1);
			testParser(P::set('abc'), 'c', TRUE, 1);

		// PregParser
			/**
			 * @todo Invalid regular expressions cause warnings; unittest command to test for warnings needed
			 * or surpress warnings and return INVALID_ARGUMENTS error?
			 */
			test(get_class(P::preg('~[a-f]~i')), 'PregParser');
			testParser(P::preg('~[a-f]+~i'), 'abc', TRUE, 3);
			testParser(P::preg('~[a-f]+~i'), 'abcz', TRUE, 3);
			testParser(P::preg('~[a-f]+~i'), 'zabc', FALSE, 0);
			testParser(P::preg('~[a-f]+~i'), 'AbC', TRUE, 3);
				// enough preg testing, we don't need to test the actual preg, just the special processing

	// Multiple
		// RepeatParser
			test(get_class(P::repeat('a', 2, 4)), 'RepeatParser');
			testParser(P::repeat('a', 2, 1), '', FALSE, Parser::INVALID_ARGUMENTS);	// invalid arguments
			testParser(P::repeat('a', 2, 4), '', FALSE, 0);
			testParser(P::repeat('a', 2, 4), 'b', FALSE, 0);
			testParser(P::repeat('a', 2, 4), 'a', FALSE, 1);
			testParser(P::repeat('a', 2, 4), 'aa', TRUE, 2);
			testParser(P::repeat('a', 2, 4), 'aaa', TRUE, 3);
			testParser(P::repeat('a', 2, 4), 'aaaa', TRUE, 4);
			testParser(P::repeat('a', 2, 4), 'aaaaa', TRUE, 4);
			testParser(P::repeat('a', 0, 1), '', TRUE, 0);
			testParser(P::repeat('a', 0, 1), 'a', TRUE, 1);
			testParser(P::repeat('a', 0, 1), 'aa', TRUE, 1);
			testParser(P::repeat('a', 0, 2), 'aa', TRUE, 2);
			testParser(P::repeat('a', 0, 2), 'aaa', TRUE, 2);
			testParser(P::repeat('a', 1, 1), 'aaa', TRUE, 1);
			testParser(P::repeat('a', 2, 2), 'a', FALSE, 1);
			testParser(P::repeat('a', 2, 2), 'aa', TRUE, 2);
			testParser(P::repeat('a', 2, 2), 'aaa', TRUE, 2);
			testParser(P::repeat('a', 0, null), '', TRUE, 0);
			testParser(P::repeat('a', 0, null), 'a', TRUE, 1);
			testParser(P::repeat('a', 2, null), 'a', FALSE, 1);
			testParser(P::repeat('a', 2, null), 'aa', TRUE, 2);
			testParser(P::repeat('a', 2, null), 'aaa', TRUE, 3);
			testParser(P::repeat('a', 2, null), 'aaaa', TRUE, 4);
			testParser(P::repeat('ab', 2, 2), 'ab', FALSE, 2);
			testParser(P::repeat('ab', 2, 2), 'abab', TRUE, 4);
			testParser(P::repeat('ab', 2, 2), 'ababab', TRUE, 4);

	// Flow
		// SequenceParser
			test(get_class(P::seq('a', 'b')), 'SequenceParser');
			testParser(P::seq(), '', FALSE, Parser::INVALID_ARGUMENTS);
			testParser(P::seq('a'), '', FALSE, 0);
			testParser(P::seq('a'), 'a', TRUE, 1);
			testParser(P::seq('a'), 'aa', TRUE, 1);
			testParser(P::seq('a'), 'b', FALSE, 0);
			testParser(P::seq('a', 'b'), 'ab', TRUE, 2);
			testParser(P::seq('a', 'b'), 'ba', FALSE, 0);
			testParser(P::seq('a', 'b'), 'aa', FALSE, 1);

		// OrParser
			test(get_class(P::choice('a', 'b')), 'OrParser');

		// FirstParser
			test(get_class(P::first('a', 'b')), 'OrDirective');
			testParser(P::first(), '', FALSE, Parser::INVALID_ARGUMENTS);
			testParser(P::first('a', 'b'), '', FALSE, 0);
			testParser(P::first('a', 'b'), 'a', TRUE, 1);
			testParser(P::first('a', 'b'), 'ab', TRUE, 1);
			testParser(P::first('a', 'b'), 'aa', TRUE, 1);
			testParser(P::first('a', 'b'), 'b', TRUE, 1);
			testParser(P::first('a', 'b'), 'ba', TRUE, 1);
			testParser(P::first('a', 'b'), 'bb', TRUE, 1);
			testParser(P::first('a', 'ab'), 'ab', TRUE, 1);
			testParser(P::first('ab', 'a'), 'ab', TRUE, 2);
			testParser(P::first('abc', 'aaa'), 'ab', FALSE, 2);

		// LongestParser
			test(get_class(P::longest('a', 'b')), 'OrDirective');
			testParser(P::longest(), '', FALSE, Parser::INVALID_ARGUMENTS);
			testParser(P::longest('a'), 'a', TRUE, 1);
			testParser(P::longest('a'), 'aa', TRUE, 1);
			testParser(P::longest('a', 'ab'), 'aa', TRUE, 1);
			testParser(P::longest('a', 'ab'), 'ab', TRUE, 2);
			testParser(P::longest('a', 'aa'), 'aa', TRUE, 2);

		// ShortestParser
			test(get_class(P::shortest('a', 'b')), 'OrDirective');
			testParser(P::shortest(), '', FALSE, Parser::INVALID_ARGUMENTS);
			testParser(P::shortest('a'), 'a', TRUE, 1);
			testParser(P::shortest('a'), 'aa', TRUE, 1);
			testParser(P::shortest('a', 'ab'), 'aa', TRUE, 1);
			testParser(P::shortest('a', 'ab'), 'ab', TRUE, 1);
			testParser(P::shortest('a', 'aa'), 'aa', TRUE, 1);
			testParser(P::shortest('aa', 'a'), 'aa', TRUE, 1);

		// AndParser
			test(get_class(P::all('a', 'a')), 'AndParser');
			testParser(P::all(), '', FALSE, Parser::INVALID_ARGUMENTS);
			testParser(P::all('a'), '', FALSE, Parser::INVALID_ARGUMENTS);
			testParser(P::all('a', 'a'), '', FALSE, 0);
			testParser(P::all('a', 'a'), 'a', TRUE, 1);
			testParser(P::all('a', 'b'), 'a', FALSE, 0);
			testParser(P::all('a', 'aa'), 'aa', TRUE, 1);
			testParser(P::all('aa', 'a'), 'aa', TRUE, 1);

		// NotParser
			test(get_class(P::not('a')), 'NotParser');
			testParser(P::not('a'), '', TRUE, 0);
			testParser(P::not('a'), 'a', FALSE, 1);

		// ExceptParser
			test(get_class(P::except('a', 'aa')), 'ExceptParser');
			testParser(P::except('a', 'aa'), '', FALSE, 0);
			testParser(P::except('a', 'aa'), 'a', TRUE, 1);
			testParser(P::except('a', 'aa'), 'aa', FALSE, 1);

	// Directives
		// LexemeParser
			test(get_class(P::lexeme('a')), 'LexemeDirective');

		// LexemeParser - lexseq
			test(get_class(P::lexseq('a', 'b')), 'LexemeDirective');
			$ws = P::context(P::whitespace());
			testParserContext(P::seq('a', 'b'), 'ab', $ws, TRUE, 2);
			testParserContext(P::seq('a', 'b'), 'a b', $ws, TRUE, 3);
			testParserContext(P::seq('a', 'b'), ' a b ', $ws, TRUE, 4);
			testParserContext(P::lexseq('a', 'b'), 'ab', $ws, TRUE, 2);
			testParserContext(P::lexseq('a', 'b'), 'a b', $ws, FALSE, 1);
			testParserContext(P::lexseq('a', 'b'), ' a b ', $ws, FALSE, 0);

		// LexemeParser - lexeme-repeat
			$ws = P::context(P::whitespace());
			testParserContext(P::plus('a'), 'aa', $ws, TRUE, 2);
			testParserContext(P::plus('a'), 'a a', $ws, TRUE, 3);
			testParserContext(P::plus('a'), ' a a ', $ws, TRUE, 4);
			testParserContext(P::lexeme(P::plus('a')), 'aa', $ws, TRUE, 2);
			testParserContext(P::lexeme(P::plus('a')), 'a a', $ws, TRUE, 1);
			testParserContext(P::lexeme(P::plus('a')), ' a a ', $ws, FALSE, 0);

		// CaseDirective
			$p = P::seq('a', P::case_insensitive('b'), 'c');
			testParser($p, 'abc', TRUE, 3);
			testParser($p, 'abC', FALSE, 2);
			testParser($p, 'aBc', TRUE, 3);
			testParser($p, 'aBC', FALSE, 2);
			testParser($p, 'Abc', FALSE, 0);
			testParser($p, 'AbC', FALSE, 0);
			testParser($p, 'ABc', FALSE, 0);
			testParser($p, 'ABC', FALSE, 0);

		// Case handling for all terminals
			$c = P::context(null, TRUE);
			$nc = P::context(null, FALSE);
			testParserContext(P::char('a'), 'a', $c, TRUE, 1);
			testParserContext(P::char('a'), 'A', $c, FALSE, 0);
			testParserContext(P::char('A'), 'a', $c, FALSE, 0);
			testParserContext(P::char('A'), 'A', $c, TRUE, 1);
			testParserContext(P::char('a'), 'a', $nc, TRUE, 1);
			testParserContext(P::char('a'), 'A', $nc, TRUE, 1);
			testParserContext(P::char('A'), 'a', $nc, TRUE, 1);
			testParserContext(P::char('A'), 'A', $nc, TRUE, 1);

			testParserContext(P::text('abc'), 'abc', $c, TRUE, 3);
			testParserContext(P::text('abc'), 'ABC', $c, FALSE, 0);
			testParserContext(P::text('ABC'), 'abc', $c, FALSE, 0);
			testParserContext(P::text('ABC'), 'ABC', $c, TRUE, 3);
			testParserContext(P::text('abc'), 'abc', $nc, TRUE, 3);
			testParserContext(P::text('abc'), 'ABC', $nc, TRUE, 3);
			testParserContext(P::text('ABC'), 'abc', $nc, TRUE, 3);
			testParserContext(P::text('ABC'), 'ABC', $nc, TRUE, 3);

			testParserContext(P::range('a', 'z'), 't', $c, TRUE, 1);
			testParserContext(P::range('a', 'z'), 'T', $c, FALSE, 0);
			testParserContext(P::range('A', 'Z'), 't', $c, FALSE, 0);
			testParserContext(P::range('A', 'Z'), 'T', $c, TRUE, 1);
			testParserContext(P::range('a', 'z'), 't', $nc, TRUE, 1);
			testParserContext(P::range('a', 'z'), 'T', $nc, TRUE, 1);
			testParserContext(P::range('A', 'Z'), 't', $nc, TRUE, 1);
			testParserContext(P::range('A', 'Z'), 'T', $nc, TRUE, 1);

			testParserContext(P::set('abc'), 'ab', $c, TRUE, 1);
			testParserContext(P::set('abc'), 'AB', $c, FALSE, 0);
			testParserContext(P::set('ABC'), 'ab', $c, FALSE, 0);
			testParserContext(P::set('ABC'), 'AB', $c, TRUE, 1);
			testParserContext(P::set('abc'), 'ab', $nc, TRUE, 1);
			testParserContext(P::set('abc'), 'AB', $nc, TRUE, 1);
			testParserContext(P::set('ABC'), 'ab', $nc, TRUE, 1);
			testParserContext(P::set('ABC'), 'AB', $nc, TRUE, 1);

			testParserContext(P::preg('~[a-z]+~'), 'abc', $c, TRUE, 3);
			testParserContext(P::preg('~[a-z]+~'), 'ABC', $c, FALSE, 0);
			testParserContext(P::preg('~[A-Z]+~'), 'abc', $c, FALSE, 0);
			testParserContext(P::preg('~[A-Z]+~'), 'ABC', $c, TRUE, 3);
			testParserContext(P::preg('~[a-z]+~'), 'abc', $nc, TRUE, 3);
			testParserContext(P::preg('~[a-z]+~'), 'ABC', $nc, TRUE, 3);
			testParserContext(P::preg('~[A-Z]+~'), 'abc', $nc, TRUE, 3);
			testParserContext(P::preg('~[A-Z]+~'), 'ABC', $nc, TRUE, 3);

		// OrDirective
			$c = P::choice('ab', 'a', 'abc');
			// Default (first);
				testParser($c, 'a', TRUE, 1);
				testParser($c, 'ab', TRUE, 2);
				testParser($c, 'abc', TRUE, 2);
				testParser($c, 'zabc', FALSE, 0);
			// First
				$p = P::or_mode_first($c);
				test(get_class($p), 'OrDirective');
				testParser($p, 'a', TRUE, 1);
				testParser($p, 'ab', TRUE, 2);
				testParser($p, 'abc', TRUE, 2);
				testParser($p, 'zabc', FALSE, 0);
			// Longest
				$p = P::or_mode_longest($c);
				test(get_class($p), 'OrDirective');
				testParser($p, 'a', TRUE, 1);
				testParser($p, 'ab', TRUE, 2);
				testParser($p, 'abc', TRUE, 3);
				testParser($p, 'zabc', FALSE, 0);
			// Shortest
				$p = P::or_mode_shortest($c);
				test(get_class($p), 'OrDirective');
				testParser($p, 'a', TRUE, 1);
				testParser($p, 'ab', TRUE, 1);
				testParser($p, 'abc', TRUE, 1);
				testParser($p, 'zabc', FALSE, 0);

	// Tokens
		$a = P::char('a')->token('A-TOKEN');
		$b = P::text('b')->token('B-TOKEN');
		$c = P::set('cC')->token('C-TOKEN');

		test(count(P::seq($a, $b, $c)->parse('abcC')->tokens), 3);
		test(count(P::seq($a, $b, $c)->token('BNF-TOKEN')->parse('abcC')->tokens), 1);
		test(count(P::choice($a, $b, $c)->parse('abc')->tokens), 1);
		test(count(P::choice($a, $b, $c)->parse('ddd')->tokens), 0);
		test(count(P::repeat($a, 1, 3)->parse('aaaa')->tokens), 3);
		test(count(P::repeat($a, 1, 4)->parse('aaaa')->tokens), 4);
		test(count(P::repeat($a, 1, 5)->parse('aaaa')->tokens), 4);
		//TODO And (return first tokenset)
		//TODO Not (can only return own token, with length 0)
		//TODO Except (return matching (first) tokenset)

	// Listeners
		class TestListener {
			public $args = array();
			public function listener() {
				$this->args = func_get_args();
			}
			public function reset() {
				$this->args = array();
			}
		}
		$listener = new TestListener();

		// onBefore
			$listener->reset();
			P::text('a')->onBefore(array($listener, 'listener'))->parse('abc');
			test(count($listener->args), 3);
			test($listener->args[1], 'abc');
			test($listener->args[2], 0);

		// onMatch - match
			$listener->reset();
			P::text('a')->onMatch(array($listener, 'listener'))->parse('abc');
			test(count($listener->args), 4);
			test($listener->args[1], 'abc');
			test($listener->args[2], 0);
			test($listener->args[3], 1);

		// onMatch - mismatch
			$listener->reset();
			P::text('az')->onMatch(array($listener, 'listener'))->parse('abc');
			test(count($listener->args), 0);

		// onMismatch - match
			$listener->reset();
			P::text('a')->onMismatch(array($listener, 'listener'))->parse('abc');
			test(count($listener->args), 0);

		// onMismatch - mismatch
			$listener->reset();
			P::text('az')->onMismatch(array($listener, 'listener'))->parse('abc');
			test(count($listener->args), 4);
			test($listener->args[1], 'abc');
			test($listener->args[2], 0);
			test($listener->args[3], 1);

		// onAfter - match
			$listener->reset();
			P::text('a')->onAfter(array($listener, 'listener'))->parse('abc');
			test(count($listener->args), 5);
			test($listener->args[1], 'abc');
			test($listener->args[2], 0);
			test($listener->args[3], 1);
			test($listener->args[4], TRUE);

		// onAfter - mismatch
			$listener->reset();
			P::text('az')->onAfter(array($listener, 'listener'))->parse('abc');
			test(count($listener->args), 5);
			test($listener->args[1], 'abc');
			test($listener->args[2], 0);
			test($listener->args[3], 1);
			test($listener->args[4], FALSE);

		UnitTest::report();

?>
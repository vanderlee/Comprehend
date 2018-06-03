<?php

	header('Content-Type: text/html; charset=utf-8');

	require_once(dirname(__FILE__).'/src/autoloader.php');
	require_once(dirname(__FILE__).'/P.php');
	require_once(dirname(__FILE__).'/UnitTest.php');
	
	use vanderlee\comprehend\core\Match;
	use vanderlee\comprehend\core\Util;

	//---------------------------------------------------------------------------------------------

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
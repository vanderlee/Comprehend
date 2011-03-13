<?php
	require_once(dirname(__FILE__).'/Parser.php');
	require_once(dirname(__FILE__).'/P.php');
	
	function test($actual = NULL, $expect = NULL) {
		static $total = 0;
		static $failed = 0;
		
		if ($total == 0) {
			echo '<big><u>Testing started</u></big>';
		}
		
		if ($actual === NULL && $expect === NULL) {
			echo '<br />'.$failed.' of '.$total.' tests failed: '.($failed == 0? 'Success' : 'Failure').'.';
		}
		
		++$total;
		
		if ($actual !== $expect) {
			++$failed;
			
			$actual = $actual === FALSE?	'<i>FALSE</i>'
					: ($actual === TRUE?	'<i>TRUE</i>'
					: 						htmlspecialchars($actual))
					;
					
			$expect = $expect === FALSE?	'<i>FALSE</i>'
					: ($expect === TRUE?	'<i>TRUE</i>'
					: 						htmlspecialchars($expect))
					;
			
			$trace = debug_backtrace();
			echo '<br />Test '.$total.' failed in "'.basename($trace[0]['file']).'" on line '.$trace[0]['line'].'. Expected "'.$expect.'", actual "'.$actual.'"';
		}
	}
	
	// Terminals	
		// AnyParser
			test(get_class(P::any()), 'AnyParser');
			test(P::any()->parse('aa'), 1);
			test(P::any()->parse(''), FALSE);
			
		// CharParser
			test(get_class(P::char('a')), 'CharParser');
			test(P::char('')->parse(''), FALSE);		// invalid input!
			test(P::char('')->parse('a'), FALSE);		// invalid input!
			test(P::char('a')->parse('a'), 1);
			test(P::char('A')->parse('a'), FALSE);
			test(P::char('a')->parse('A'), FALSE);
			test(P::char('a')->parse('b'), FALSE);
			test(P::char('a')->parse(''), FALSE);
			test(P::char('a')->parse('aa'), 1);
			test(P::char('aa')->parse('aaa'), 1);
			
		// TextParser
			test(get_class(P::text('foo')), 'TextParser');
			test(P::text('')->parse(''), FALSE);		// invalid input!
			test(P::text('')->parse('foo'), FALSE);		// invalid input!
			test(P::text('foo')->parse('foo'), 3);		
			test(P::text('FOO')->parse('foo'), FALSE);		
			test(P::text('foo')->parse('FOO'), FALSE);
			test(P::text('foo')->parse('foobar'), 3);
			test(P::text('bar')->parse('foobar'), FALSE);
			
		// RangeParser
			test(get_class(P::range('a', 'z')), 'RangeParser');
			test(P::range('', '')->parse(''), FALSE);	// invalid input!
			test(P::range('', '')->parse('a'), FALSE);	// invalid input!
			test(P::range(null, 'z')->parse('foo'), 1);
			test(P::range('a', null)->parse('foo'), 1);
			test(P::range('', 'z')->parse('foo'), 1);
			test(P::range('a', '')->parse('foo'), 1);
			test(P::range('a', 'z')->parse('foo'), 1);
			test(P::range('A', 'Z')->parse('foo'), FALSE);
		
		// SetParser
			test(get_class(P::set('az')), 'SetParser');
			test(P::set('')->parse(''), FALSE);		// invalid input!
			test(P::set('')->parse('abc'), FALSE);	// invalid input!
			test(P::set('a')->parse('abc'), 1);
			test(P::set('b')->parse('abc'), FALSE);
			test(P::set('az')->parse('abc'), 1);
			test(P::set('az')->parse('b'), FALSE);
			test(P::set('az')->parse('z'), 1);
			test(P::set('abc')->parse('a'), 1);
			test(P::set('abc')->parse('b'), 1);
			test(P::set('abc')->parse('c'), 1);
		
		// PregParser
			/**
			 * @todo Invalid regular expressions cause warnings; unittest command to test for warnings needed
			 */				
			test(get_class(P::preg('~[a-f]~i')), 'PregParser');
			test(P::preg('~[a-f]+~i')->parse('abc'), 3);
			test(P::preg('~[a-f]+~i')->parse('abcz'), 3);
			test(P::preg('~[a-f]+~i')->parse('zabc'), FALSE);
			test(P::preg('~[a-f]+~i')->parse('AbC'), 3);
				// enough preg testing, we don't need to test the actual preg, just the special processing
		
	// Multiple
		// RepeatParser
			test(get_class(P::repeat('a', 2, 4)), 'RepeatParser');
			test(P::repeat('a', 2, 4)->parse(''), FALSE);
			test(P::repeat('a', 2, 4)->parse('b'), FALSE);
			test(P::repeat('a', 2, 4)->parse('a'), FALSE);
			test(P::repeat('a', 2, 4)->parse('aa'), 2);
			test(P::repeat('a', 2, 4)->parse('aaa'), 3);
			test(P::repeat('a', 2, 4)->parse('aaaa'), 4);
			test(P::repeat('a', 2, 4)->parse('aaaaa'), 4);
			test(P::repeat('a', 0, 1)->parse(''), 0);
			test(P::repeat('a', 0, 1)->parse('a'), 1);
			test(P::repeat('a', 0, 1)->parse('aa'), 1);
			test(P::repeat('a', 0, 2)->parse('aa'), 2);
			test(P::repeat('a', 0, 2)->parse('aaa'), 2);
			test(P::repeat('a', 1, 1)->parse('aaa'), 1);
			test(P::repeat('a', 2, 2)->parse('a'), FALSE);
			test(P::repeat('a', 2, 2)->parse('aa'), 2);
			test(P::repeat('a', 2, 2)->parse('aaa'), 2);
			test(P::repeat('a', 2, 1)->parse('aaa'), FALSE);
			test(P::repeat('a', 0, null)->parse(''), 0);
			test(P::repeat('a', 0, null)->parse('a'), 1);
			test(P::repeat('a', 2, null)->parse('a'), FALSE);
			test(P::repeat('a', 2, null)->parse('aa'), 2);
			test(P::repeat('a', 2, null)->parse('aaa'), 3);
			test(P::repeat('a', 2, null)->parse('aaaa'), 4);
	
	// Flow
		
		
	// Report
		test();
?>
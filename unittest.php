<?php 

	class UnitTest {
		public static $total = 0;
		public static $failed = 0;
		
		public static function report() {
			echo '<br />'.self::$failed.' of '.self::$total.' tests failed: '.(self::$failed == 0? 'Success' : 'Failure').'.';
		}
		
		public static function test() {
			if (self::$total == 0) {
				echo '<big><u>Testing started</u></big>';
			}
		
			self::$total++;
		}
	
		public static function fail() {
			self::$failed++;
		}
	
		private function __construct() {}
	}
	
	function test($actual = NULL, $expect = NULL) {		
		UnitTest::test();
				
		if ($actual !== $expect) {
			UnitTest::fail();
			
			$actual = $actual === FALSE?	'<i>FALSE</i>'
					: ($actual === TRUE?	'<i>TRUE</i>'
					: 						htmlspecialchars($actual))
					;
					
			$expect = $expect === FALSE?	'<i>FALSE</i>'
					: ($expect === TRUE?	'<i>TRUE</i>'
					: 						htmlspecialchars($expect))
					;
			
			$trace = debug_backtrace();
			echo '<br />Test '.(UnitTest::$total).' failed in "'.basename($trace[0]['file']).'" on line '.$trace[0]['line'].'. Expected "'.$expect.'", actual "'.$actual.'"';
		}
	}
	
	function testParserContext(Parser $parser, $in, ParserContext $context = null, $match, $length) {
		UnitTest::test();
		
		$m = $parser->parse($in, 0, $context);	
		
		if ($m->match != $match) {
			UnitTest::fail();
			
			$actual = '<i>'.($m->match? 'TRUE' : 'FALSE').'</i>';
			$expect = '<i>'.($match? 'TRUE' : 'FALSE').'</i>';
			
			$trace = debug_backtrace();
			echo '<br />Parser test '.(UnitTest::$total).' failed in "'.basename($trace[0]['file']).'" on line '.$trace[0]['line'].'. Expected match "'.$expect.'", actual "'.$actual.'"';
		} else if ($m->length != $length) {
			UnitTest::fail();
			
			$actual = '<i>'.$m->length.'</i>';
			$expect = '<i>'.$length.'</i>';
			
			$trace = debug_backtrace();
			echo '<br />Parser test '.(UnitTest::$total).' failed in "'.basename($trace[0]['file']).'" on line '.$trace[0]['line'].'. Expected length "'.$expect.'", actual "'.$actual.'"';
		}
	}
	
	function testParser($parser, $in, $match, $length) {
		UnitTest::test();
		
		$m = $parser->parse($in, 0);	
		
		if ($m->match != $match) {
			UnitTest::fail();
			
			$actual = '<i>'.($m->match? 'TRUE' : 'FALSE').'</i>';
			$expect = '<i>'.($match? 'TRUE' : 'FALSE').'</i>';
			
			$trace = debug_backtrace();
			echo '<br />Parser test '.(UnitTest::$total).' failed in "'.basename($trace[0]['file']).'" on line '.$trace[0]['line'].'. Expected match "'.$expect.'", actual "'.$actual.'"';
		} else if ($m->length != $length) {
			UnitTest::fail();
			
			$actual = '<i>'.$m->length.'</i>';
			$expect = '<i>'.$length.'</i>';
			
			$trace = debug_backtrace();
			echo '<br />Parser test '.(UnitTest::$total).' failed in "'.basename($trace[0]['file']).'" on line '.$trace[0]['line'].'. Expected length "'.$expect.'", actual "'.$actual.'"';
		}
	}
	
?>
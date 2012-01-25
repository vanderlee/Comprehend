<?php
	class P {
		// Stub
		public static function stub()					{	return new StubParser();	}
	
		// Directives
		public static function context($skipper = NULL, $case_sensitive = TRUE) 	
														{	return new ParserContext($skipper, $case_sensitive); }
		public static function lexeme($parser) 			{	return new LexemeDirective($parser); }
		public static function case_sensitive($parser) 	{	return new CaseDirective($parser, TRUE); }
		public static function case_insensitive($parser){	return new CaseDirective($parser, FALSE); }
		public static function or_mode_first($parser) 	{	return new OrDirective($parser, ParserContext::OR_FIRST); }
		public static function or_mode_longest($parser) {	return new OrDirective($parser, ParserContext::OR_LONGEST); }
		public static function or_mode_shortest($parser){	return new OrDirective($parser, ParserContext::OR_SHORTEST); }
		
		// Terminals
		public static function any() 					{	return new AnyParser(); }
		public static function char($char) 				{	return new CharParser($char); }
		public static function text($text) 				{	return new TextParser($text); }					// token
		public static function texts() 					{	$texts = array();								// tokens
															foreach (func_get_args() as $text) {
																$texts[] = new TextParser($text);
															}
															return new OrParser($texts);
														}
		public static function range($first, $last) 	{	return new RangeParser($first, $last); }
		public static function set($set) 				{	return new SetParser($set); }
		public static function notset($set) 			{	return new ExceptParser(new AnyParser(), new SetParser($set)); }
		public static function preg($pattern) 			{	return new PregParser($pattern); }
		public static function whitespace() 			{	return new PregParser('~\s+~'); }
		
		// Multiple	
		public static function repeat($p, $n, $m)		{	return new RepeatParser($p, $n, $m); }			// multi, multiple
		public static function exact($p, $n) 			{	return new RepeatParser($p, $n, $n); }			// times
		public static function kleene($p) 				{	return new RepeatParser($p, 0, NULL); }			// star
		public static function plus($p) 				{	return new RepeatParser($p, 1, NULL); }			// many, more
		public static function optional($p) 			{	return new RepeatParser($p, 0, 1); }			// opt, one_or_zero
		public static function separated($separator, $p){	return new SequenceParser($p, new RepeatParser(new SequenceParser($separator, $p), 0, NULL)); }
		
		// Flow
		public static function seq() 					{	return new SequenceParser(func_get_args()); }
		public static function lexseq() 				{	return new LexemeDirective(new SequenceParser(func_get_args())); }
		public static function choice() 				{	return new OrParser(func_get_args()); }
		public static function first() 					{	return new OrDirective(new OrParser(func_get_args()), ParserContext::OR_FIRST); }
		public static function longest() 				{	return new OrDirective(new OrParser(func_get_args()), ParserContext::OR_LONGEST); }
		public static function shortest() 				{	return new OrDirective(new OrParser(func_get_args()), ParserContext::OR_SHORTEST); }
		public static function all() 					{	return new AndParser(func_get_args()); }
		public static function not() 					{	return new NotParser(func_get_args()); }
		public static function except($p_a, $p_b) 		{	return new ExceptParser($p_a, $p_b); }
		
		// Predefined terminals
		public static function bin() 					{	return new SetParser('01'); }	
		public static function oct() 					{	return new SetParser('01234567'); }	
		public static function dec() 					{	return new SetParser('0123456789'); }	
		public static function hex() 					{	return new SetParser('0123456789abcdefABCDEF'); }	
		public static function hex_upper() 				{	return new SetParser('0123456789ABCDEF'); }	
		public static function hex_lower() 				{	return new SetParser('0123456789abcdef'); }	
		public static function alpha()					{	return new PregParser('~[[:alpha:]]~'); }	
		public static function alnum()					{	return new PregParser('~[[:alnum:]]~'); }	
		public static function printable()				{	return new PregParser('~[[:print:]]~'); }	
		
		// reserved keyword aliasses (requires PHP 5.2.3+)
		public static function __callStatic($method, $args) {
			switch ($method) {
				case 'or':		return self::first($args);	
				case 'and':		return self::all($args);
			}
			return FALSE;
		}
	}
?>
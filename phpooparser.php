<?php

	/**
	 * Parser
	 * LALR(1) grammar parser framework, ISO-8895-1 support only due to PHP limitations on native string format.
	 * @version 0.0.1
	 * @author Martijn W. van der Lee (martijn-at-vanderlee-dot-com)
	 * @copyright Copyright (c) 2011, Martijn W. van der Lee
	 * @license <R&D version; forbidden to use for any purpose>
	 */
	 
	/** @todo TODO
			XOrParser
			SkipperParser
			FalseParser (always returns FALSE; used for "disabled" skipper)
			
			Return tuple(match,length) instead of variadic FALSE/length
			-> Pass along ParserContext with the skipper in it with each doParse and parse call?
				-> Externalize or-priority
			
			addMismatchListener	
				-> onMatch(Parser &$parser, &$in, $offset, &$length);
				-> onMismatch(Parser &$parser, &$in, $offset);
				-> onBefore(Parser &$parser, &$in, $offset);
				-> onAfter(Parser &$parser, &$in, $offset, &$length);	// length may be FALSE
	 */
	
	// PHP Base extension
	//TODO Only used once; refactor inside Parser class
	if (!function_exists('array_flatten')) {
		function array_flatten($a, $f = array()){
			if (!$a || !is_array($a)) {
				return '';
			}
			foreach($a as $k => $v) {
				if (is_array($v)) {
					$f = array_flatten($v, $f);
				} else {
					$f[$k] = $v;
				}
			}	
			return $f;
		}
	}
	
	class ParserUtil {
		private function __construct() {}	// Pure static
	
		public static function getParserArgs($args) {
			$parsers = array();
			foreach (array_flatten($args) as $arg) {
				if (($parser = ParserUtil::getParserArg($arg)) !== FALSE) {
					$parsers[] = $parser;
				}
			}
			return $parsers;
		}
		
		public static function getParserArg($arg) {
			// Get very first non-array value of (recursive) array
			while (is_array($arg)) {
				$arg = reset($arg);
			}

			if (is_string($arg)) {
				switch (strlen($arg)) {
					case 0:		return FALSE;
					case 1:		return new CharParser($arg);
					default:	return new TextParser($arg);
				}
			} else if (is_int($arg)) {
				return new CharParser(ParserUtil::getCharArg($arg));
			}
			return $arg;	// assume parser or fail normally later on
		}
		
		public static function getCharArg($arg) {
			if (is_numeric($arg)) {
				return chr($arg);
			}
			return $arg[0];
		}		
	}
	
	class ParserContext {
		// Contains skipper class and functionality
		protected static $skipper = null;
		public static function setSkipper(Parser $skipper)	{	self::$skipper = $skipper;	}
		protected function skip($in, $offset) {
			if ((self::$skipper instanceof Parser) && ($skip = self::$skipper->parse($in, $offset)) !== FALSE) {	// skipper always optional
				return $skip;
			}
			return 0;
		}		
	}
	
	abstract class Parser {
		protected static $skipper = null;
		public static function setSkipper(Parser $skipper)	{	self::$skipper = $skipper;	}
		protected function skip($in, $offset) {
			if ((self::$skipper instanceof Parser) && ($skip = self::$skipper->parse($in, $offset)) !== FALSE) {	// skipper always optional
				return $skip;
			}
			return 0;
		}
		
		abstract protected function doParse($in, $offset);
		
		public function parse($in, $offset = 0) {
			// onBefore
			foreach ((array)$this->before_listeners as $before_listener) {
				call_user_func_array($before_listener, array(&$this, &$in, $offset));
			}
			
			if (($length = $this->doParse($in, $offset)) === FALSE) {
				// onMismatch
				foreach ((array)$this->mismatch_listeners as $mismatch_listener) {
					call_user_func_array($mismatch_listener, array(&$this, &$in, $offset));
				}
			} else {
				// onMatch
				foreach ((array)$this->match_listeners as $match_listener) {
					call_user_func_array($match_listener, array(&$this, &$in, $offset, &$length));
				}
			}
			
			// onAfter
			foreach ((array)$this->after_listeners as $after_listener) {
				call_user_func_array($after_listener, array(&$this, &$in, $offset, &$length));
			}
			
			return $length;			
		}
		
		private $match_listeners = array();
		public function onMatch($match_listener) {
			$this->match_listeners[] = $match_listener;
		}
		
		private $mismatch_listeners = array();
		public function onMismatch($mismatch_listener) {
			$this->mismatch_listeners[] = $mismatch_listener;
		}
		
		private $before_listeners = array();
		public function onBefore($before_listener) {
			$this->before_listeners[] = $before_listener;
		}		
		
		private $after_listeners = array();
		public function onAfter($after_listener) {
			$this->after_listeners[] = $after_listener;
		}
	}
	
	class LexemeParser extends Parser {
		private $parser = null;
		public function __construct(Parser $parser) {
			$this->parser = $parser;
		}	
		protected function doParse($in, $offset) {
			$skipper = self::$skipper;
			self::$skipper = null;
			return $this->parser->parse($in, $offset);
			self::$skipper = $skipper;
		}
	}
	
	class AnyParser extends Parser {
		public function __construct() {}
		protected function doParse($in, $offset) {
			if ($offset < strlen($in)) {
				return 1;
			}
			return FALSE;
		}
	}
	
	class CharParser extends Parser {
		private $char = null;
		public function __construct($char) {
			$this->char = ParserUtil::getCharArg($char);
		}
		protected function doParse($in, $offset) {
			if ($offset >= strlen($in))	return FALSE;
		
			if ($in[$offset] == $this->char) {
				return 1; 
			}
			return FALSE;
		}
	}
	
	class TextParser extends Parser {
		private $text 	= null;
		private $length = null;
		public function __construct($text) {
			$this->text = $text;
			$this->length = strlen($text);
		}
		protected function doParse($in, $offset) {
			if ($offset + strlen($text) >= strlen($in))	return FALSE;
			
			if (substr($in, $offset, $this->length) == $this->text) {
				return $this->length; 
			}
			return FALSE;
		}
	}

	class RangeParser extends Parser {
		private $first	= null;
		private $last	= null;
		public function __construct($first, $last) {
			$this->first	= ord(ParserUtil::getCharArg($first));
			$this->last		= ord(ParserUtil::getCharArg($last));			
		}
		protected function doParse($in, $offset) {
			if ($offset >= strlen($in)) return FALSE;
			
			$ord = ord($in[$offset]);
			if ($this->first <= $ord && $ord <= $this->last) {
				return 1;
			}
			return FALSE;
		}
	}
	
	class SetParser extends Parser {
		private $set = null;
		public function __construct($set) {
			$this->set = $set;
		}
		protected function doParse($in, $offset) {
			if ($offset >= strlen($in))	return FALSE;
		
			if (strchr($this->set, $in[$offset]) !== FALSE) {
				return 1;
			}
			return FALSE;
		}
	}
	
	class PregParser extends Parser {
		private $pattern = null;
		public function __construct($pattern) {
			$this->pattern = $pattern;
		}
		protected function doParse($in, $offset) {
			if (preg_match($this->pattern, substr($in, $offset), $m) !== FALSE) {
				if (strpos($in, $m[0], $offset) == $offset) {
					return strlen($m[0]);
				}
			}
			return FALSE;
		}	
	}

	// Multi parser;
	class RepeatParser extends Parser {
		private $parser	= null;
		private $min	= null;
		private $max 	= null;
		public function __construct($parser, $min = 0, $max = null) {
			$this->parser = ParserUtil::getParserArg($parser);
			$this->min = $min;
			$this->max = $max;
		}
		protected function doParse($in, $offset) {
			$total = 0;
			$matches = 0;
			do {
				$total += $this->skip($in, $offset);
				if (($length = $this->parser->parse($in, $offset + $total)) !== FALSE) {
					$total += $length;
					++$matches;
				}
			} while ($length !== FALSE && ($this->max == null || $matches < $this->max));
						
			return ($matches >= $this->min && ($this->max == null || $matches <= $this->max))? $total : FALSE;
		}		
	}	

	// Array Parsers	
	class OrParser extends Parser {
		private $parsers = null;
		public function __construct() {
			$this->parsers = ParserUtil::getParserArgs(func_get_args());
		}
		protected function doParse($in, $offset) {
			foreach ($this->parsers as $parser) {
				if (($return = $parser->parse($in, $offset)) !== FALSE) {
					return $return;
				}
			}
			return FALSE;
		}		
	}
	
	/**
	 * AndParser
	 * Matches the longest input matched by all child parsers or returns FALSE is atleast one does not match.
	 */
	class AndParser extends Parser {
		private $parsers = null;
		public function __construct() {
			$this->parsers = ParserUtil::getParserArgs(func_get_args());
		}
		protected function doParse($in, $offset) {
			$return = PHP_INT_MAX;
			foreach ($this->parsers as $parser) {
				if (($return_sub = $parser->parse($in, $offset)) === FALSE) {
					return FALSE;
				} else {
					$return = min($return, $return_sub);
				}
			}
			return $return;
		}		
	}
	
	class LongestParser extends Parser {
		private $parsers = null;
		public function __construct() {
			$this->parsers = ParserUtil::getParserArgs(func_get_args());
		}
		protected function doParse($in, $offset) {
			$return = FALSE;
			foreach ($this->parsers as $parser) {
				if (($return_sub = $parser->parse($in, $offset)) !== FALSE) {
					$return = ($return === FALSE? $return_sub : max($return, $return_sub));
				}
			}
			return $return;
		}		
	}
	
	class ShortestParser extends Parser {
		private $parsers = null;
		public function __construct() {
			$this->parsers = ParserUtil::getParserArgs(func_get_args());
		}
		protected function doParse($in, $offset) {
			$return = FALSE;
			foreach ($this->parsers as $parser) {
				if (($return_sub = $parser->parse($in, $offset)) !== FALSE) {
					$return = ($return === FALSE? $return_sub : min($return, $return_sub));
				}
			}
			return $return;
		}		
	}
	
	class SequenceParser extends Parser {
		private $parsers = null;
		public function __construct() {
			$this->parsers = ParserUtil::getParserArgs(func_get_args());
		}
		protected function doParse($in, $offset) {
			$total = 0;
			foreach ($this->parsers as $parser) {
				$total += $this->skip($in, $offset);
				if (($length = $parser->parse($in, $offset + $total)) === FALSE) {		// must match
					return FALSE;
				}
				$total += $length;
			}
			return $total;
		}		
	}
	
	/**
	 * NotParser
	 * Use with caution. You shouldn't need to use this with BNF grammars
	 */
	class NotParser extends Parser {
		private $parser	= null;
		public function __construct($parser) {
			$this->parser = ParserUtil::getParserArg($parser);
		}
		protected function doParse($in, $offset) {
			if ($this->parser->parse($in, $offset) === FALSE) {
				return 0;
			}
			return FALSE;
		}
	}
	
	class ExceptParser extends Parser {
		private $parser_match	= null;
		private $parser_not		= null;
		public function __construct($parser_match, $parser_not) {
			$this->parser_match	= ParserUtil::getParserArg($parser_match);
			$this->parser_not	= ParserUtil::getParserArg($parser_not);
		}
		protected function doParse($in, $offset) {
			if (($length = $this->parser_match->parse($in, $offset)) !== FALSE
			 && $this->parser_not->parse($in, $offset) === FALSE) {
				return $length;
			}
			return FALSE;
		}	
	}	
	
	class P {
		// Directives
		public static function lexeme(Parser $parser) 	{	return new LexemeParser($parser); }
		
		// Terminals
		public static function any() 					{	return new AnyParser(); }
		public static function char($char) 				{	return new CharParser($char); }
		public static function text($text) 				{	return new TextParser($text); }					// token
		public static function texts() 					{	$texts = array();								// tokens
															foreach (array_flatten(func_get_args()) as $text) {
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
		public static function kleene($p) 				{	return new RepeatParser($p, 0, null); }			// star
		public static function plus($p) 				{	return new RepeatParser($p, 1, null); }			// many, more
		public static function optional($p) 			{	return new RepeatParser($p, 0, 1); }			// opt, one_or_zero
		
		// Flow
		public static function seq() 					{	return new SequenceParser(func_get_args()); }
		public static function lexseq() 				{	return new LexemeParser(new SequenceParser(func_get_args())); }
		public static function first() 					{	return new OrParser(func_get_args()); }
		public static function longest() 				{	return new LongestParser(func_get_args()); }
		public static function shortest() 				{	return new ShortestParser(func_get_args()); }
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
		
	// Unittest framework?
	function test(Parser $p, $text) {
		if (($length = $p->parse($text)) === FALSE) {			
			echo '<br/>Failure: '.$text;
		} else {		
			echo '<br/>Success: '.$text.': '.$length;
		}
	}

	// http://www.w3.org/TR/css3-syntax/
	
	// CSS3 productions rules
	$num		= P::first(P::lexseq(P::kleene(P::dec()), P::char('.'), $integer), P::plus(P::dec()));	// num	::=	[0-9]+ | [0-9]* '.' [0-9]+
	$nl			= P::texts("\r \n", "\n", "\r", "\f");			// nl	::=	#xA | #xD #xA | #xD | #xC
	$nonascii 	= P::range(0x80, 0xFF); 						// nonascii	::=	[#x80-#xD7FF#xE000-#xFFFD#x10000-#x10FFFF]	Note: PHP only supports ISO-8859-1 strings
	$wc			= P::set("\t\n\f\r ");							// wc	::=	#x9 | #xA | #xC | #xD | #x20
	$w			= P::kleene($wc);								// w	::=	wc*
	$unicode 	= P::lexseq('\\', P::repeat(P::hex(), 1, 6), P::optional($wc));	// unicode	::=	'\' [0-9a-fA-F]{1,6} wc?
	$escape		= P::first($unicode, P::lexseq('\\', P::first(P::range(0x20, 0x7E), $nonascii)));	// escape	::=	unicode | '\' [#x20-#x7E#x80-#xD7FF#xE000-#xFFFD#x10000-#x10FFFF]
	$urlchar	= P::first($escape, P::first(0x09, 0x21, P::range(0x23, 0x26), P::range(0x28, 0x7E)), $nonascii);	// urlchar	::=	[#x9#x21#x23-#x26#x28-#x7E] | nonascii | escape
	$nmstart	= P::first(P::alpha(), '_', $nonascii, $escape);			// nmstart	::=	[a-zA-Z] | '_' | nonascii | escape
	$nmchar		= P::first(P::alnum(), '-', '_', $nonascii, $escape);		// nmchar	::=	[a-zA-Z0-9] | '-' | '_' | nonascii | escape
	$name		= P::plus($nmchar);											// name	::=	nmchar+
	$ident		= P::lexseq(P::optional('-'), $nmstart, P::kleene($nmchar));	// ident	::=	'-'? nmstart nmchar*
	$stringchar	= P::first($urlchar, 0x20, P::lexseq('\\', $nl));				// stringchar	::=	urlchar | #x20 | '\' nl
	$string		= P::first(	P::lexseq('"', P::kleene(P::first($stringchar, "'")), '"')
						,	P::lexseq("'", P::kleene(P::first($stringchar, '"')), "'"));	// string	::=	'"' (stringchar | "'")* '"' | "'" (stringchar | '"')* "'"
						
	// CSS3 tokens
	$IDENT			= $ident;						// IDENT	::=	ident
	$ATKEYWORD		= P::lexseq('@', $ident);		// ATKEYWORD	::=	'@' ident
	$STRING			= $string;						// STRING	::=	string
	$HASH			= P::lexseq('#', $name);		// HASH	::=	'#' name
	$NUMBER			= $num;							// NUMBER	::=	num
	$PERCENTAGE		= P::lexseq($num, '%');			// PERCENTAGE	::=	num '%'
	$DIMENSION		= P::lexseq($num, $ident);		// DIMENSION	::=	num ident
	$URI			= P::lexseq('url(', $w, P::first($string, P::kleene($urlchar)), $w, ')');	// URI	::=	"url(" w (string | urlchar* ) w ")"
	$UNICODE_RANGE	= P::lexseq('U+', P::repeat(P::hex_upper(), 1, 6), P::optional(P::seq('-', P::repeat(P::hex_upper(), 1, 6))));	// UNICODE-RANGE	::=	"U+" [0-9A-F?]{1,6} ('-' [0-9A-F]{1,6})?
	$CDO			= P::text('<!--');				// CDO	::=	"<!--"
	$CDC			= P::text('-->');				// CDC	::=	"-->"
	$S				= P::plus($wc);					// S	::=	wc+
	$COMMENT		= P::lexseq('/*', P::kleene(P::notset('*')), P::plus('*'), P::kleene(P::seq(P::notset('/'), P::kleene(P::notset('*')), P::plus('*'))), '/');	// COMMENT	::=	"/*" [^*]* '*'+ ([^/] [^*]* '*'+)* "/"
	$FUNCTION		= P::lexseq($ident, '(');		// FUNCTION	::=	ident '('
	$INCLUDES		= P::text('~=');				// INCLUDES	::=	"~="
	$DASHMATCH		= P::text('|=');				// DASHMATCH	::=	"|="
	//$PREFIXMATCH	= P::text('^=');				// PREFIXMATCH	::=	"^="
	//$SUFFIXMATCH	= P::text('$=');				// SUFFIXMATCH	::=	"$="
	//$SUBSTRINGMATCH	= P::text('*=');			// SUBSTRINGMATCH	::=	"*="
	//$CHAR			= // any other character not matched by the above rules except for " or '	// CHAR	::=	any other character not matched by the above rules, except for " or '
	//$BOM			= P::lexseq(0xFE, 0xFF);		// BOM	::=	#xFEFF
	
	test($COMMENT, '/* ablash/ **** /kf */');

	// CSS Stylesheet grammar
	$ruleset		= 
	$block			= P::seq('{', P::kleene($S), P::kleene(P::first($any, $block, P::seq($ATKEYWORD, P::kleene($S)), P::seq(';', , P::kleene($S)))), '}', P::kleene($S));	// block       : '{' S* [ any | block | ATKEYWORD S* | ';' S* ]* '}' S*;
	$at_rule		= P::seq($ATKEYWORD, P::kleene($S), P::kleene($any), P::first($block, P::seq(';', P::kleene($S))));		// at-rule     : ATKEYWORD S* any* [ block | ';' S* ];
	$statement		= P::first($ruleset, $at_rule);						// statement   : ruleset | at-rule;
	$stylesheet		= P::kleene(P::first($CDO, $CDC, $S $statement));	// stylesheet  : [ CDO | CDC | S | statement ]*;
	
	/*
			ruleset     : selector? '{' S* declaration? [ ';' S* declaration? ]* '}' S*;
			selector    : any+;
			declaration : property ':' S* value;
			property    : IDENT S*;
			value       : [ any | block | ATKEYWORD S* ]+;
			any         : [ IDENT | NUMBER | PERCENTAGE | DIMENSION | STRING
						  | DELIM | URI | HASH | UNICODE-RANGE | INCLUDES
						  | FUNCTION S* any* ')' | DASHMATCH | '(' S* any* ')'
						  | '[' S* any* ']' ] S*;		
		
		
	*/
		
?>